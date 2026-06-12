<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CompoundPhysical;
use App\Enum\DataConfidence;
use App\Repository\AromaticCompoundRepository;
use App\Repository\CompoundPhysicalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Auto-fetch des propriétés physico-chimiques depuis PubChem (Plan Phase 2).
 *
 * Pour chaque composé en base ayant un CAS valide mais une donnée physique
 * manquante (logP non renseigné), interroge PubChem pour récupérer XLogP3 et
 * persiste en base avec confidence = ESTIMATED (XLogP3 est une valeur prédite).
 *
 * Industrialise la collecte automatisable de la couche physico-chimique sur les
 * ~60 composés de la Phase B du plan d'acquisition. Boiling point + vapor
 * pressure restent manuels (sous des endpoints PubChem moins normalisés).
 *
 * Usage :
 *   bin/console app:fetch:physical              # tous les composés avec CAS, sans logP
 *   bin/console app:fetch:physical --all        # tous, force re-fetch
 *   bin/console app:fetch:physical --dry-run    # simulation
 *
 * Respecte les guidelines PubChem (max 5 req/s — délai 250 ms entre requêtes).
 *
 * @see docs/PLAN_ACQUISITION_DONNEES.md
 */
#[AsCommand(
    name: 'app:fetch:physical',
    description: 'Auto-fetch XLogP3 depuis PubChem pour les composés en base.',
)]
final class FetchPhysicalFromPubChemCommand extends Command
{
    private const string PUBCHEM_BASE = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug';

    /**
     * Délai entre requêtes (μs) — PubChem recommande max 5 req/s.
     */
    private const int REQUEST_DELAY_US = 250_000;

    public function __construct(
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly CompoundPhysicalRepository $compoundPhysicalRepository,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Re-fetch même si logP déjà renseigné.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $forceAll = (bool) $input->getOption('all');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune écriture en BDD.');
        }

        $compounds = $this->aromaticCompoundRepository->findAll();
        $fetched = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($compounds as $compound) {
            $compoundId = $compound->getId();
            $name = (string) $compound->getName();
            $cas = $compound->getCasNumber();

            if ($compoundId === null || $cas === null || trim($cas) === '') {
                $io->text(\sprintf('  SKIP %s : pas de CAS', $name));
                ++$skipped;
                continue;
            }

            $existing = $this->compoundPhysicalRepository->findOneBy([
                'compound' => $compound,
            ]);

            if (! $forceAll && $existing?->getLogP() !== null) {
                $io->text(\sprintf('  SKIP %s : logP déjà renseigné (%g)', $name, $existing->getLogP()));
                ++$skipped;
                continue;
            }

            [$logP, $formula] = $this->fetchProperties($cas, $io);
            if ($logP === null) {
                ++$failed;
                usleep(self::REQUEST_DELAY_US);
                continue;
            }

            if (! $dryRun) {
                if ($formula !== null && $compound->getFormula() === null) {
                    $compound->setFormula($formula);
                }

                $target = $existing ?? new CompoundPhysical($compound);
                $target->setLogP($logP);
                $target->setSource(\sprintf('PubChem XLogP3 (auto-fetch via CAS %s)', $cas));
                // XLogP3 = prédiction algorithmique → tier ESTIMATED.
                // Si la valeur existante était MEASURED/LITERATURE, on ne dégrade pas.
                if ($existing === null || $existing->getConfidence() === DataConfidence::PLACEHOLDER) {
                    $target->setConfidence(DataConfidence::ESTIMATED);
                }
                if ($existing === null) {
                    $this->em->persist($target);
                }
            }

            $io->text(\sprintf('  FETCH %s (%s) → XLogP3 = %g', $name, $cas, $logP));
            ++$fetched;
            usleep(self::REQUEST_DELAY_US);
        }

        if (! $dryRun && $fetched > 0) {
            $this->em->flush();
        }

        $io->success(\sprintf(
            'Auto-fetch terminé — %d fetchés, %d ignorés, %d échoués%s.',
            $fetched,
            $skipped,
            $failed,
            $dryRun ? ' (dry-run)' : '',
        ));

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Interroge PubChem `/compound/name/{CAS}/property/XLogP,MolecularFormula/JSON`.
     *
     * @return array{0: float|null, 1: string|null} [XLogP3, formule brute] — logP null si échec/absence
     */
    private function fetchProperties(string $cas, SymfonyStyle $io): array
    {
        $url = self::PUBCHEM_BASE . '/compound/name/' . urlencode($cas) . '/property/XLogP,MolecularFormula/JSON';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                $io->text(\sprintf('  ❌ PubChem HTTP %d pour CAS %s', $response->getStatusCode(), $cas));

                return [null, null];
            }

            $data = $response->toArray();
            $props = $data['PropertyTable']['Properties'][0] ?? null;
            if (! is_array($props)) {
                $io->text(\sprintf('  ❌ Pas de propriétés pour CAS %s', $cas));

                return [null, null];
            }

            $formula = isset($props['MolecularFormula']) && is_string($props['MolecularFormula'])
                ? $props['MolecularFormula']
                : null;

            $rawLogP = $props['XLogP'] ?? null;
            if (! is_numeric($rawLogP) || ! is_finite((float) $rawLogP)) {
                $io->text(\sprintf('  ❌ Pas de XLogP pour CAS %s', $cas));

                return [null, $formula];
            }

            return [(float) $rawLogP, $formula];
        } catch (TransportExceptionInterface $e) {
            $io->text('  ❌ Erreur réseau PubChem : ' . $e->getMessage());

            return [null, null];
        }
    }
}
