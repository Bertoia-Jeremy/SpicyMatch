<?php

namespace App\DataFixtures;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AromaticCompoundFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for($i = 0; $i < 12; $i++){
            //copy("/home/jbertoia/Images/composeCanelle.jpeg", "/home/jbertoia/Images/composeCanelle_$i.jpeg");
            $entity = new AromaticCompound();
            $entity->setName('aromatic_compound_'.$i)
                ->setCreatedAt(new \DateTime('now'))
                ->setUpdatedAt(new \DateTime('now'))
                ->addSpices($this->getReference($i.'spice', Spices::class))
                //->setImageFile(new UploadedFile("/home/jbertoia/Images/composeCanelle_$i.jpeg", 'testCan.jpeg',
                //    null, null, true))
            ;
            $this->addReference($i.'aromaticCompound', $entity);

            if($i > 2 && $i <8){
                $entity->addSpices($this->getReference(($i-1).'spice', Spices::class));
                $entity->addSpices($this->getReference(($i+1).'spice', Spices::class));
                $entity->addSpices($this->getReference(($i+2).'spice', Spices::class));
            }
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            SpicesFixtures::class,
        );
    }
}
