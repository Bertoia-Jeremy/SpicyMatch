<?php

namespace App\Controller\Admin;

use App\Entity\AlchemyFlavors;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AlchemyFlavorsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AlchemyFlavors::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name'),
            TextareaField::new('description'),
            TextareaField::new('cooking'),
            TextareaField::new('informations'),
            DateTimeField::new('created_at')->hideOnForm(),
            DateTimeField::new('updated_at')->hideOnForm(),
        ];
    }

   /* public function createEntity(string $entityFqcn)
    {
        $entity = new AlchemyFlavors();
        $entity->setCreatedAt(new \DateTime('now'));
        $entity->setUpdatedAt(new \DateTime('now'));

        return $entity;
    }
*/
}
