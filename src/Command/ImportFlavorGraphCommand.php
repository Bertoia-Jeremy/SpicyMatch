<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import:flavorgraph',
    description: 'Importe la matrice d\'affinité FlavorGraph dans ingredient_pairing (swap atomique)'
)]
final class ImportFlavorGraphCommand extends Command
{
    private const DEFAULT_FILE = 'data/flavorgraph/pairing_matrix.csv';
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const BATCH = 1000;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Chemin CSV matrice', self::DEFAULT_FILE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $path = $this->resolvePath((string) $input->getOption('file'));
        if (null === $path) {
            $io->error('Fichier hors périmètre autorisé (data/flavorgraph/) ou introuvable.');

            return Command::FAILURE;
        }

        $slugToId = $this->loadSlugToId();

        $handle = fopen($path, 'rb');
        if (false === $handle) {
            $io->error('CSV illisible.');

            return Command::FAILURE;
        }

        $header = fgetcsv($handle, escape: '\\');
        if (false === $header || ! \in_array('affinity', $header, true)) {
            fclose($handle);
            $io->error('En-tête CSV invalide (colonnes attendues : spice_slug_a, spice_slug_b, affinity).');

            return Command::FAILURE;
        }
        $idx = array_flip($header);

        $rows = [];
        $unresolved = 0;
        while (false !== ($cols = fgetcsv($handle, escape: '\\'))) {
            $a = $slugToId[$cols[$idx['spice_slug_a']] ?? ''] ?? null;
            $b = $slugToId[$cols[$idx['spice_slug_b']] ?? ''] ?? null;
            if (null === $a || null === $b) {
                ++$unresolved;

                continue;
            }
            $score = max(0.0, min(1.0, (float) ($cols[$idx['affinity']] ?? 0.0)));
            $rows[] = [$a, $b, $score];
            $rows[] = [$b, $a, $score];
        }
        fclose($handle);

        if ($dryRun) {
            $io->success(
                \sprintf('DRY-RUN : %d lignes à insérer (2×paires), %d paires ignorées (slug non résolu).', \count(
                    $rows
                ), $unresolved)
            );

            return Command::SUCCESS;
        }

        $inserted = $this->rebuild($rows);

        $io->success(
            \sprintf('Import : %d lignes dans ingredient_pairing, %d paires ignorées.', $inserted, $unresolved)
        );

        return Command::SUCCESS;
    }

    /**
     * @param list<array{0: int, 1: int, 2: float}> $rows
     */
    private function rebuild(array $rows): int
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ingredient_pairing_tmp');
        $this->connection->executeStatement('DROP TABLE IF EXISTS ingredient_pairing_old');
        $this->connection->executeStatement('CREATE TABLE ingredient_pairing_tmp LIKE ingredient_pairing');

        $inserted = 0;
        $this->connection->beginTransaction();
        try {
            foreach (array_chunk($rows, self::BATCH) as $chunk) {
                $values = implode(',', array_map(
                    static fn (array $r): string => \sprintf('(%d,%d,%F)', $r[0], $r[1], $r[2]),
                    $chunk,
                ));
                $inserted += $this->connection->executeStatement(
                    'INSERT INTO ingredient_pairing_tmp (spice_a_id, spice_b_id, affinity_score) VALUES '.$values,
                );
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->connection->executeStatement('DROP TABLE IF EXISTS ingredient_pairing_tmp');

            throw $e;
        }

        $this->connection->executeStatement(
            'RENAME TABLE ingredient_pairing TO ingredient_pairing_old, ingredient_pairing_tmp TO ingredient_pairing',
        );
        $this->connection->executeStatement('DROP TABLE ingredient_pairing_old');

        return $inserted;
    }

    /**
     * @return array<string, int>
     */
    private function loadSlugToId(): array
    {
        $map = [];
        $result = $this->connection->executeQuery('SELECT id, slug FROM spices WHERE deleted_at IS NULL');
        /** @var array{id: int, slug: string} $row */
        foreach ($result->iterateAssociative() as $row) {
            $map[$row['slug']] = (int) $row['id'];
        }

        return $map;
    }

    private function resolvePath(string $file): ?string
    {
        $candidate = str_starts_with($file, '/') ? $file : $this->projectDir.'/'.$file;
        $real = realpath($candidate);
        if (false === $real || ! is_file($real) || filesize($real) > self::MAX_FILE_SIZE) {
            return null;
        }

        $allowed = realpath($this->projectDir.'/data/flavorgraph');
        if (false === $allowed || ! str_starts_with($real, $allowed.'/')) {
            return null;
        }

        return $real;
    }
}
