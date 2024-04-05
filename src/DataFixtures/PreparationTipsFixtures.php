<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PreparationTips;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PreparationTipsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $nbForReference = 0;

        for ($i = 0; $i < 108; ++$i) {
            $preparationTip = new PreparationTips();

            $preparationTip->setTitle($faker->title())
                ->setText($faker->text(250))
                ->setAdvantages($faker->text(100))
                ->setSpice($this->getReference("Spice_".$nbForReference))
                ->setPreparationMethod($this->getReference("PreparationMethod_".rand(0, 11)))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());
                
            $manager->persist($preparationTip);
            $nbForReference = $nbForReference >= 35 ? 0 : ++$nbForReference;
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            SpicesFixtures::class,
            PreparationMethodsFixtures::class,
        ];
    }
}
