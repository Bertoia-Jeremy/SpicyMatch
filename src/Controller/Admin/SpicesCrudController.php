<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Spices;
use App\Form\Admin\Translation\SpiceTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * @extends AbstractCrudController<Spices>
 */
class SpicesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Spices::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'admin.field.name'),
            TextareaField::new('description', 'admin.field.description')->setMaxLength(100),
            TextareaField::new('cooking', 'admin.field.cooking_advice')->hideOnIndex(),
            TextareaField::new('benefits', 'admin.field.benefits')->hideOnIndex(),
            TextareaField::new('informations', 'admin.field.extra_informations')->hideOnIndex(),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            TextField::new('imageFile', 'admin.field.image')->setFormType(VichImageType::class)->hideOnIndex(),
            ImageField::new('file', 'admin.field.image')->setBasePath('/uploads/spices')->onlyOnIndex()->setRequired(
                true
            ),
            AssociationField::new('aromaticGroups', 'admin.field.aromatic_group'),
            AssociationField::new('spicyType', 'admin.field.spicy_type'),
            //  AssociationField::new('aco_ids', 'Composants aromatiques'),
            AssociationField::new('aromaticsCompounds', 'admin.field.main_compounds'),
            AssociationField::new('secondary_aromatics_compounds', 'admin.field.secondary_compounds'),
            // onlyOnIndex pour le voir juste sur le tableau, onlyOnUpdated pour juste au moment de la modif
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(SpiceTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
