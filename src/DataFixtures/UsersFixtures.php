<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UsersFixtures extends Fixture
{
    const ROLES = ["ROLE_ADMIN", "ROLE_USER"];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    )
    {
        
    }
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < 12; ++$i) {
            $user = new Users();
            $user->setUsername($faker->userName())
                ->setPassword($this->hasher->hashPassword($user, "password"))
                ->setIsVerified((bool) rand(0,1))
                ->setEmail($faker->mail())
                ->setRoles(self::ROLES[rand(0, 1)])
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime())
            ;
            $manager->persist($user);
            $this->addReference($user, 'User');
        }

        $manager->flush();
    }
}
