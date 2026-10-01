<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Gamification\GamificationManagerInterface;
use App\Message\ContentReadEvent;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ContentReadGamificationHandler
{
    public function __construct(
        private UsersRepository $usersRepository,
        private GamificationManagerInterface $manager,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ContentReadEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
        if ($user === null) {
            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);
        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($event, $user, $progression): void {
            $this->manager->lockForUpdate($progression);

            $stats = $this->manager->getOrCreateStats($user);
            if ($stats->getId() !== null) {
                $this->em->refresh($stats);
            }

            if ($stats->hasReadContentKind($event->kind)) {
                return;
            }

            $stats->recordReadContentKind($event->kind);
            $this->manager->process($progression, 'content_read', [
                'kind' => $event->kind->value,
            ]);

            $this->em->flush();
        });
    }
}
