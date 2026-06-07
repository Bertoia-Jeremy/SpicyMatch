<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Achievement;
use App\Form\Admin\Translation\AchievementTranslationType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Achievement>
 */
class AchievementCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Achievement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.entity.achievement_singular')
            ->setEntityLabelInPlural('admin.entity.achievement_plural')
            ->setDefaultSort([
                'rarity' => 'ASC',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();
        yield TextField::new('slug');
        yield TextField::new('name', 'admin.field.name');
        yield TextField::new('icon', 'admin.field.icon');
        yield TextField::new('description', 'admin.field.description');
        yield ChoiceField::new('trigger', 'admin.field.trigger')
            ->setChoices(array_combine(
                array_map(fn ($t) => $t->value, \App\Enum\AchievementTrigger::cases()),
                \App\Enum\AchievementTrigger::cases()
            ));
        yield IntegerField::new('triggerValue', 'admin.field.trigger_value');
        yield IntegerField::new('xpReward', 'admin.field.xp_reward');
        yield ChoiceField::new('rarity', 'admin.field.rarity')
            ->setChoices(array_combine(
                array_map(fn ($r) => $this->translator->trans($r->label()), \App\Enum\AchievementRarity::cases()),
                \App\Enum\AchievementRarity::cases()
            ));
        yield TextField::new('easterEggSlug', 'admin.field.easter_egg_slug')
            ->hideOnIndex();
        yield CollectionField::new('translations', 'admin.field.translations')
            ->setEntryType(AchievementTranslationType::class)
            ->setEntryIsComplex()
            ->onlyOnForms();
    }
}
