<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Achievement;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AchievementFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $achievements = [
            // --- FIRST_MATCH ---
            [
                'slug'         => 'first_match',
                'name'         => 'Première Alchimie',
                'description'  => 'Créez votre tout premier mélange d\'épices.',
                'icon'         => 'fa-solid fa-flask',
                'trigger'      => AchievementTrigger::FIRST_MATCH,
                'triggerValue' => 1,
                'xpReward'     => 20,
                'rarity'       => AchievementRarity::COMMON,
            ],

            // --- N_MATCHES ---
            [
                'slug'         => 'matches_5',
                'name'         => 'Apprenti Alchimiste',
                'description'  => 'Réalisez 5 mélanges d\'épices.',
                'icon'         => 'fa-solid fa-mortar-pestle',
                'trigger'      => AchievementTrigger::N_MATCHES,
                'triggerValue' => 5,
                'xpReward'     => 50,
                'rarity'       => AchievementRarity::COMMON,
            ],
            [
                'slug'         => 'matches_10',
                'name'         => 'Alchimiste',
                'description'  => 'Réalisez 10 mélanges d\'épices.',
                'icon'         => 'fa-solid fa-fire',
                'trigger'      => AchievementTrigger::N_MATCHES,
                'triggerValue' => 10,
                'xpReward'     => 100,
                'rarity'       => AchievementRarity::RARE,
            ],
            [
                'slug'         => 'matches_25',
                'name'         => 'Maître des Arômes',
                'description'  => 'Réalisez 25 mélanges d\'épices.',
                'icon'         => 'fa-solid fa-crown',
                'trigger'      => AchievementTrigger::N_MATCHES,
                'triggerValue' => 25,
                'xpReward'     => 250,
                'rarity'       => AchievementRarity::EPIC,
            ],
            [
                'slug'         => 'matches_50',
                'name'         => 'Grand Maître Alchimiste',
                'description'  => 'Réalisez 50 mélanges d\'épices.',
                'icon'         => 'fa-solid fa-star',
                'trigger'      => AchievementTrigger::N_MATCHES,
                'triggerValue' => 50,
                'xpReward'     => 500,
                'rarity'       => AchievementRarity::LEGENDARY,
            ],

            // --- N_SPICES_USED ---
            [
                'slug'         => 'spices_3',
                'name'         => 'Curieux',
                'description'  => 'Combinez 3 épices différentes dans un même mélange.',
                'icon'         => 'fa-solid fa-seedling',
                'trigger'      => AchievementTrigger::N_SPICES_USED,
                'triggerValue' => 3,
                'xpReward'     => 30,
                'rarity'       => AchievementRarity::COMMON,
            ],
            [
                'slug'         => 'spices_5',
                'name'         => 'Explorateur des Saveurs',
                'description'  => 'Combinez 5 épices différentes dans un même mélange.',
                'icon'         => 'fa-solid fa-compass',
                'trigger'      => AchievementTrigger::N_SPICES_USED,
                'triggerValue' => 5,
                'xpReward'     => 75,
                'rarity'       => AchievementRarity::RARE,
            ],
            [
                'slug'         => 'spices_8',
                'name'         => 'Symphonie d\'Épices',
                'description'  => 'Combinez 8 épices différentes dans un même mélange.',
                'icon'         => 'fa-solid fa-music',
                'trigger'      => AchievementTrigger::N_SPICES_USED,
                'triggerValue' => 8,
                'xpReward'     => 200,
                'rarity'       => AchievementRarity::EPIC,
            ],
        ];

        foreach ($achievements as $data) {
            $achievement = new Achievement();
            $achievement->setSlug($data['slug'])
                ->setName($data['name'])
                ->setDescription($data['description'])
                ->setIcon($data['icon'])
                ->setTrigger($data['trigger'])
                ->setTriggerValue($data['triggerValue'])
                ->setXpReward($data['xpReward'])
                ->setRarity($data['rarity']);

            $manager->persist($achievement);
            $this->addReference('achievement_' . $data['slug'], $achievement);
        }

        $manager->flush();
    }
}
