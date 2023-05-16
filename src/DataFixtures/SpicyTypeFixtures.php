<?php

namespace App\DataFixtures;

use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SpicyTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        //copy("/home/jbertoia/Images/tige.jpeg", "/home/jbertoia/Images/tige_$i.jpeg");

        for($i = 0; $i < 6; $i++){
            $entity = new SpicyType();
            $entity->setName('spicy_type_'.$i)
                ->setCreatedAt(new \DateTime('now'))
                ->setUpdatedAt(new \DateTime('now'))
            //->setImageFile(new UploadedFile("/home/jbertoia/Images/tige_$i.jpeg", 'testTige.jpeg',
            //    null, null, true))
            ;
            $this->addReference($i.'spicyType', $entity);
            $manager->persist($entity);
        }

        $manager->flush();
    }
}
