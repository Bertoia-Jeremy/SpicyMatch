<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contact;
use App\Entity\Users;

class ContactFactory
{
    public function create(Users $user): Contact
    {
        $contact = new Contact();
        $contact->setUserId($user)
            ->setEmail($user->getEmail())
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
            ->setIsTreated(false)
        ;

        return $contact;
    }
}
