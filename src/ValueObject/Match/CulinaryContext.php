<?php

declare(strict_types=1);

namespace App\ValueObject\Match;

use App\Enum\OdtMatrix;

/**
 * Contexte culinaire injecté dans le moteur de compatibilité OAV.
 *
 * MVP (Phase 1-2) : wrapper mince sur OdtMatrix.
 * Extensible en Phase 3 (fatRatio, cookingTimeMin, temperatureCelsius)
 * sans casser les appels existants grâce aux valeurs par défaut PHP.
 *
 * Invariant : immutable (readonly).
 */
final readonly class CulinaryContext
{
    public function __construct(
        public readonly OdtMatrix $matrix = OdtMatrix::AIR,
    ) {
    }

    /**
     * Contexte par défaut : matrice air (profil olfactif sec).
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Construit un contexte depuis une valeur de requête HTTP.
     */
    public static function fromRequest(string $raw): self
    {
        return new self(OdtMatrix::from(strtolower(trim($raw))));
    }
}
