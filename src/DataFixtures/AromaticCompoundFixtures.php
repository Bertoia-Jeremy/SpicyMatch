<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AromaticCompoundFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        for ($i = 0; $i < 36; ++$i) {
            $aromaticCompound = new AromaticCompound();

            $aromaticCompound->setName($faker->name())
                ->setDescription($faker->text(260))
                ->setCooking($faker->text(260))
                ->setInformations($faker->text(260))
                ->addSpices($this->getReference('Spice'.rand(0, 11), Spices::class))
                ->addSpices($this->getReference('Spice'.rand(12, 23), Spices::class))
                ->addSpices($this->getReference('Spice'.rand(24, 35), Spices::class))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

                
            $manager->persist($aromaticCompound);
            $this->addReference("AromaticCompound".$i, $aromaticCompound);
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
