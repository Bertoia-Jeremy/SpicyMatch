<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Moyenne géométrique — correcte pour grandeurs log-normales (ODT, concentrations).
 * geomean = exp(Σ ln(x_i) / n) — passage par les logs évite l'overflow du produit.
 */
final class GeometricMean
{
    /**
     * @param list<float> $values strictement positifs
     */
    public static function of(array $values): float
    {
        if ([] === $values) {
            throw new \InvalidArgumentException('Moyenne géométrique : liste vide.');
        }

        $sumLog = 0.0;
        foreach ($values as $v) {
            if ($v <= 0.0) {
                throw new \InvalidArgumentException(\sprintf(
                    'Moyenne géométrique : valeur ≤ 0 interdite (reçu %g).',
                    $v,
                ));
            }
            $sumLog += log($v);
        }

        return exp($sumLog / count($values));
    }

    public static function ofRange(float $min, float $max): float
    {
        return self::of([$min, $max]);
    }
}
