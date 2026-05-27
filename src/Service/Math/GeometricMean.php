<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Moyenne géométrique — agrégat correct pour des grandeurs log-normalement
 * distribuées (Levier 3).
 *
 * Les seuils olfactifs (ODT) varient d'un facteur 10-100 entre études et suivent
 * une distribution log-normale. La moyenne arithmétique d'une plage [a, b] est
 * biaisée vers le haut ; la moyenne géométrique √(a·b) (centre en espace log) est
 * la valeur représentative correcte.
 *
 *   geomean(a, b) = exp((ln a + ln b) / 2) = √(a·b)
 *   geomean(x_1, …, x_n) = (Π x_i)^(1/n) = exp((Σ ln x_i) / n)
 */
final class GeometricMean
{
    /**
     * @param list<float> $values valeurs strictement positives
     */
    public static function of(array $values): float
    {
        if ($values === []) {
            throw new \InvalidArgumentException('Moyenne géométrique : liste vide.');
        }

        $sumLog = 0.0;
        foreach ($values as $v) {
            if ($v <= 0.0) {
                throw new \InvalidArgumentException(\sprintf(
                    'Moyenne géométrique : valeur ≤ 0 interdite (reçu %g).',
                    $v
                ), );
            }
            $sumLog += log($v);
        }

        // Passage par les logs : évite l'overflow du produit Π sur de grandes plages.
        return exp($sumLog / count($values));
    }

    /**
     * Cas usuel d'une plage min/max (2 bornes).
     */
    public static function ofRange(float $min, float $max): float
    {
        return self::of([$min, $max]);
    }
}
