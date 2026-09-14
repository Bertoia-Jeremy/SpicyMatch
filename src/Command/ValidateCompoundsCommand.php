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
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:validate:compounds',
    description: 'Double-vérifie CAS + formules via PubChem API. Génère un rapport YAML.',
)]
final class ValidateCompoundsCommand extends Command
{
    private const PUBCHEM_BASE = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug';

    private const REQUEST_DELAY_US = 250_000;

    private const NAME_SIMILARITY_THRESHOLD = 55;

    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'apply',
            null,
            InputOption::VALUE_NONE,
            'Applique les corrections de formule en DB (sans --apply : rapport seul)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $date = (new \DateTimeImmutable())->format('Y-m-d_His');

        $io->title('Validation CAS + Formules via PubChem — ' . $date);

        if ($apply) {
            $io->warning('Mode APPLY : les corrections de formule seront appliquées en DB.');
        } else {
            $io->note('Mode RAPPORT : aucune modification en DB (ajouter --apply pour corriger).');
        }

        $compounds = $this->connection->fetchAllAssociative(
            'SELECT id, name, cas_number, formula FROM aromatic_compound
             WHERE cas_number IS NOT NULL AND deleted_at IS NULL
             ORDER BY id',
        );

        if ($compounds === []) {
            $io->warning('Aucun composé avec numéro CAS en base.');

            return Command::SUCCESS;
        }

        $report = [
            'date' => $date,
            'total' => count($compounds),
            'validated' => 0,
            'warnings' => 0,
            'errors' => 0,
            'corrections_applied' => 0,
            'compounds' => [],
        ];

        foreach ($compounds as $compound) {
            $io->section("#{$compound['id']} — {$compound['name']} (CAS: {$compound['cas_number']})");
            $result = $this->validateCompound($compound, $apply, $io);
            $report['compounds'][] = $result;

            match ($result['status']) {
                'validated' => ++$report['validated'],
                'warning' => ++$report['warnings'],
                'error' => ++$report['errors'],
                default => null,
            };

            if ($result['correction_applied'] ?? false) {
                ++$report['corrections_applied'];
            }

            usleep(self::REQUEST_DELAY_US);
        }

        $this->writeReport($report, $date);

        $io->newLine();
        $io->table(
            ['Statut', 'Nombre'],
            [
                ['✅ Validés', (string) $report['validated']],
                ['⚠️  Avertissements', (string) $report['warnings']],
                ['❌ Erreurs', (string) $report['errors']],
                ['🔧 Corrections appliquées', (string) $report['corrections_applied']],
            ],
        );

        $reportPath = "data/validation_reports/cas_validation_{$date}.yaml";
        $io->success("Rapport écrit dans : {$reportPath}");

        return $report['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array{id: int|string, name: string, cas_number: string, formula: string|null} $compound
     * @return array<string, mixed>
     */
    private function validateCompound(array $compound, bool $apply, SymfonyStyle $io): array
    {
        $cas = $compound['cas_number'];
        $storedFormula = $compound['formula'];
        $storedName = $compound['name'];

        if (! $this->isValidCasFormat($cas)) {
            $io->error("CAS invalide (format inattendu) : {$cas}");

            return [
                'id' => $compound['id'],
                'name' => $storedName,
                'cas' => $cas,
                'status' => 'error',
                'issue' => "Format CAS invalide : {$cas}",
            ];
        }

        $properties = $this->fetchPubChemProperties($cas, $io);
        if ($properties === null) {
            return [
                'id' => $compound['id'],
                'name' => $storedName,
                'cas' => $cas,
                'status' => 'error',
                'issue' => 'PubChem inaccessible ou CAS non trouvé',
            ];
        }

        $pubchemFormula = $properties['MolecularFormula'];
        $pubchemIupac = $properties['IUPACName'];

        $synonyms = $this->fetchPubChemSynonyms($cas, $io);
        $nameFound = $this->nameInSynonyms($storedName, $synonyms, $pubchemIupac);
        $nameSimilarity = $this->computeNameSimilarity($storedName, $pubchemIupac);

        $formulaMatch = $pubchemFormula === $storedFormula;
        $issues = [];
        $status = 'validated';

        if (! $formulaMatch) {
            $issues[] = "Formule stockée [{$storedFormula}] ≠ PubChem [{$pubchemFormula}]";
            $status = 'warning';

            if ($apply) {
                $this->connection->executeStatement(
                    'UPDATE aromatic_compound SET formula = ?, updated_at = NOW() WHERE id = ?',
                    [$pubchemFormula, $compound['id']],
                );
                $io->text("  🔧 Formule corrigée : {$storedFormula} → {$pubchemFormula}");
            }
        }

        if (! $nameFound) {
            $issues[] = sprintf(
                'Nom [%s] non trouvé dans synonymes PubChem (IUPAC: %s, similarité: %d%%)',
                $storedName,
                $pubchemIupac,
                $nameSimilarity,
            );

            if ($nameSimilarity < self::NAME_SIMILARITY_THRESHOLD) {
                $status = 'error';
            } else {
                $status = 'warning';
            }
        }

        if ($issues === []) {
            $io->text("  ✅ CAS confirmé, formule et nom concordants (IUPAC: {$pubchemIupac})");
        } else {
            foreach ($issues as $issue) {
                $io->text("  ⚠️  {$issue}");
            }
        }

        return [
            'id' => $compound['id'],
            'name' => $storedName,
            'cas' => $cas,
            'status' => $status,
            'stored_formula' => $storedFormula,
            'pubchem_formula' => $pubchemFormula,
            'formula_match' => $formulaMatch,
            'pubchem_iupac' => $pubchemIupac,
            'name_in_synonyms' => $nameFound,
            'name_similarity_pct' => $nameSimilarity,
            'issues' => $issues,
            'correction_applied' => $apply && ! $formulaMatch,
        ];
    }

    /**
     * @return array{MolecularFormula: string, IUPACName: string}|null
     */
    private function fetchPubChemProperties(string $cas, SymfonyStyle $io): ?array
    {
        $url = self::PUBCHEM_BASE . '/compound/name/' . urlencode($cas) . '/property/MolecularFormula,IUPACName/JSON';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                $io->text("  ❌ PubChem HTTP {$response->getStatusCode()} pour CAS {$cas}");

                return null;
            }

            $data = $response->toArray();
            $props = $data['PropertyTable']['Properties'][0] ?? null;

            if ($props === null) {
                $io->text("  ❌ Aucune donnée PubChem pour CAS {$cas}");

                return null;
            }

            $formula = $this->sanitizeFormula((string) ($props['MolecularFormula'] ?? ''));
            $iupac = $this->sanitizeText((string) ($props['IUPACName'] ?? ''));

            return [
                'MolecularFormula' => $formula,
                'IUPACName' => $iupac,
            ];
        } catch (TransportExceptionInterface $e) {
            $io->text('  ❌ Erreur réseau PubChem : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return string[]
     */
    private function fetchPubChemSynonyms(string $cas, SymfonyStyle $io): array
    {
        $url = self::PUBCHEM_BASE . '/compound/name/' . urlencode($cas) . '/synonyms/JSON';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray();
            $synonyms = $data['InformationList']['Information'][0]['Synonym'] ?? [];

            return array_map(fn (mixed $s): string => $this->sanitizeText((string) $s), $synonyms);
        } catch (TransportExceptionInterface $e) {
            return [];
        }
    }

    /**
     * @param string[] $synonyms
     */
    private function nameInSynonyms(string $storedName, array $synonyms, string $iupacName): bool
    {
        $needle = mb_strtolower($storedName);

        foreach ($synonyms as $synonym) {
            if (mb_strtolower($synonym) === $needle) {
                return true;
            }
        }

        $needleAscii = $this->toAscii($storedName);

        foreach ($synonyms as $synonym) {
            if ($this->toAscii($synonym) === $needleAscii) {
                return true;
            }
        }

        return $this->computeNameSimilarity($storedName, $iupacName) >= self::NAME_SIMILARITY_THRESHOLD;
    }

    private function computeNameSimilarity(string $a, string $b): int
    {
        similar_text(mb_strtolower($this->toAscii($a)), mb_strtolower($this->toAscii($b)), $percent);

        return (int) round($percent);
    }

    private function isValidCasFormat(string $cas): bool
    {
        return (bool) preg_match('/^\d{2,7}-\d{2}-\d$/', $cas);
    }

    private function sanitizeFormula(string $raw): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '';

        if (! preg_match('/^[A-Z]/', $clean) || strlen($clean) > 50) {
            throw new \RuntimeException('Formule PubChem suspecte ou trop longue : ' . mb_substr($raw, 0, 100));
        }

        return $clean;
    }

    private function sanitizeText(string $raw): string
    {
        $clean = strip_tags($raw);
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? $clean;

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($clean, \Normalizer::FORM_C);
            $clean = $normalized !== false ? $normalized : $clean;
        }

        $clean = mb_substr($clean, 0, 500);

        $injectionPatterns = ['/\[INST\]/i', '/###\s*System/i', '/<\|im_start\|>/i', '/Ignore previous instructions/i'];
        foreach ($injectionPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                throw new \RuntimeException('Contenu suspect détecté dans la réponse PubChem (possible prompt injection)');
            }
        }

        return trim($clean);
    }

    private function toAscii(string $str): string
    {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);

        return $transliterated !== false ? $transliterated : mb_strtolower($str);
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeReport(array $report, string $date): void
    {
        $dir = $this->projectDir . '/data/validation_reports';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $yaml = "# Rapport validation CAS/Formules — SpicyMatch\n";
        $yaml .= "# Source de validation : PubChem REST API (NCBI)\n";
        $yaml .= "date: {$date}\n";
        $yaml .= "total: {$report['total']}\n";
        $yaml .= "validated: {$report['validated']}\n";
        $yaml .= "warnings: {$report['warnings']}\n";
        $yaml .= "errors: {$report['errors']}\n";
        $yaml .= "corrections_applied: {$report['corrections_applied']}\n";
        $yaml .= "compounds:\n";

        foreach ($report['compounds'] as $c) {
            $yaml .= "  - id: {$c['id']}\n";
            $yaml .= "    name: \"{$c['name']}\"\n";
            $yaml .= "    cas: \"{$c['cas']}\"\n";
            $yaml .= "    status: {$c['status']}\n";

            if (isset($c['stored_formula'])) {
                $yaml .= "    stored_formula: \"{$c['stored_formula']}\"\n";
                $yaml .= "    pubchem_formula: \"{$c['pubchem_formula']}\"\n";
                $yaml .= '    formula_match: ' . ($c['formula_match'] ? 'true' : 'false') . "\n";
                $yaml .= "    pubchem_iupac: \"{$c['pubchem_iupac']}\"\n";
                $yaml .= '    name_in_synonyms: ' . ($c['name_in_synonyms'] ? 'true' : 'false') . "\n";
                $yaml .= "    name_similarity_pct: {$c['name_similarity_pct']}\n";
            }

            if (! empty($c['issues'])) {
                $yaml .= "    issues:\n";
                foreach ($c['issues'] as $issue) {
                    $yaml .= '      - "' . addslashes($issue) . "\"\n";
                }
            }

            if (isset($c['issue'])) {
                $yaml .= "    issue: \"{$c['issue']}\"\n";
            }
        }

        file_put_contents("{$dir}/cas_validation_{$date}.yaml", $yaml);
    }
}
