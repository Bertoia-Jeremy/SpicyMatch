<?php

namespace App\Controller\Admin;

use App\Entity\CookingTips;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CookingTipsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CookingTips::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title', 'Titre'),
            ChoiceField::new('cookingStep', 'Etape de cuisson')->setChoices([
                'Avant' => 'Avant', 
                'Début' => 'Début',
                'Milieu' => 'Milieu', 
                'Fin' => 'Fin', 
                'Après' => 'Après'
            ]),
            TextareaField::new('text', 'Texte')->setMaxLength(100),
            AssociationField::new('spice', 'Epice'),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
        ];
    }
}
