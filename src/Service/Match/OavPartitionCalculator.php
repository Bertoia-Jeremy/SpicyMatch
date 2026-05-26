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
 *  1. Partition de Nernst (équilibre thermodynamique biphasique eau/huile) :
 *     À l'équilibre : C_oil / C_water = K_ow = 10^logP.
 *     Bilan matière C_total = φ_oil × C_oil + φ_water × C_water →
 *       C_water = C_total / (K_ow × φ_oil + φ_water)
 *       C_oil   = C_total × K_ow / (K_ow × φ_oil + φ_water)
 *
 *  2. Décroissance temporelle (volatilisation à chaud, ordre 1) :
 *     k(T) = 0 sous T_inert (50 °C), max au point d'ébullition, linéaire entre.
 *     C(t) = C_0 × exp(-k(T) × t).
 *
 * Modes d'usage :
 *  - effectiveOav()      : calcul absolu à partir d'une concentration brute (cf. 3B)
 *  - correctionFactor()  : facteur multiplicatif sans dimension appliqué à un OAV
 *                          précalculé (shadow table). Utilisé par MatchPipeline.
 */
final readonly class OavPartitionCalculator
{
    /**
     * Constante de perte au point d'ébullition (fraction/min). 0.1 = 10 %/min.
     */
    private const float K_AT_BOILING = 0.1;

    /**
     * Température sous laquelle la volatilisation est négligée.
     */
    private const int T_INERT_CELSIUS = 50;

    /**
     * Vrai si le contexte introduit une physique non triviale (Nernst ou décroissance).
     *
     * Contexte neutre = pas de phase grasse ET pas de cuisson → factor = 1 partout
     * → la shadow table fournit déjà la bonne réponse. Skip pour économiser les requêtes.
     */
    public function needsCorrection(CulinaryContext $ctx): bool
    {
        return $ctx->fatRatio > 0.0 || $ctx->cookingTimeMin > 0;
    }

    /**
     * Facteur multiplicatif appliqué à l'OAV brut pour obtenir l'OAV effectif.
     *
     * factor = partition(matrix, K_ow, φ_oil, φ_water) × decay(T, bp, t)
     *
     * Retourne 1.0 si pas de données physiques (mode dégradé : aucune correction).
     */
    public function correctionFactor(?CompoundPhysical $physical, CulinaryContext $ctx): float
    {
        if ($physical === null) {
            return 1.0;
        }

        return $this->partitionFactor($physical, $ctx) * $this->decayFactor($physical, $ctx);
    }

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

        $factor = $this->correctionFactor($physical, $ctx);

        return $concentrationPpm * $factor / $odtPpm;
    }

    /**
     * Fraction de la concentration totale présente dans la phase ciblée (eau, huile, gaz).
     */
    private function partitionFactor(CompoundPhysical $physical, CulinaryContext $ctx): float
    {
        $kOw = $physical->octanolWaterPartition();
        if ($kOw === null) {
            return 1.0;
        }

        $denom = $kOw * $ctx->fatRatio + $ctx->waterRatio;
        if ($denom <= 0.0) {
            return 1.0;
        }

        return match ($ctx->matrix) {
            OdtMatrix::OIL => $kOw / $denom,
            OdtMatrix::WATER => 1.0 / $denom,
            OdtMatrix::AIR => 1.0,
        };
    }

    /**
     * Fraction conservée après cuisson : exp(-k(T) × t).
     */
    private function decayFactor(CompoundPhysical $physical, CulinaryContext $ctx): float
    {
        if ($ctx->cookingTimeMin <= 0) {
            return 1.0;
        }

        $bp = $physical->getBoilingPointCelsius();
        if ($bp === null) {
            return 1.0;
        }

        if ($ctx->temperatureCelsius <= self::T_INERT_CELSIUS) {
            return 1.0;
        }

        $span = max(1, $bp - self::T_INERT_CELSIUS);
        $progress = min(1.0, ($ctx->temperatureCelsius - self::T_INERT_CELSIUS) / $span);
        $k = self::K_AT_BOILING * $progress;

        return exp(-$k * $ctx->cookingTimeMin);
    }
}
