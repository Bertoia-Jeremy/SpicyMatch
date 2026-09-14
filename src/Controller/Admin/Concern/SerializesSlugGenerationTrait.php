<?php

declare(strict_types=1);

namespace App\Controller\Admin\Concern;

use Doctrine\ORM\EntityManagerInterface;

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
