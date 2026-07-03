<?php

declare(strict_types=1);

namespace App\ValueObject\Match;

use App\Enum\OdtMatrix;

/**
 * Contexte culinaire (matrice ODT + ratios biphasiques + cuisson).
 * Invariants : ratios ∈ [0, 1], fat+water ≈ 1 (±0.001), time ∈ [0, 1440], temp ∈ [-50, 500].
 */
final readonly class CulinaryContext
{
    /**
     * Bornes publiques — source unique des validations (API, UI, VO).
     */
    public const float FAT_RATIO_MIN = 0.0;

    public const float FAT_RATIO_MAX = 1.0;

    public const int COOKING_TIME_MIN = 0;

    public const int COOKING_TIME_MAX = 1440; // 24 h

    public const int TEMPERATURE_MIN = -50;

    public const int TEMPERATURE_MAX = 500;

    private const float RATIO_SUM_TOLERANCE = 0.001;

    public function __construct(
        public OdtMatrix $matrix = OdtMatrix::AIR,
        public float $fatRatio = 0.0,
        public float $waterRatio = 1.0,
        public int $cookingTimeMin = 0,
        public int $temperatureCelsius = 20,
    ) {
        if ($fatRatio < self::FAT_RATIO_MIN || $fatRatio > self::FAT_RATIO_MAX) {
            throw new \InvalidArgumentException(\sprintf('fatRatio doit être ∈ [0, 1], reçu %f', $fatRatio));
        }

        if ($waterRatio < self::FAT_RATIO_MIN || $waterRatio > self::FAT_RATIO_MAX) {
            throw new \InvalidArgumentException(\sprintf('waterRatio doit être ∈ [0, 1], reçu %f', $waterRatio));
        }

        if (abs($fatRatio + $waterRatio - 1.0) > self::RATIO_SUM_TOLERANCE) {
            throw new \InvalidArgumentException(\sprintf(
                'fatRatio + waterRatio doit être ≈ 1 (tolérance %f), reçu %f',
                self::RATIO_SUM_TOLERANCE,
                $fatRatio + $waterRatio,
            ));
        }

        if ($cookingTimeMin < self::COOKING_TIME_MIN || $cookingTimeMin > self::COOKING_TIME_MAX) {
            throw new \InvalidArgumentException(\sprintf(
                'cookingTimeMin doit être ∈ [0, %d], reçu %d',
                self::COOKING_TIME_MAX,
                $cookingTimeMin
            ), );
        }

        if ($temperatureCelsius < self::TEMPERATURE_MIN || $temperatureCelsius > self::TEMPERATURE_MAX) {
            throw new \InvalidArgumentException(\sprintf(
                'temperatureCelsius doit être ∈ [%d, %d], reçu %d',
                self::TEMPERATURE_MIN,
                self::TEMPERATURE_MAX,
                $temperatureCelsius,
            ), );
        }
    }

    /**
     * Signature déterministe pour cache (hors variations volontaires de matrice côté appelant).
     */
    public function signature(): string
    {
        return \sprintf(
            '%s|%.3f|%d|%d',
            $this->matrix->value,
            $this->fatRatio,
            $this->cookingTimeMin,
            $this->temperatureCelsius,
        );
    }

    public function signatureHash(): string
    {
        return substr(hash('xxh3', $this->signature()), 0, 16);
    }

    public static function default(): self
    {
        return new self();
    }

    public static function fromRequest(string $raw): self
    {
        return new self(OdtMatrix::from(strtolower(trim($raw))));
    }

    /**
     * Vrai si le contexte introduit une physique non triviale (= au-delà du neutre).
     */
    public function isCustom(): bool
    {
        return OdtMatrix::AIR !== $this->matrix
            || 0.0 !== $this->fatRatio
            || 0 !== $this->cookingTimeMin
            || 20 !== $this->temperatureCelsius;
    }

    public function getLabel(): string
    {
        if ($this->cookingTimeMin > 0 && $this->fatRatio > 0.0) {
            return $this->fatRatio >= 0.75 ? 'Sauté' : 'Émulsion chaude';
        }

        if ($this->cookingTimeMin > 0) {
            return match ($this->matrix) {
                OdtMatrix::OIL => 'Confit',
                OdtMatrix::WATER => 'Bouillon',
                OdtMatrix::AIR => 'Cuisson sèche',
            };
        }

        return match ($this->matrix) {
            OdtMatrix::WATER => 'Eau',
            OdtMatrix::OIL => 'Huile',
            OdtMatrix::AIR => 'À sec',
        };
    }

    public function getIcon(): string
    {
        if ($this->cookingTimeMin > 0 && $this->fatRatio > 0.0) {
            return 'fa-fire-flame-curved';
        }

        if ($this->cookingTimeMin > 0) {
            return match ($this->matrix) {
                OdtMatrix::WATER => 'fa-glass-water',
                OdtMatrix::OIL => 'fa-droplet',
                OdtMatrix::AIR => 'fa-sun',
            };
        }

        return match ($this->matrix) {
            OdtMatrix::WATER => 'fa-glass-water',
            OdtMatrix::OIL => 'fa-droplet',
            OdtMatrix::AIR => 'fa-wind',
        };
    }
}
