<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Data\SpiceDuoImporter;
use App\ValueObject\SpiceDuoRow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import:spice-duos',
    description: 'Importe les duos méthode × moment (Mot du Chef) depuis un CSV de data/.',
)]
final class ImportSpiceDuosCommand extends Command
{
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly SpiceDuoImporter $importer,
        private readonly EntityManagerInterface $em,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin CSV (dans data/)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation : tout est exécuté puis annulé.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $file */
        $file = $input->getArgument('file');
        $dryRun = (bool) $input->getOption('dry-run');

        $resolvedPath = realpath($file) ?: realpath($this->projectDir . '/' . ltrim($file, '/'));
        $allowedDir = realpath($this->projectDir . '/data');

        if ($resolvedPath === false || $allowedDir === false || ! str_starts_with($resolvedPath, $allowedDir . '/')) {
            $io->error(\sprintf('Le fichier "%s" doit se trouver dans data/.', $file));

            return Command::FAILURE;
        }

        $size = filesize($resolvedPath);
        if ($size === false || $size > self::MAX_FILE_SIZE) {
            $io->error('Fichier trop volumineux (max 10 Mo).');

            return Command::FAILURE;
        }

        $handle = fopen($resolvedPath, 'r');
        if ($handle === false) {
            $io->error('Lecture impossible.');

            return Command::FAILURE;
        }

        $io->title(\sprintf('Import duos depuis %s', $resolvedPath));
        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune écriture en BDD.');
        }

        $header = fgetcsv($handle, escape: '\\');
        if (! \is_array($header)) {
            fclose($handle);
            $io->error('CSV vide.');

            return Command::FAILURE;
        }
        $header = array_map(static fn (?string $h): string => trim(str_replace("\u{FEFF}", '', (string) $h)), $header);

        $missing = array_diff(SpiceDuoRow::COLUMNS, $header);
        if ($missing !== []) {
            fclose($handle);
            $io->error('Colonnes manquantes : ' . implode(', ', $missing));

            return Command::FAILURE;
        }

        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        $created = 0;
        $updated = 0;
        $errors = [];
        $seen = [];
        $line = 1;

        try {
            while (($cols = fgetcsv($handle, escape: '\\')) !== false) {
                ++$line;
                if ($cols === [null]) {
                    continue;
                }

                $data = [];
                foreach ($header as $i => $name) {
                    $data[$name] = $cols[$i] ?? null;
                }

                try {
                    $row = SpiceDuoRow::fromArray($data);
                    $key = $row->pairKey();
                    if (isset($seen[$key])) {
                        throw new \RuntimeException(\sprintf('doublon de la ligne %d (même épice, méthode et moment).', $seen[$key]));
                    }
                    $seen[$key] = $line;

                    $this->importer->upsert($row) ? ++$created : ++$updated;
                } catch (\InvalidArgumentException|\RuntimeException $e) {
                    $errors[] = \sprintf('Ligne %d : %s', $line, $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        if ($errors !== [] || $dryRun) {
            $connection->rollBack();
        } else {
            $connection->commit();
        }

        $io->table(['Créés', 'Mis à jour', 'Erreurs'], [[$created, $updated, \count($errors)]]);

        if ($errors !== []) {
            $io->error($errors);
            $io->note('Aucune écriture (tout ou rien).');

            return Command::FAILURE;
        }

        $io->success($dryRun ? 'Dry-run OK, rien écrit.' : 'Import terminé.');

        return Command::SUCCESS;
    }
}
