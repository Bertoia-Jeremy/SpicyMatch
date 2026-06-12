<?php

declare(strict_types=1);

namespace App\Exception\Match;

/**
 * Levée par RecomputeOavTableHandler lorsqu'une opération DBAL échoue.
 *
 * Encapsule l'exception Doctrine DBAL originale en exception domaine.
 * La cause est préservée dans la chaîne ($previous) pour les outils de monitoring.
 *
 * @see App\MessageHandler\RecomputeOavTableHandler
 */
final class OavRebuildFailedException extends \RuntimeException
{
    public static function fromDbalException(\Throwable $cause): self
    {
        return new self('OAV table rebuild failed: ' . $cause->getMessage(), (int) $cause->getCode(), $cause);
    }
}
