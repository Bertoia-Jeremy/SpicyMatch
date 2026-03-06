<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CookingTips;
use App\Entity\Spices;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 3 cooking insertion moments per spice (90 total).
 *
 * Steps:
 *   0 = En infusion préalable (avant cuisson)
 *   1 = En début de cuisson (dans le corps gras)
 *   2 = En milieu de cuisson
 *   3 = En fin de cuisson / hors du feu
 *   4 = À cru, au moment de servir
 *
 * Run: php bin/console doctrine:fixtures:load --append --group=CookingTipsFixtures
 */
class CookingTipsFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var array<string, list<array{step: int, cooking_step: string, title: string, advantages: string, text: string}>> */
    private const TIPS = [
        'spice_cannelle' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Infusion dans le lait ou la crème',  'advantages' => 'Perfectionne les crèmes desserts et béchamels',          'text' => 'Infusez un bâton dans le lait chaud 20 min avant de préparer votre crème anglaise ou béchamel. Retirez avant incorporation — arômes pleinement extraits.'],
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras ou le bouillon',  'advantages' => 'Diffuse ses arômes dans toute la préparation',           'text' => 'Faites revenir le bâton entier dans le beurre ou l\'huile chaude 1-2 min avant d\'ajouter les autres ingrédients. Idéal pour les currys et biryanis.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Saupoudrage final',                  'advantages' => 'Préserve les arômes délicats, évite l\'amertume',        'text' => 'Ajoutez la poudre moulue hors du feu sur les desserts, porridges ou sauces. La chaleur résiduelle suffit à développer l\'arôme sans le dégrader.'],
        ],
        'spice_clou_girofle' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Dans le vin ou le bouillon froid',   'advantages' => 'Extraction lente et contrôlée de l\'eugenol',             'text' => 'Infusez 2-3 clous dans le vin ou le bouillon à froid plusieurs heures avant cuisson. Intensité parfaitement maîtrisée.'],
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans l\'oignon piqué',               'advantages' => 'Technique classique des béchamels et pot-au-feu',        'text' => 'Piquez un oignon coupé en deux avec 2-3 clous de girofle. Plongez dans vos bouillons ou béchamels dès le début — retire avant service.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les mijotés',                   'advantages' => 'Profondeur aromatique dans les ragoûts longue durée',    'text' => 'Incorporez les clous entiers à mi-cuisson dans les ragoûts, daube ou navarin. 2-3 clous pour 4 personnes — pas plus.'],
        ],
        'spice_laurier' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le bouillon ou le fond',        'advantages' => 'Parfume toute la durée de cuisson des longues préparations','text' => 'Plongez 2 feuilles dès le départ dans vos bouillons, courts-bouillons et fonds de sauce. Laissez infuser toute la durée.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les ragoûts et braisés',        'advantages' => 'Intégration progressive dans les jus de cuisson',          'text' => 'Ajoutez à mi-cuisson dans les ragoûts et daubes. Les feuilles sèches sont plus concentrées que les fraîches — ajustez en conséquence.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Retrait avant service',              'advantages' => 'N\'oubliez jamais de retirer — toujours indigeste',       'text' => 'Le laurier se retire systématiquement avant de servir. Il a rempli son rôle aromatique — le laisser serait désagréable en bouche.'],
        ],
        'spice_fenouil' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras',                 'advantages' => 'Révèle les notes anisées chaudes dès la première minute', 'text' => 'Faites revenir les graines torréfiées dans l\'huile d\'olive chaude 30 secondes avant les légumes ou viandes. Elles éclatent et parfument l\'huile.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les sauces pour poissons',      'advantages' => 'Affinité naturelle avec le poisson et les crustacés',     'text' => 'Incorporez les graines moulues dans vos fumet de poisson et sauces à mi-cuisson. Elles s\'intègrent au liquide sans amertume.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Graines fraîches en finition',  'advantages' => 'Texture croquante et fraîcheur aromatique sur le plat',  'text' => 'Parsemez quelques graines entières grillées sur vos poissons grillés ou salades chaudes au moment de servir. Contraste de texture réussi.'],
        ],
        'spice_anis_etoile' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Dans le bouillon ou le fond',        'advantages' => 'Extrait l\'anéthol sans dominance dans les bouillons asiatiques','text' => 'Plongez l\'étoile entière dans le fond de canard ou de porc à froid. Laissez infuser 1-2h avant de cuire — arôme subtil et profond.'],
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans les compotes et cuissons fruitées','advantages' => 'Parfume les pommes, poires et coings dès le départ',  'text' => 'Ajoutez 1-2 étoiles dès le début pour les compotes et pochages de fruits. L\'arôme anisé se marie naturellement au sucre de cuisson.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les sauces braisées',           'advantages' => 'Profondeur anisée dans les canards laqués et porc',      'text' => 'Ajoutez à mi-cuisson dans le canard laqué, porc braisé ou bouillon pho. 1 étoile pour 500ml de sauce suffit.'],
        ],
        'spice_estragon' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Marinade pour volaille',             'advantages' => 'Pénètre les fibres de la viande pour un arôme profond',  'text' => 'Mélangez estragon émietté, ail, huile et citron. Enduisez le poulet et laissez mariner 2-4h au frais avant de rôtir.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les sauces crémeuses',          'advantages' => 'Préserve les notes anisées volatiles de l\'estragon',    'text' => 'Incorporez l\'estragon haché dans les sauces béarnaise et crèmes en toute fin, hors du feu. La chaleur excessive détruit les arômes.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Ciselé sur les salades et crudités','advantages' => 'Fraîcheur maximale, arôme anisé vif',                'text' => 'Ciselez finement l\'estragon frais sur vos salades vertes, tomates et asperges. Aucune cuisson — tout l\'arôme est préservé.'],
        ],
        'spice_basilic' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Huile aromatique au basilic',        'advantages' => 'Base parfumée pour vinaigrettes et finitions',            'text' => 'Mixez des feuilles fraîches avec de l\'huile d\'olive et filtrez. Utilisez cette huile verte pour assaisonner pâtes, poissons et légumes.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Feuilles sur plat chaud',            'advantages' => 'Légèrement fané, libère un arôme doux et chaud',           'text' => 'Déposez les feuilles entières sur la pizza chaude ou les pâtes fumantes en fin de cuisson. Elles fanent en quelques secondes — moment parfait.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Cru sur salade caprese',         'advantages' => 'Arôme frais intégral, contraste avec la tomate et mozzarella','text' => 'Le basilic frais ne supporte pas la cuisson. Disposez les feuilles entières juste avant de servir sur tomates, mozzarella, melon.'],
        ],
        'spice_coriandre' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras pour curry',      'advantages' => 'Libère les terpènes dans l\'huile, base aromatique du curry','text' => 'Faites revenir les graines moulues ou torréfiées dans l\'huile chaude 1 min avant l\'oignon. Elles forment la base aromatique des currys indiens.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les mijotés et tagines',        'advantages' => 'S\'intègre aux jus de cuisson pour profondeur aromatique',  'text' => 'Ajoutez à mi-cuisson dans les tagines et mijotés. La coriandre moulue épaissit légèrement les sauces et leur donne une rondeur florale.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Graines entières en garniture', 'advantages' => 'Texture croquante et fraîcheur aromatique distinctive',    'text' => 'Parsemez quelques graines légèrement concassées sur les salades, houmous et plats de légumes. Croquant et arôme floral-citronné.'],
        ],
        'spice_cardamome' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Dans le lait pour chai',             'advantages' => 'Extrait les arômes floraux sans amertume',                'text' => 'Infusez 3-4 gousses légèrement fendues dans le lait frémissant 10-15 min avant de préparer le masala chai. Filtrez soigneusement.'],
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le ghee pour biryani',          'advantages' => 'Infuse le corps gras, parfume tout le riz ensuite',       'text' => 'Faites revenir les gousses entières fendues dans le ghee chaud 30-60 secondes avant d\'ajouter l\'oignon. Technique centrale du biryani.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Poudre dans les desserts',           'advantages' => 'S\'intègre aux pâtes sucrées et ganaches au chocolat',   'text' => 'Ajoutez la poudre de graines fraîchement moulues dans votre crème ou ganache à mi-cuisson. Affinité remarquable avec le chocolat noir.'],
        ],
        'spice_muscade' => [
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les sauces crémeuses',          'advantages' => 'S\'intègre progressivement à la béchamel en développement','text' => 'Incorporez la muscade râpée dans votre béchamel ou crème en cours de cuisson. L\'arôme camphrée s\'harmonise avec les produits laitiers.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Râpée fraîche hors du feu',          'advantages' => 'Arôme maximal préservé sur purées et gratins',             'text' => 'Râpez la muscade entière directement sur vos purées, gratins et épinards à la crème en fin de cuisson. Quelques passages de microplane suffisent.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Finition sur cappuccino et œufs','advantages' => 'Note aromatique de chef, touche visuelle',              'text' => 'Une légère râpure de muscade sur un cappuccino ou un oeuf cocotte fini est un classique de la cuisine française et italienne.'],
        ],
        'spice_poivre_noir' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Dans la marinade sèche',             'advantages' => 'Pénètre les fibres et rehausse toute la viande',          'text' => 'Incorporez le poivre concassé dans vos marinades sèches pour steaks et côtes. Laissez agir 1-4h au frais. Le piquant pénètre en profondeur.'],
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le fond pour les sauces',       'advantages' => 'Extrait la pipérine dans les liquides pour profondeur',   'text' => 'Incorporez le poivre concassé dès le départ dans vos fonds bruns. Il libère progressivement son piquant et ses notes boisées.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Tour de moulin sur le plat fini','advantages' => 'Fraîcheur aromatique maximale, présentation soignée',   'text' => 'Un généreux tour de moulin sur le plat fini — steak, salade, pâtes, fromage — apporte fraîcheur aromatique et piquant vif. L\'essentiel.'],
        ],
        'spice_poivre_long' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans les braisés et gibiers',        'advantages' => 'Notes médiévales chaudes et sucrées dans les longues cuissons','text' => 'Incorporez 1-2 chatons entiers dans vos civets, braisés de gibier et daubes dès le départ. Arôme complexe qui se développe avec le temps.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Poudre sur chocolat et fromages',    'advantages' => 'Complexité épicée légèrement sucrée pour les accords sucrés-salés','text' => 'Râpez ou saupoudrez en fin sur les fondants au chocolat, mousses et plateaux de fromages affinés. Accord rare et sophistiqué.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Finition gastronomique',          'advantages' => 'Touche de distinction sur les plats de haute cuisine',   'text' => 'Quelques copeaux ou une pincée de poudre fine sur les carpaccios, ceviche et foie gras poêlé. Poivre gastronomique par excellence.'],
        ],
        'spice_piment_cayenne' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans l\'huile chaude',               'advantages' => 'Capsaïcine libérée dans le corps gras pour diffusion',   'text' => 'Incorporez une pincée dans l\'huile chaude avant les légumes. La capsaïcine est liposoluble — le gras véhicule le piquant dans toute la préparation.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les sauces et currys',          'advantages' => 'Dosage progressif pour ajuster l\'intensité du piquant',  'text' => 'Ajoutez par petites quantités en milieu de cuisson et goûtez à chaque ajout. Le piquant du cayenne s\'intensifie à la chaleur.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les sauces piquantes',          'advantages' => 'Piquant vif et immédiat préservé',                       'text' => 'Pour les sauces piquantes et huiles, ajoutez hors du feu pour préserver l\'intensité maximale. Se mélange parfaitement en fin de cuisson.'],
        ],
        'spice_paprika_doux' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans l\'huile chaude',               'advantages' => 'Pigments dissous dans le gras — couleur vive garantie',  'text' => 'Faites "bloomer" le paprika 30 secondes dans l\'huile chaude hors du feu avant d\'ajouter les légumes. La couleur rouge-orangée se libère dans le gras.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans le goulash ou le poulet',       'advantages' => 'Colore et parfume progressivement toute la préparation',  'text' => 'Incorporez généreusement dans le goulash hongrois ou le poulet au paprika à mi-cuisson. Il s\'intègre parfaitement aux liquides de braisage.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Saupoudrage final',                  'advantages' => 'Couleur éclatante et arôme doux préservé en finition',   'text' => 'Saupoudrez sur les plats finalisés — houmous, oeufs, viandes — pour une couleur chaleureuse et une note légèrement fumée.'],
        ],
        'spice_thym' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le bouquet garni',              'advantages' => 'Parfume toute la durée des longues cuissons',              'text' => 'Liez branches de thym, laurier et persil. Plongez dès le début dans bouillons, pot-au-feu, fonds et braisés. Thymol résistant à la chaleur.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Sur les viandes rôties',             'advantages' => 'Protège la surface et parfume par contact direct',         'text' => 'Déposez des branches de thym frais sur le poulet ou l\'agneau en rôtisserie à mi-cuisson. Arôme intense par contact avec la chaleur sèche.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Retrait avant service',              'advantages' => 'Ne jamais servir les branches entières en bouche',       'text' => 'Le thym en branches se retire comme le laurier. L\'arôme est dans le plat. Les feuilles émiettées cuites peuvent être laissées si finement incorporées.'],
        ],
        'spice_origan' => [
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans la sauce tomate',               'advantages' => 'S\'intègre parfaitement aux tomates acidulées en mijotage', 'text' => 'Incorporez l\'origan émietté dans votre sauce tomate à mi-cuisson. L\'acidité de la tomate équilibre les notes chaudes de l\'origan.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les marinades après grillade',  'advantages' => 'Parfume les jus de grillades refroidissants',             'text' => 'Émettez de l\'origan sur les grillades chaudes dès la sortie du feu. L\'arôme s\'active au contact de la chaleur résiduelle des jus.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Cru sur la pizza et la salade',  'advantages' => 'Note herbacée fraîche caractéristique de la pizza italienne','text' => 'Sur la pizza napolitaine, l\'origan séché s\'applique à cru sur la garniture avant enfournement ou directement à la sortie du four.'],
        ],
        'spice_cumin' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras',                 'advantages' => 'Technique "tarka" — cumin grillé dans l\'huile ou ghee',  'text' => 'Faites grésiller les graines de cumin dans l\'huile ou le ghee chaud 30-45 secondes. Elles doivent crépiter et libérer leur arôme — versez alors sur le plat.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Moulu dans les féculents',           'advantages' => 'S\'intègre aux houmous, falafels et chili en cours de cuisson','text' => 'Incorporez la poudre à mi-cuisson dans les houmous, chili con carne et tajines. Le cumin moulu épaissit légèrement les sauces.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Finition sur le yaourt et les dips','advantages' => 'Note terreuse et chaude qui sublime les produits laitiers','text' => 'Saupoudrez du cumin grillé concassé sur le raïta, labneh ou yaourt nature. Contraste classique de la cuisine moyen-orientale.'],
        ],
        'spice_curcuma' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras',                 'advantages' => 'Libère la curcumine dans le gras — meilleure biodisponibilité','text' => 'Incorporez dans l\'huile ou le ghee chaud 1 min avant les légumes. La curcumine est liposoluble — le gras est essentiel à son absorption.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les currys et soupes',          'advantages' => 'Colore uniformément le plat et s\'intègre aux liquides',  'text' => 'Ajoutez à mi-cuisson dans vos currys, soupes de lentilles et riz. Le curcuma colore en profondeur et ne perd pas sa couleur à la cuisson.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans le golden milk',                'advantages' => 'Absorption optimale avec poivre noir et lait chaud',      'text' => 'Préparez le lait doré en fin de cuisson : mélangez curcuma, poivre noir et lait chaud sans faire bouillir. Le poivre augmente l\'absorption de 2000%.'],
        ],
        'spice_gingembre' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras ou le bouillon',  'advantages' => 'Note épicée-sucrée dans toute la préparation',            'text' => 'Incorporez la poudre dans l\'huile ou le bouillon chaud dès le début. Base aromatique des currys d\'Asie du Sud-Est et des marinades.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les pâtisseries',               'advantages' => 'Zingérone se développe à la chaleur — notes plus douces',  'text' => 'Incorporez dans vos pâtes à pain d\'épices et gâteaux à mi-mélange. Le séchage a transformé les gingérols piquants en zingérone plus sucrée.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les smoothies et infusions',    'advantages' => 'Antiémétique puissant, réchauffant naturel',              'text' => 'Ajoutez à froid dans vos smoothies ou à l\'eau frémissante pour infusion. Le gingembre séché en poudre est plus concentré que le frais.'],
        ],
        'spice_piment_jamaique' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Marinade jerk overnight',            'advantages' => 'Les 4 arômes pénètrent profondément dans les viandes',    'text' => 'Base de la marinade jerk : piment de la Jamaïque moulu avec piment scotch bonnet, ail et thym. Laisser mariner le poulet une nuit entière.'],
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans les bouillons de charcuterie',  'advantages' => 'Parfume les cuissons longues de jambon et saucisses',     'text' => 'Incorporez quelques baies entières dans l\'eau de cuisson des jambons, saucisses et pickles. Arôme complexe et persistant.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les pains d\'épices et desserts','advantages' => 'Profondeur épicée caractéristique des pains d\'épices',  'text' => 'Incorporez moulu dans vos pâtes à pain d\'épices, bredele et gâteaux aux épices à mi-mélange. Remplace avantageusement le mélange 4 épices.'],
        ],
        'spice_macis' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Dans la crème ou le lait',           'advantages' => 'Arôme floral extrait en douceur pour crèmes raffinées',   'text' => 'Infusez une fleur de macis dans la crème tiède 20-30 min avant de réaliser votre crème brûlée ou panna cotta. Filtrez soigneusement.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les bisques et potages',        'advantages' => 'Note florale discrète qui complexifie les fonds de mer',  'text' => 'Incorporez une pincée de macis moulu dans votre bisque de homard ou crème de crustacés à mi-cuisson. Affinité remarquable avec les fruits de mer.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans la béchamel fine',              'advantages' => 'Remplacement élégant de la muscade en béchamel raffinée', 'text' => 'Préférez le macis à la muscade pour les béchamels servies avec des mets fins. Son arôme plus délicat ne s\'impose pas et complète mieux.'],
        ],
        'spice_anis_vert' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'En tisane digestive',                'advantages' => 'Carminatif puissant, douceur post-repas',                  'text' => 'Infusez 1 cuillère à café de graines dans 250ml d\'eau frémissante 10 min. Boire en fin de repas — efficacité reconnue contre les ballonnements.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les biscuits et pâtisseries',  'advantages' => 'Note anisée douce typique des biscuits méditerranéens',   'text' => 'Incorporez les graines moulues dans vos pâtes à biscuits anisés, kourambiedes et pain d\'épices à mi-mélange. Arôme sucré naturel.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'En finition sur salades et fromages','advantages' => 'Note fraîche anisée croquante sur les assiettes',   'text' => 'Parsemez quelques graines entières sur vos salades, fromages frais et plateaux de charcuterie pour une note anisée inattendue.'],
        ],
        'spice_poivre_sichuan' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Huile aromatique au poivre de Sichuan','advantages' => 'Effet "ma" délicat diffus dans tout le plat',           'text' => 'Infusez les cosses torréfiées dans l\'huile neutre à 60°C pendant 1h. Filtrez — l\'huile rouge est la base de nombreuses sauces sichuanaises.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Concassé sur le plat chaud',         'advantages' => 'Effet anesthésiant immédiat et notes citronnées fraîches','text' => 'Concassez grossièrement et saupoudrez sur le mapo tofu ou le canard de Pékin à la sortie du feu. L\'effet fourmillant s\'active au contact des papilles.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Poudre fraîche sur carpaccio',   'advantages' => 'Note citronnée-florale distinctive sur les crus',         'text' => 'Saupoudrez de poudre fraîche sur les sashimis, carpaccios de boeuf et fromages. L\'effet anesthésiant léger est une expérience gustative unique.'],
        ],
        'spice_piment_espelette' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras',                 'advantages' => 'Chaleur douce diffuse dans tout le plat dès le début',   'text' => 'Incorporez dans l\'huile d\'olive chaude avec l\'ail pour la base de vos sauces basques. L\'Espelette ne brûle pas dans la cuisine comme le cayenne.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans le beurre d\'Espelette',        'advantages' => 'Finition classique basque, remplace avantageusement le poivre','text' => 'Incorporez dans du beurre doux tempéré. Ce beurre composé est la finition signature des plats de Gascogne — sur le poisson, les grillades et les œufs.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Saupoudré sur les œufs et fromages','advantages' => 'Couleur rouge vive, chaleur douce et notes fruitées',  'text' => 'Sur les œufs au plat, le jambon de Bayonne, le fromage de brebis basque — l\'Espelette est le condiment universel du Pays Basque.'],
        ],
        'spice_poivre_blanc' => [
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les sauces blanches',           'advantages' => 'Piquant invisible — aucune tache dans la béchamel blanche','text' => 'Incorporez le poivre blanc moulu dans vos béchamels et velouté en cours de cuisson. Le blanc sur blanc préserve la présentation irréprochable.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les crèmes et fumets',          'advantages' => 'Piquant pur et net en finition sur les poissons fins',    'text' => 'Ajoutez en fin de cuisson dans les fumet de poisson et crèmes fraîches. Son piquant direct — sans les tanins du noir — est idéal sur les mets délicats.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Tour de moulin sur viandes blanches','advantages' => 'Note poivrée pure sans colorer les assiettes claires','text' => 'Un tour de moulin de poivre blanc sur les blancs de volaille, filets de sole et risottos crémeux garantit l\'élégance visuelle et la précision du piquant.'],
        ],
        'spice_romarin' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans le corps gras',                 'advantages' => 'Infuse le gras de son arôme résineux pour toute la cuisson','text' => 'Faites revenir les branches dans l\'huile d\'olive chaude 1-2 min avant les pommes de terre ou l\'agneau. L\'arôme pénètre profondément le corps gras.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Sur les rôtis et grillades',         'advantages' => 'Crée une croûte aromatique protectrice sur les viandes',  'text' => 'Déposez des branches sur l\'agneau ou le veau à mi-cuisson en rôtisserie. L\'arôme camphrée se développe progressivement à chaleur sèche.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Infusion dans la sauce de cuisson',  'advantages' => 'Concentre l\'arôme dans les sucs pour la sauce d\'accompagnement','text' => 'En fin de rôtissage, déglacez avec les branches de romarin encore chaudes. Les sucs se chargent d\'arôme résineux — la sauce obtenue est exceptionnelle.'],
        ],
        'spice_marjolaine' => [
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les farces et saucisses',       'advantages' => 'Note florale délicate dans les viandes hachées',          'text' => 'Incorporez la marjolaine séchée dans vos farces de volaille, saucisses et terrine à mi-préparation. Plus délicate que l\'origan — dosez généreusement.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les vinaigrettes et sauces',   'advantages' => 'Notes douces et florales en finition sur les crudités',   'text' => 'Ajoutez en fin dans vos vinaigrettes, sauces tomates légères et marinades. La marjolaine perd ses arômes à forte chaleur — l\'ajouter en fin préserve tout.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Brins frais en garniture',       'advantages' => 'Finition herbacée délicate et musquée sur les viandes blanches','text' => 'Déposez quelques brins de marjolaine fraîche sur vos viandes blanches et fromages au moment de servir. Arôme subtil, présentation soignée.'],
        ],
        'spice_sauge' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Friture dans le beurre',             'advantages' => 'Feuilles croustillantes et beurre noisette parfumé',      'text' => 'Faites frire les feuilles entières dans le beurre moussant jusqu\'à croustillant (30-60 sec). Versez beurre et feuilles sur gnocchi, pâtes et poissons.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans les farces pour volaille',      'advantages' => 'Arôme camphrée qui neutralise les odeurs fortes',          'text' => 'Incorporez la sauge hachée dans les farces de dinde, pintade et porc. Son arôme puissant contre-balance les saveurs grasses des viandes farcies.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans les sauces brunes',             'advantages' => 'Note herbacée chaude en finition sur les jus de viande',  'text' => 'Infusez la sauge dans le jus de rôtissage en fin de cuisson. Quelques feuilles dans le liquide chaud 5 min suffisent à parfumer toute la sauce.'],
        ],
        'spice_carvi' => [
            ['step' => 1, 'cooking_step' => 'En début de cuisson',   'title' => 'Dans les choux et légumes',          'advantages' => 'Atténue le soufre des choux, note anisée en profondeur',  'text' => 'Ajoutez le carvi torréfié dans l\'eau de cuisson des choux, ou dans le beurre pour les choux sautés dès le début. Il contre les odeurs soufrées.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans la choucroute',                 'advantages' => 'Arôme signature de la choucroute alsacienne et germanique','text' => 'Incorporez les graines entières ou concassées dans la choucroute à mi-cuisson. Elles s\'intègrent au liquide acide et aux graisses de cuisson.'],
            ['step' => 4, 'cooking_step' => 'À cru, au moment de servir','title' => 'Sur les fromages et pains',      'advantages' => 'Note anisée-poivrée sur les fromages à pâte lavée',      'text' => 'Parsemez les graines de carvi sur le Munster, Tilsit et pains de seigle au moment de servir. Association traditionnelle d\'Europe centrale.'],
        ],
        'spice_safran' => [
            ['step' => 0, 'cooking_step' => 'En infusion préalable', 'title' => 'Infusion obligatoire 20-30 minutes', 'advantages' => 'Libère pleinement la couleur dorée et l\'arôme safranal', 'text' => 'Émiettez les filaments dans 2-3 cuillères à soupe de bouillon ou d\'eau tiède (40-50°C, jamais bouillante). Laissez infuser minimum 20-30 min — la couleur s\'intensifie progressivement.'],
            ['step' => 2, 'cooking_step' => 'En milieu de cuisson',  'title' => 'Dans la paella et le risotto',       'advantages' => 'S\'intègre parfaitement au bouillon de cuisson des céréales', 'text' => 'Incorporez l\'infusion de safran avec son liquide à mi-cuisson du risotto ou de la paella. Le riz s\'imprègne uniformément de la couleur dorée.'],
            ['step' => 3, 'cooking_step' => 'En fin de cuisson',     'title' => 'Dans la bouillabaisse et fumets',    'advantages' => 'Note florale et métallique délicate sur les fruits de mer','text' => 'Ajoutez l\'infusion dans la bouillabaisse ou les fumets en fin de cuisson. Le safran se marie particulièrement avec les poissons nobles et crustacés.'],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::TIPS as $spiceRef => $tips) {
            /** @var Spices $spice */
            $spice = $this->getReference($spiceRef, Spices::class);

            foreach ($tips as $tipData) {
                $tip = new CookingTips();
                $tip->setSpice($spice)
                    ->setStep($tipData['step'])
                    ->setCookingStep($tipData['cooking_step'])
                    ->setTitle($tipData['title'])
                    ->setAdvantages($tipData['advantages'])
                    ->setText($tipData['text'])
                    ->setCreatedAt($now)
                    ->setUpdatedAt($now);

                $manager->persist($tip);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            SpicesFixtures::class,
        ];
    }
}
