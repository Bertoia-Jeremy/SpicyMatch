<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SpicyTypeFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var array<string, array{name: string, description: string, icon: string}>
     */
    private const array TYPES = [
        'graine' => [
            'name' => 'Graine',
            'description' => 'Épices issues de graines séchées (poivre, cumin, coriandre, cardamome…).',
            'icon' => 'fa-solid fa-seedling',
        ],
        'poudre' => [
            'name' => 'Poudre',
            'description' => 'Épices réduites en poudre fine, souvent issues de baies ou de rhizomes séchés.',
            'icon' => 'fa-solid fa-jar',
        ],
        'rhizome' => [
            'name' => 'Rhizome / Racine',
            'description' => 'Épices issues de rhizomes ou racines souterrains (gingembre, curcuma, galanga…).',
            'icon' => 'fa-solid fa-carrot',
        ],
        'ecorce' => [
            'name' => 'Écorce / Bâton',
            'description' => 'Épices obtenues par séchage d\'écorce aromatique (cannelle, cassia…).',
            'icon' => 'fa-solid fa-scroll',
        ],
        'feuille' => [
            'name' => 'Feuille / Herbe',
            'description' => 'Épices et herbes aromatiques issues de feuilles séchées (laurier, thym, origan…).',
            'icon' => 'fa-solid fa-leaf',
        ],
        'fleur' => [
            'name' => 'Fleur / Stigmate',
            'description' => 'Épices récoltées sur des fleurs ou leurs parties (safran, clou de girofle, câpre…).',
            'icon' => 'fa-solid fa-spa',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::TYPES as $key => $data) {
            $entity = new SpicyType();
            $entity->setName($data['name'])
                ->setDescription($data['description'])
                ->setIcon($data['icon'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->addReference('spicyType_' . $key, $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['spice_content'];
    }
}
