<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CompoundPhysical;
use App\Repository\AromaticCompoundRepository;
use App\Repository\CompoundPhysicalRepository;
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
 * Ingère les propriétés physico-chimiques (logP, point d'ébullition, tension de vapeur)
 * depuis un fichier YAML versionné.
 *
 * Usage :
 *   bin/console app:import:physical
 *   bin/console app:import:physical --file=fixtures/compound_physical.yaml --dry-run
 *
 * Format YAML attendu :
 *   - compound_name: "Eugenol"
 *     log_p: 2.27
 *     boiling_point_celsius: 254
 *     vapor_pressure_pa: 0.030
 *     source: "PubChem CID 3314"
 *
 * Matching exact par nom. Idempotent : UPDATE si la ligne existe (OneToOne), INSERT sinon.
 *
 * Sécurité : fichier confiné dans fixtures/ (path traversal guard) et < 10 Mo.
 */
#[AsCommand(
    name: 'app:import:physical',
    description: 'Ingère les propriétés physico-chimiques (logP, bp, vp) depuis un YAML'
)]
final class ImportCompoundPhysicalCommand extends Command
{
    private const string DEFAULT_FILE = 'fixtures/compound_physical.yaml';

    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly CompoundPhysicalRepository $compoundPhysicalRepository,
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $file */
        $file = $input->getOption('file');
        $dryRun = (bool) $input->getOption('dry-run');

        $resolvedPath = $this->guardPath($file, $io);
        if ($resolvedPath === null) {
            return Command::FAILURE;
        }

        $io->title(\sprintf('Import propriétés physico-chimiques depuis %s', $resolvedPath));
        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune écriture en BDD.');
        }

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
            if ($compoundName === null) {
                $io->warning('Entrée ignorée (compound_name manquant).');
                ++$skipped;
                continue;
            }

            $compound = $this->aromaticCompoundRepository->findOneBy([
                'name' => $compoundName,
            ]);

            if ($compound === null) {
                $io->warning(\sprintf('Composé "%s" introuvable en BDD — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            $logP = $this->parseFloat($entry['log_p'] ?? null);
            $boilingPoint = $this->parseInt($entry['boiling_point_celsius'] ?? null);
            $vaporPressure = $this->parseFloat($entry['vapor_pressure_pa'] ?? null);
            $source = isset($entry['source']) ? (string) $entry['source'] : null;

            if ($logP === null && $boilingPoint === null && $vaporPressure === null) {
                $io->warning(\sprintf('Aucune donnée exploitable pour "%s" — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            $existing = $this->compoundPhysicalRepository->findOneBy([
                'compound' => $compound,
            ]);

            if ($existing !== null) {
                $existing->setLogP($logP);
                $existing->setBoilingPointCelsius($boilingPoint);
                $existing->setVaporPressurePa($vaporPressure);
                $existing->setSource($source);
                $io->text(
                    \sprintf(
                        '  UPDATE %s : logP=%s, bp=%s, vp=%s',
                        $compoundName,
                        $logP ?? 'null',
                        $boilingPoint ?? 'null',
                        $vaporPressure ?? 'null'
                    )
                );
                ++$updated;
            } else {
                $physical = new CompoundPhysical($compound);
                $physical->setLogP($logP);
                $physical->setBoilingPointCelsius($boilingPoint);
                $physical->setVaporPressurePa($vaporPressure);
                $physical->setSource($source);
                $this->em->persist($physical);
                $io->text(
                    \sprintf(
                        '  INSERT %s : logP=%s, bp=%s, vp=%s',
                        $compoundName,
                        $logP ?? 'null',
                        $boilingPoint ?? 'null',
                        $vaporPressure ?? 'null'
                    )
                );
                ++$inserted;
            }

            if (! $dryRun && ($inserted + $updated) % 500 === 0 && ($inserted + $updated) > 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (! $dryRun) {
            $this->em->flush();
        }

        $io->success(\sprintf(
            'Import terminé — %d insérés, %d mis à jour, %d ignorés%s.',
            $inserted,
            $updated,
            $skipped,
            $dryRun ? ' (dry-run)' : '',
        ));

        return Command::SUCCESS;
    }

    /**
     * Résolution + path traversal guard + taille max.
     */
    private function guardPath(string $file, SymfonyStyle $io): ?string
    {
        $resolvedPath = realpath($file);
        $allowedDir = realpath($this->projectDir . '/fixtures');

        if ($resolvedPath === false || $allowedDir === false || ! str_starts_with($resolvedPath, $allowedDir . '/')) {
            $io->error(\sprintf('Le fichier "%s" doit se trouver dans fixtures/.', $file));

            return null;
        }

        $size = filesize($resolvedPath);
        if ($size === false || $size > self::MAX_FILE_SIZE) {
            $io->error('Fichier trop volumineux (max 10 Mo).');

            return null;
        }

        return $resolvedPath;
    }

    private function parseFloat(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function parseInt(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
