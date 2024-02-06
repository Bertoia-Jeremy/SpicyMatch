<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AromaticGroups;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AromaticGroupsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AromaticGroups::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom'),
            ColorField::new('color', 'Couleur'),
            TextareaField::new('description', 'Description')->setMaxLength(100),
            TextareaField::new('cooking', 'Conseil de cuisine'),
            TextareaField::new('informations', 'Informations supplémentaires')->hideOnIndex(),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
        ];
    }
}
