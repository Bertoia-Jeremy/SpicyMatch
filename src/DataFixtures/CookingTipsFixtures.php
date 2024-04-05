<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CookingTips;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory as Factory;

class CookingTipsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $steps = [
            0 => 'Avant',
            1 => 'Début',
            2 => 'Milieu',
            3 => 'Fin',
            4 => 'Après',
        ];
        $nbForReference = 0;

        for ($i = 0; $i < 108; ++$i) {
            $randomStep = rand(0, 4);

            $cookingTip = new CookingTips();
            $cookingTip->setCookingStep($steps[$randomStep])
                ->setStep($randomStep)
                ->setTitle($faker->title())
                ->setText($faker->text(250))
                ->setAdvantages($faker->text(100))
                ->setSpice($this->getReference("Spice_".$nbForReference))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

            $manager->persist($cookingTip);
            $nbForReference = $nbForReference >= 35 ? 0 : ++$nbForReference;
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
