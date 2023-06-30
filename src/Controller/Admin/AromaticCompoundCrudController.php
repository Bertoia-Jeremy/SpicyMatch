<?php

namespace App\Controller\Admin;

use App\Entity\AromaticCompound;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AromaticCompoundCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AromaticCompound::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom'),
            TextareaField::new('description', 'Description'),
            TextareaField::new('cooking', 'Conseil de cuisine'),
            TextareaField::new('informations', 'Informations supplémentaires')->hideOnIndex(),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
            AssociationField::new('alchemyFlavors', 'Alchimie des saveurs')
        ];
    }
}
