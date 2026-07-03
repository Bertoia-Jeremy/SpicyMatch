<?php

declare(strict_types=1);

namespace App\Command;

use App\ValueObject\CasNumber;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Intégrité OFFLINE des composés (CAS présent/valide/unique, formule).
 * Complète app:validate:compounds (cross-check PubChem online). Garde CI/import.
 *
 * Règles dures (→ FAILURE) :
 *   - CAS manquant
 *   - CAS de format ou checksum invalide (faute de frappe)
 *   - CAS dupliqué (deux composés au même CAS)
 *   - formule manquante
 *
 * Règles souples (→ warning, n'échoue pas) :
 *   - nom potentiellement ambigu sur la chiralité (isomère) sans préfixe stéréo
 *     (ex: "Carvone" sans R-/S- → R et S ont des odeurs opposées)
 *
 * Usage :
 *   php bin/console app:check:compounds
 *   php bin/console app:check:compounds --strict   # les warnings deviennent bloquants
 */
#[AsCommand(
    name: 'app:check:compounds',
    description: 'Contrôle intégrité offline des composés (CAS présent/valide/unique, formule).',
)]
final class CheckCompoundsIntegrityCommand extends Command
{
    /**
     * Racines de noms dont la forme nue masque une ambiguïté d'isomère perceptuellement
     * significative. Détecté si le nom contient la racine sans marqueur stéréo (R-, S-,
     * D-, L-, cis-, trans-, (+)-, (-)-, α-, β-, …).
     *
     * @var list<string>
     */
    private const array ISOMER_SENSITIVE_ROOTS = [
        'carvone',   // R = carvi/aneth, S = menthe verte
        'limonène',  // D = orange, L = térébenthine/pin
        'limonene',
        'anéthol',   // cis (toxique) vs trans (anisé)
        'anethol',
        'linalol',   // R vs S nuances florales
        'menthol',
        'pinène',
        'pinene',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'strict',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'Traite les warnings (ambiguïté isomère) comme bloquants.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $strict = (bool) $input->getOption('strict');

        /** @var list<array{id: int, name: string, cas_number: ?string, formula: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, cas_number, formula FROM aromatic_compound WHERE deleted_at IS NULL ORDER BY id',
        );

        $errors = [];
        $warnings = [];
        $seenCas = [];

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $cas = $row['cas_number'];
            $formula = $row['formula'];

            if (null === $cas || '' === trim((string) $cas)) {
                $errors[] = \sprintf('#%d "%s" : CAS manquant.', $row['id'], $name);
            } else {
                $cas = trim((string) $cas);
                if (! CasNumber::isValid($cas)) {
                    $errors[] = \sprintf('#%d "%s" : CAS "%s" invalide (format ou checksum).', $row['id'], $name, $cas);
                } elseif (isset($seenCas[$cas])) {
                    $errors[] = \sprintf(
                        '#%d "%s" : CAS "%s" dupliqué (déjà sur "%s").',
                        $row['id'],
                        $name,
                        $cas,
                        $seenCas[$cas]
                    );
                } else {
                    $seenCas[$cas] = $name;
                }
            }

            if (null === $formula || '' === trim((string) $formula)) {
                $errors[] = \sprintf('#%d "%s" : formule manquante.', $row['id'], $name);
            }

            if ($this->isIsomerAmbiguous($name)) {
                $warnings[] = \sprintf('#%d "%s" : nom sans marqueur stéréo — isomère ambigu.', $row['id'], $name);
            }
        }

        foreach ($warnings as $w) {
            $io->warning($w);
        }

        if ([] !== $errors) {
            $io->error(\sprintf('%d erreur(s) d\'intégrité :', count($errors)));
            $io->listing($errors);

            return Command::FAILURE;
        }

        if ($strict && [] !== $warnings) {
            $io->error(\sprintf('%d warning(s) bloquant(s) en mode --strict.', count($warnings)));

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Intégrité OK — %d composés, CAS valides/uniques, formules présentes%s.',
            count($rows),
            [] !== $warnings ? \sprintf(' (%d warning isomère)', count($warnings)) : '',
        ));

        return Command::SUCCESS;
    }

    private function isIsomerAmbiguous(string $name): bool
    {
        $lower = mb_strtolower($name);

        // Présence d'un marqueur stéréo → considéré comme désambiguïsé.
        $hasStereoMarker = 1 === preg_match(
            '/(^|[^a-z])(r|s|d|l|cis|trans|\(\+\)|\(-\)|α|β|alpha|beta)[\s\-]/iu',
            $name
        );
        if ($hasStereoMarker) {
            return false;
        }

        foreach (self::ISOMER_SENSITIVE_ROOTS as $root) {
            if (str_contains($lower, $root)) {
                return true;
            }
        }

        return false;
    }
}
