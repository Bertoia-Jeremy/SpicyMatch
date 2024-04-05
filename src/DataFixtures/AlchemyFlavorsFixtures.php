<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AlchemyFlavorsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        for ($i = 0; $i < 48; ++$i) {
            $alchemyFlavor = new AlchemyFlavors();

            $alchemyFlavor->setName($faker->name())
                ->setDescription($faker->text(260))
                ->setCooking($faker->text(260))
                ->setInformations($faker->text(260))
                ->addAromaticsCompounds($this->getReference('AromaticCompound_'.rand(0, 11), AromaticCompound::class))
                ->addAromaticsCompounds($this->getReference('AromaticCompound_'.rand(12, 23), AromaticCompound::class))
                ->addAromaticsCompounds($this->getReference('AromaticCompound_'.rand(24, 35), AromaticCompound::class))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

            $manager->persist($alchemyFlavor);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AromaticCompoundFixtures::class,
        ];
    }
}
