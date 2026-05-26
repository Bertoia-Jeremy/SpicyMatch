<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Entity\CompoundPhysical;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\CulinaryContext;

/**
 * Calcule l'OAV efficace d'un composé dans un contexte culinaire donné.
 *
 * Deux phénomènes physiques modélisés :
 *
 *  1. Partition de Nernst (équilibre thermodynamique) :
 *     Mélange biphasique eau/huile de fractions volumiques (φ_water, φ_oil).
 *     À l'équilibre : C_oil / C_water = K_ow = 10^logP.
 *     Bilan matière C_total = φ_oil × C_oil + φ_water × C_water →
 *       C_water = C_total / (K_ow × φ_oil + φ_water)
 *       C_oil   = C_total × K_ow / (K_ow × φ_oil + φ_water)
 *
 *  2. Décroissance temporelle (volatilisation à chaud) :
 *     Cinétique d'ordre 1 simplifiée. La vitesse k(T) est nulle sous T_inert (50 °C),
 *     maximale au point d'ébullition, linéaire entre les deux.
 *     C(t) = C_0 × exp(-k(T) × t).
 *
 * Mode dégradé : si physique absente ou logP manquant → fallback OAV brut.
 */
final readonly class OavPartitionCalculator
{
    /**
     * Constante de perte au point d'ébullition (fraction/min). 0.1 = 10 %/min.
     * Calibration empirique : ≈ 95 % de perte en 30 min à pleine ébullition.
     */
    private const float K_AT_BOILING = 0.1;

    /**
     * Température sous laquelle la volatilisation est négligée.
     */
    private const int T_INERT_CELSIUS = 50;

    /**
     * @return float|null OAV efficace. Null si ODT manquant ou ≤ 0 (donnée non disponible).
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

        $cPhase = $this->phaseConcentration($physical, $concentrationPpm, $ctx);
        $cFinal = $this->applyCookingDecay($physical, $cPhase, $ctx);

        return $cFinal / $odtPpm;
    }

    /**
     * Concentration dans la phase ciblée (eau, huile, ou gaz) après Nernst.
     */
    private function phaseConcentration(CompoundPhysical $physical, float $cTotal, CulinaryContext $ctx): float
    {
        $kOw = $physical->octanolWaterPartition();
        if ($kOw === null) {
            return $cTotal;
        }

        $denom = $kOw * $ctx->fatRatio + $ctx->waterRatio;
        if ($denom <= 0.0) {
            return $cTotal;
        }

        return match ($ctx->matrix) {
            OdtMatrix::OIL => $cTotal * $kOw / $denom,
            OdtMatrix::WATER => $cTotal / $denom,
            // Matrice air : pas de phase solvant explicite — concentration totale (Henry simplifié).
            OdtMatrix::AIR => $cTotal,
        };
    }

    /**
     * Décroissance exp(-k(T) × t) entre T_inert et bp ; saturée au-delà.
     */
    private function applyCookingDecay(CompoundPhysical $physical, float $cBase, CulinaryContext $ctx): float
    {
        if ($ctx->cookingTimeMin <= 0) {
            return $cBase;
        }

        $bp = $physical->getBoilingPointCelsius();
        if ($bp === null) {
            return $cBase;
        }

        if ($ctx->temperatureCelsius <= self::T_INERT_CELSIUS) {
            return $cBase;
        }

        $span = max(1, $bp - self::T_INERT_CELSIUS);
        $progress = min(1.0, ($ctx->temperatureCelsius - self::T_INERT_CELSIUS) / $span);
        $k = self::K_AT_BOILING * $progress;

        return $cBase * exp(-$k * $ctx->cookingTimeMin);
    }
}
