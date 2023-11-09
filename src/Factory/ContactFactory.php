<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\Contact;

class ContactFactory {
    
    public function create(): Contact{
        $contact = new Contact();
        $contact->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
            ->setIsTreated(false)
        ;

        return $contact;
    }
}