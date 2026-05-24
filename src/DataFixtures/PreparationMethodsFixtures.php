<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PreparationMethods;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class PreparationMethodsFixtures extends Fixture implements FixtureGroupInterface
{
    private const METHODS = [
        'method_entiere' => [
            'name' => 'Utilisation entière',
            'description' => 'L\'épice est incorporée telle quelle dans la préparation — entière, en branche ou en bâton. Elle parfume pendant la cuisson et se retire avant service.',
            'tools' => 'Aucun outil requis. Pinces pour le retrait en fin de cuisson.',
            'informations' => 'Méthode douce qui libère progressivement les arômes sans les concentrer excessivement. Idéale pour les longues cuissons.',
            'advice' => 'Toujours retirer avant service pour éviter les mauvaises surprises en bouche. Compter 1 unité pour 4 personnes.',
        ],
        'method_mouture' => [
            'name' => 'Mouture',
            'description' => 'L\'épice est finement moulue au mortier ou au moulin juste avant usage. La mouture fraîche libère un maximum d\'huiles essentielles pour une intensité aromatique optimale.',
            'tools' => 'Mortier et pilon, moulin à épices ou moulin à café réservé aux épices.',
            'informations' => 'Les épices moulues perdent 50 % de leurs arômes en 6 mois. Moudre uniquement la quantité nécessaire.',
            'advice' => 'Ne jamais moudre à l\'avance. Pour les mélanges d\'épices complexes, moudre chaque épice séparément puis assembler.',
        ],
        'method_torreface' => [
            'name' => 'Torréfaction à sec',
            'description' => 'Chauffer l\'épice dans une poêle sèche à feu moyen, sans matière grasse, pour activer et développer les composés aromatiques. Transforme les notes crues en arômes grillés plus profonds.',
            'tools' => 'Poêle à fond épais en acier ou en fonte, spatule en bois.',
            'informations' => 'Technique essentielle pour les graines (cumin, coriandre, fenouil, carvi). La chaleur sèche déclenche une légère réaction de Maillard.',
            'advice' => 'Surveiller attentivement — 30 secondes de trop et l\'épice brûle et devient amère. Secouer ou remuer constamment. Refroidir immédiatement sur une assiette froide.',
        ],
        'method_infusion' => [
            'name' => 'Infusion',
            'description' => 'Faire infuser l\'épice dans un liquide chaud (eau, lait, crème, bouillon, huile) pour en extraire les arômes sans chaleur directe intense.',
            'tools' => 'Casserole, passoire fine, fouet, récipient hermétique pour les infusions à froid.',
            'informations' => 'La durée dépend de la puissance aromatique : 5-10 min pour le safran, 20-30 min pour la cannelle, 1h pour une huile aromatique.',
            'advice' => 'Ne jamais faire bouillir — la chaleur excessive (>90°C) dégrade les arômes délicats. Infuser à frémissement (75-85°C). Filtrer soigneusement avant usage.',
        ],
        'method_concassage' => [
            'name' => 'Concassage',
            'description' => 'Briser l\'épice grossièrement au couteau ou au mortier pour libérer les arômes tout en conservant de la texture. Solution intermédiaire entre entière et mouture fine.',
            'tools' => 'Couteau de chef et planche à découper, ou mortier et pilon.',
            'informations' => 'Convient aux épices en grains (poivre, baies de piment de la Jamaïque, poivre de Sichuan). Crée des éclats concentrés de saveur.',
            'advice' => 'Plus le concassage est grossier, plus la diffusion des arômes est lente. Adapter selon la durée de cuisson prévue.',
        ],
        'method_emietee' => [
            'name' => 'Émiettage à la main',
            'description' => 'Frotter ou émietter l\'épice séchée entre les paumes pour libérer les huiles essentielles par friction, juste avant d\'ajouter à la préparation.',
            'tools' => 'Mains propres et sèches. Aucun outil requis.',
            'informations' => 'Idéal pour les herbes séchées (thym, origan, romarin, marjolaine, sauge). La chaleur des mains active les huiles essentielles.',
            'advice' => 'Technique rapide et instinctive. S\'applique uniquement aux épices sèches — les herbes fraîches se hachent au couteau. Émietter au-dessus du plat pour capturer les huiles.',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::METHODS as $ref => $data) {
            $method = new PreparationMethods();
            $method->setName($data['name'])
                ->setDescription($data['description'])
                ->setTools($data['tools'])
                ->setInformations($data['informations'])
                ->setAdvice($data['advice'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->addReference($ref, $method);
            $manager->persist($method);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['spice_content'];
    }
}
