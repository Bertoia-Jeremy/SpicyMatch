<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Niveau de confiance d'une donnée physico-chimique (Levier 2 — provenance).
 *
 * Permet de tracer la qualité de chaque valeur (ODT, concentration, logP…) et,
 * en aval, de calculer la confiance d'un score de compatibilité (= tier le plus
 * faible parmi les données contributrices) et de l'exposer à l'UI sans afficher
 * un "64 %" net bâti sur du placeholder.
 *
 * Hiérarchie (du plus fiable au moins fiable) :
 *   A — MEASURED     : mesure expérimentale, source autoritaire unique, CAS validé
 *   B — LITERATURE   : agrégat littérature (moyenne géométrique de N études)
 *   C — ESTIMATED    : prédit (EPI Suite, QSAR, corrélation structure-propriété)
 *   D — PLACEHOLDER  : valeur fictive de dev/staging (à remplacer)
 */
enum DataConfidence: string
{
    case MEASURED = 'measured';
    case LITERATURE = 'literature';
    case ESTIMATED = 'estimated';
    case PLACEHOLDER = 'placeholder';

    /**
     * Rang numérique : plus élevé = plus fiable. Sert aux comparaisons et au min().
     */
    public function rank(): int
    {
        return match ($this) {
            self::MEASURED => 4,
            self::LITERATURE => 3,
            self::ESTIMATED => 2,
            self::PLACEHOLDER => 1,
        };
    }

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function label(): string
    {
        return 'enum.confidence.' . $this->value;
    }

    /**
     * Lettre de tier (A/B/C/D) pour affichage compact.
     */
    public function tier(): string
    {
        return match ($this) {
            self::MEASURED => 'A',
            self::LITERATURE => 'B',
            self::ESTIMATED => 'C',
            self::PLACEHOLDER => 'D',
        };
    }

    /**
     * Vrai si la donnée est exploitable en production (tier ≥ littérature).
     */
    public function isProductionGrade(): bool
    {
        return $this->rank() >= self::LITERATURE
            ->rank();
    }

    /**
     * Retourne le tier le plus faible d'un ensemble (= confiance globale d'un agrégat).
     * Le maillon le plus faible détermine la confiance de la chaîne.
     */
    public static function weakest(self ...$confidences): self
    {
        if ($confidences === []) {
            return self::PLACEHOLDER;
        }

        $weakest = $confidences[0];
        foreach ($confidences as $c) {
            if ($c->rank() < $weakest->rank()) {
                $weakest = $c;
            }
        }

        return $weakest;
    }
}
