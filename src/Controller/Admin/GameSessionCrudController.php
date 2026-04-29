<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GameSession;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<GameSession>
 */
class GameSessionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GameSession::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Session de jeu')
            ->setEntityLabelInPlural('Sessions de jeu')
            ->setDefaultSort([
                'startedAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield AssociationField::new('user', 'Utilisateur');
        yield TextField::new('gameMode.value', 'Mode')
            ->formatValue(fn ($value) => $value);
        yield TextField::new('difficulty.value', 'Difficulté')
            ->formatValue(fn ($value) => $value);
        yield IntegerField::new('correctAnswers', 'Bonnes réponses');
        yield IntegerField::new('totalQuestions', 'Total questions');
        yield IntegerField::new('score', 'XP gagné');
        yield DateTimeField::new('startedAt', 'Début');
        yield DateTimeField::new('finishedAt', 'Fin');
    }
}
