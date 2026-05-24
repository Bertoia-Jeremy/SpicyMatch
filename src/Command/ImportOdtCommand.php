<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\OdtMatrix;
use App\Repository\AromaticCompoundRepository;
use App\Repository\CompoundOdtRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Ingère les seuils olfactifs (ODT) depuis un fichier YAML versionné.
 *
 * Usage :
 *   bin/console app:import:odt
 *   bin/console app:import:odt --file=fixtures/compound_odt.yaml
 *   bin/console app:import:odt --matrix=water
 *   bin/console app:import:odt --dry-run
 *
 * Format YAML attendu (fixtures/compound_odt.yaml) :
 *   - compound_name: eugenol
 *     odt_ppm: 0.0001
 *     matrix: air
 *     source: "van Gemert (2011) p.78"
 *
 * Matching par nom (case-insensitive). Idempotent : UPDATE si l'entrée existe, INSERT sinon.
 *
 * Sécurité : le fichier doit se trouver dans fixtures/ du projet (path traversal guard).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §6.6
 */
#[AsCommand(name: 'app:import:odt', description: 'Ingère les seuils olfactifs (ODT) depuis un fichier YAML')]
final class ImportOdtCommand extends Command
{
    private const DEFAULT_FILE = 'fixtures/compound_odt.yaml';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly CompoundOdtRepository $compoundOdtRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Chemin vers le fichier YAML', self::DEFAULT_FILE)
            ->addOption(
                'matrix',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Matrice par défaut si non précisée dans le YAML (air|water|oil)',
                'air'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $file */
        $file = $input->getOption('file');
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var string $defaultMatrixStr */
        $defaultMatrixStr = $input->getOption('matrix');
        $defaultMatrix = OdtMatrix::tryFrom($defaultMatrixStr) ?? OdtMatrix::AIR;

        // ── Guard path traversal : le fichier doit être dans fixtures/ ──────────
        $resolvedPath = realpath($file);
        $allowedDir = realpath($this->projectDir . '/fixtures');

        if ($resolvedPath === false || $allowedDir === false || ! str_starts_with($resolvedPath, $allowedDir . '/')) {
            $io->error(sprintf('Le fichier "%s" doit se trouver dans le répertoire fixtures/ du projet.', $file));

            return Command::FAILURE;
        }

        // ── Guard taille ────────────────────────────────────────────────────────
        $fileSize = filesize($resolvedPath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            $io->error('Fichier trop volumineux (max 10 Mo).');

            return Command::FAILURE;
        }

        $io->title(sprintf('Import ODT depuis %s', $resolvedPath));
        $dryRun && $io->warning('Mode DRY-RUN : aucune écriture en BDD.');

        // PARSE_EXCEPTION_ON_INVALID_TYPE : bloque les types YAML dangereux (!!php/object, etc.)
        $entries = Yaml::parseFile($resolvedPath, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        if (! is_array($entries)) {
            $io->error('Le fichier YAML doit contenir une liste de composés.');

            return Command::FAILURE;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                ++$skipped;
                continue;
            }

            $compoundName = isset($entry['compound_name']) ? (string) $entry['compound_name'] : null;
            $odtPpmRaw = $entry['odt_ppm'] ?? null;
            $source = isset($entry['source']) ? (string) $entry['source'] : 'inconnu';
            $matrixStr = isset($entry['matrix']) ? (string) $entry['matrix'] : $defaultMatrixStr;
            $matrix = OdtMatrix::tryFrom($matrixStr) ?? $defaultMatrix;

            if ($compoundName === null || $odtPpmRaw === null) {
                $io->warning(sprintf('Entrée ignorée (champs manquants) : %s', json_encode($entry)));
                ++$skipped;
                continue;
            }

            // Guard non-numérique : (float)"N/A" = 0.0 mais le message serait trompeur
            if (! is_numeric($odtPpmRaw)) {
                $io->warning(sprintf(
                    'ODT non numérique pour "%s" : %s — ignoré.',
                    $compoundName,
                    json_encode($odtPpmRaw)
                ));
                ++$skipped;
                continue;
            }

            $odtPpm = (float) $odtPpmRaw;

            // Guard ODT invalide : une valeur ≤ 0 causerait une division par zéro dans le rebuild OAV
            if ($odtPpm <= 0.0) {
                $io->warning(sprintf('ODT invalide (≤ 0) pour "%s" — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            // Matching par nom
            $compound = $this->aromaticCompoundRepository->findOneBy([
                'name' => $compoundName,
            ]);

            if ($compound === null) {
                $io->warning(sprintf('Composé "%s" introuvable en BDD — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            $compoundId = $compound->getId();
            if ($compoundId === null) {
                ++$skipped;
                continue;
            }

            // Recherche de l'entrée existante (PK composite)
            $existing = $this->compoundOdtRepository->findForCompound($compoundId, $matrix);

            if ($existing !== null) {
                $existing->setOdtPpm((string) $odtPpm);
                $existing->setReferenceSource($source);
                $io->text(sprintf('  UPDATE %s (%s) = %.8f ppm', $compoundName, $matrix->value, $odtPpm));
                ++$updated;
            } else {
                $odt = new \App\Entity\CompoundOdt($compound, $matrix, (string) $odtPpm, $source);
                $this->em->persist($odt);
                $io->text(sprintf('  INSERT %s (%s) = %.8f ppm', $compoundName, $matrix->value, $odtPpm));
                ++$inserted;
            }

            // Batch flush+clear toutes les 500 opérations : évite l'accumulation de l'UnitOfWork
            // en RAM sur les gros datasets (van Gemert ≈ 6 000 lignes).
            if (! $dryRun && ($inserted + $updated) % 500 === 0 && ($inserted + $updated) > 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (! $dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Import terminé — %d insérés, %d mis à jour, %d ignorés%s.',
            $inserted,
            $updated,
            $skipped,
            $dryRun ? ' (dry-run)' : ''
        ));

        return Command::SUCCESS;
    }
}
