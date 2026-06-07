<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SpicyType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<SpicyType>
 */
class SpicyTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SpicyType::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'admin.field.name'),
            TextareaField::new('description', 'admin.field.description')->setMaxLength(100),
            TextareaField::new('cooking', 'admin.field.cooking_advice'),
            TextareaField::new('informations', 'admin.field.extra_informations')->hideOnIndex(),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
        ];
    }
}
