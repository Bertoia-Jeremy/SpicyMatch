<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Users;

class UsersFactory
{
    public function create(): Users
    {
        $user = new Users();
        $user->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        return $user;
    }
}
