<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AromaticCompound;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 15 real aromatic compounds with correct botanical descriptions.
 *
 * CAS numbers validated via PubChem API (double-check: re-query by CAS → nom IUPAC concordant).
 * Formulas validated via NIST WebBook.
 *
 * These compounds drive spice compatibility:
 *   - eugenol + cinnamaldéhyde + linalool  → cinnamon/clove/bay family
 *   - anethole + estragole                 → anise/fennel/tarragon family
 *   - linalool + terpinène-4-ol + géraniol → coriander/cardamom family
 *   - capsaïcine + pipérine                → chili/pepper family
 *   - thymol + carvacrol                   → thyme/oregano/cumin family
 *   - curcumine + zingerone                → turmeric/ginger family
 *   - safranal                             → saffron (unique)
 */
class AromaticCompoundFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var array<string, array{name: string, cas_number: string, formula: string, description: string, cooking: string, informations: string}>
     */
    private const COMPOUNDS = [
        'eugenol' => [
            'name' => 'Eugenol',
            'cas_number' => '97-53-0',
            'formula' => 'C10H12O2',
            'description' => 'Principal composé aromatique du clou de girofle et de la cannelle, appartenant à la famille des phénylpropanoïdes. Arôme chaud, épicé et légèrement sucré.',
            'cooking' => 'Confère une note chaude et complexe aux mélanges d\'épices. Présent dans le ras el hanout, le poudre de cinq-épices et les marinades.',
            'informations' => 'Molécule aux propriétés antioxydantes et antiseptiques. Représente 70-90% de l\'huile essentielle de clou de girofle.',
        ],
        'cinnamaldehyde' => [
            'name' => 'Cinnamaldéhyde',
            'cas_number' => '104-55-2',
            'formula' => 'C9H8O',
            'description' => 'Aldéhyde aromatique responsable de l\'arôme caractéristique de la cannelle. Note sucrée, chaude et légèrement boisée.',
            'cooking' => 'Arôme signature des desserts à la cannelle, des vin chauds et des currys doux. S\'associe parfaitement avec le clou de girofle.',
            'informations' => 'Représente 55-90% de l\'huile essentielle de cannelle. Instable à la chaleur prolongée.',
        ],
        'anethole' => [
            'name' => 'Anéthol',
            'cas_number' => '104-46-1',
            'formula' => 'C10H12O',
            'description' => 'Éther phénolique responsable de l\'arôme anisé caractéristique du fenouil, de l\'anis étoilé et de l\'estragon.',
            'cooking' => 'Arôme dominant des boissons anisées (pastis, ouzo). Excellente association avec les fruits de mer et les légumes sucrés.',
            'informations' => 'Représente 70-90% de l\'huile essentielle d\'anis étoilé. Note très persistante.',
        ],
        'estragole' => [
            'name' => 'Estragole',
            'cas_number' => '140-67-0',
            'formula' => 'C10H12O',
            'description' => 'Phénylpropanoïde aux notes anisées plus douces et herbacées, typique du basilic, de l\'estragon et secondairement du fenouil.',
            'cooking' => 'Arôme caractéristique du basilic frais et de l\'estragon. Entre dans la composition du vinaigre d\'estragon.',
            'informations' => 'Aussi appelé méthylchavicol. Proche de l\'anéthol mais avec des notes plus vertes et herbacées.',
        ],
        'linalool' => [
            'name' => 'Linalol',
            'cas_number' => '78-70-6',
            'formula' => 'C10H18O',
            'description' => 'Alcool terpénique floral et légèrement frais, dominant dans la coriandre, la cardamome et le basilic doux.',
            'cooking' => 'Apporte une note florale délicate aux mélanges. Essentiel dans les currys thaïs, les marinades à la coriandre.',
            'informations' => 'Présent dans plus de 200 plantes aromatiques. Ses deux énantiomères ont des arômes distincts (floral vs boisé).',
        ],
        'terpinene4ol' => [
            'name' => 'Terpinèn-4-ol',
            'cas_number' => '562-74-3',
            'formula' => 'C10H18O',
            'description' => 'Alcool monoterpénique aux notes fraîches, légèrement terreuses et poivrées. Principal composé actif de la cardamome et du tea tree.',
            'cooking' => 'Arôme complexe de la cardamome verte. Intervient dans le masala chai, les mélanges d\'épices indiennes et le café épicé.',
            'informations' => 'Composé majoritaire de l\'huile de tea tree et de la cardamome. Propriétés antimicrobiennes reconnues.',
        ],
        'geraniol' => [
            'name' => 'Géraniol',
            'cas_number' => '106-24-1',
            'formula' => 'C10H18O',
            'description' => 'Alcool terpénique floral aux notes de rose et de géranium, présent dans la coriandre et secondairement dans la cardamome.',
            'cooking' => 'Renforce les notes florales des mélanges d\'épices. Excellent dans les desserts délicats et les thés aromatisés.',
            'informations' => 'Arôme de rose très puissant, seuil de perception très bas. Présent naturellement dans les roses, géraniums et citronnelles.',
        ],
        'limonene' => [
            'name' => 'D-Limonène',
            'cas_number' => '5989-27-5',
            'formula' => 'C10H16',
            'description' => 'Monoterpène aux notes citronnées et fraîches, présent en quantités variables dans de nombreuses épices (poivre, cardamome, cumin).',
            'cooking' => 'Note de fond citronnée qui allège les mélanges. Excellent dans les marinades, les sauces et les vinaigrettes épicées.',
            'informations' => 'Terpène parmi les plus répandus dans la nature. Le D-limonène sent l\'orange, le L-limonène sent la térébenthine.',
        ],
        'capsaicine' => [
            'name' => 'Capsaïcine',
            'cas_number' => '404-86-4',
            'formula' => 'C18H27NO3',
            'description' => 'Capsaïcinoïde responsable du piquant des piments. Stimule les récepteurs TRPV1 créant une sensation de chaleur intense.',
            'cooking' => 'Dosage précis essentiel selon le niveau de chaleur souhaité. Base des sauces piquantes, des currys relevés et des marinades.',
            'informations' => 'Mesurée en unités Scoville (SHU). La capsaïcine pure atteint 16 000 000 SHU. Liposoluble — neutralisée par les corps gras.',
        ],
        'piperine' => [
            'name' => 'Pipérine',
            'cas_number' => '94-62-2',
            'formula' => 'C17H19NO3',
            'description' => 'Alcaloïde du poivre noir et blanc responsable du piquant caractéristique, plus lent et plus persistant que la capsaïcine.',
            'cooking' => 'Le piquant du poivre. Rehausse tous les plats et améliore l\'absorption des autres composés aromatiques (notamment la curcumine).',
            'informations' => 'La pipérine augmente la biodisponibilité de la curcumine jusqu\'à 2000%. Arôme poivré distinct de la capsaïcine.',
        ],
        'thymol' => [
            'name' => 'Thymol',
            'cas_number' => '89-83-8',
            'formula' => 'C10H14O',
            'description' => 'Phénol monoterpénique aux notes herbacées, légèrement médicamenteuses et chaleureuses. Composé dominant du thym et présent dans le cumin.',
            'cooking' => 'Arôme signature du thym frais. Résiste bien à la cuisson. Indispensable dans les bouquets garnis et les ragoûts méditerranéens.',
            'informations' => 'Fort pouvoir antiseptique. Utilisé en aromathérapie. Se dégrade à haute température en para-cymène (note citronnée).',
        ],
        'carvacrol' => [
            'name' => 'Carvacrol',
            'cas_number' => '499-75-2',
            'formula' => 'C10H14O',
            'description' => 'Phénol monoterpénique isomère du thymol, dominant dans l\'origan et présent dans le thym. Note herbacée plus chaude et moins fraîche.',
            'cooking' => 'Arôme caractéristique de l\'origan séché, de la pizza méditerranéenne. S\'associe naturellement avec le thymol pour des notes herbacées complexes.',
            'informations' => 'Présent à 60-80% dans l\'HE d\'origan méditerranéen. Puissante activité antifongique et antibactérienne.',
        ],
        'curcumine' => [
            'name' => 'Curcumine',
            'cas_number' => '458-37-7',
            'formula' => 'C21H20O6',
            'description' => 'Polyphénol jaune-orangé responsable de la couleur et de l\'arôme terreux-doré du curcuma, présent secondairement dans le gingembre.',
            'cooking' => 'Colorant et aromatisant naturel des currys, du riz safrané, des soupes dorées. S\'associe bien au gingembre pour un duo anti-inflammatoire.',
            'informations' => 'Faible biodisponibilité isolée, fortement augmentée en présence de pipérine (+2000%) et de corps gras.',
        ],
        'zingerone' => [
            'name' => 'Zingérone',
            'cas_number' => '122-48-5',
            'formula' => 'C11H14O3',
            'description' => 'Composé phénolique formé lors du séchage du gingembre. Note douce, chaude et légèrement épicée, moins piquante que les gingérols frais.',
            'cooking' => 'Arôme dominant du gingembre séché en poudre. Entre dans la composition du pain d\'épices, des ginger ales et des currys.',
            'informations' => 'La zingérone se forme lors du séchage par transformation des gingérols. Le gingembre frais contient surtout des gingérols (plus piquants).',
        ],
        'safranal' => [
            'name' => 'Safranal',
            'cas_number' => '116-26-7',
            'formula' => 'C10H14O',
            'description' => 'Aldéhyde terpénique exclusif au safran, responsable de son arôme floral, mielleuse et légèrement métallique absolument unique.',
            'cooking' => 'Quelques stigmates suffisent pour parfumer un plat entier. Bouillabaisse, risotto milanais, paella — le safranal est irremplaçable.',
            'informations' => 'Se forme lors du séchage à partir du picrocrocin. Seuil olfactif extrêmement bas (détectable à quelques ppb).',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::COMPOUNDS as $key => $data) {
            $entity = new AromaticCompound();
            $entity->setName($data['name'])
                ->setCasNumber($data['cas_number'])
                ->setFormula($data['formula'])
                ->setDescription($data['description'])
                ->setCooking($data['cooking'])
                ->setInformations($data['informations'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->addReference('compound_' . $key, $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['spice_content'];
    }
}
