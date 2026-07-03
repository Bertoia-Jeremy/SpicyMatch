<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GdprRequest;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<GdprRequest>
 */
class GdprRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GdprRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.entity.gdpr_request_singular')
            ->setEntityLabelInPlural('admin.entity.gdpr_request_plural')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'admin.field.email');
        yield TextField::new('requestType.value', 'admin.field.gdpr_request_type')
            ->formatValue(fn ($value) => $value)
            ->hideOnForm();
        yield TextareaField::new('message', 'admin.field.message')
            ->hideOnIndex()
            ->setFormTypeOption('disabled', true);
        yield DateTimeField::new('createdAt', 'admin.field.created_at')
            ->hideOnForm();
        yield DateTimeField::new('treatedAt', 'admin.field.treated_at')
            ->setFormTypeOption('required', false);
    }
}
