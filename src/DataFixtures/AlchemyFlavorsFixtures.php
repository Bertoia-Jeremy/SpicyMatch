<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 12 aromatic flavor profiles linked to real aromatic compounds.
 *
 * AlchemyFlavors are culinary descriptors grouping compounds by perceived taste/smell.
 * They are NOT used in compatibility scoring but enrich the data model for future use.
 */
class AlchemyFlavorsFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var array<string, array{name: string, description: string, compounds: string[]}>
     */
    private const FLAVORS = [
        'epice_brulant' => [
            'name'        => 'Épicé & Brûlant',
            'description' => 'Chaleur intense et persistante en bouche, typique des piments et du poivre.',
            'compounds'   => ['compound_capsaicine', 'compound_piperine'],
        ],
        'chaud_clou' => [
            'name'        => 'Chaud & Cloutés',
            'description' => 'Notes chaudes, épicées et légèrement sucrées évoquant le clou de girofle et la cannelle.',
            'compounds'   => ['compound_eugenol', 'compound_cinnamaldehyde'],
        ],
        'anise_fenouille' => [
            'name'        => 'Anisé & Fenouillé',
            'description' => 'Arôme caractéristique de l\'anis, doux et légèrement sucré, notes de réglisse.',
            'compounds'   => ['compound_anethole', 'compound_estragole'],
        ],
        'floral_leger' => [
            'name'        => 'Floral & Léger',
            'description' => 'Notes florales délicates évoquant la rose et le géranium, légères et aériennes.',
            'compounds'   => ['compound_linalool', 'compound_geraniol'],
        ],
        'herbace_sauvage' => [
            'name'        => 'Herbacé & Sauvage',
            'description' => 'Notes végétales franches, légèrement médicinales, typiques des herbes méditerranéennes.',
            'compounds'   => ['compound_thymol', 'compound_carvacrol'],
        ],
        'fruite_citron' => [
            'name'        => 'Fruité & Citronné',
            'description' => 'Notes citronnées et légèrement sucrées apportant fraîcheur et vivacité aux mélanges.',
            'compounds'   => ['compound_limonene', 'compound_geraniol'],
        ],
        'terreux_dore' => [
            'name'        => 'Terreux & Doré',
            'description' => 'Notes profondes, terreuses et légèrement amères, évoquant la terre humide et les épices dorées.',
            'compounds'   => ['compound_curcumine'],
        ],
        'zingibere_chaud' => [
            'name'        => 'Zingibéré & Chaud',
            'description' => 'Chaleur douce et progressive du gingembre séché, moins agressive que la capsaïcine.',
            'compounds'   => ['compound_zingerone'],
        ],
        'precieux_delicat' => [
            'name'        => 'Précieux & Délicat',
            'description' => 'Arôme unique et complexe du safran — floral, mielleuse, légèrement métallique.',
            'compounds'   => ['compound_safranal'],
        ],
        'camphre_frais' => [
            'name'        => 'Camphrés & Frais',
            'description' => 'Notes fraîches, légèrement médicinales et eucalyptées caractéristiques de la cardamome.',
            'compounds'   => ['compound_terpinene4ol', 'compound_limonene'],
        ],
        'balsamique_resineux' => [
            'name'        => 'Balsamique & Résineux',
            'description' => 'Notes boisées et résineuses, rondes et persistantes, à la croisée de l\'épicé et du doux.',
            'compounds'   => ['compound_eugenol', 'compound_anethole'],
        ],
        'poivre_musque' => [
            'name'        => 'Poivré & Musqué',
            'description' => 'Poivré persistant avec une note musquée en fond, complexe et légèrement terreux.',
            'compounds'   => ['compound_piperine', 'compound_terpinene4ol'],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        foreach (self::FLAVORS as $key => $data) {
            $entity = new AlchemyFlavors();
            $entity->setName($data['name'])
                ->setDescription($data['description'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            foreach ($data['compounds'] as $compoundRef) {
                /** @var AromaticCompound $compound */
                $compound = $this->getReference($compoundRef, AromaticCompound::class);
                $entity->addAromaticsCompounds($compound);
            }

            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AromaticCompoundFixtures::class];
    }
}
