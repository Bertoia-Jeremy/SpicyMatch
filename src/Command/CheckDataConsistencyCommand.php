<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Data\DataConsistencyChecker;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check:data',
    description: 'Valide la cohérence cross-tables des données OAV (invariants, sommes, trous).',
)]
final class CheckDataConsistencyCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DataConsistencyChecker $checker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Traite les warnings comme bloquants.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $strict = (bool) $input->getOption('strict');

        $violations = [
            ...$this->checker->checkOavValues($this->fetchOavRows()),
            ...$this->checker->checkConcentrationSums($this->fetchConcentrationSums(), $this->fetchSpiceNames()),
            ...$this->checker->checkMissingAirOdt($this->fetchCompoundsWithoutAirOdt()),
            ...$this->checker->checkCookingMoments($this->fetchCookingTipSteps()),
            ...$this->checker->checkSpiceDuoSpices($this->fetchSpiceDuoSpices()),
            ...$this->checker->checkDuplicatePreparationTips($this->fetchDuplicatePreparationTips()),
            ...$this->checker->checkDuplicateCookingMoments($this->fetchDuplicateCookingMoments()),
        ];

        $errors = array_filter($violations, static fn (array $v) => $v['severity'] === 'error');
        $warnings = array_filter($violations, static fn (array $v) => $v['severity'] === 'warning');

        foreach ($warnings as $w) {
            $io->warning($w['message']);
        }

        if ($errors !== []) {
            $io->error(\sprintf('%d erreur(s) de cohérence :', count($errors)));
            $io->listing(array_map(static fn (array $v) => $v['message'], $errors));

            return Command::FAILURE;
        }

        if ($strict && $warnings !== []) {
            $io->error(\sprintf('%d warning(s) bloquant(s) en mode --strict.', count($warnings)));

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Cohérence OK%s.',
            $warnings !== [] ? \sprintf(' (%d warning)', count($warnings)) : '',
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{spice_id: int, aromatic_compound_id: int, matrix: string, oav_value: float}>
     */
    private function fetchOavRows(): array
    {
        /** @var list<array{spice_id: int, aromatic_compound_id: int, matrix: string, oav_value: float}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT spice_id, aromatic_compound_id, matrix, oav_value FROM spice_active_compound',
        );

        return $rows;
    }

    /**
     * @return array<int, float> spice_id => Σ concentration_ppm
     */
    private function fetchConcentrationSums(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT spice_id, SUM(concentration_ppm) AS total FROM spice_compound_concentration GROUP BY spice_id',
        );

        $sums = [];
        foreach ($rows as $r) {
            $sums[(int) $r['spice_id']] = (float) $r['total'];
        }

        return $sums;
    }

    /**
     * @return array<int, string> spice_id => name
     */
    private function fetchSpiceNames(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, name FROM spices WHERE deleted_at IS NULL');

        $names = [];
        foreach ($rows as $r) {
            $names[(int) $r['id']] = (string) $r['name'];
        }

        return $names;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function fetchCompoundsWithoutAirOdt(): array
    {
        /** @var list<array{id: int, name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT ac.id, ac.name
             FROM aromatic_compound ac
             JOIN spice_compound_concentration c ON c.aromatic_compound_id = ac.id
             WHERE ac.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM compound_odt o
                   WHERE o.aromatic_compound_id = ac.id AND o.matrix = 'air'
               )
             ORDER BY ac.id",
        );

        return $rows;
    }

    /**
     * @return list<array{id: int, step: int}>
     */
    private function fetchCookingTipSteps(): array
    {
        /** @var list<array{id: int, step: int}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT id, step FROM cooking_tips');

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'step' => (int) $r['step'],
        ], $rows);
    }

    /**
     * @return list<array{id: int, prep_spice_id: int, cook_spice_id: int}>
     */
    private function fetchSpiceDuoSpices(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.id, pt.spice_id AS prep_spice_id, ct.spice_id AS cook_spice_id
             FROM spice_duo d
             JOIN preparation_tips pt ON pt.id = d.preparation_tip_id
             JOIN cooking_tips ct ON ct.id = d.cooking_tip_id',
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'prep_spice_id' => (int) $r['prep_spice_id'],
            'cook_spice_id' => (int) $r['cook_spice_id'],
        ], $rows);
    }

    /**
     * @return list<array{spice_id: int, preparation_method_id: int, total: int}>
     */
    private function fetchDuplicatePreparationTips(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT spice_id, preparation_method_id, COUNT(*) AS total
             FROM preparation_tips
             WHERE deleted_at IS NULL
             GROUP BY spice_id, preparation_method_id
             HAVING COUNT(*) > 1',
        );

        return array_map(static fn (array $r): array => [
            'spice_id' => (int) $r['spice_id'],
            'preparation_method_id' => (int) $r['preparation_method_id'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * @return list<array{spice_id: int, step: int, total: int}>
     */
    private function fetchDuplicateCookingMoments(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT spice_id, step, COUNT(*) AS total
             FROM cooking_tips
             WHERE deleted_at IS NULL
             GROUP BY spice_id, step
             HAVING COUNT(*) > 1',
        );

        return array_map(static fn (array $r): array => [
            'spice_id' => (int) $r['spice_id'],
            'step' => (int) $r['step'],
            'total' => (int) $r['total'],
        ], $rows);
    }
}
