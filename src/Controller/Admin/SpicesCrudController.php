<?php

namespace App\Controller\Admin;

use App\Entity\Spices;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class SpicesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Spices::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom')
            TextareaField::new('description'),
            ,
            TextareaField::new('description', 'Description'),
            TextareaField::new('cooking', 'Conseil de cuisine'),
            TextareaField::new('informations', 'Informations supplémentaires'),
            DateTimeField::new('created_at', 'Créé le')->hideOnForm(),
            DateTimeField::new('updated_at', 'Modifié le')->hideOnForm(),
            TextField::new('imageFile', 'Image')->setFormType(VichImageType::class)->hideOnIndex(),
            ImageField::new('file', 'Image')->setBasePath('/uploads/spices')->onlyOnIndex(),
            AssociationField::new('agr_id', 'Groupe aromatique'),
            AssociationField::new('sty_id', 'Type d\'épice')
            //onlyOnIndex pour le voir juste sur le tableau, onlyOnUpdated pour juste au moment de la modif
        ];
    }

}
