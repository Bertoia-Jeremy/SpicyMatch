<?php

declare(strict_types=1);

namespace App\Service\Guest;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class GuestHistoryRegistry
{
    public const int MAX_HISTORIES = 20;

    private const string HISTORIES_KEY = 'guest_histories';

    private const string PENDING_FAVORITES_KEY = 'guest_pending_favorites';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function remember(int $historyId): void
    {
        $session = $this->session();
        if (! $session instanceof SessionInterface) {
            return;
        }

        $ids = array_values(array_diff($this->ids(self::HISTORIES_KEY), [$historyId]));
        $ids[] = $historyId;
        $session->set(self::HISTORIES_KEY, \array_slice($ids, -self::MAX_HISTORIES));
    }

    public function owns(int $historyId): bool
    {
        return \in_array($historyId, $this->ids(self::HISTORIES_KEY), true);
    }

    public function markFavoritePending(int $historyId): void
    {
        $session = $this->session();
        if (! $session instanceof SessionInterface || ! $this->owns($historyId)) {
            return;
        }

        $pending = array_values(array_diff($this->ids(self::PENDING_FAVORITES_KEY), [$historyId]));
        $pending[] = $historyId;
        $session->set(self::PENDING_FAVORITES_KEY, $pending);
    }

    /**
     * @return array{histories: list<int>, favorites: list<int>}
     */
    public function pull(): array
    {
        $session = $this->session();
        if (! $session instanceof SessionInterface) {
            return [
                'histories' => [],
                'favorites' => [],
            ];
        }

        $histories = $this->ids(self::HISTORIES_KEY);
        $favorites = array_values(array_intersect($this->ids(self::PENDING_FAVORITES_KEY), $histories));
        $session->remove(self::HISTORIES_KEY);
        $session->remove(self::PENDING_FAVORITES_KEY);

        return [
            'histories' => $histories,
            'favorites' => $favorites,
        ];
    }

    /**
     * @return list<int>
     */
    private function ids(string $key): array
    {
        $session = $this->session();
        if (! $session instanceof SessionInterface || ! $session->isStarted() && ! $this->requestStack->getCurrentRequest()?->hasPreviousSession()) {
            return [];
        }

        $raw = $session->get($key, []);

        return \is_array($raw) ? array_values(array_filter($raw, \is_int(...))) : [];
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->hasSession() === true ? $request->getSession() : null;
    }
}
