<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\SerializesSlugGenerationTrait;
use App\Entity\AromaticGroups;
use App\Form\Admin\Translation\AromaticGroupsTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<AromaticGroups>
 */
class AromaticGroupsCrudController extends AbstractCrudController
{
    use SerializesSlugGenerationTrait;

    public static function getEntityFqcn(): string
    {
        return AromaticGroups::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'admin.field.name'),
            ColorField::new('color', 'admin.field.color'),
            TextareaField::new('description', 'admin.field.description')->setMaxLength(100),
            TextareaField::new('cooking', 'admin.field.cooking_advice'),
            TextareaField::new('informations', 'admin.field.extra_informations')->hideOnIndex(),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(AromaticGroupsTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
