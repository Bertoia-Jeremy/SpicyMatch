<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 30 real spices with their main and secondary aromatic compounds.
 *
 * Designed to create clear, testable compatibility groups:
 *
 *   → Famille Cannelle/Girofle (eugenol + cinnamaldéhyde)
 *       Cannelle + Clou de Girofle + Laurier + Piment de la Jamaïque + Macis
 *
 *   → Famille Anis/Fenouil (anéthol ± estragole)
 *       Fenouil + Anis Étoilé + Anis Vert + Estragon + Basilic
 *
 *   → Famille Coriandre/Cardamome (linalol + terpinèn-4-ol + géraniol)
 *       Coriandre + Cardamome + Muscade + Basilic + Poivre de Sichuan
 *
 *   → Famille Piment/Poivre (capsaïcine + pipérine)
 *       Piment de Cayenne + Piment d'Espelette + Paprika + Poivre Noir + Poivre Blanc + Poivre Long
 *
 *   → Famille Thym/Origan/Cumin (thymol + carvacrol)
 *       Thym + Origan + Cumin + Romarin + Marjolaine + Sauge + Carvi
 *
 *   → Famille Curcuma/Gingembre (curcumine)
 *       Curcuma + Gingembre
 *
 *   → Safran (safranal unique — peu ou pas de compatibilité cross-group)
 */
class SpicesFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /**
     * @var array<string, array{
     *   name: string,
     *   description: string,
     *   cooking: string,
     *   informations: string,
     *   benefits: string,
     *   group: string,
     *   type: string,
     *   main: string[],
     *   secondary: string[],
     * }>
     */
    private const SPICES = [
        // ── Famille Phénylpropanoïdes ───────────────────────────────────────────

        'cannelle' => [
            'name' => 'Cannelle de Ceylan',
            'description' => 'Écorce intérieure séchée du cannelier de Ceylan. Arôme doux, chaud et légèrement sucré — la plus délicate des cannelles.',
            'cooking' => 'Utilisée entière ou moulue dans les desserts, les currys doux, le vin chaud, les tagines et les porridges.',
            'informations' => 'À distinguer de la Cassia (cannelle de Chine), plus forte en cinnamaldéhyde et potentiellement plus riche en coumarine.',
            'benefits' => 'Propriétés antioxydantes, aide à réguler la glycémie. Antiseptique naturel.',
            'group' => 'group_phenylpropanoides',
            'type' => 'spicyType_ecorce',
            'main' => ['compound_eugenol', 'compound_cinnamaldehyde', 'compound_linalool'],
            'secondary' => [],
        ],
        'clou_girofle' => [
            'name' => 'Clou de Girofle',
            'description' => 'Bouton floral séché du giroflier. Arôme très puissant, chaud, épicé et légèrement anesthésiant en bouche.',
            'cooking' => 'Utilisé entier dans les bouillons et les vin chauds, moulu dans les mélanges d\'épices (poudre de cinq-épices, ras el hanout).',
            'informations' => 'Contient la plus haute concentration d\'eugenol de toutes les épices (70-90% de l\'huile essentielle). Utilisation modérée.',
            'benefits' => 'Anesthésique naturel, antiseptique, antifongique. Traditionnellement utilisé pour soulager les maux de dents.',
            'group' => 'group_phenylpropanoides',
            'type' => 'spicyType_fleur',
            'main' => ['compound_eugenol', 'compound_cinnamaldehyde'],
            'secondary' => ['compound_linalool'],
        ],
        'laurier' => [
            'name' => 'Laurier Noble',
            'description' => 'Feuille séchée du laurier sauce. Arôme complexe, boisé et légèrement eucalypté, plus discret que les autres épices de sa famille.',
            'cooking' => 'Incontournable dans les bouquets garnis, les marinades, les sauces et les courts-bouillons. Se retire avant service.',
            'informations' => 'À ne pas confondre avec le laurier-cerise (toxique). Les feuilles sèches sont plus concentrées que les fraîches.',
            'benefits' => 'Favorise la digestion, propriétés anti-inflammatoires légères.',
            'group' => 'group_phenylpropanoides',
            'type' => 'spicyType_feuille',
            'main' => ['compound_eugenol', 'compound_linalool'],
            'secondary' => ['compound_cinnamaldehyde', 'compound_terpinene4ol'],
        ],
        'fenouil' => [
            'name' => 'Fenouil (graines)',
            'description' => 'Graines séchées du fenouil commun. Arôme anisé doux, frais et légèrement sucré.',
            'cooking' => 'Poisson, saucisses italiennes, pains aromatiques, tisanes digestives. Se torréfie à sec pour révéler ses arômes.',
            'informations' => 'Les graines contiennent deux fois plus d\'anéthol que la plante fraîche. Souvent confondu avec l\'anis vert.',
            'benefits' => 'Carminatif puissant, favorise la lactation, aide à la digestion.',
            'group' => 'group_aldehydes_speciaux',
            'type' => 'spicyType_graine',
            'main' => ['compound_anethole'],
            'secondary' => ['compound_estragole', 'compound_linalool'],
        ],
        'anis_etoile' => [
            'name' => 'Anis Étoilé (Badiane)',
            'description' => 'Fruit séché en étoile du badianier de Chine. Arôme anisé intense, plus chaud et plus épicé que le fenouil.',
            'cooking' => 'Poudre de cinq-épices, bouillons asiatiques, canard laqué, vin chaud. À doser avec précaution.',
            'informations' => 'Malgré son nom, sans lien botanique avec l\'anis vert. Source principale d\'acide shikimique pour la synthèse du Tamiflu.',
            'benefits' => 'Antiviral, digestif, expectorant naturel.',
            'group' => 'group_aldehydes_speciaux',
            'type' => 'spicyType_fleur',
            'main' => ['compound_anethole'],
            'secondary' => ['compound_estragole'],
        ],
        'estragon' => [
            'name' => 'Estragon Français',
            'description' => 'Herbe aromatique fine aux notes anisées douces et herbacées, plus complexe que le fenouil.',
            'cooking' => 'Sauces béarnaise, poulet à l\'estragon, vinaigrettes, marinades légères. S\'ajoute en fin de cuisson.',
            'informations' => 'L\'estragon français (A. dracunculus var. sativa) est préféré au russe, moins aromatique. Se conserve mieux séché.',
            'benefits' => 'Stimulant de l\'appétit, propriétés antioxydantes.',
            'group' => 'group_aldehydes_speciaux',
            'type' => 'spicyType_feuille',
            'main' => ['compound_estragole', 'compound_anethole'],
            'secondary' => [],
        ],

        // ── Famille Terpènes Oxygénés ───────────────────────────────────────────

        'basilic' => [
            'name' => 'Basilic Grand Vert',
            'description' => 'Herbe aromatique estivale aux notes florales, anisées douces et légèrement poivrées.',
            'cooking' => 'Pesto, salade caprese, pizzas, sauces tomates. À utiliser frais, jamais cuit — la chaleur détruit ses arômes.',
            'informations' => 'Plus de 60 variétés de basilic aux profils aromatiques distincts (citron, cannelle, thaï…). Le grand vert est le plus commun.',
            'benefits' => 'Antibactérien, antioxydant, adaptogène léger.',
            'group' => 'group_terpenes_oxyg',
            'type' => 'spicyType_feuille',
            'main' => ['compound_linalool', 'compound_estragole'],
            'secondary' => ['compound_eugenol'],
        ],
        'coriandre' => [
            'name' => 'Coriandre (graines)',
            'description' => 'Graines de coriandre séchées. Arôme floral, légèrement citronné et chaud — très différent des feuilles fraîches.',
            'cooking' => 'Base des currys indiens, du garam masala, des marinades pour viandes grillées. Se torréfie avant usage.',
            'informations' => 'Les feuilles fraîches et les graines ont des profils aromatiques totalement différents. Les graines sont adoucies par la torréfaction.',
            'benefits' => 'Digestif, antimicrobien, aide à contrôler la glycémie.',
            'group' => 'group_terpenes_oxyg',
            'type' => 'spicyType_graine',
            'main' => ['compound_linalool', 'compound_geraniol'],
            'secondary' => ['compound_terpinene4ol', 'compound_limonene'],
        ],
        'cardamome' => [
            'name' => 'Cardamome Verte',
            'description' => 'Gousses séchées de la cardamome verte. Arôme complexe, frais, floral et légèrement eucalypté — reine des épices.',
            'cooking' => 'Masala chai, café arabe, desserts moyen-orientaux, currys doux. Utiliser les graines moulues au dernier moment.',
            'informations' => 'Troisième épice la plus chère après le safran et la vanille. Originaire d\'Inde et du Guatemala.',
            'benefits' => 'Digestif, antioxydant puissant, aide à la santé bucco-dentaire.',
            'group' => 'group_terpenes_oxyg',
            'type' => 'spicyType_graine',
            'main' => ['compound_terpinene4ol', 'compound_linalool'],
            'secondary' => ['compound_geraniol', 'compound_limonene'],
        ],
        'muscade' => [
            'name' => 'Muscade',
            'description' => 'Graine séchée et râpée de Myristica fragrans. Arôme chaud, légèrement camphrée, boisée et sucrée.',
            'cooking' => 'Béchamel, purée de pommes de terre, gratins, desserts d\'automne. À utiliser fraîchement râpée, en quantité modérée.',
            'informations' => 'La muscade entière peut être râpée à la demande pour des arômes optimaux. Deux épices pour un seul fruit : muscade et macis.',
            'benefits' => 'Digestif, léger somnifère à faibles doses. Toxique à haute dose — ne jamais dépasser 5g.',
            'group' => 'group_terpenes_oxyg',
            'type' => 'spicyType_graine',
            'main' => ['compound_terpinene4ol'],
            'secondary' => ['compound_eugenol', 'compound_linalool'],
        ],

        // ── Famille Capsaïcinoïdes & Alcaloïdes ────────────────────────────────

        'poivre_noir' => [
            'name' => 'Poivre Noir',
            'description' => 'Baies de poivre récoltées avant maturité et séchées. Le poivre le plus aromatique — piquant vif et notes boisées.',
            'cooking' => 'Universel — moulins à poivre, marinades, sauces, viandes, fromages. Incontournable de la cuisine mondiale.',
            'informations' => 'Le même poivrier donne poivre noir, blanc, vert et rouge selon la maturité et le traitement.',
            'benefits' => 'Améliore l\'absorption des nutriments (notamment la curcumine +2000%), stimule la digestion.',
            'group' => 'group_capsaicinoïdes',
            'type' => 'spicyType_graine',
            'main' => ['compound_piperine'],
            'secondary' => ['compound_limonene', 'compound_thymol'],
        ],
        'poivre_long' => [
            'name' => 'Poivre Long',
            'description' => 'Épice ancienne aux chatons allongés, piquant doux et notes chaudes, terreuses et légèrement sucrées.',
            'cooking' => 'Cuisine médiévale revisitée, fromages affinés, desserts chocolat-épices, gibier. Redécouvert par la haute cuisine.',
            'informations' => 'Très utilisé en Europe jusqu\'au XVIe siècle avant d\'être supplanté par le poivre noir. Revient en force en gastronomie.',
            'benefits' => 'Digestif, anti-inflammatoire, traditionnellement utilisé en médecine ayurvédique.',
            'group' => 'group_capsaicinoïdes',
            'type' => 'spicyType_graine',
            'main' => ['compound_piperine'],
            'secondary' => ['compound_limonene'],
        ],
        'piment_cayenne' => [
            'name' => 'Piment de Cayenne',
            'description' => 'Piment rouge séché et moulu à puissance élevée. Chaleur immédiate et persistante.',
            'cooking' => 'Sauces piquantes, marinades, huile pimentée, currys relevés. Dose modérée — très puissant.',
            'informations' => 'Autour de 30 000 à 50 000 SHU. Originaire d\'Amérique du Sud, largement cultivé en Louisiane et en Inde.',
            'benefits' => 'Accélère le métabolisme, analgésique topique, favorise la circulation sanguine.',
            'group' => 'group_capsaicinoïdes',
            'type' => 'spicyType_poudre',
            'main' => ['compound_capsaicine'],
            'secondary' => ['compound_piperine'],
        ],
        'paprika_doux' => [
            'name' => 'Paprika Doux',
            'description' => 'Poudre de poivrons rouges séchés et doux. Couleur vive, arôme légèrement sucré et fumé, piquant modéré.',
            'cooking' => 'Poulet au paprika, goulash, huile de paprika, marinades. Colore magnifiquement les plats sans brûler.',
            'informations' => 'Le paprika de Hongrie et d\'Espagne (pimentón) sont les références mondiales. Nombreuses variantes de doux à fumé.',
            'benefits' => 'Riche en antioxydants (vitamine C, caroténoïdes), anti-inflammatoire.',
            'group' => 'group_capsaicinoïdes',
            'type' => 'spicyType_poudre',
            'main' => ['compound_capsaicine'],
            'secondary' => ['compound_piperine'],
        ],

        // ── Famille Monoterpènes Phénoliques ───────────────────────────────────

        'thym' => [
            'name' => 'Thym Commun',
            'description' => 'Herbe méditerranéenne aromatique aux notes herbacées franches, chaudes et légèrement médicinales.',
            'cooking' => 'Bouquets garnis, marinades, volailles rôties, légumes provençaux. Résiste bien à la cuisson prolongée.',
            'informations' => 'Nombreuses variétés (thym citron, serpolet). Le thym commun (Thymus vulgaris) est le plus utilisé en cuisine.',
            'benefits' => 'Antiseptique puissant, expectorant, antifongique. Traditionnel contre la toux et les infections respiratoires.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_feuille',
            'main' => ['compound_thymol', 'compound_carvacrol'],
            'secondary' => [],
        ],
        'origan' => [
            'name' => 'Origan Méditerranéen',
            'description' => 'Herbe séchée aux notes herbacées intenses et légèrement poivrées, plus chaudes que le thym.',
            'cooking' => 'Pizza, sauce tomate, grillades, marinades grecques (tzatziki). Le séchage intensifie son arôme par rapport au frais.',
            'informations' => 'L\'origan méditerranéen (Origanum vulgare ssp. hirtum) est beaucoup plus aromatique que l\'origan d\'Europe du Nord.',
            'benefits' => 'Antioxydant majeur (ORAC très élevé), antibactérien, antifongique.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_feuille',
            'main' => ['compound_carvacrol', 'compound_thymol'],
            'secondary' => [],
        ],
        'cumin' => [
            'name' => 'Cumin',
            'description' => 'Graines de cumin aux notes terreuses, chaudes et légèrement herbacées. Pilier de la cuisine orientale et indienne.',
            'cooking' => 'Garam masala, falafels, tajines, chili con carne, humus. Torréfier à sec avant usage pour révéler ses arômes.',
            'informations' => 'À ne pas confondre avec le cumin des prés (carvi). L\'un des condiments les plus utilisés dans le monde.',
            'benefits' => 'Digestif puissant, riche en fer, aide à la perte de poids, anti-inflammatoire.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_graine',
            'main' => ['compound_thymol', 'compound_carvacrol'],
            'secondary' => ['compound_limonene'],
        ],

        // ── Famille Curcuminoïdes & Arylalcanones ──────────────────────────────

        'curcuma' => [
            'name' => 'Curcuma',
            'description' => 'Rhizome séché et moulu aux notes terreuses, légèrement amères et boisées. Épice dorée de la cuisine indienne.',
            'cooking' => 'Currys, riz épicé, lait doré (golden milk), smoothies anti-inflammatoires. Combine idéalement avec le poivre noir.',
            'informations' => 'La curcumine représente seulement 2-5% du curcuma séché. Sa biodisponibilité augmente de 2000% avec la pipérine.',
            'benefits' => 'Anti-inflammatoire majeur, antioxydant, neuroprotecteur potentiel. Utilisé depuis 4000 ans en médecine ayurvédique.',
            'group' => 'group_curcuminoides',
            'type' => 'spicyType_rhizome',
            'main' => ['compound_curcumine'],
            'secondary' => ['compound_terpinene4ol'],
        ],
        'gingembre' => [
            'name' => 'Gingembre Séché',
            'description' => 'Rhizome de gingembre séché et réduit en poudre. Chaleur plus douce que le frais, notes sucrées et épicées.',
            'cooking' => 'Pain d\'épices, currys, smoothies, marinades asiatiques, ginger ale. Se substitue au frais en ajustant les doses.',
            'informations' => 'Le séchage transforme les gingérols piquants en zingérone plus douce. Le frais et le séché ont des profils aromatiques distincts.',
            'benefits' => 'Antiémétique puissant, anti-inflammatoire, stimule la digestion et la circulation sanguine.',
            'group' => 'group_curcuminoides',
            'type' => 'spicyType_rhizome',
            'main' => ['compound_zingerone'],
            'secondary' => ['compound_curcumine', 'compound_limonene'],
        ],

        // ── Nouvelles épices Phénylpropanoïdes ─────────────────────────────────

        'piment_jamaique' => [
            'name' => 'Piment de la Jamaïque',
            'description' => 'Baies séchées du Pimenta dioica — arôme unique qui évoque à la fois le clou de girofle, la cannelle et le poivre d\'où son nom "Quatre-épices".',
            'cooking' => 'Cuisine antillaise et jamaïcaine (jerk chicken), marinades, charcuteries, pains d\'épices, pickles. Entier ou moulu.',
            'informations' => 'Appelé "allspice" en anglais car son arôme rappelle un mélange d\'épices. Originaire des Caraïbes, seule épice majeure exclusive à l\'Amérique.',
            'benefits' => 'Antimicrobien, anesthésique léger, aide à la digestion.',
            'group' => 'group_phenylpropanoides',
            'type' => 'spicyType_graine',
            'main' => ['compound_eugenol', 'compound_cinnamaldehyde'],
            'secondary' => ['compound_piperine'],
        ],
        'macis' => [
            'name' => 'Macis',
            'description' => 'Arille rouge séchée entourant la noix de muscade. Arôme plus délicat et floral que la muscade, légèrement résineux.',
            'cooking' => 'Béchamel fine, potages crémeux, pâtisseries, puddings anglais, saucisses à chair fine. Préféré à la muscade en cuisine sucrée raffinée.',
            'informations' => 'Le macis et la muscade proviennent du même fruit (Myristica fragrans). Le macis séché vire de rouge à orange-jaune.',
            'benefits' => 'Digestif, antiémétique, antibactérien. Propriétés identiques à la muscade mais en concentrations moindres.',
            'group' => 'group_phenylpropanoides',
            'type' => 'spicyType_fleur',
            'main' => ['compound_eugenol', 'compound_linalool'],
            'secondary' => ['compound_terpinene4ol', 'compound_cinnamaldehyde'],
        ],

        // ── Nouvelles épices Anis/Fenouil ───────────────────────────────────────

        'anis_vert' => [
            'name' => 'Anis Vert',
            'description' => 'Graines d\'anis commun aux notes anisées douces et fraîches, moins intenses que la badiane.',
            'cooking' => 'Pains anisés, biscuits, liqueurs (pastis, anisette, ouzo), infusions digestives. S\'associe au citron et aux pommes.',
            'informations' => 'L\'anis vert (Pimpinella anisum) est distinct de l\'anis étoilé (badiane). Cultivé depuis l\'Antiquité en Méditerranée.',
            'benefits' => 'Carminatif, expectorant, stimulant digestif, galactogogue.',
            'group' => 'group_aldehydes_speciaux',
            'type' => 'spicyType_graine',
            'main' => ['compound_anethole'],
            'secondary' => ['compound_estragole', 'compound_linalool'],
        ],

        // ── Nouvelles épices Terpènes Oxygénés ─────────────────────────────────

        'poivre_sichuan' => [
            'name' => 'Poivre de Sichuan',
            'description' => 'Baies de Zanthoxylum aux notes citronnées, florales et à l\'effet anesthésiant/fourmillant unique (parestésie). Pas un vrai poivre.',
            'cooking' => 'Cuisine sichuanaise (mapo tofu, canard laqué), marinades asiatiques, sel et poivre de Sichuan. À utiliser torréfié.',
            'informations' => 'L\'effet "ma" (engourdissement) vient du sanshool, qui stimule les mécanorécepteurs tactiles. Interdit à l\'import US jusqu\'en 2005.',
            'benefits' => 'Stimulant digestif, analgésique topique, antibactérien.',
            'group' => 'group_terpenes_oxyg',
            'type' => 'spicyType_graine',
            'main' => ['compound_limonene', 'compound_geraniol'],
            'secondary' => ['compound_linalool'],
        ],

        // ── Nouvelles épices Capsaïcinoïdes ────────────────────────────────────

        'piment_espelette' => [
            'name' => 'Piment d\'Espelette',
            'description' => 'Piment AOP du Pays Basque, séché et moulu. Chaleur douce et progressive, notes fruitées et légèrement fumées.',
            'cooking' => 'Cuisine basque et landaise (piperade, axoa, jambon de Bayonne), sauces, chocolat noir épicé. Remplace le poivre en cuisine raffinée.',
            'informations' => 'Classé 4 sur l\'échelle de Scoville (1 500 à 2 500 SHU). AOC depuis 2000, cultivé dans 10 communes du Pays Basque français.',
            'benefits' => 'Antioxydant, stimule le métabolisme, riche en vitamine C.',
            'group' => 'group_capsaicinoïdes',
            'type' => 'spicyType_poudre',
            'main' => ['compound_capsaicine'],
            'secondary' => ['compound_piperine', 'compound_linalool'],
        ],
        'poivre_blanc' => [
            'name' => 'Poivre Blanc',
            'description' => 'Grains de poivre mûrs dont on retire la peau avant séchage. Piquant net et pur, moins aromatique que le noir, notes légèrement fermentées.',
            'cooking' => 'Sauces blanches et béchamels (pour ne pas marquer visuellement), poissons, volailles à la crème. Indispensable en cuisine classique française.',
            'informations' => 'Provient du même poivrier (Piper nigrum) que le noir. Le dépulpage élimine les composés aromatiques de l\'écorce — plus de pipérine pure.',
            'benefits' => 'Digestif, améliore l\'absorption des nutriments, antimicrobien.',
            'group' => 'group_capsaicinoïdes',
            'type' => 'spicyType_graine',
            'main' => ['compound_piperine'],
            'secondary' => ['compound_limonene'],
        ],

        // ── Nouvelles épices Monoterpènes Phénoliques ──────────────────────────

        'romarin' => [
            'name' => 'Romarin',
            'description' => 'Herbe méditerranéenne aux aiguilles persistantes. Arôme puissant, résineux, légèrement camphrée et boisé.',
            'cooking' => 'Agneau rôti, pommes de terre, focaccia, marinades pour gibier. S\'utilise en branche ou haché fin — puissant, doser avec précaution.',
            'informations' => 'Résiste à la cuisson prolongée sans perdre ses arômes. Symbole de fidélité et de mémoire dans la tradition européenne.',
            'benefits' => 'Stimulant cognitif, antioxydant puissant, améliore la circulation sanguine, tonique capillaire.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_feuille',
            'main' => ['compound_thymol', 'compound_carvacrol'],
            'secondary' => ['compound_linalool'],
        ],
        'marjolaine' => [
            'name' => 'Marjolaine',
            'description' => 'Herbe proche de l\'origan mais plus douce, florale et légèrement musquée. Notes délicates, herbacées et chaudes.',
            'cooking' => 'Fines herbes, farces, sauces tomates légères, vinaigrettes, viandes blanches. À utiliser séchée ou fraîche en fin de cuisson.',
            'informations' => 'Souvent confondue avec l\'origan, elle est plus délicate. Originaire de la région méditerranéenne, très utilisée en Allemagne et en Autriche.',
            'benefits' => 'Digestive, antispasmodique, expectorante. Traditionnellement utilisée contre les troubles du sommeil.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_feuille',
            'main' => ['compound_carvacrol', 'compound_thymol'],
            'secondary' => ['compound_terpinene4ol', 'compound_linalool'],
        ],
        'sauge' => [
            'name' => 'Sauge Officinale',
            'description' => 'Herbe méditerranéenne aux feuilles argentées et veloutées. Arôme puissant, légèrement camphrée, herbacé et chaud.',
            'cooking' => 'Saltimbocca, beurre de sauge, farces pour volailles et porc, gnocchi. Incontournable en cuisine italienne et allemande. Doser avec précaution.',
            'informations' => '"Salvia" vient du latin "salvare" (sauver). Considérée pendant des siècles comme plante universelle de santé. Plus de 900 espèces connues.',
            'benefits' => 'Antimicrobien majeur, aide à la ménopause (bouffées de chaleur), antioxydant, améliore la mémoire.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_feuille',
            'main' => ['compound_thymol', 'compound_carvacrol'],
            'secondary' => ['compound_linalool', 'compound_geraniol'],
        ],
        'carvi' => [
            'name' => 'Carvi (Cumin des Prés)',
            'description' => 'Graines de carvi aux notes anisées-poivrées très caractéristiques et légèrement amères. Souvent confondu avec le cumin.',
            'cooking' => 'Pain de seigle, choucroute, fromages (Tilsit, Munster), choux braisés, harissa. Très utilisé en Europe centrale et du Nord.',
            'informations' => 'Malgré son nom, botaniquement proche de l\'aneth et du fenouil, pas du cumin. L\'une des plus vieilles épices d\'Europe (traces archéologiques -3000 ans).',
            'benefits' => 'Carminatif puissant, digestif, expectorant, antimicrobien.',
            'group' => 'group_monoterpenes_phenoliques',
            'type' => 'spicyType_graine',
            'main' => ['compound_carvacrol', 'compound_limonene'],
            'secondary' => ['compound_thymol'],
        ],

        // ── Safran (unique) ─────────────────────────────────────────────────────

        'safran' => [
            'name' => 'Safran',
            'description' => 'Stigmates séchés du crocus sativus. L\'épice la plus chère du monde — arôme unique, floral, mielleuse et légèrement métallique.',
            'cooking' => 'Bouillabaisse, paella, risotto milanais, desserts persans. Infuser dans un liquide chaud 20 minutes avant usage.',
            'informations' => 'Il faut 150 000 fleurs pour produire 1 kg de safran. La majorité de la production mondiale vient d\'Iran.',
            'benefits' => 'Antidépresseur léger prouvé cliniquement, antioxydant puissant, protecteur de la vue.',
            'group' => 'group_aldehydes_speciaux',
            'type' => 'spicyType_fleur',
            'main' => ['compound_safranal'],
            'secondary' => [],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::SPICES as $key => $data) {
            $entity = new Spices();
            $entity->setImageSize(0);
            $entity->setName($data['name'])
                ->setSlug(str_replace('_', '-', $key))
                ->setDescription($data['description'])
                ->setCooking($data['cooking'])
                ->setInformations($data['informations'])
                ->setBenefits($data['benefits'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now)
                ->setAromaticGroups($this->getReference($data['group'], AromaticGroups::class))
                ->setSpicyType($this->getReference($data['type'], SpicyType::class));

            foreach ($data['main'] as $compoundRef) {
                $entity->addAromaticsCompounds(
                    $this->getReference($compoundRef, \App\Entity\AromaticCompound::class)
                );
            }

            foreach ($data['secondary'] as $compoundRef) {
                $entity->addSecondaryAromaticsCompound(
                    $this->getReference($compoundRef, \App\Entity\AromaticCompound::class)
                );
            }

            $this->addReference('spice_' . $key, $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AromaticGroupsFixtures::class, SpicyTypeFixtures::class, AromaticCompoundFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['spice_content'];
    }
}
