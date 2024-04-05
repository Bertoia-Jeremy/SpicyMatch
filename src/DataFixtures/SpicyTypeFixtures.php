<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class SpicyTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < 6; ++$i) {
            $spicyType = new SpicyType();
            $spicyType->setName($faker->name())
                ->setDescription($faker->text(260))
                ->setCooking($faker->text(260))
                ->setInformations($faker->text(260))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

            $manager->persist($spicyType);
            $this->addReference('SpicyType_'.$i, $spicyType);
        }

        $manager->flush();
    }
}
