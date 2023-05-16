<?php

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SpicesFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $spicyNumber = 1;
        for($i = 0; $i < 12; $i++){
            copy("/home/jbertoia/Images/cannelle.webp", "/home/jbertoia/Images/cannelle_$i.webp");
            $entity = new Spices();
            $entity->setName('spices_'.$i)
                ->setCreatedAt(new \DateTime('now'))
                ->setUpdatedAt(new \DateTime('now'))
                ->setAromaticGroups($this->getReference($i.'aromaticGroup', AromaticGroups::class))
                ->setSpicyType($this->getReference($spicyNumber.'spicyType', SpicyType::class))
                ->setImageFile(new UploadedFile("/home/jbertoia/Images/cannelle_$i.webp", 'test.webp',
                    null, null, true))
            ;
            $this->addReference($i.'spice', $entity);
            $manager->persist($entity);

            if($spicyNumber > 4){
                $spicyNumber = 1;
            }else{
                $spicyNumber++;
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            AromaticGroupsFixtures::class,
            SpicyTypeFixtures::class,
        );
    }
}
