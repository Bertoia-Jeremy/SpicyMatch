<?php

namespace App\DataFixtures;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AlchemyFlavorsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for($i = 0; $i < 12; $i++){
            $entity = new AlchemyFlavors();
            $entity->setName('alchemy_flavor_'.$i)
                ->setCreatedAt(new \DateTime('now'))
                ->setUpdatedAt(new \DateTime('now'))
                ->addAromaticsCompounds($this->getReference($i, AromaticCompound::class))
            ;
            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            AromaticCompoundFixtures::class,
        );
    }
}
