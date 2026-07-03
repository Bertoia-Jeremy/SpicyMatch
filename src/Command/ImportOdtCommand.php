<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Repository\AromaticCompoundRepository;
use App\Repository\CompoundOdtRepository;
use App\Service\Math\GeometricMean;
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
        $allowedDir = realpath($this->projectDir.'/fixtures');

        if (false === $resolvedPath || false === $allowedDir || ! str_starts_with($resolvedPath, $allowedDir.'/')) {
            $io->error(sprintf('Le fichier "%s" doit se trouver dans le répertoire fixtures/ du projet.', $file));

            return Command::FAILURE;
        }

        // ── Guard taille ────────────────────────────────────────────────────────
        $fileSize = filesize($resolvedPath);
        if (false === $fileSize || $fileSize > self::MAX_FILE_SIZE) {
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
            $source = isset($entry['source']) ? (string) $entry['source'] : 'inconnu';
            $matrixStr = isset($entry['matrix']) ? (string) $entry['matrix'] : $defaultMatrixStr;
            $matrix = OdtMatrix::tryFrom($matrixStr) ?? $defaultMatrix;
            $confidence = $this->resolveConfidence($entry);

            if (null === $compoundName) {
                $io->warning(sprintf('Entrée ignorée (compound_name manquant) : %s', json_encode($entry)));
                ++$skipped;
                continue;
            }

            // odt_ppm (ponctuel) OU odt_min + odt_max (plage → moyenne géométrique).
            $odtPpm = $this->resolveOdtPpm($entry, $compoundName, $io);
            if (null === $odtPpm) {
                ++$skipped;
                continue;
            }

            // Matching par nom
            $compound = $this->aromaticCompoundRepository->findOneBy([
                'name' => $compoundName,
            ]);

            if (null === $compound) {
                $io->warning(sprintf('Composé "%s" introuvable en BDD — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            $compoundId = $compound->getId();
            if (null === $compoundId) {
                ++$skipped;
                continue;
            }

            // Recherche de l'entrée existante (PK composite)
            $existing = $this->compoundOdtRepository->findForCompound($compoundId, $matrix);

            if (null !== $existing) {
                $existing->setOdtPpm((string) $odtPpm);
                $existing->setReferenceSource($source);
                $existing->setConfidence($confidence);
                $io->text(
                    sprintf(
                        '  UPDATE %s (%s) = %.8f ppm [%s]',
                        $compoundName,
                        $matrix->value,
                        $odtPpm,
                        $confidence->tier()
                    )
                );
                ++$updated;
            } else {
                $odt = new \App\Entity\CompoundOdt($compound, $matrix, (string) $odtPpm, $source);
                $odt->setConfidence($confidence);
                $this->em->persist($odt);
                $io->text(
                    sprintf(
                        '  INSERT %s (%s) = %.8f ppm [%s]',
                        $compoundName,
                        $matrix->value,
                        $odtPpm,
                        $confidence->tier()
                    )
                );
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

    /**
     * Résout l'ODT en ppm depuis une entrée : valeur ponctuelle `odt_ppm`
     * OU plage `odt_min`/`odt_max` agrégée par moyenne géométrique.
     *
     * @param array<string, mixed> $entry
     *
     * @return float|null null si donnée absente/invalide (l'appelant skip)
     */
    private function resolveOdtPpm(array $entry, string $compoundName, SymfonyStyle $io): ?float
    {
        $hasRange = isset($entry['odt_min'], $entry['odt_max']);

        if ($hasRange) {
            $min = $entry['odt_min'];
            $max = $entry['odt_max'];
            if (! is_numeric($min) || ! is_numeric($max) || (float) $min <= 0.0 || (float) $max <= 0.0) {
                $io->warning(sprintf('Plage ODT invalide pour "%s" — ignoré.', $compoundName));

                return null;
            }

            return GeometricMean::ofRange((float) $min, (float) $max);
        }

        $raw = $entry['odt_ppm'] ?? null;
        if (null === $raw || ! is_numeric($raw)) {
            $io->warning(sprintf('ODT manquant ou non numérique pour "%s" — ignoré.', $compoundName));

            return null;
        }

        $odt = (float) $raw;
        if ($odt <= 0.0) {
            $io->warning(sprintf('ODT invalide (≤ 0) pour "%s" — ignoré.', $compoundName));

            return null;
        }

        return $odt;
    }

    /**
     * Niveau de confiance depuis l'entrée YAML (défaut PLACEHOLDER si absent/invalide).
     *
     * @param array<string, mixed> $entry
     */
    private function resolveConfidence(array $entry): DataConfidence
    {
        $raw = isset($entry['confidence']) ? (string) $entry['confidence'] : null;

        return null !== $raw ? (DataConfidence::tryFrom(
            $raw
        ) ?? DataConfidence::PLACEHOLDER) : DataConfidence::PLACEHOLDER;
    }
}
