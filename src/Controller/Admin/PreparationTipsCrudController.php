<?php

namespace App\Controller\Admin;

use App\Entity\PreparationTips;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PreparationTipsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PreparationTips::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title', 'Titre'),
            TextareaField::new('text', 'Texte')->setMaxLength(100),
            AssociationField::new('spice', 'Epice'),
            AssociationField::new('preparationMethod', 'Méthode de préparation'),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
        ];
    }
}
