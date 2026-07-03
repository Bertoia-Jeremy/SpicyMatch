<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AromaticCompound;
use App\Entity\CompoundOdt;
use App\Entity\SpiceCompoundConcentration;
use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Repository\AromaticCompoundRepository;
use App\Repository\CompoundOdtRepository;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ingère la feuille maître d'acquisition (CSV consolidé, 1 ligne par épice × composé)
 * directement en base. Contrairement aux imports YAML, cette commande CRÉE les composés
 * manquants (nom + CAS + formule) — la donnée reste privée dans data/acquisition/ (gitignoré).
 *
 * Colonnes : spice_name, compound_name, cas_number, formula, concentration_ppm,
 * concentration_source, concentration_confidence, log_p, boiling_point_celsius,
 * vapor_pressure_pa, physical_source, odt_air_ppm, odt_water_ppm, odt_oil_ppm,
 * odt_confidence, odt_source, notes.
 *
 * Matching épice/composé par nom exact. Idempotent (upsert). La couche physico-chimique
 * (logP/bp/vp) reste déléguée à app:fetch:physical (PubChem) : ignorée ici.
 */
#[AsCommand(
    name: 'app:import:acquisition-csv',
    description: 'Ingère data/acquisition/*.csv en base (crée composés + concentrations + ODT).',
)]
final class ImportAcquisitionCsvCommand extends Command
{
    private const string DEFAULT_FILE = 'data/acquisition/acquisition_master.csv';
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
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
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Chemin CSV (dans data/acquisition/)',
                self::DEFAULT_FILE
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $file */
        $file = $input->getOption('file');
        $dryRun = (bool) $input->getOption('dry-run');

        $resolvedPath = realpath($file) ?: realpath($this->projectDir.'/'.ltrim($file, '/'));
        $allowedDir = realpath($this->projectDir.'/data/acquisition');

        if (false === $resolvedPath || false === $allowedDir || ! str_starts_with($resolvedPath, $allowedDir.'/')) {
            $io->error(\sprintf('Le fichier "%s" doit se trouver dans data/acquisition/.', $file));

            return Command::FAILURE;
        }

        $size = filesize($resolvedPath);
        if (false === $size || $size > self::MAX_FILE_SIZE) {
            $io->error('Fichier trop volumineux (max 10 Mo).');

            return Command::FAILURE;
        }

        $handle = fopen($resolvedPath, 'r');
        if (false === $handle) {
            $io->error('Lecture impossible.');

            return Command::FAILURE;
        }

        $io->title(\sprintf('Import acquisition CSV depuis %s', $resolvedPath));
        $dryRun && $io->warning('Mode DRY-RUN : aucune écriture en BDD.');

        $stats = [
            'compounds_created' => 0,
            'concentrations' => 0,
            'odt' => 0,
            'skipped' => 0,
        ];

        /** @var array<string, AromaticCompound> $compoundCache */
        $compoundCache = [];
        /** @var array<string, \App\Entity\Spices|null> $spiceCache */
        $spiceCache = [];

        $header = fgetcsv($handle, escape: '\\');
        if (\is_array($header) && isset($header[0])) {
            $header[0] = str_replace("\u{FEFF}", '', (string) $header[0]); // strip BOM
        }

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (($row[0] ?? '') === '') {
                continue;
            }

            $spiceName = trim((string) ($row[0] ?? ''));
            $compoundName = trim((string) ($row[1] ?? ''));
            if ('' === $spiceName || '' === $compoundName) {
                ++$stats['skipped'];
                continue;
            }

            $cas = trim((string) ($row[2] ?? '')) ?: null;
            $formula = trim((string) ($row[3] ?? '')) ?: null;

            $compound = $compoundCache[$compoundName] ??= $this->resolveCompound(
                $compoundName,
                $cas,
                $formula,
                $dryRun,
                $stats,
                $io
            );

            $spice = $spiceCache[$spiceName] ??= $this->spicesRepository->findOneBy([
                'name' => $spiceName,
            ]);
            if (null === $spice) {
                $io->warning(\sprintf('Épice "%s" introuvable — ligne ignorée.', $spiceName));
                ++$stats['skipped'];
                continue;
            }

            $this->upsertConcentration($spice, $compound, $row, $dryRun, $stats);
            $this->upsertOdt($compound, $row, $dryRun, $stats);
        }

        fclose($handle);

        if (! $dryRun) {
            $this->em->flush();
        }

        $io->success(\sprintf(
            'Terminé — %d composés créés, %d concentrations, %d ODT, %d ignorés%s.',
            $stats['compounds_created'],
            $stats['concentrations'],
            $stats['odt'],
            $stats['skipped'],
            $dryRun ? ' (dry-run)' : '',
        ));
        $io->note('Penser à : app:fetch:physical (logP) puis app:recompute:oav --sync.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int> $stats
     */
    private function resolveCompound(
        string $name,
        ?string $cas,
        ?string $formula,
        bool $dryRun,
        array &$stats,
        SymfonyStyle $io,
    ): AromaticCompound {
        $existing = $this->aromaticCompoundRepository->findOneBy([
            'name' => $name,
        ]);
        if (null !== $existing) {
            return $existing;
        }

        $compound = new AromaticCompound();
        $compound->setName($name);
        $compound->setCasNumber($cas);
        $compound->setFormula($formula);
        $now = new \DateTimeImmutable();
        $compound->setCreatedAt($now);
        $compound->setUpdatedAt($now);

        if (! $dryRun) {
            $this->em->persist($compound);
        }

        $io->text(\sprintf('  CREATE composé "%s" (CAS %s)', $name, $cas ?? '—'));
        ++$stats['compounds_created'];

        return $compound;
    }

    /**
     * @param list<string|null>  $row
     * @param array<string, int> $stats
     */
    private function upsertConcentration(
        \App\Entity\Spices $spice,
        AromaticCompound $compound,
        array $row,
        bool $dryRun,
        array &$stats,
    ): void {
        $raw = $row[4] ?? null;
        if (null === $raw || ! is_numeric($raw) || (float) $raw < 0.0) {
            return;
        }

        $ppm = (string) (float) $raw;
        $source = trim((string) ($row[5] ?? '')) ?: 'acquisition_csv';
        $confidence = DataConfidence::tryFrom(trim((string) ($row[6] ?? ''))) ?? DataConfidence::ESTIMATED;

        $existing = null !== $compound->getId()
            ? $this->em->find(SpiceCompoundConcentration::class, [
                'spice' => $spice,
                'aromaticCompound' => $compound,
            ])
            : null;

        if (null !== $existing) {
            $existing->setConcentrationPpm($ppm);
            $existing->setSource($source);
            $existing->setConfidence($confidence);
        } else {
            $entity = new SpiceCompoundConcentration($spice, $compound, $ppm, $source);
            $entity->setConfidence($confidence);
            if (! $dryRun) {
                $this->em->persist($entity);
            }
        }

        ++$stats['concentrations'];
    }

    /**
     * @param list<string|null>  $row
     * @param array<string, int> $stats
     */
    private function upsertOdt(AromaticCompound $compound, array $row, bool $dryRun, array &$stats): void
    {
        $confidence = DataConfidence::tryFrom(trim((string) ($row[14] ?? ''))) ?? DataConfidence::ESTIMATED;
        $source = trim((string) ($row[15] ?? '')) ?: 'acquisition_csv';

        $matrices = [
            [OdtMatrix::AIR, $row[11] ?? null],
            [OdtMatrix::WATER, $row[12] ?? null],
            [OdtMatrix::OIL, $row[13] ?? null],
        ];

        foreach ($matrices as [$matrix, $raw]) {
            if (null === $raw || ! is_numeric($raw) || (float) $raw <= 0.0) {
                continue;
            }

            $ppm = (string) (float) $raw;
            $compoundId = $compound->getId();
            $existing = null !== $compoundId ? $this->compoundOdtRepository->findForCompound(
                $compoundId,
                $matrix
            ) : null;

            if (null !== $existing) {
                $existing->setOdtPpm($ppm);
                $existing->setReferenceSource($source);
                $existing->setConfidence($confidence);
            } else {
                $odt = new CompoundOdt($compound, $matrix, $ppm, $source);
                $odt->setConfidence($confidence);
                if (! $dryRun) {
                    $this->em->persist($odt);
                }
            }

            ++$stats['odt'];
        }
    }
}
