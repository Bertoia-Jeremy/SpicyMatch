<?php

declare(strict_types=1);

namespace App\Exception\Match;

/**
 * Levée lorsque le mortier ne satisfait pas les invariants du domaine OAV.
 *
 * Catchée dans MatchController pour retourner un 400 Bad Request.
 * Les messages sont pensés pour être exposés directement dans la réponse JSON.
 *
 * @see App\ValueObject\Match\MortarIds
 */
final class InvalidMortarException extends \InvalidArgumentException
{
    public static function invalidCount(): self
    {
        return new self('"spices" doit contenir entre 1 et 10 IDs valides.');
    }
}
