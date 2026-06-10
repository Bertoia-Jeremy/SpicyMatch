<?php

declare(strict_types=1);

namespace App\Service\Match\Strategy;

/**
 * Comportements spécifiques à une matrice ODT.
 *
 * Ajouter une matrice = 1 strategy + 1 case OdtMatrix + 1 ligne dans `strategy()`.
 */
interface MatrixStrategy
{
    /**
     * Fraction de la concentration totale dans la phase ciblée après partition Nernst.
     * Air : factor=1 (pas de phase solvant). Water : 1/(K_ow·φ_oil+φ_water). Oil : K_ow/idem.
     *
     * @param float $kOw        10^logP
     * @param float $fatRatio   ∈ [0, 1]
     * @param float $waterRatio ∈ [0, 1]
     */
    public function partitionFactor(float $kOw, float $fatRatio, float $waterRatio): float;

    /**
     * TTL cache du profil OAV mortier dans cette matrice (secondes).
     * Variable selon la maturité des données ODT (air mature 24h, water/oil 1h).
     */
    public function cacheTtlSeconds(): int;
}
