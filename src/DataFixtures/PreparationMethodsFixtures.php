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
        'method_torrefie_moulu' => [
            'name' => 'Torréfié puis moulu',
            'description' => 'L\'épice entière est d\'abord grillée à sec, puis moulue une fois refroidie. Les arômes de noisette grillée se mêlent à la diffusion rapide et homogène d\'une poudre fraîche.',
            'tools' => 'Poêle à fond épais, assiette froide, mortier ou moulin à épices.',
            'informations' => 'L\'enchaînement des deux gestes donne le parfum le plus intense : la chaleur réveille l\'épice, la mouture l\'ouvre au moment de servir.',
            'advice' => 'Laisser refroidir complètement avant de moudre, sinon la poudre devient pâteuse. Moudre juste avant usage, en petite quantité.',
        ],
        'method_matiere_grasse' => [
            'name' => 'Activé dans la matière grasse',
            'description' => 'L\'épice est chauffée doucement dans un corps gras (huile, beurre, crème) qui capte et diffuse ses arômes dans tout le plat.',
            'tools' => 'Casserole ou poêle, cuillère en bois.',
            'informations' => 'Beaucoup de parfums d\'épices se dissolvent dans le gras et pas dans l\'eau : c\'est le gras qui les emmène dans le plat.',
            'advice' => 'Feu doux à moyen et quelques dizaines de secondes suffisent. Dès que ça embaume, mouiller ou retirer du feu pour éviter l\'amertume.',
        ],
        'method_pate' => [
            'name' => 'Broyé en pâte',
            'description' => 'L\'épice est pilée avec un liquide ou des aromates frais (ail, gingembre, oignon) pour former une pâte à ajouter au plat.',
            'tools' => 'Mortier et pilon, ou mixeur.',
            'informations' => 'La pâte enrobe les arômes et les protège de l\'évaporation. C\'est la base des pâtes de curry.',
            'advice' => 'Ajouter juste assez de liquide pour lier. Faire revenir la pâte quelques instants pour en adoucir le côté cru.',
        ],
        'method_rehydrate' => [
            'name' => 'Réhydraté',
            'description' => 'L\'épice séchée est assouplie dans un liquide chaud avant d\'être utilisée : sa chair redevient tendre et son goût s\'arrondit.',
            'tools' => 'Bol, eau ou bouillon chaud, couteau.',
            'informations' => 'Indispensable pour les piments secs entiers (ancho, chipotle) et certains champignons utilisés comme condiments.',
            'advice' => 'Tremper 15 à 20 minutes dans un liquide chaud mais pas bouillant. Le liquide de trempage peut servir dans la sauce.',
        ],
        'method_grille' => [
            'name' => 'Grillé ou rôti',
            'description' => 'L\'épice fraîche (piment, ail, oignon, échalote) est exposée à une chaleur vive — gril, flamme ou four — jusqu\'à ce que sa peau cloque ou noircisse et que sa chair devienne tendre et fumée.',
            'tools' => 'Gril du four, plaque, pinces. Sac ou récipient couvert pour laisser étuver.',
            'informations' => 'La chaleur vive fait caraméliser les sucres et adoucit le piquant ou l\'âcreté. Ne concerne que les épices fraîches : les graines se torréfient à sec.',
            'advice' => 'Retourner régulièrement pour une coloration uniforme. Laisser étuver sous un couvercle facilite le retrait de la peau ; ne pas rincer à l\'eau, on perdrait le parfum fumé.',
        ],
        'method_sirop' => [
            'name' => 'Mis en sirop',
            'description' => 'L\'épice est mijotée ou infusée dans un sirop de sucre qui capte son parfum, ou qui la confit. Le sirop obtenu parfume boissons, desserts et fruits.',
            'tools' => 'Casserole, passoire fine, bocal ou bouteille propre.',
            'informations' => 'Le sucre retient les arômes délicats des herbes et des fleurs, que la cuisson seule ferait disparaître. Le sirop se garde ensuite au frais.',
            'advice' => 'Chauffer doucement sans laisser caraméliser. Retirer du feu avant d\'ajouter les herbes fraîches pour ne pas les cuire, puis filtrer.',
        ],
        'method_maceration' => [
            'name' => 'Macération',
            'description' => 'L\'épice trempe longuement à froid ou à tiède dans un liquide (vinaigre, alcool, saumure) qui s\'en imprègne. Elle sert aussi à conserver ou à adoucir.',
            'tools' => 'Bocal ou bouteille propre à fermeture hermétique, passoire fine.',
            'informations' => 'Le vinaigre et l\'alcool captent des parfums que l\'eau n\'emporte pas. Plus le repos est long, plus le goût passe dans le liquide.',
            'advice' => 'Garder à l\'abri de la lumière et bien immerger l\'épice. Goûter régulièrement et filtrer quand l\'intensité convient.',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        $repository = $manager->getRepository(PreparationMethods::class);

        foreach (self::METHODS as $ref => $data) {
            $existing = $repository->findOneBy([
                'name' => $data['name'],
            ]);
            if ($existing instanceof PreparationMethods) {
                $this->addReference($ref, $existing);

                continue;
            }

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
