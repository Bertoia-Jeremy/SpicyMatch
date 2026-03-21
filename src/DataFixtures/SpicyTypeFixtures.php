<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SpicyTypeFixtures extends Fixture
{
    /**
     * @var array<string, array{name: string, description: string}>
     */
    private const TYPES = [
        'graine' => [
            'name' => 'Graine',
            'description' => 'Épices issues de graines séchées (poivre, cumin, coriandre, cardamome…).',
        ],
        'poudre' => [
            'name' => 'Poudre',
            'description' => 'Épices réduites en poudre fine, souvent issues de baies ou de rhizomes séchés.',
        ],
        'rhizome' => [
            'name' => 'Rhizome / Racine',
            'description' => 'Épices issues de rhizomes ou racines souterrains (gingembre, curcuma, galanga…).',
        ],
        'ecorce' => [
            'name' => 'Écorce / Bâton',
            'description' => 'Épices obtenues par séchage d\'écorce aromatique (cannelle, cassia…).',
        ],
        'feuille' => [
            'name' => 'Feuille / Herbe',
            'description' => 'Épices et herbes aromatiques issues de feuilles séchées (laurier, thym, origan…).',
        ],
        'fleur' => [
            'name' => 'Fleur / Stigmate',
            'description' => 'Épices récoltées sur des fleurs ou leurs parties (safran, clou de girofle, câpre…).',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::TYPES as $key => $data) {
            $entity = new SpicyType();
            $entity->setName($data['name'])
                ->setDescription($data['description'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->addReference('spicyType_' . $key, $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }
}
