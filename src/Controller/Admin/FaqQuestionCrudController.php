<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FaqQuestion;
use App\Form\Admin\Translation\FaqQuestionTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<FaqQuestion>
 */
class FaqQuestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FaqQuestion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.entity.faq_question_singular')
            ->setEntityLabelInPlural('admin.entity.faq_question_plural')
            ->setSearchFields(['question', 'answer'])
            ->setDefaultSort([
                'position' => 'ASC',
                'id' => 'ASC',
            ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('published');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('category', 'admin.field.faq_category'),
            TextField::new('question', 'admin.field.question'),
            TextareaField::new('answer', 'admin.field.answer')
                ->setNumOfRows(8)
                ->setHelp('admin.help.faq_answer')
                ->hideOnIndex(),
            IntegerField::new('position', 'admin.field.position')
                ->setHelp('admin.help.position'),
            BooleanField::new('published', 'admin.field.published'),
            AssociationField::new('spices', 'admin.field.spices')
                ->autocomplete()
                ->setRequired(false)
                ->setHelp('admin.help.faq_spices')
                ->onlyOnForms(),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(FaqQuestionTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
