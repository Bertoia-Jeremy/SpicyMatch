<?php

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AromaticGroupsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $colors = ['FFFF00', 'dfaf2c', 'FF4500', 'd9381e', 'FF0000', '800080',
            'EE82EE', '4B0082', '0000FF', '25fde9', '008000', '7FFF00'];

        foreach ($colors as $key => $color){
            $entity = new AromaticGroups();
            $entity->setName('aromatic_group_'.$key)
                ->setColor('#'.$color)
                ->setCreatedAt(new \DateTime('now'))
                ->setUpdatedAt(new \DateTime('now'));
            $this->addReference($key, $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }
}
