<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PreparationTips;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Repository\CookingTipsRepository;
use App\Repository\PreparationTipsRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class SpicyMatchHistoryFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private CookingTipsRepository $cookingTipsRepository,
        private PreparationTipsRepository $preparationTipsRepository
    ){

    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $preparationIds = "";
        $cookingIds = "";

        for ($i = 0; $i < 12; ++$i) {

            /** @var SpicyMatch $spicyMatch */
            $spicyMatch = $this->getReference("SpicyMatch_".rand(0, 23));
            $spices = explode(',', $spicyMatch->getSpicesIds());

            foreach($spices as $spiceId){
                $cooking = $this->cookingTipsRepository->findOneBy(['spiceId' => $spiceId], 'RAND()');
                $cookingIds .= $cooking->getId().",";
                
                $preparation = $this->preparationTipsRepository->findOneBy(['spiceId' => $spiceId], 'RAND()');
                $preparationIds .= $preparation->getId().",";
            }

            $preparationIds = trim($preparationIds, ",");
            $cookingIds = trim($cookingIds, ",");

            $entity = new SpicyMatchHistory();
            $entity->setTitle($faker->title())
                ->setFavorite((bool) rand(0, 1))
                ->setPreparationTipsIds($preparationIds)
                ->setCookingTipsIds($cookingIds)
                ->setSpicyMatchId($spicyMatch)
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            SpicyMatchFixtures::class,
            CookingTipsFixtures::class,
            PreparationTipsFixtures::class,
        ];
    }
}
