<?php

declare(strict_types=1);

namespace App\ValueObject\Match;

use App\Enum\OdtMatrix;

/**
 * Contexte culinaire injecté dans le moteur de compatibilité OAV.
 *
 * Phase 1-2 (livré) : matrix uniquement — wrapper sur OdtMatrix.
 * Phase 3 (présent) : fatRatio + waterRatio + cookingTimeMin + temperatureCelsius
 *   permettent au futur OavPartitionCalculator d'appliquer Nernst + décroissance
 *   temporelle sans changer la signature publique (defaults rétrocompatibles).
 *
 * Invariants :
 *  - immutable (readonly)
 *  - fatRatio + waterRatio ≈ 1 (tolérance 0.001)
 *  - ratios ∈ [0, 1]
 *  - cookingTimeMin ≥ 0
 */
final readonly class CulinaryContext
{
    private const float RATIO_SUM_TOLERANCE = 0.001;

    public function __construct(
        public OdtMatrix $matrix = OdtMatrix::AIR,
        public float $fatRatio = 0.0,
        public float $waterRatio = 1.0,
        public int $cookingTimeMin = 0,
        public int $temperatureCelsius = 20,
    ) {
        if ($fatRatio < 0.0 || $fatRatio > 1.0) {
            throw new \InvalidArgumentException(\sprintf('fatRatio doit être ∈ [0, 1], reçu %f', $fatRatio));
        }

        if ($waterRatio < 0.0 || $waterRatio > 1.0) {
            throw new \InvalidArgumentException(\sprintf('waterRatio doit être ∈ [0, 1], reçu %f', $waterRatio));
        }

        if (abs($fatRatio + $waterRatio - 1.0) > self::RATIO_SUM_TOLERANCE) {
            throw new \InvalidArgumentException(\sprintf(
                'fatRatio + waterRatio doit être ≈ 1 (tolérance %f), reçu %f',
                self::RATIO_SUM_TOLERANCE,
                $fatRatio + $waterRatio,
            ));
        }

        if ($cookingTimeMin < 0) {
            throw new \InvalidArgumentException(\sprintf('cookingTimeMin doit être ≥ 0, reçu %d', $cookingTimeMin));
        }
    }

    /**
     * Contexte par défaut : matrice air (profil olfactif sec), aucune cuisson.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Construit un contexte depuis une valeur de requête HTTP (matrix seul).
     */
    public static function fromRequest(string $raw): self
    {
        return new self(OdtMatrix::from(strtolower(trim($raw))));
    }
}
