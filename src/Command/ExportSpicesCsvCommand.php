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

/**
 * Exporte toutes les entités épices en CSV.
 *
 * CSV = source de vérité éditoriale. À exécuter :
 *   - AVANT un vidage DB (snapshot de sauvegarde)
 *   - APRÈS un import (snapshot post-import daté)
 *
 * Usage :
 *   php bin/console app:export:spices-csv
 *   php bin/console app:export:spices-csv --output-dir=data/exports
 */
#[AsCommand(
    name: 'app:export:spices-csv',
    description: 'Export all spice entities to dated CSV files in data/exports/',
)]
final class ExportSpicesCsvCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output-dir',
            null,
            InputOption::VALUE_OPTIONAL,
            'Répertoire de sortie (relatif à la racine du projet)',
            'data/exports',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = (new \DateTimeImmutable())->format('Y-m-d_His');

        $outputDir = $this->projectDir . '/' . ltrim((string) $input->getOption('output-dir'), '/');

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0755, true)) {
            $io->error("Impossible de créer le répertoire : {$outputDir}");

            return Command::FAILURE;
        }

        $io->title('Export CSV Épices SpicyMatch — ' . $date);

        $exports = [
            'aromatic_groups' => $this->exportAromaticGroups($outputDir, $date),
            'spicy_types' => $this->exportSpicyTypes($outputDir, $date),
            'alchemy_flavors' => $this->exportAlchemyFlavors($outputDir, $date),
            'aromatic_compounds' => $this->exportAromaticCompounds($outputDir, $date),
            'spices' => $this->exportSpices($outputDir, $date),
            'spice_groups_mapping' => $this->exportSpiceGroupsMapping($outputDir, $date),
            'compound_odt' => $this->exportCompoundOdt($outputDir, $date),
            'spice_compound_concentration' => $this->exportSpiceCompoundConcentration($outputDir, $date),
            'spice_active_compound' => $this->exportSpiceActiveCompound($outputDir, $date),
        ];

        $io->table(
            ['Fichier', 'Lignes exportées'],
            array_map(
                static fn (string $_name, array $result): array => [$result['file'], (string) $result['rows']],
                array_keys($exports),
                $exports,
            ),
        );

        $io->success("Export terminé dans : {$outputDir}");

        return Command::SUCCESS;
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportAromaticGroups(string $dir, string $date): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, color, description, cooking, informations
             FROM aromatic_groups
             WHERE deleted_at IS NULL
             ORDER BY id',
        );

        return $this->writeCsv(
            $dir . "/aromatic_groups_{$date}.csv",
            ['id', 'name', 'color', 'description', 'cooking', 'informations'],
            $rows,
        );
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportSpicyTypes(string $dir, string $date): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, description, cooking, informations
             FROM spicy_type
             WHERE deleted_at IS NULL
             ORDER BY id',
        );

        return $this->writeCsv(
            $dir . "/spicy_types_{$date}.csv",
            ['id', 'name', 'description', 'cooking', 'informations'],
            $rows,
        );
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportAlchemyFlavors(string $dir, string $date): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, description, cooking, informations
             FROM alchemy_flavors
             WHERE deleted_at IS NULL
             ORDER BY id',
        );

        return $this->writeCsv(
            $dir . "/alchemy_flavors_{$date}.csv",
            ['id', 'name', 'description', 'cooking', 'informations'],
            $rows,
        );
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportAromaticCompounds(string $dir, string $date): array
    {
        // cas_number et formula peuvent ne pas exister encore en DB — gestion gracieuse
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, name, cas_number, formula, description, cooking, informations
                 FROM aromatic_compound
                 WHERE deleted_at IS NULL
                 ORDER BY id',
            );
            $headers = ['id', 'name', 'cas_number', 'formula', 'description', 'cooking', 'informations'];
        } catch (\Doctrine\DBAL\Exception) {
            // Colonnes cas_number/formula absentes (avant schema:update)
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, name, description, cooking, informations
                 FROM aromatic_compound
                 WHERE deleted_at IS NULL
                 ORDER BY id',
            );
            $headers = ['id', 'name', 'description', 'cooking', 'informations'];
        }

        return $this->writeCsv($dir . "/aromatic_compounds_{$date}.csv", $headers, $rows);
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportSpices(string $dir, string $date): array
    {
        // origin et botanical_family peuvent ne pas exister encore
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT s.id, s.name, s.slug, ag.name AS aromatic_group, st.name AS spicy_type,
                        s.origin, s.botanical_family, s.description, s.cooking, s.informations, s.benefits
                 FROM spices s
                 LEFT JOIN aromatic_groups ag ON ag.id = s.aromaticGroups
                 LEFT JOIN spicy_type st ON st.id = s.spicy_type
                 WHERE s.deleted_at IS NULL
                 ORDER BY s.id',
            );
            $headers = [
                'id',
                'name',
                'slug',
                'aromatic_group',
                'spicy_type',
                'origin',
                'botanical_family',
                'description',
                'cooking',
                'informations',
                'benefits',
            ];
        } catch (\Doctrine\DBAL\Exception) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT s.id, s.name, s.slug, ag.name AS aromatic_group, st.name AS spicy_type,
                        s.description, s.cooking, s.informations, s.benefits
                 FROM spices s
                 LEFT JOIN aromatic_groups ag ON ag.id = s.aromaticGroups
                 LEFT JOIN spicy_type st ON st.id = s.spicy_type
                 WHERE s.deleted_at IS NULL
                 ORDER BY s.id',
            );
            $headers = [
                'id',
                'name',
                'slug',
                'aromatic_group',
                'spicy_type',
                'description',
                'cooking',
                'informations',
                'benefits',
            ];
        }

        return $this->writeCsv($dir . "/spices_{$date}.csv", $headers, $rows);
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportSpiceGroupsMapping(string $dir, string $date): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.name AS spice_name, ac.name AS compound_name, "primary" AS compound_type
             FROM spices_aromatic_compound sac
             JOIN spices s ON s.id = sac.spices_id
             JOIN aromatic_compound ac ON ac.id = sac.aromatic_compound_id
             UNION ALL
             SELECT s.name AS spice_name, ac.name AS compound_name, "secondary" AS compound_type
             FROM secondary_spices_aromatic_compound ssac
             JOIN spices s ON s.id = ssac.spices_id
             JOIN aromatic_compound ac ON ac.id = ssac.aromatic_compound_id
             ORDER BY spice_name, compound_type, compound_name',
        );

        return $this->writeCsv(
            $dir . "/spice_compounds_mapping_{$date}.csv",
            ['spice_name', 'compound_name', 'compound_type'],
            $rows,
        );
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportCompoundOdt(string $dir, string $date): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT ac.name AS aromatic_compound_name, ac.cas_number, co.matrix, co.odt_ppm, co.reference_source
                 FROM compound_odt co
                 JOIN aromatic_compound ac ON ac.id = co.aromatic_compound_id
                 ORDER BY ac.name, co.matrix',
            );
        } catch (\Doctrine\DBAL\Exception) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT ac.name AS aromatic_compound_name, co.matrix, co.odt_ppm, co.reference_source
                 FROM compound_odt co
                 JOIN aromatic_compound ac ON ac.id = co.aromatic_compound_id
                 ORDER BY ac.name, co.matrix',
            );
        }

        if ($rows === []) {
            return $this->writeCsv(
                $dir . "/compound_odt_{$date}.csv",
                ['aromatic_compound_name', 'cas_number', 'matrix', 'odt_ppm', 'reference_source'],
                []
            );
        }

        $headers = array_keys($rows[0]);

        return $this->writeCsv($dir . "/compound_odt_{$date}.csv", $headers, $rows);
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportSpiceCompoundConcentration(string $dir, string $date): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.name AS spice_name, ac.name AS aromatic_compound_name,
                    scc.concentration_ppm, scc.source
             FROM spice_compound_concentration scc
             JOIN spices s ON s.id = scc.spice_id
             JOIN aromatic_compound ac ON ac.id = scc.aromatic_compound_id
             ORDER BY s.name, ac.name',
        );

        return $this->writeCsv(
            $dir . "/spice_compound_concentration_{$date}.csv",
            ['spice_name', 'aromatic_compound_name', 'concentration_ppm', 'source'],
            $rows,
        );
    }

    /**
     * @return array{file: string, rows: int}
     */
    private function exportSpiceActiveCompound(string $dir, string $date): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.name AS spice_name, ac.name AS aromatic_compound_name, sac.oav_value
             FROM spice_active_compound sac
             JOIN spices s ON s.id = sac.spice_id
             JOIN aromatic_compound ac ON ac.id = sac.aromatic_compound_id
             ORDER BY s.name, sac.oav_value DESC',
        );

        return $this->writeCsv(
            $dir . "/spice_active_compound_{$date}.csv",
            ['spice_name', 'aromatic_compound_name', 'oav_value'],
            $rows,
        );
    }

    /**
     * @param string[]                         $headers
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array{file: string, rows: int}
     */
    private function writeCsv(string $filePath, array $headers, array $rows): array
    {
        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier en écriture : {$filePath}");
        }

        // BOM UTF-8 pour compatibilité Excel
        fwrite($handle, "\xEF\xBB\xBF");

        // En-têtes
        fputcsv($handle, $headers, separator: ',', enclosure: '"', escape: '\\');

        // Données
        foreach ($rows as $row) {
            $line = array_map(static function (mixed $value): string {
                if ($value === null) {
                    return '';
                }

                // Sanitize : supprimer les retours à la ligne internes qui casseraient le CSV
                return str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);
            }, $row);

            fputcsv($handle, $line, separator: ',', enclosure: '"', escape: '\\');
        }

        fclose($handle);

        return [
            'file' => basename($filePath),
            'rows' => count($rows),
        ];
    }
}
