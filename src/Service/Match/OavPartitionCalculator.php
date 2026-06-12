<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Entity\CompoundPhysical;
use App\ValueObject\Match\CulinaryContext;

/**
 * OAV efficace sous contexte culinaire : partition Nernst (eau/huile) × décroissance thermique.
 *
 *  Nernst : C_water = C_total / (K_ow·φ_oil + φ_water), C_oil = K_ow·C_water.
 *  Decay  : k(T) nul sous T_inert, max au bp, linéaire entre. C(t) = C0·exp(-k(T)·t).
 *
 * effectiveOav() = calcul absolu (concentration brute). correctionFactor() = facteur ×
 * appliqué à un OAV précalculé (MatchPipeline + shadow table).
 */
final readonly class OavPartitionCalculator
{
    /**
     * Calibration empirique : monoterpènes (limonène, linalol) bouillis 30 min ≈ 5 % rétention
     * → exp(-0.1 × 30) ≈ 0.05. Domaine : cuisson conventionnelle, ≤ 1 atm.
     */
    private const float K_AT_BOILING = 0.1;

    /**
     * Sous 50 °C, évaporation négligée pour les arômes culinaires (Henry, vapor pressure).
     */
    private const int T_INERT_CELSIUS = 50;

    public function needsCorrection(CulinaryContext $ctx): bool
    {
        return $ctx->fatRatio > 0.0 || $ctx->cookingTimeMin > 0;
    }

    public function correctionFactor(?CompoundPhysical $physical, CulinaryContext $ctx): float
    {
        if ($physical === null) {
            return 1.0;
        }

        return $this->partitionFactor($physical, $ctx) * $this->decayFactor($physical, $ctx);
    }

    /**
     * Null si ODT manquant (≤ 0). 0.0 si concentration nulle.
     */
    public function effectiveOav(
        ?CompoundPhysical $physical,
        float $concentrationPpm,
        float $odtPpm,
        CulinaryContext $ctx,
    ): ?float {
        if ($odtPpm <= 0.0) {
            return null;
        }

        if ($concentrationPpm <= 0.0) {
            return 0.0;
        }

        if ($physical === null) {
            return $concentrationPpm / $odtPpm;
        }

        return $concentrationPpm * $this->correctionFactor($physical, $ctx) / $odtPpm;
    }

    private function partitionFactor(CompoundPhysical $physical, CulinaryContext $ctx): float
    {
        $kOw = $physical->octanolWaterPartition();
        if ($kOw === null) {
            return 1.0;
        }

        return $ctx->matrix->strategy()
            ->partitionFactor($kOw, $ctx->fatRatio, $ctx->waterRatio);
    }

    private function decayFactor(CompoundPhysical $physical, CulinaryContext $ctx): float
    {
        if ($ctx->cookingTimeMin <= 0) {
            return 1.0;
        }

        $bp = $physical->getBoilingPointCelsius();
        if ($bp === null || $ctx->temperatureCelsius <= self::T_INERT_CELSIUS) {
            return 1.0;
        }

        $span = max(1, $bp - self::T_INERT_CELSIUS);
        $progress = min(1.0, ($ctx->temperatureCelsius - self::T_INERT_CELSIUS) / $span);
        $k = self::K_AT_BOILING * $progress;

        return exp(-$k * $ctx->cookingTimeMin);
    }
}
