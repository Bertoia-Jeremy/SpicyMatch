<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cinétique aromatique d'un composé, dérivée de son point d'ébullition.
 *
 * HEAD  : bp < 150 °C — note de tête, s'évapore vite (limonène, myrcène).
 * HEART : 150 ≤ bp ≤ 250 °C — note de cœur (linalol, géraniol, cinnamaldéhyde).
 * BASE  : bp > 250 °C — note de fond, résiste à la cuisson (eugénol, capsaïcine).
 */
enum AromaKinetics: string
{
    case HEAD = 'head';
    case HEART = 'heart';
    case BASE = 'base';

    /**
     * Dérive la cinétique depuis un point d'ébullition (°C).
     *
     * Retourne null si la donnée n'est pas disponible — l'appelant doit décider
     * (fallback HEART, masquer, ignorer) plutôt qu'inférer à tort.
     */
    public static function fromBoilingPoint(?int $celsius): ?self
    {
        if ($celsius === null) {
            return null;
        }

        return match (true) {
            $celsius < 150 => self::HEAD,
            $celsius > 250 => self::BASE,
            default => self::HEART,
        };
    }

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function label(): string
    {
        return 'enum.kinetics.' . $this->value;
    }
}
