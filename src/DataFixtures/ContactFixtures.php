<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Contact;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ContactFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < 12; ++$i) {
            $contact = new Contact();

            $contact->setUserId($this->getReference("User_".rand(0, 11)))
                ->setEmail($faker->email())
                ->setSubject($faker->title())
                ->setMessage($faker->text(300))
                ->setIsTreated((bool) rand(0,1))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime());
                
            $manager->persist($contact);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UsersFixtures::class,
        ];
    }
}
