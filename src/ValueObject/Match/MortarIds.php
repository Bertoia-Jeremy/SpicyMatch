<?php

declare(strict_types=1);

namespace App\ValueObject\Match;

use App\Exception\Match\InvalidMortarException;

/**
 * Value Object immuable représentant le mortier (ensemble d'IDs d'épices).
 *
 * Invariants garantis à la construction :
 *   - count ∈ [1, 10]
 *   - tous les IDs résultants sont > 0 (les IDs ≤ 0 sont silencieusement écartés
 *     pour rester cohérent avec le parsing HTTP existant)
 *   - doublons dédupliqués automatiquement
 *
 * Centralise la validation auparavant dispersée entre MatchController (count check),
 * CandidateVetoRepository (guard mortarSize === 0) et MortarProfileBuilder.
 */
final class MortarIds
{
    private const int MIN_COUNT = 1;
    private const int MAX_COUNT = 10;

    /**
     * @var list<int>
     */
    private readonly array $ids;

    /**
     * @param int[] $ids IDs d'épices (peuvent contenir des doublons ou des valeurs ≤ 0)
     */
    public function __construct(array $ids)
    {
        // Les IDs ≤ 0 sont écartés silencieusement (cohérence avec le parsing HTTP).
        // La déduplication est une normalisation, pas un filtrage silencieux.
        $filtered = array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));

        $count = count($filtered);
        if ($count < self::MIN_COUNT || $count > self::MAX_COUNT) {
            throw InvalidMortarException::invalidCount();
        }

        $this->ids = $filtered;
    }

    /**
     * @return list<int> IDs déduplicés dans l'ordre fourni
     */
    public function toArray(): array
    {
        return $this->ids;
    }

    /**
     * @return list<int> IDs triés par ordre croissant — déterministe pour les clés de cache
     */
    public function sorted(): array
    {
        $sorted = $this->ids;
        sort($sorted);

        return $sorted;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    public function contains(int $id): bool
    {
        return in_array($id, $this->ids, true);
    }
}
