<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test users:
 *   - admin     / admin@spicymatch.local   password: Admin1234!   ROLE_ADMIN + ROLE_USER
 *   - alice     / alice@spicymatch.local   password: Alice1234!   ROLE_USER
 *   - bob       / bob@spicymatch.local     password: Bob1234!     ROLE_USER
 */
class UsersFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('now');

        $usersData = [
            'admin' => [
                'username' => 'admin',
                'mail' => 'admin@spicymatch.local',
                'password' => 'Admin1234!',
                'roles' => ['ROLE_ADMIN'],
            ],
            'alice' => [
                'username' => 'alice',
                'mail' => 'alice@spicymatch.local',
                'password' => 'Alice1234!',
                'roles' => [],
            ],
            'bob' => [
                'username' => 'bob',
                'mail' => 'bob@spicymatch.local',
                'password' => 'Bob1234!',
                'roles' => [],
            ],
        ];

        foreach ($usersData as $key => $data) {
            $user = new Users();
            $user->setUsername($data['username'])
                ->setMail($data['mail'])
                ->setRoles($data['roles'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now)
                ->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

            $this->addReference('user_' . $key, $user);
            $manager->persist($user);
        }

        $manager->flush();
    }
}
