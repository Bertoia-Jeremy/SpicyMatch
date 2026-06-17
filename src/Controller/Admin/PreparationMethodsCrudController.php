<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\SerializesSlugGenerationTrait;
use App\Entity\PreparationMethods;
use App\Form\Admin\Translation\PreparationMethodsTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PreparationMethods>
 */
class PreparationMethodsCrudController extends AbstractCrudController
{
    use SerializesSlugGenerationTrait;

    public static function getEntityFqcn(): string
    {
        return PreparationMethods::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'admin.field.name'),
            TextareaField::new('description', 'admin.field.description')->setMaxLength(100),
            TextareaField::new('tools', 'admin.field.tools_needed')->setMaxLength(100),
            TextareaField::new('advice', 'admin.field.advice_single')->setMaxLength(100),
            TextareaField::new('informations', 'admin.field.extra_short')->setMaxLength(100),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(PreparationMethodsTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
