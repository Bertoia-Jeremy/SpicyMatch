<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Real aromatic compound families — matched to the design system palette.
 *
 * Groups reflect the biochemical family of a spice's dominant compounds,
 * which shapes its aromatic profile and culinary compatibility.
 */
class AromaticGroupsFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var array<string, array{name: string, color: string, description: string, cooking: string}>
     */
    private const GROUPS = [
        'phenylpropanoides' => [
            'name' => 'Phénylpropanoïdes',
            'color' => '#b45309',  // saffron-700
            'description' => 'Grande famille de molécules dérivées de la phénylalanine, caractérisée par un arôme chaud, épicé et légèrement sucré.',
            'cooking' => 'Ces épices s\'associent naturellement pour créer des mélanges profonds et chaleureux (ras el hanout, poudre de cinq-épices).',
        ],
        'terpenes_oxyg' => [
            'name' => 'Terpènes Oxygénés',
            'color' => '#d97706',  // saffron-600
            'description' => 'Alcools et esters terpéniques dégageant des notes florales, fraîches et légèrement herbacées.',
            'cooking' => 'Idéals dans les mélanges délicats, les marinades et les desserts aux arômes fleuris.',
        ],
        'capsaicinoïdes' => [
            'name' => 'Capsaïcinoïdes & Alcaloïdes',
            'color' => '#7f1d1d',  // paprika-900
            'description' => 'Molécules responsables de la sensation de chaleur et de piquant, typiques des piments et du poivre.',
            'cooking' => 'Utilisés pour apporter chaleur et profondeur, ils se combinent bien entre eux pour moduler l\'intensité piquante.',
        ],
        'monoterpenes_phenoliques' => [
            'name' => 'Monoterpènes Phénoliques',
            'color' => '#15803d',  // green-700
            'description' => 'Phénols mono-terpéniques aromatiques aux notes herbacées, camphrées et légèrement antiseptiques.',
            'cooking' => 'Épices méditerranéennes par excellence — thym, origan, cumin — parfaites pour les plats mijotés et les grillades.',
        ],
        'curcuminoides' => [
            'name' => 'Curcuminoïdes & Arylalcanones',
            'color' => '#a16207',  // turmeric-700
            'description' => 'Pigments polyphénoliques et cétones aromatiques aux notes terreuses, dorées et légèrement amères.',
            'cooking' => 'Le curcuma et le gingembre partagent cette famille et se complètent dans les currys, les soupes et les bouillons.',
        ],
        'aldehydes_speciaux' => [
            'name' => 'Aldéhydes & Lactones Spéciaux',
            'color' => '#0891b2',  // cyan-600
            'description' => 'Aldéhydes terpéniques et lactones qui confèrent des arômes uniques, souvent délicats et très caractéristiques.',
            'cooking' => 'Le safran, l\'anis et le fenouil appartiennent à cette catégorie — ils structurent les plats autour d\'un arôme central fort.',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::GROUPS as $key => $data) {
            $entity = new AromaticGroups();
            $entity->setName($data['name'])
                ->setColor($data['color'])
                ->setDescription($data['description'])
                ->setCooking($data['cooking'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->addReference('group_'.$key, $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['spice_content'];
    }
}
