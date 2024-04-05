<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AromaticGroupsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $colors = [
            'FFFF00', 'dfaf2c', 'FF4500', 'd9381e', 
            'FF0000', '800080', 'EE82EE', '4B0082', 
            '0000FF', '25fde9', '008000', '7FFF00'
        ]; // 12 Colors

        foreach ($colors as $key => $color) {
            $aromaticGroup = new AromaticGroups();

            $aromaticGroup->setName($faker->name())
                ->setDescription($faker->text(260))
                ->setCooking($faker->text(260))
                ->setInformations($faker->text(260))
                ->setColor('#' . $color)
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());

                
            $manager->persist($aromaticGroup);
            $this->addReference('AromaticGroup_'.$key, $aromaticGroup);
        }

        $manager->flush();
    }
}
