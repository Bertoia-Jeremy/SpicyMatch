<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FaqCategory;
use App\Form\Admin\Translation\FaqCategoryTranslationType;
use App\Repository\FaqQuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<FaqCategory>
 */
class FaqCategoryCrudController extends AbstractCrudController
{
    /**
     * @var array<int, true>|null
     */
    private ?array $categoryIdsInUse = null;

    public function __construct(
        private readonly FaqQuestionRepository $faqQuestionRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FaqCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.entity.faq_category_singular')
            ->setEntityLabelInPlural('admin.entity.faq_category_plural')
            ->setDefaultSort([
                'position' => 'ASC',
                'id' => 'ASC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $deletable = fn (FaqCategory $category): bool => ! $this->isInUse($category);

        return $actions
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action): Action => $action->displayIf($deletable))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $action): Action => $action->displayIf($deletable));
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($this->faqQuestionRepository->count([
            'category' => $entityInstance,
        ]) > 0) {
            $this->addFlash('danger', 'admin.flash.faq_category_in_use');

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'admin.field.name'),
            TextField::new('code', 'admin.field.code')
                ->setHelp('admin.help.faq_category_code'),
            IntegerField::new('position', 'admin.field.position')
                ->setHelp('admin.help.position'),
            CollectionField::new('translations', 'admin.field.translations')
                ->setEntryType(FaqCategoryTranslationType::class)
                ->setEntryIsComplex()
                ->onlyOnForms(),
        ];
    }

    private function isInUse(FaqCategory $category): bool
    {
        $this->categoryIdsInUse ??= array_fill_keys($this->faqQuestionRepository->findCategoryIdsInUse(), true);

        return isset($this->categoryIdsInUse[$category->getId()]);
    }
}
