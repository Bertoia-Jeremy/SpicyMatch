<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Form\Admin\Translation\SpiceDuoTranslationType;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<SpiceDuo>
 */
class SpiceDuoCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SpiceDuo::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort([
            'id' => 'DESC',
        ]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->addSelect('pt', 'ct', 'sp')
            ->leftJoin('entity.preparationTip', 'pt')
            ->leftJoin('entity.cookingTip', 'ct')
            ->leftJoin('pt.spice', 'sp');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('label', 'admin.menu.spice_duos')->onlyOnIndex(),
            AssociationField::new('preparationTip', 'admin.field.preparation_tip')
                ->setFormTypeOption('choice_label', static fn (PreparationTips $t): string => \sprintf('%s — %s', $t->getSpice()?->getName(), $t->getTitle()))
                ->hideOnIndex(),
            AssociationField::new('cookingTip', 'admin.field.cooking_tip')
                ->setFormTypeOption('choice_label', fn (CookingTips $t): string => \sprintf('%s — %s', $t->getSpice()?->getName(), $t->getMoment() === null ? '?' : $this->translator->trans($t->getMoment()->label())))
                ->hideOnIndex(),
            IntegerField::new('rank', 'admin.field.rank'),
            TextField::new('title', 'admin.field.hook'),
            TextareaField::new('effect', 'admin.field.effect')->hideOnIndex(),
            TextareaField::new('science', 'admin.field.science')->hideOnIndex(),
            TextareaField::new('example', 'admin.field.example')->hideOnIndex(),
            DateTimeField::new('created_at', 'admin.field.created_at')->hideOnForm(),
            DateTimeField::new('updated_at', 'admin.field.updated_at')->hideOnForm(),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(SpiceDuoTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }
}
