<?php

declare(strict_types=1);

namespace App\Controller\Admin\Concern;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Sérialise la création d'entités sluggables via un verrou applicatif MariaDB
 * (GET_LOCK) pour éliminer la race « check-then-act » du SlugListener : deux
 * créations concurrentes du même nom ne peuvent plus passer le COUNT en parallèle
 * puis violer UNIQUE(slug). Préférable au try/catch + retry (qui casse sur la
 * fermeture de l'EntityManager après une UniqueConstraintViolationException).
 *
 * Le verrou n'enveloppe que persistEntity (création) : le slug est immuable après
 * coup, donc updateEntity ne régénère jamais de slug → aucune race à l'update.
 * À n'utiliser que sur les CRUD d'entités Sluggable.
 */
trait SerializesSlugGenerationTrait
{
    private const SLUG_LOCK = 'spicymatch_slug_gen';

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $connection = $entityManager->getConnection();
        $connection->executeStatement('SELECT GET_LOCK(:key, 10)', [
            'key' => self::SLUG_LOCK,
        ]);

        try {
            parent::persistEntity($entityManager, $entityInstance);
        } finally {
            $connection->executeStatement('SELECT RELEASE_LOCK(:key)', [
                'key' => self::SLUG_LOCK,
            ]);
        }
    }
}
