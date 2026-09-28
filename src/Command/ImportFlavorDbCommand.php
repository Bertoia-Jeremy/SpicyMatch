<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SpiceCompoundConcentration;
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

#[AsCommand(
    name: 'app:import:flavordb',
    description: 'Ingère les concentrations de composés depuis FlavorDB ou un dump YAML'
)]
final class ImportFlavorDbCommand extends Command
{
    private const string DEFAULT_FILE = 'fixtures/spice_compound_concentration.yaml';

    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire(param: 'kernel.project_dir')]
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

        $resolvedPath = realpath($file);
        $allowedDir = realpath($this->projectDir . '/fixtures');

        if ($resolvedPath === false || $allowedDir === false || ! str_starts_with($resolvedPath, $allowedDir . '/')) {
            $io->error(sprintf('Le fichier "%s" doit se trouver dans le répertoire fixtures/ du projet.', $file));

            return Command::FAILURE;
        }

        $fileSize = filesize($resolvedPath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            $io->error('Fichier trop volumineux (max 10 Mo).');

            return Command::FAILURE;
        }

        $io->title(sprintf('Import concentrations FlavorDB depuis %s', $resolvedPath));
        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune écriture en BDD.');
        }

        $entries = Yaml::parseFile($resolvedPath, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        if (! is_array($entries)) {
            $io->error('Le fichier YAML doit contenir une liste de concentrations.');

            return Command::FAILURE;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

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

            if ($spiceName === null || $compoundName === null || $concentrationPpmRaw === null) {
                $io->warning(sprintf('Entrée ignorée (champs manquants) : %s', json_encode($entry)));
                ++$skipped;
                continue;
            }

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

            $concentrationPpm = (float) $concentrationPpmRaw;
            if ($concentrationPpm < 0.0) {
                $io->warning(
                    sprintf('concentration_ppm négative pour "%s/%s" — ignorée.', $spiceName, $compoundName)
                );
                ++$skipped;
                continue;
            }

            $spice = $spiceCache[$spiceName] ??= $this->spicesRepository->findOneBy([
                'name' => $spiceName,
            ]);
            if ($spice === null) {
                $io->warning(sprintf('Épice "%s" introuvable — ignorée.', $spiceName));
                ++$skipped;
                continue;
            }

            $compound = $compoundCache[$compoundName] ??= $this->aromaticCompoundRepository->findOneBy([
                'name' => $compoundName,
            ]);
            if ($compound === null) {
                $io->warning(sprintf('Composé "%s" introuvable — ignoré.', $compoundName));
                ++$skipped;
                continue;
            }

            $existing = $this->em->find(
                SpiceCompoundConcentration::class,
                [
                    'spice' => $spice,
                    'aromaticCompound' => $compound,
                ]
            );

            if ($existing !== null) {
                $existing->setConcentrationPpm((string) $concentrationPpm);
                $existing->setSource($source);
                $io->text(sprintf('  UPDATE %s / %s = %s ppm', $spiceName, $compoundName, $concentrationPpm));
                ++$updated;
            } else {
                $concentration = new SpiceCompoundConcentration(
                    $spice,
                    $compound,
                    (string) $concentrationPpm,
                    $source
                );
                $this->em->persist($concentration);
                $io->text(sprintf('  INSERT %s / %s = %s ppm', $spiceName, $compoundName, $concentrationPpm));
                ++$inserted;
            }

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
