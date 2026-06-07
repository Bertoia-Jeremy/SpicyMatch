<?php

namespace App\Controller\Admin;

use App\Entity\PreparationTips;
use App\Form\Admin\Translation\PreparationTipsTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PreparationTips>
 */
class PreparationTipsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PreparationTips::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title', 'admin.field.title'),
            TextareaField::new('text', 'admin.field.text')->setMaxLength(100),
            TextareaField::new('advantages', 'admin.field.advantages')->setMaxLength(100),
            AssociationField::new('spice', 'admin.field.spice'),
            AssociationField::new('preparationMethod', 'admin.field.preparation_method'),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(PreparationTipsTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
