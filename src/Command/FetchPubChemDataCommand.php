<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\DataConfidence;
use App\Repository\AromaticCompoundRepository;
use App\Repository\CompoundPhysicalRepository;
use App\Service\PubChem\PubChemCompoundProperties;
use App\Service\PubChem\PubChemPropertyFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Auto-fetch des propriétés PubChem (Plan Phase 2) pour les composés en base.
 *
 * Pour chaque composé avec un CAS valide, interroge PubChem (XLogP3, formule
 * brute, CID, InChIKey) et persiste :
 *   - XLogP3 → CompoundPhysical::logP, confidence ESTIMATED (valeur prédite).
 *   - Formule brute → AromaticCompound::formula, si absente.
 *   - CID / InChIKey → AromaticCompound::pubchemCid / inchiKey, identifiants
 *     canoniques (pas de confidence : ce ne sont pas des mesures).
 *
 * CID et InChIKey sont persistés indépendamment du succès de XLogP3 : les
 * trois propriétés viennent du même appel PUG REST mais n'ont aucune
 * dépendance logique entre elles.
 *
 * Usage :
 *   bin/console app:fetch:pubchem              # composés incomplets (CAS présent)
 *   bin/console app:fetch:pubchem --all        # tous, force re-fetch
 *   bin/console app:fetch:pubchem --force      # écrase aussi les valeurs déjà renseignées
 *                                               # (y compris logP en confidence MEASURED/LITERATURE) — implique --all
 *   bin/console app:fetch:pubchem --dry-run    # simulation
 *
 * Respecte les guidelines PubChem (max 5 req/s — délai 250 ms entre composés).
 *
 * @see docs/PLAN_ACQUISITION_DONNEES.md
 */
#[AsCommand(
    name: 'app:fetch:pubchem',
    description: 'Auto-fetch XLogP3, formule, CID et InChIKey depuis PubChem pour les composés en base.',
)]
final class FetchPubChemDataCommand extends Command
{
    private const string LOCK_NAME = 'spicymatch_fetch_pubchem';

    /**
     * Délai entre composés (μs) — PubChem recommande max 5 req/s.
     */
    private const int REQUEST_DELAY_US = 250_000;

    public function __construct(
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly CompoundPhysicalRepository $compoundPhysicalRepository,
        private readonly EntityManagerInterface $em,
        private readonly PubChemPropertyFetcher $pubChemPropertyFetcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Re-fetch même si toutes les données sont déjà renseignées.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Écrase les valeurs déjà renseignées, y compris logP en confidence MEASURED/LITERATURE (implique --all).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connection = $this->em->getConnection();

        $acquired = $connection->fetchOne('SELECT GET_LOCK(?, ?)', [self::LOCK_NAME, 0]);
        if (null === $acquired || '1' !== (string) $acquired) {
            $io->error('Une autre exécution de app:fetch:pubchem est déjà en cours.');

            return Command::FAILURE;
        }

        try {
            return $this->doExecute($input, $io);
        } finally {
            $connection->executeStatement('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }

    private function doExecute(InputInterface $input, SymfonyStyle $io): int
    {
        $force = (bool) $input->getOption('force');
        $forceAll = $force || (bool) $input->getOption('all');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune écriture en BDD.');
        }

        if ($force) {
            $io->warning('Mode FORCE : écrase les données existantes, y compris logP en confidence MEASURED/LITERATURE.');
        }

        $compounds = $this->aromaticCompoundRepository->findAll();
        $fetched = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($compounds as $compound) {
            $name = (string) $compound->getName();
            $cas = $compound->getCasNumber();

            if (null === $cas || '' === trim($cas)) {
                $io->text(\sprintf('  SKIP %s : pas de CAS', $name));
                ++$skipped;
                continue;
            }

            $existingPhysical = $this->compoundPhysicalRepository->findOneBy([
                'compound' => $compound,
            ]);

            if (! $forceAll
                && null !== $existingPhysical?->getLogP()
                && null !== $compound->getPubchemCid()
                && null !== $compound->getInchiKey()
            ) {
                $io->text(\sprintf('  SKIP %s : logP/CID/InChIKey déjà renseignés', $name));
                ++$skipped;
                continue;
            }

            $properties = $this->pubChemPropertyFetcher->fetch($cas);

            if (null === $properties->logP && null === $properties->formula && null === $properties->cid && null === $properties->inchiKey) {
                $io->text(\sprintf('  ❌ Aucune donnée PubChem pour %s (%s)', $name, $cas));
                ++$failed;
                usleep(self::REQUEST_DELAY_US);
                continue;
            }

            if ($dryRun) {
                $io->text(\sprintf(
                    '  FETCH (dry-run) %s (%s) → XLogP3=%s formule=%s CID=%s InChIKey=%s',
                    $name,
                    $cas,
                    $properties->logP ?? '—',
                    $properties->formula ?? '—',
                    $properties->cid ?? '—',
                    $properties->inchiKey ?? '—',
                ));
            } else {
                $this->applyProperties($compound, $existingPhysical, $properties, $cas, $force, $io);
                $this->em->flush();

                $persistedLogP = $existingPhysical?->getLogP()
                    ?? $this->compoundPhysicalRepository->findOneBy(['compound' => $compound])?->getLogP();

                $io->text(\sprintf(
                    '  FETCH %s (%s) → XLogP3=%s formule=%s CID=%s InChIKey=%s',
                    $name,
                    $cas,
                    $persistedLogP ?? '—',
                    $compound->getFormula() ?? '—',
                    $compound->getPubchemCid() ?? '—',
                    $compound->getInchiKey() ?? '—',
                ));
            }
            ++$fetched;
            usleep(self::REQUEST_DELAY_US);
        }

        $io->success(\sprintf(
            'Auto-fetch terminé — %d fetchés, %d ignorés, %d échoués%s.',
            $fetched,
            $skipped,
            $failed,
            $dryRun ? ' (dry-run)' : '',
        ));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    private function applyProperties(
        AromaticCompound $compound,
        ?CompoundPhysical $existingPhysical,
        PubChemCompoundProperties $properties,
        string $cas,
        bool $force,
        SymfonyStyle $io,
    ): void {
        if (null !== $properties->formula && ($force || null === $compound->getFormula())) {
            $compound->setFormula($properties->formula);
        }

        $protectedTiers = [DataConfidence::MEASURED, DataConfidence::LITERATURE];
        $hasProtectedLogP = ! $force
            && null !== $existingPhysical
            && null !== $existingPhysical->getLogP()
            && \in_array($existingPhysical->getConfidence(), $protectedTiers, true);

        if (null !== $properties->logP && ! $hasProtectedLogP) {
            $target = $existingPhysical ?? new CompoundPhysical($compound);
            $target->setLogP($properties->logP);
            $target->setSource(\sprintf('PubChem XLogP3 (auto-fetch via CAS %s)', $cas));
            if (null === $existingPhysical || DataConfidence::PLACEHOLDER === $existingPhysical->getConfidence() || $force) {
                $target->setConfidence(DataConfidence::ESTIMATED);
            }
            if (null === $existingPhysical) {
                $this->em->persist($target);
            }
        }

        if (null !== $properties->cid && ($force || null === $compound->getPubchemCid())) {
            $collision = $this->aromaticCompoundRepository->findOneBy(['pubchemCid' => $properties->cid]);
            if (null !== $collision && $collision !== $compound) {
                $io->warning(\sprintf(
                    'CID %d déjà attribué à "%s" — non assigné à "%s" (collision à investiguer manuellement).',
                    $properties->cid,
                    (string) $collision->getName(),
                    (string) $compound->getName(),
                ));
            } else {
                $compound->setPubchemCid($properties->cid);
            }
        }

        if (null !== $properties->inchiKey && ($force || null === $compound->getInchiKey())) {
            $collision = $this->aromaticCompoundRepository->findOneBy(['inchiKey' => $properties->inchiKey]);
            if (null !== $collision && $collision !== $compound) {
                $io->warning(\sprintf(
                    'InChIKey %s déjà attribué à "%s" — non assigné à "%s" (collision à investiguer manuellement).',
                    $properties->inchiKey,
                    (string) $collision->getName(),
                    (string) $compound->getName(),
                ));
            } else {
                $compound->setInchiKey($properties->inchiKey);
            }
        }
    }
}
