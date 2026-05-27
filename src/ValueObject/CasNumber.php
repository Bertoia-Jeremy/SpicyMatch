<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Numéro CAS (Chemical Abstracts Service) — identifiant universel d'un composé.
 *
 * Format : jusqu'à 10 chiffres en 3 blocs `XXXXXXX-YY-Z` :
 *   - bloc 1 : 2 à 7 chiffres
 *   - bloc 2 : 2 chiffres
 *   - bloc 3 : 1 chiffre de contrôle (checksum)
 *
 * Le chiffre de contrôle suit un algorithme déterministe : en lisant les chiffres
 * de droite à gauche (hors checksum), chaque chiffre est multiplié par sa position
 * (1, 2, 3, …) ; la somme mod 10 doit égaler le checksum.
 *
 * Exemple — Eugénol 97-53-0 :
 *   chiffres (hors check) lus de droite : 3,5,7,9
 *   3×1 + 5×2 + 7×3 + 9×4 = 3 + 10 + 21 + 36 = 70 → 70 mod 10 = 0 ✓
 *
 * Intérêt qualité (Levier 1) : le checksum détecte les fautes de frappe / digits
 * transposés à la saisie — un CAS mal recopié échoue la validation au lieu de
 * pointer silencieusement vers le mauvais composé (ou aucun).
 *
 * Immuable (readonly). Construire via fromString() ; isValid() pour tester sans throw.
 */
final readonly class CasNumber
{
    private const string PATTERN = '/^(\d{2,7})-(\d{2})-(\d)$/';

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $raw): self
    {
        $normalized = trim($raw);

        if (! self::matchesFormat($normalized, $digits, $checkDigit)) {
            throw new \InvalidArgumentException(\sprintf('Numéro CAS de format invalide : "%s".', $raw));
        }

        if (! self::checksumValid($digits, $checkDigit)) {
            throw new \InvalidArgumentException(\sprintf(
                'Numéro CAS "%s" : chiffre de contrôle invalide (faute de frappe probable).',
                $raw
            ), );
        }

        return new self($normalized);
    }

    public static function isValid(string $raw): bool
    {
        $normalized = trim($raw);

        return self::matchesFormat($normalized, $digits, $checkDigit)
            && self::checksumValid($digits, $checkDigit);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Vérifie le format et extrait la chaîne de chiffres (hors checksum) + le checksum.
     *
     * @param-out string $digits    Concaténation bloc1+bloc2 (sans tirets ni checksum)
     * @param-out int    $checkDigit Chiffre de contrôle déclaré
     */
    private static function matchesFormat(string $value, ?string &$digits = null, ?int &$checkDigit = null): bool
    {
        if (preg_match(self::PATTERN, $value, $m) !== 1) {
            return false;
        }

        $digits = $m[1] . $m[2];
        $checkDigit = (int) $m[3];

        return true;
    }

    /**
     * Valide le chiffre de contrôle CAS.
     */
    private static function checksumValid(string $digits, int $checkDigit): bool
    {
        $sum = 0;
        $position = 1;

        // Parcours de droite à gauche, poids croissant 1, 2, 3, …
        for ($i = strlen($digits) - 1; $i >= 0; --$i) {
            $sum += ((int) $digits[$i]) * $position;
            ++$position;
        }

        return ($sum % 10) === $checkDigit;
    }
}
