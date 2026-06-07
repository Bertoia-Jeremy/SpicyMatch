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
            ->setEntityLabelInSingular('admin.entity.game_session_singular')
            ->setEntityLabelInPlural('admin.entity.game_session_plural')
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
        yield AssociationField::new('user', 'admin.field.user');
        yield TextField::new('gameMode.value', 'admin.field.game_mode')
            ->formatValue(fn ($value) => $value);
        yield TextField::new('difficulty.value', 'admin.field.difficulty')
            ->formatValue(fn ($value) => $value);
        yield IntegerField::new('correctAnswers', 'admin.field.correct_answers');
        yield IntegerField::new('totalQuestions', 'admin.field.total_questions');
        yield IntegerField::new('score', 'admin.field.xp_gained');
        yield DateTimeField::new('startedAt', 'admin.field.started_short');
        yield DateTimeField::new('finishedAt', 'admin.field.finished_short');
    }
}
