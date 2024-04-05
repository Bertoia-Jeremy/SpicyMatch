<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PreparationMethods;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PreparationMethodsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < 12; ++$i) {
            $preparationMethod = new PreparationMethods();
            $preparationMethod->setName($faker->title())
                ->setDescription($faker->text(300))
                ->setInformations($faker->text(300))
                ->setAdvice($faker->text(300))
                ->setTools($faker->paragraph(4))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

            $manager->persist($preparationMethod);
            $this->addReference('PreparationMethod_'.$i, $preparationMethod);
        }

        $manager->flush();
    }
}
