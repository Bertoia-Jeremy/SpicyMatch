<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Achievement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AchievementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Achievement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Succès')
            ->setEntityLabelInPlural('Succès')
            ->setDefaultSort([
                'rarity' => 'ASC',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();
        yield TextField::new('slug');
        yield TextField::new('name', 'Nom');
        yield TextField::new('icon', 'Icône');
        yield TextField::new('description');
        yield ChoiceField::new('trigger', 'Trigger')
            ->setChoices(array_combine(
                array_map(fn ($t) => $t->value, \App\Enum\AchievementTrigger::cases()),
                \App\Enum\AchievementTrigger::cases()
            ));
        yield IntegerField::new('triggerValue', 'Valeur seuil');
        yield IntegerField::new('xpReward', 'XP Récompense');
        yield ChoiceField::new('rarity', 'Rareté')
            ->setChoices(array_combine(
                array_map(fn ($r) => $r->label(), \App\Enum\AchievementRarity::cases()),
                \App\Enum\AchievementRarity::cases()
            ));
        yield TextField::new('easterEggSlug', 'Easter Egg Slug')
            ->hideOnIndex();
    }
}
