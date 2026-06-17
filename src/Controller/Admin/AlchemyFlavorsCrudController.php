<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\SerializesSlugGenerationTrait;
use App\Entity\AlchemyFlavors;
use App\Form\Admin\Translation\AlchemyFlavorsTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<AlchemyFlavors>
 */
class AlchemyFlavorsCrudController extends AbstractCrudController
{
    use SerializesSlugGenerationTrait;

    public static function getEntityFqcn(): string
    {
        return AlchemyFlavors::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'admin.field.name'),
            TextareaField::new('description', 'admin.field.description')->setMaxLength(100),
            TextareaField::new('cooking', 'admin.field.cooking_advice')->hideOnIndex(),
            TextareaField::new('informations', 'admin.field.extra_informations')->hideOnIndex(),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            AssociationField::new('aromaticsCompounds', 'admin.field.aromatic_compounds'),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(AlchemyFlavorsTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
