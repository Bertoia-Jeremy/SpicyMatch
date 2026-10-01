<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Users;
use App\Message\FavoriteToggledEvent;
use App\Message\MatchSavedEvent;
use App\Repository\SpicyMatchHistoryRepository;
use App\Service\Guest\GuestHistoryRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class GuestHistoryClaimSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private GuestHistoryRegistry $registry,
        private SpicyMatchHistoryRepository $historyRepository,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (! $user instanceof Users || $user->getId() === null) {
            return;
        }

        ['histories' => $ids, 'favorites' => $favorites] = $this->registry->pull();
        $histories = $this->historyRepository->findGuestHistories($ids);
        if ($histories === []) {
            return;
        }

        $now = $this->clock->now();
        $sealed = [];
        $favorited = false;
        foreach ($histories as $history) {
            $history->getSpicyMatch()?->setUser($user);
            if (\in_array($history->getId(), $favorites, true) && ! $history->isFavorite()) {
                $history->setFavorite(true);
                $history->setUpdatedAt($now);
                $favorited = true;
            }
            if ($history->getSealedAt() !== null) {
                $sealed[] = (int) $history->getId();
            }
        }
        $this->em->flush();

        foreach ($sealed as $historyId) {
            $this->bus->dispatch(new MatchSavedEvent($historyId, $user->getId()));
        }
        if ($favorited) {
            $this->bus->dispatch(new FavoriteToggledEvent($user->getId()));
        }
    }
}
