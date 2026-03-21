<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PreparationMethods;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 2 preparation tips per spice (60 total).
 * Run: php bin/console doctrine:fixtures:load --append --group=PreparationTipsFixtures.
 */
class PreparationTipsFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var array<string, list<array{method: string, title: string, advantages: string, text: string}>>
     */
    private const TIPS = [
        'spice_cannelle' => [
            [
                'method' => 'method_entiere',
                'title' => 'Bâton entier',
                'advantages' => 'Parfume en douceur, retrait facile avant service',
                'text' => 'Plongez le bâton entier dans votre liquide chaud (compote, vin chaud, curry). Il libère ses arômes progressivement et se retire sans trace.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Poudre fraîchement moulue',
                'advantages' => 'Arôme maximal pour desserts et mélanges d\'épices',
                'text' => 'Réduisez le bâton en poudre au mortier juste avant usage. La fraîcheur est incomparable face aux poudres du commerce.',
            ],
        ],
        'spice_clou_girofle' => [
            [
                'method' => 'method_entiere',
                'title' => 'Entier dans le bouillon',
                'advantages' => 'Infuse lentement, contrôle de l\'intensité par le nombre',
                'text' => 'Incorporez les clous entiers dans vos bouillons, vin chaud ou marinades. Comptez 2-3 clous pour 4 personnes — ils sont très puissants.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Moulu dans les mélanges',
                'advantages' => 'Se répartit uniformément dans les épices composées',
                'text' => 'Moudre finement pour les mélanges (ras el hanout, cinq-épices). Utiliser avec modération — l\'eugenol est intense.',
            ],
        ],
        'spice_laurier' => [
            [
                'method' => 'method_entiere',
                'title' => 'Feuille entière en bouquet',
                'advantages' => 'Parfume les longues cuissons, retrait aisé',
                'text' => 'Incorporez 1-2 feuilles entières dans vos bouillons, ragoûts et sauces. La feuille sèche est plus concentrée que la fraîche.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'Émiettée pour marinades',
                'advantages' => 'Surface de contact maximale pour courtes marinades',
                'text' => 'Émiettez les feuilles sèches entre les paumes et incorporez aux marinades à froid pour viandes et légumes.',
            ],
        ],
        'spice_fenouil' => [
            [
                'method' => 'method_torreface',
                'title' => 'Graines torréfiées',
                'advantages' => 'Notes grillées plus profondes, moins crues',
                'text' => 'Toastez les graines 1-2 minutes à sec dans une poêle chaude en remuant. Utilisez immédiatement ou moulez après refroidissement.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Moulues après torréfaction',
                'advantages' => 'Intensité aromatique maximale pour saucisses et pains',
                'text' => 'Torréfiez d\'abord les graines puis moulez-les au mortier. La poudre obtenue se mélange parfaitement aux farces et pâtes à pain.',
            ],
        ],
        'spice_anis_etoile' => [
            [
                'method' => 'method_entiere',
                'title' => 'Étoile entière en cuisson',
                'advantages' => 'Parfume les bouillons et compotes sans dominer',
                'text' => 'Plongez l\'étoile entière dans vos bouillons asiatiques ou compotes de poires. Retirez après cuisson — son arôme est puissant.',
            ],
            [
                'method' => 'method_infusion',
                'title' => 'Infusion dans liquide chaud',
                'advantages' => 'Contrôle précis de l\'intensité par durée d\'infusion',
                'text' => 'Faites infuser l\'étoile dans du vin rouge chaud, du lait ou du sirop 15-20 min à feu doux. Filtrez avant usage.',
            ],
        ],
        'spice_estragon' => [
            [
                'method' => 'method_emietee',
                'title' => 'Séché émietté à la main',
                'advantages' => 'Libère les notes anisées, idéal pour vinaigrettes',
                'text' => 'Émiettez l\'estragon séché entre les paumes juste avant de l\'ajouter. Parfait pour les vinaigrettes et marinades légères.',
            ],
            [
                'method' => 'method_entiere',
                'title' => 'Branche fraîche entière',
                'advantages' => 'Arôme frais et délicat pour les sauces en fin de cuisson',
                'text' => 'Placez une branche entière dans votre sauce béarnaise ou poulet en cocotte. Retirez avant service ou hachez finement.',
            ],
        ],
        'spice_basilic' => [
            [
                'method' => 'method_entiere',
                'title' => 'Feuilles entières fraîches',
                'advantages' => 'Conserve les huiles essentielles, présentation soignée',
                'text' => 'Utilisez les feuilles entières crues sur les salades caprese et pizzas chaudes. La chaleur les fane en quelques secondes — parfait.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'Feuilles séchées émiettées',
                'advantages' => 'Pratique hors saison pour sauces et farces',
                'text' => 'Émiettez le basilic séché directement dans vos sauces tomates, farces ou marinades. Moins parfumé que le frais mais plus concentré en certains composés.',
            ],
        ],
        'spice_coriandre' => [
            [
                'method' => 'method_torreface',
                'title' => 'Graines torréfiées à sec',
                'advantages' => 'Développe les notes chaudes et grillées du curry',
                'text' => 'Faites sauter les graines 1-2 min dans une poêle sèche jusqu\'à ce qu\'elles embaument. Refroidissez et utilisez entières ou moulues.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Moulue fraîche',
                'advantages' => 'Base des currys et mélanges d\'épices indiens',
                'text' => 'Moulez les graines torréfiées refroidies au mortier. La poudre fraîche est la base du garam masala et des currys maison.',
            ],
        ],
        'spice_cardamome' => [
            [
                'method' => 'method_entiere',
                'title' => 'Gousse entière en infusion',
                'advantages' => 'Arôme floral délicat, intensité contrôlée',
                'text' => 'Légèrement fendez la gousse et plongez-la dans votre chai, café ou riz. Elle libère ses arômes progressivement pendant la cuisson.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Graines extraites et moulues',
                'advantages' => 'Puissance aromatique maximale pour desserts et mélanges',
                'text' => 'Extrayez les graines noires de la gousse et moulez-les finement au dernier moment. Évitez de moudre la cosse verte — amère et sans arôme.',
            ],
        ],
        'spice_muscade' => [
            [
                'method' => 'method_mouture',
                'title' => 'Râpée fraîche à la microplane',
                'advantages' => 'Arôme chaud incomparable, bien supérieur à la poudre',
                'text' => 'Râpez directement la noix entière sur la préparation ou dans la sauce. Quelques passages de microplane suffisent — elle est concentrée.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'En poudre fine',
                'advantages' => 'Pratique pour les mélanges et préparations en masse',
                'text' => 'La poudre de muscade du commerce convient pour les béchamels en grande quantité. Choisissez une poudre fraîche, au parfum vif et intense.',
            ],
        ],
        'spice_poivre_noir' => [
            [
                'method' => 'method_mouture',
                'title' => 'Fraîchement moulu au moulin',
                'advantages' => 'Arôme et piquant supérieurs au poivre pré-moulu',
                'text' => 'Un bon moulin à poivre réglable est un investissement essentiel. Moulez directement sur le plat chaud ou froid.',
            ],
            [
                'method' => 'method_concassage',
                'title' => 'Concassé grossièrement',
                'advantages' => 'Crée des éclats de saveur intense dans viandes et sauces',
                'text' => 'Concassez au mortier ou sous un verre épais. Idéal pour les steaks au poivre, le saumon gravlax et les marinades.',
            ],
        ],
        'spice_poivre_long' => [
            [
                'method' => 'method_mouture',
                'title' => 'Finement moulu',
                'advantages' => 'Notes chaudes et sucrées dans desserts chocolat-épices',
                'text' => 'Moudre le chaton au mortier — il est plus dur que le poivre noir. La poudre est douce, légèrement sucrée et très parfumée.',
            ],
            [
                'method' => 'method_concassage',
                'title' => 'Concassé pour les viandes',
                'advantages' => 'Éclats aromatiques dans les ragoûts et gibiers',
                'text' => 'Cassez les chatons en morceaux au couteau ou mortier. Incorporez aux marinades de gibier ou braisés pour une profondeur aromatique médiévale.',
            ],
        ],
        'spice_piment_cayenne' => [
            [
                'method' => 'method_entiere',
                'title' => 'Poudre directe',
                'advantages' => 'Dosage précis, diffusion homogène dans la préparation',
                'text' => 'La poudre de cayenne s\'incorpore directement dans les sauces, huiles ou marinades. Commencez par une petite pincée et ajustez.',
            ],
            [
                'method' => 'method_infusion',
                'title' => 'Infusion dans l\'huile',
                'advantages' => 'Crée une huile pimentée contrôlée en intensité',
                'text' => 'Infusez 1/2 cuillère à café dans 100 ml d\'huile neutre tiède 30 min. L\'huile rouge obtenue est idéale pour les finitions.',
            ],
        ],
        'spice_paprika_doux' => [
            [
                'method' => 'method_entiere',
                'title' => 'Poudre directe',
                'advantages' => 'Colore et parfume simultanément, dosage facile',
                'text' => 'Incorporez directement dans les sauces, marinades ou panures. Le paprika doux est polyvalent et souligne sans dominer.',
            ],
            [
                'method' => 'method_infusion',
                'title' => 'Dans le corps gras chaud',
                'advantages' => 'Libère les pigments et arômes dans l\'huile pour colorer',
                'text' => 'Ajoutez le paprika à l\'huile chaude (pas fumante) hors du feu et remuez 30 secondes avant d\'ajouter les autres ingrédients.',
            ],
        ],
        'spice_thym' => [
            [
                'method' => 'method_entiere',
                'title' => 'Branches entières en bouquet',
                'advantages' => 'Parfume les longues cuissons, retrait aisé',
                'text' => 'Liez les branches avec du laurier pour un bouquet garni classique. Plongez dans les bouillons, braisés ou fonds de sauce.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'Séché émietté',
                'advantages' => 'Pratique pour les marinades sèches et croûtes d\'épices',
                'text' => 'Émiettez le thym séché entre les paumes pour libérer les huiles. Idéal pour les marinades sèches et le recouvert d\'herbes pour les viandes.',
            ],
        ],
        'spice_origan' => [
            [
                'method' => 'method_emietee',
                'title' => 'Séché émietté à la main',
                'advantages' => 'Libère les notes chaudes et herbacées en quelques secondes',
                'text' => 'L\'origan séché doit être émietté juste avant usage. Il est plus puissant séché que frais — une cuillère à café suffit pour une sauce.',
            ],
            [
                'method' => 'method_entiere',
                'title' => 'Brindilles entières',
                'advantages' => 'Parfume les marinades et grillades à chaleur directe',
                'text' => 'Déposez des brindilles d\'origan sur les grillades ou dans les marinades. Elles protègent les surfaces et parfument par contact.',
            ],
        ],
        'spice_cumin' => [
            [
                'method' => 'method_torreface',
                'title' => 'Graines torréfiées à sec',
                'advantages' => 'Notes terreuses et grillées, moins d\'amertume crue',
                'text' => 'Toastez les graines 1-2 min dans une poêle sèche. L\'arôme envahit la cuisine — c\'est le signal qu\'elles sont prêtes.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Moulu après torréfaction',
                'advantages' => 'Base des currys, falafels et mélanges d\'épices du Maghreb',
                'text' => 'Moulez au mortier après torréfaction. La poudre fraîche est transformatrice dans les houmous, chili et mélanges de tajine.',
            ],
        ],
        'spice_curcuma' => [
            [
                'method' => 'method_entiere',
                'title' => 'Poudre directe',
                'advantages' => 'Dosage précis, soluble dans les liquides et corps gras',
                'text' => 'Le curcuma en poudre se dissout dans les liquides chauds et les corps gras. Combinez toujours avec du poivre noir pour maximiser l\'absorption.',
            ],
            [
                'method' => 'method_infusion',
                'title' => 'Suspension dans le gras',
                'advantages' => 'Active la curcumine, améliore couleur et biodisponibilité',
                'text' => 'Incorporez dans l\'huile ou le ghee chaud avant les autres ingrédients. La chaleur et le gras dissolvent mieux la curcumine.',
            ],
        ],
        'spice_gingembre' => [
            [
                'method' => 'method_entiere',
                'title' => 'Poudre directe',
                'advantages' => 'Notes sucrées et épicées, légèrement différentes du frais',
                'text' => 'La poudre de gingembre séché a un profil légèrement différent du frais (zingérone vs gingérol). Idéale pour les pains d\'épices et pâtisseries.',
            ],
            [
                'method' => 'method_infusion',
                'title' => 'Infusion dans liquide chaud',
                'advantages' => 'Tisane réchauffante, base de ginger ale maison',
                'text' => 'Infusez 1/2 cuillère à café dans 250 ml d\'eau frémissante 10 min. Filtrez et ajoutez miel et citron. Puissant contre les nausées.',
            ],
        ],
        'spice_piment_jamaique' => [
            [
                'method' => 'method_entiere',
                'title' => 'Baies entières en marinade',
                'advantages' => 'Diffusion progressive des 4 arômes caractéristiques',
                'text' => 'Incorporez les baies entières dans vos marinades pour jerk chicken ou charcuteries. Leur arôme mêle clou de girofle, poivre, cannelle et muscade.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Fraîchement moulu',
                'advantages' => 'Les 4 notes aromatiques concentrées dans la poudre',
                'text' => 'Moulez les baies au mortier pour les pains d\'épices, pickles et mélanges de charcuteries. Arôme complexe et chaud.',
            ],
        ],
        'spice_macis' => [
            [
                'method' => 'method_entiere',
                'title' => 'Fleur entière en infusion',
                'advantages' => 'Arôme plus délicat et floral que la muscade',
                'text' => 'Plongez une fleur de macis dans vos crèmes dessert ou bouillons fins. Son arôme est plus raffiné et moins intense que la muscade.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Poudre pour pâtisserie',
                'advantages' => 'Se mélange parfaitement aux pâtes et crèmes',
                'text' => 'Moulez ou utilisez en poudre pour les puddings anglais, saucisses fine chair et béchamels raffinées. Préféré à la muscade en cuisine sucrée.',
            ],
        ],
        'spice_anis_vert' => [
            [
                'method' => 'method_torreface',
                'title' => 'Graines légèrement torréfiées',
                'advantages' => 'Notes anisées plus douces et moins "médicinales"',
                'text' => 'Une légère torréfaction (30-45 sec) adoucit le caractère herbacé de l\'anis. Utilisez entier pour les pains anisés ou moulez après.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Moulues en poudre fine',
                'advantages' => 'Base des liqueurs et biscuits anisés',
                'text' => 'La poudre d\'anis vert s\'incorpore aux pâtes à biscuits, pain d\'épices et infusions digestives.',
            ],
        ],
        'spice_poivre_sichuan' => [
            [
                'method' => 'method_torreface',
                'title' => 'Torréfié puis écrasé',
                'advantages' => 'Développe les notes citronnées et florales, réduit l\'amertume',
                'text' => 'Torréfiez 1-2 min à sec puis retirez les graines noires (amères) et ne conservez que les cosses rouges. Écrasez au mortier.',
            ],
            [
                'method' => 'method_concassage',
                'title' => 'Grossièrement concassé',
                'advantages' => 'Éclats d\'effet anesthésiant concentré pour les plats sichuanais',
                'text' => 'Concassez grossièrement pour les mélanges sel-poivre de Sichuan et les huiles aromatiques. L\'effet "ma" est plus prononcé en morceaux.',
            ],
        ],
        'spice_piment_espelette' => [
            [
                'method' => 'method_entiere',
                'title' => 'Poudre directe AOP',
                'advantages' => 'Chaleur douce et progressive, notes fruitées préservées',
                'text' => 'Incorporez directement en fin de cuisson ou sur le plat fini. La poudre d\'Espelette est plus délicate que le cayenne — dosez librement.',
            ],
            [
                'method' => 'method_infusion',
                'title' => 'Dans le beurre ou l\'huile',
                'advantages' => 'Parfume les corps gras pour vinaigrettes et finitions',
                'text' => 'Faites infuser 1 cuillère dans 50g de beurre fondu tiède 5 min. Le beurre d\'Espelette est une finition classique de la cuisine basque.',
            ],
        ],
        'spice_poivre_blanc' => [
            [
                'method' => 'method_mouture',
                'title' => 'Fraîchement moulu',
                'advantages' => 'Piquant pur sans trace visuelle dans les sauces blanches',
                'text' => 'Moudre directement dans les sauces blanches, béchamels et crèmes. Indispensable quand la présentation visuelle compte.',
            ],
            [
                'method' => 'method_concassage',
                'title' => 'Concassé',
                'advantages' => 'Éclats de piquant pur dans poissons et viandes à la crème',
                'text' => 'Concassez les grains pour les marinades et cuissons en croûte. Le poivre blanc concassé est plus piquant que moulu.',
            ],
        ],
        'spice_romarin' => [
            [
                'method' => 'method_entiere',
                'title' => 'Branches entières',
                'advantages' => 'Parfume puissamment les rôtis et légumes sans amertume',
                'text' => 'Glissez les branches sous les viandes à rôtir ou dans la focaccia. L\'arôme résineux se développe lentement à la chaleur.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'Haché ou émietté fin',
                'advantages' => 'S\'incorpore aux marinades sèches et farces',
                'text' => 'Hachez finement les aiguilles au couteau ou émiettez le séché. À doser avec modération — le romarin est très puissant moulu.',
            ],
        ],
        'spice_marjolaine' => [
            [
                'method' => 'method_emietee',
                'title' => 'Séchée émiettée',
                'advantages' => 'Notes douces et florales pour fines herbes et vinaigrettes',
                'text' => 'Émiettez entre les paumes juste avant usage. La marjolaine séchée est plus concentrée que fraîche — utilisez moitié moins.',
            ],
            [
                'method' => 'method_entiere',
                'title' => 'Fraîche en bouquet',
                'advantages' => 'Arôme musqué délicat pour sauces légères et viandes blanches',
                'text' => 'Ajoutez quelques brins frais en fin de cuisson ou comme garniture. La marjolaine fraîche perd vite son arôme à la chaleur.',
            ],
        ],
        'spice_sauge' => [
            [
                'method' => 'method_entiere',
                'title' => 'Feuilles entières',
                'advantages' => 'Technique du beurre de sauge — croustillante et concentrée',
                'text' => 'Faites frire les feuilles entières dans du beurre chaud jusqu\'à ce qu\'elles soient croustillantes. Arôme puissant et texture distinctive.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'Émiettée dans farces',
                'advantages' => 'S\'intègre aux farces de volaille et saucisses de porc',
                'text' => 'Émiettez ou hachez finement la sauge séchée pour les farces. Un pilier de la cuisine italienne et anglaise.',
            ],
        ],
        'spice_carvi' => [
            [
                'method' => 'method_torreface',
                'title' => 'Graines torréfiées',
                'advantages' => 'Notes anisées-poivrées plus douces, idéal pour le pain',
                'text' => 'Torréfiez 1 min à sec pour atténuer l\'amertume et développer les arômes chauds. Parfait pour les pains de seigle et choux.',
            ],
            [
                'method' => 'method_mouture',
                'title' => 'Moulu',
                'advantages' => 'S\'intègre aux fromages, harissa et sauces sans texture',
                'text' => 'Moulez les graines pour les incorporer aux fromages (Tilsit, Munster), harissa maison ou vinaigrettes. Arôme persistant et caractéristique.',
            ],
        ],
        'spice_safran' => [
            [
                'method' => 'method_infusion',
                'title' => 'Infusion dans liquide tiède',
                'advantages' => 'Libère la couleur et l\'arôme safranal de façon optimale',
                'text' => 'Émiettez les filaments dans 2-3 cuillères à soupe d\'eau tiède, de lait ou de bouillon. Laissez infuser 20-30 min minimum — la couleur dorée s\'intensifie.',
            ],
            [
                'method' => 'method_emietee',
                'title' => 'Filaments émiettés directs',
                'advantages' => 'Pour les courtes cuissons humides type risotto, paella',
                'text' => 'Émiettez les filaments entre les doigts directement dans le plat liquide en cours de cuisson. Moins efficace qu\'en infusion mais pratique.',
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::TIPS as $spiceRef => $tips) {
            /** @var Spices $spice */
            $spice = $this->getReference($spiceRef, Spices::class);

            foreach ($tips as $tipData) {
                $tip = new PreparationTips();
                $tip->setSpice($spice)
                    ->setTitle($tipData['title'])
                    ->setAdvantages($tipData['advantages'])
                    ->setText($tipData['text'])
                    ->setPreparationMethod($this->getReference($tipData['method'], PreparationMethods::class))
                    ->setCreatedAt($now)
                    ->setUpdatedAt($now);

                $manager->persist($tip);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [SpicesFixtures::class, PreparationMethodsFixtures::class];
    }
}
