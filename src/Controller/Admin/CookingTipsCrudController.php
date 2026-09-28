<?php

namespace App\Controller\Admin;

use App\Entity\CookingTips;
use App\Enum\CookingMoment;
use App\Form\Admin\Translation\CookingTipsTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/**
 * @extends AbstractCrudController<CookingTips>
 */
class CookingTipsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CookingTips::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title', 'admin.field.title'),
            ChoiceField::new('moment', 'admin.field.cooking_moment')
                ->setFormType(EnumType::class)
                ->setFormTypeOptions([
                    'class' => CookingMoment::class,
                    'choice_label' => static fn (CookingMoment $m): string => $m->label(),
                    'choice_translation_domain' => 'messages',
                ])
                ->formatValue(static fn (?CookingMoment $m): string => $m instanceof CookingMoment ? $m->label() : ''),
            TextareaField::new('text', 'admin.field.text')->setMaxLength(100),
            TextareaField::new('advantages', 'admin.field.advantages')->setMaxLength(100),
            AssociationField::new('spice', 'admin.field.spice'),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(CookingTipsTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
