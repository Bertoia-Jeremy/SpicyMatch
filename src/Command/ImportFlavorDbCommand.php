<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
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
 * Ingère les concentrations de composés aromatiques depuis FlavorDB ou un dump local.
 *
 * Usage :
 *   bin/console app:import:flavordb --file=fixtures/spice_compound_concentration.yaml
 *   bin/console app:import:flavordb --dry-run
 *
 * Format YAML attendu (fixtures/spice_compound_concentration.yaml) :
 *   - spice_name: clou-de-girofle
 *     compound_name: eugenol
 *     concentration_ppm: 850000
 *     source: "FlavorDB ingredient_id=42"
 *
 * Matching épice par nom (exact, case-sensitive — utiliser le nom tel qu'en BDD).
 * Matching composé par nom (exact).
 * Idempotent : UPDATE si (spice_id, aromatic_compound_id) existe, INSERT sinon.
 *
 * Sécurité : le fichier doit se trouver dans fixtures/ du projet (path traversal guard).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §6.6
 */
#[AsCommand(
    name: 'app:import:flavordb',
    description: 'Ingère les concentrations de composés depuis FlavorDB ou un dump YAML'
)]
final class ImportFlavorDbCommand extends Command
{
    private const DEFAULT_FILE = 'fixtures/spice_compound_concentration.yaml';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Chemin vers le fichier YAML de concentrations',
                self::DEFAULT_FILE
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $file */
        $file = $input->getOption('file');
        $dryRun = (bool) $input->getOption('dry-run');

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

        $io->title(sprintf('Import concentrations FlavorDB depuis %s', $resolvedPath));
        $dryRun && $io->warning('Mode DRY-RUN : aucune écriture en BDD.');

        // PARSE_EXCEPTION_ON_INVALID_TYPE : bloque les types YAML dangereux (!!php/object, etc.)
        $entries = Yaml::parseFile($resolvedPath, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        if (! is_array($entries)) {
            $io->error('Le fichier YAML doit contenir une liste de concentrations.');

            return Command::FAILURE;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        // Cache local pour éviter N+1 sur les noms
        $spiceCache = [];
        $compoundCache = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                ++$skipped;
                continue;
            }

            $spiceName = isset($entry['spice_name']) ? (string) $entry['spice_name'] : null;
            $compoundName = isset($entry['compound_name']) ? (string) $entry['compound_name'] : null;
            $concentrationPpmRaw = $entry['concentration_ppm'] ?? null;
            $source = isset($entry['source']) ? (string) $entry['source'] : 'FlavorDB';

            if (null === $spiceName || null === $compoundName || null === $concentrationPpmRaw) {
                $io->warning(sprintf('Entrée ignorée (champs manquants) : %s', json_encode($entry)));
                ++$skipped;
                continue;
            }

            // Guard non-numérique : (float)"N/A" = 0.0 mais le message serait trompeur
            if (! is_numeric($concentrationPpmRaw)) {
                $io->warning(sprintf(
                    'concentration_ppm non numérique pour "%s/%s" : %s — ignorée.',
                    $spiceName,
                    $compoundName,
                    json_encode($concentrationPpmRaw)
                ));
                ++$skipped;
                continue;
            }

            // Guard concentration invalide
            $concentrationPpm = (float) $concentrationPpmRaw;
            if ($concentrationPpm < 0.0) {
                $io->warning(
                    sprintf('concentration_ppm négative pour "%s/%s" — ignorée.', $spiceName, $compoundName)
                );
                ++$skipped;
                continue;
            }

            // Matching épice
            $spice = $spiceCache[$spiceName] ??= $this->spicesRepository->findOneBy([
                'name' => $spiceName,
            ]);
            if (null === $spice) {
                $io->warning(sprintf('Épice "%s" introuvable — ignorée.', $spiceName));
                ++$skipped;
                continue;
            }

            // Matching composé
            $compound = $compoundCache[$compoundName] ??= $this->aromaticCompoundRepository->findOneBy([
                'name' => $compoundName,
            ]);
            if (null === $compound) {
                $io->warning(sprintf('Composé "%s" introuvable — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            // Recherche entrée existante
            $existing = $this->em->find(
                \App\Entity\SpiceCompoundConcentration::class,
                [
                    'spice' => $spice,
                    'aromaticCompound' => $compound,
                ]
            );

            if (null !== $existing) {
                $existing->setConcentrationPpm((string) $concentrationPpm);
                $existing->setSource($source);
                $io->text(sprintf('  UPDATE %s / %s = %s ppm', $spiceName, $compoundName, $concentrationPpm));
                ++$updated;
            } else {
                $concentration = new \App\Entity\SpiceCompoundConcentration(
                    $spice,
                    $compound,
                    (string) $concentrationPpm,
                    $source
                );
                $this->em->persist($concentration);
                $io->text(sprintf('  INSERT %s / %s = %s ppm', $spiceName, $compoundName, $concentrationPpm));
                ++$inserted;
            }

            // Batch flush+clear toutes les 500 opérations : évite l'accumulation de l'UnitOfWork
            // en RAM sur les gros datasets (FlavorDB peut dépasser 10 000 lignes).
            // Reset des caches locaux : après clear(), les entités Doctrine sont détachées.
            if (! $dryRun && ($inserted + $updated) % 500 === 0 && ($inserted + $updated) > 0) {
                $this->em->flush();
                $this->em->clear();
                $spiceCache = [];
                $compoundCache = [];
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

        $io->note('Penser à lancer : bin/console app:recompute:oav');

        return Command::SUCCESS;
    }
}
