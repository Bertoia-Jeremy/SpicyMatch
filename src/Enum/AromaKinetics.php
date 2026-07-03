<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cinétique aromatique dérivée du point d'ébullition.
 * HEAD bp < 150 °C (limonène). HEART 150–250 (linalol). BASE > 250 (eugénol).
 */
enum AromaKinetics: string
{
    case HEAD = 'head';
    case HEART = 'heart';
    case BASE = 'base';

    /**
     * Null si bp inconnu — pas d'inférence par défaut.
     */
    public static function fromBoilingPoint(?int $celsius): ?self
    {
        if (null === $celsius) {
            return null;
        }

        return match (true) {
            $celsius < 150 => self::HEAD,
            $celsius > 250 => self::BASE,
            default => self::HEART,
        };
    }

    /**
     * Clé de traduction.
     */
    public function label(): string
    {
        return 'enum.kinetics.'.$this->value;
    }
}
