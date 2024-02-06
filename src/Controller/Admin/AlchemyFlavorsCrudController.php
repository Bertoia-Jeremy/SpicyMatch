<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AlchemyFlavors;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
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
            TextField::new('name', 'Nom'),
            TextareaField::new('description', 'Description')->setMaxLength(100),
            TextareaField::new('cooking', 'Conseil de cuisine')->hideOnIndex(),
            TextareaField::new('informations', 'Informations supplémentaires')->hideOnIndex(),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
            AssociationField::new('aromaticsCompounds', 'Composants aromatiques'),
        ];
    }
}
