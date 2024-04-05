<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\SpicyMatch;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SpicyMatchFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < 24; ++$i) {
            $nbSpices = rand(2, 10);
            $spicesIds = "";

            for ($j = 0; $j < $nbSpices; ++$j) {
                $spice = $this->getReference("Spice_".rand(0, 35));
                $spicesIds .= $spice->getId(). ",";
            }
            $spicesIds = trim($spicesIds, ",");

            $spicyMatch = new SpicyMatch();
            $spicyMatch->setUserId($this->getReference("User_".rand(0, 11)))
                ->setNbSpice($nbSpices)
                ->setSpicesIds($spicesIds)
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());
             
            $manager->persist($spicyMatch);
            $this->addReference('SpicyMatch_'.$i, $spicyMatch);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UsersFixtures::class,
            SpicesFixtures::class,
        ];
    }
}
