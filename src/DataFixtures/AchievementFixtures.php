<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Achievement;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Enum\GameMode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AchievementFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $achievements = [
            // --- FIRST_MATCH ---
            [
                'slug' => 'first_match',
                'name' => 'Première Alchimie',
                'description' => 'Créez votre tout premier mélange d\'épices.',
                'icon' => 'fa-solid fa-flask',
                'trigger' => AchievementTrigger::FIRST_MATCH,
                'triggerValue' => 1,
                'xpReward' => 20,
                'rarity' => AchievementRarity::COMMON,
            ],

            // --- N_MATCHES ---
            [
                'slug' => 'matches_5',
                'name' => 'Apprenti Alchimiste',
                'description' => 'Réalisez 5 mélanges d\'épices.',
                'icon' => 'fa-solid fa-mortar-pestle',
                'trigger' => AchievementTrigger::N_MATCHES,
                'triggerValue' => 5,
                'xpReward' => 50,
                'rarity' => AchievementRarity::COMMON,
            ],
            [
                'slug' => 'matches_10',
                'name' => 'Alchimiste',
                'description' => 'Réalisez 10 mélanges d\'épices.',
                'icon' => 'fa-solid fa-fire',
                'trigger' => AchievementTrigger::N_MATCHES,
                'triggerValue' => 10,
                'xpReward' => 100,
                'rarity' => AchievementRarity::RARE,
            ],
            [
                'slug' => 'matches_25',
                'name' => 'Maître des Arômes',
                'description' => 'Réalisez 25 mélanges d\'épices.',
                'icon' => 'fa-solid fa-crown',
                'trigger' => AchievementTrigger::N_MATCHES,
                'triggerValue' => 25,
                'xpReward' => 250,
                'rarity' => AchievementRarity::EPIC,
            ],
            [
                'slug' => 'matches_50',
                'name' => 'Grand Maître Alchimiste',
                'description' => 'Réalisez 50 mélanges d\'épices.',
                'icon' => 'fa-solid fa-star',
                'trigger' => AchievementTrigger::N_MATCHES,
                'triggerValue' => 50,
                'xpReward' => 500,
                'rarity' => AchievementRarity::LEGENDARY,
            ],

            // --- N_FAVORITES ---
            [
                'slug' => 'favorites_1',
                'name' => 'Premier Coup de Cœur',
                'description' => 'Ajoutez votre premier mélange à vos favoris.',
                'icon' => 'fa-solid fa-star',
                'trigger' => AchievementTrigger::N_FAVORITES,
                'triggerValue' => 1,
                'xpReward' => 15,
                'rarity' => AchievementRarity::COMMON,
            ],
            [
                'slug' => 'favorites_5',
                'name' => 'Collectionneur d\'Arômes',
                'description' => 'Ajoutez 5 mélanges à vos favoris.',
                'icon' => 'fa-solid fa-bookmark',
                'trigger' => AchievementTrigger::N_FAVORITES,
                'triggerValue' => 5,
                'xpReward' => 60,
                'rarity' => AchievementRarity::RARE,
            ],
            [
                'slug' => 'favorites_10',
                'name' => 'Bibliothèque des Saveurs',
                'description' => 'Ajoutez 10 mélanges à vos favoris.',
                'icon' => 'fa-solid fa-book-open',
                'trigger' => AchievementTrigger::N_FAVORITES,
                'triggerValue' => 10,
                'xpReward' => 150,
                'rarity' => AchievementRarity::EPIC,
            ],

            // --- N_SPICES_USED ---
            [
                'slug' => 'spices_3',
                'name' => 'Curieux',
                'description' => 'Combinez 3 épices différentes dans un même mélange.',
                'icon' => 'fa-solid fa-seedling',
                'trigger' => AchievementTrigger::N_SPICES_USED,
                'triggerValue' => 3,
                'xpReward' => 30,
                'rarity' => AchievementRarity::COMMON,
            ],
            [
                'slug' => 'spices_5',
                'name' => 'Explorateur des Saveurs',
                'description' => 'Combinez 5 épices différentes dans un même mélange.',
                'icon' => 'fa-solid fa-compass',
                'trigger' => AchievementTrigger::N_SPICES_USED,
                'triggerValue' => 5,
                'xpReward' => 75,
                'rarity' => AchievementRarity::RARE,
            ],
            [
                'slug' => 'spices_8',
                'name' => 'Symphonie d\'Épices',
                'description' => 'Combinez 8 épices différentes dans un même mélange.',
                'icon' => 'fa-solid fa-music',
                'trigger' => AchievementTrigger::N_SPICES_USED,
                'triggerValue' => 8,
                'xpReward' => 200,
                'rarity' => AchievementRarity::EPIC,
            ],

            // --- EASTER EGGS ---
            [
                'slug' => 'egg_grain_de_sel',
                'name' => 'Grain de Sel',
                'description' => 'Vous avez trouvé l\'icône cachée du footer !',
                'icon' => 'fa-solid fa-salt-shaker',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 50,
                'rarity' => AchievementRarity::RARE,
                'easterEggSlug' => 'grain_de_sel',
            ],
            [
                'slug' => 'egg_perdu_dans_le_souk',
                'name' => 'Perdu dans le Souk',
                'description' => 'Même perdu, vous avez su garder votre calme.',
                'icon' => 'fa-solid fa-map-signs',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 50,
                'rarity' => AchievementRarity::RARE,
                'easterEggSlug' => 'perdu_dans_le_souk',
            ],
            [
                'slug' => 'egg_alchimiste_de_l_ombre',
                'name' => 'Alchimiste de l\'Ombre',
                'description' => 'Maîtrise totale de la lumière et de l\'obscurité.',
                'icon' => 'fa-solid fa-moon',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 50,
                'rarity' => AchievementRarity::RARE,
                'easterEggSlug' => 'alchimiste_de_l_ombre',
            ],
            [
                'slug' => 'egg_temps_de_l_infusion',
                'name' => 'Temps de l\'Infusion',
                'description' => 'La patience est la clé d\'un bon mélange (4:20).',
                'icon' => 'fa-solid fa-hourglass-end',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 100,
                'rarity' => AchievementRarity::EPIC,
                'easterEggSlug' => 'temps_de_l_infusion',
            ],
            [
                'slug' => 'egg_equilibre_des_contraires',
                'name' => 'Équilibre des Contraires',
                'description' => 'Le feu et la glace réunis dans un prisme.',
                'icon' => 'fa-solid fa-yin-yang',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 100,
                'rarity' => AchievementRarity::EPIC,
                'easterEggSlug' => 'equilibre_des_contraires',
            ],
            [
                'slug' => 'egg_secret_du_curry',
                'name' => 'Secret du Curry',
                'description' => 'La sainte trinité : Curcuma, Cumin, Gingembre.',
                'icon' => 'fa-solid fa-scroll',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 150,
                'rarity' => AchievementRarity::EPIC,
                'easterEggSlug' => 'secret_du_curry',
            ],
            [
                'slug' => 'egg_le_poids_de_l_or',
                'name' => 'Le Poids de l\'Or',
                'description' => 'Vous avez trouvé la valeur du Poivre Noir.',
                'icon' => 'fa-solid fa-coins',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 100,
                'rarity' => AchievementRarity::EPIC,
                'easterEggSlug' => 'le_poids_de_l_or',
            ],
            [
                'slug' => 'egg_la_recette_perdue',
                'name' => 'La Recette Perdue',
                'description' => 'Les 4 mots sacrés ont été réunis.',
                'icon' => 'fa-solid fa-key',
                'trigger' => AchievementTrigger::EASTER_EGG_FOUND,
                'triggerValue' => 1,
                'xpReward' => 200,
                'rarity' => AchievementRarity::EPIC,
                'easterEggSlug' => 'la_recette_perdue',
            ],

            // --- SPECIAL ---
            [
                'slug' => 'prisme_des_terpenes',
                'name' => 'Prisme des Terpènes',
                'description' => 'Explorez au moins une épice de chaque grande famille aromatique.',
                'icon' => 'fa-solid fa-gem',
                'trigger' => AchievementTrigger::ALL_TERPENES_VISITED,
                'triggerValue' => 1,
                'xpReward' => 1000,
                'rarity' => AchievementRarity::LEGENDARY,
            ],

            // ── v3 : Gameplay & Exploration (enabled=false → activation progressive) ──

            [
                'slug' => 'apprenti_terpenes',
                'name' => 'Apprenti des Terpènes',
                'description' => 'Consultez au moins 5 épices d\'une même famille aromatique.',
                'icon' => 'fa-solid fa-leaf',
                'trigger' => AchievementTrigger::GROUP_MASTERY_READ,
                'triggerValue' => 5,
                'xpReward' => 80,
                'rarity' => AchievementRarity::RARE,
                'enabled' => false,
                // contextAromaticGroup set via SQL after insert (FK needed)
            ],
            [
                'slug' => 'rat_bibliotheque_gourmand',
                'name' => 'Rat de Bibliothèque Gourmand',
                'description' => 'Consultez 20 fiches d\'épices différentes.',
                'icon' => 'fa-solid fa-book-open-reader',
                'trigger' => AchievementTrigger::SPICE_READ,
                'triggerValue' => 20,
                'xpReward' => 100,
                'rarity' => AchievementRarity::RARE,
                'enabled' => false,
            ],
            [
                'slug' => 'puriste_phenols',
                'name' => 'Puriste des Phénols',
                'description' => 'Obtenez un score d\'au moins 20 en Défi de Scoville sur les épices phénoliques.',
                'icon' => 'fa-solid fa-flask-vial',
                'trigger' => AchievementTrigger::GAME_SCORE_THRESHOLD,
                'triggerValue' => 20,
                'xpReward' => 150,
                'rarity' => AchievementRarity::EPIC,
                'enabled' => false,
                'contextGameMode' => GameMode::SURVIVAL,
                // contextAromaticGroup set via SQL after insert (FK needed)
            ],
            [
                'slug' => 'nez_selectif',
                'name' => 'Nez Sélectif',
                'description' => 'Réalisez 5 parties parfaites en Hors Saison.',
                'icon' => 'fa-solid fa-wine-glass',
                'trigger' => AchievementTrigger::GAME_PERFECT_RUN,
                'triggerValue' => 5,
                'xpReward' => 200,
                'rarity' => AchievementRarity::EPIC,
                'enabled' => false,
                'contextGameMode' => GameMode::INTRUS,
            ],
            [
                'slug' => 'tour_du_monde',
                'name' => 'Le Tour du Monde',
                'description' => 'Utilisez 50 épices différentes dans les jeux de l\'Académie.',
                'icon' => 'fa-solid fa-earth-americas',
                'trigger' => AchievementTrigger::N_UNIQUE_SPICES_USED_IN_GAMES,
                'triggerValue' => 50,
                'xpReward' => 300,
                'rarity' => AchievementRarity::LEGENDARY,
                'enabled' => false,
            ],
            [
                'slug' => 'expert_methodes',
                'name' => 'Expert des Méthodes',
                'description' => 'Consultez au moins une épice pour chaque méthode de préparation existante.',
                'icon' => 'fa-solid fa-utensils',
                'trigger' => AchievementTrigger::ALL_PREPARATION_METHODS_READ,
                'triggerValue' => 0,
                'xpReward' => 200,
                'rarity' => AchievementRarity::EPIC,
                'enabled' => false,
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
                ->setRarity($data['rarity'])
                ->setEasterEggSlug($data['easterEggSlug'] ?? null)
                ->setEnabled($data['enabled'] ?? true);

            if (isset($data['contextGameMode'])) {
                $achievement->setContextGameMode($data['contextGameMode']);
            }

            $manager->persist($achievement);
            $this->addReference('achievement_'.$data['slug'], $achievement);
        }

        $manager->flush();
    }
}
