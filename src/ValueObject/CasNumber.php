<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Numéro CAS au format XXXXXXX-YY-Z (2-7 / 2 / 1 chiffre de contrôle).
 *
 * Le checksum détecte les fautes de frappe : sum(digit_i × position_i) mod 10 = check.
 * Ex. eugénol 97-53-0 → 3×1+5×2+7×3+9×4 = 70 mod 10 = 0 ✓.
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
                $raw,
            ));
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
     * @param-out string $digits     bloc1 + bloc2 concaténés
     * @param-out int    $checkDigit chiffre de contrôle déclaré
     */
    private static function matchesFormat(string $value, ?string &$digits = null, ?int &$checkDigit = null): bool
    {
        if (1 !== preg_match(self::PATTERN, $value, $m)) {
            return false;
        }

        $digits = $m[1].$m[2];
        $checkDigit = (int) $m[3];

        return true;
    }

    private static function checksumValid(string $digits, int $checkDigit): bool
    {
        $sum = 0;
        $position = 1;

        for ($i = strlen($digits) - 1; $i >= 0; --$i) {
            $sum += ((int) $digits[$i]) * $position;
            ++$position;
        }

        return ($sum % 10) === $checkDigit;
    }
}
