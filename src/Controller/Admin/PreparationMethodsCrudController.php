<?php

namespace App\Controller\Admin;

use App\Entity\PreparationMethods;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PreparationMethodsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PreparationMethods::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom'),
            TextareaField::new('description', 'Description')->setMaxLength(100),
            TextareaField::new('tools', 'Outils nécessaires')->setMaxLength(100),
            TextareaField::new('advice', 'Conseil')->setMaxLength(100),
            TextareaField::new('informations', 'Infos supplémentaires')->setMaxLength(100),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
        ];
    }
}
