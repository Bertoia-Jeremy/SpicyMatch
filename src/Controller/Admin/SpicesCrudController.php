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
            TextField::new('name'),
            TextareaField::new('description'),
            TextareaField::new('cooking'),
            TextareaField::new('informations'),
            DateTimeField::new('created_at')->hideOnForm(),
            DateTimeField::new('updated_at')->hideOnForm(),
            TextField::new('imageFile')->setFormType(VichImageType::class)->hideOnIndex(),
            ImageField::new('file')->setBasePath('/uploads/spices')->onlyOnIndex(),
            AssociationField::new('agr_id'),
            AssociationField::new('sty_id')
            //onlyOnIndex pour le voir juste sur le tableau, onlyOnUpdated pour juste au moment de la modif
        ];
    }

}
