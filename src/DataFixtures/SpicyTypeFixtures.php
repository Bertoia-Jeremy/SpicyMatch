<?php

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SpicyTypeFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for($i = 0; $i < 12; $i++){
            copy("/home/jbertoia/Images/cannelle.webp", "/home/jbertoia/Images/cannelle_$i.webp");
            $entity = new Spices();
            $entity->setName('spices_'.$i)
                ->setCreatedAt(new \DateTime('now'))
                ->setUpdatedAt(new \DateTime('now'))
                ->setAromaticGroups($this->getReference($i, AromaticGroups::class))
                ->setImageFile(new UploadedFile("/home/jbertoia/Images/cannelle_$i.webp", 'test.webp',
                    null, null, true))
            ;
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            AromaticGroupsFixtures::class,
        );
    }
}
