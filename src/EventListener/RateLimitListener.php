<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Users;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Applies per-user rate limiting to Live Component actions and user-mutation routes.
 *
 * Listens on kernel.request (priority 10) — BEFORE the firewall, so authenticated
 * users are keyed by their own token-storage user; anonymous hits use the IP.
 *
 * Scope:
 *   - POST /_components/... → lc_actions (60/min)
 *   - POST /users/gamification/toggle → user_actions (30/min)
 *   - POST /users/badge/equip/... → user_actions (30/min)
 *   - POST /spicymatch/history/.../rename, /.../favorite/toggle → user_actions (30/min)
 *
 * Out of scope:
 *   - GET routes (no mutation)
 *   - /api/gamification/egg/... → already limited inline in EasterEggController
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class RateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.lc_actions')]
        private readonly RateLimiterFactory $lcActionsLimiter,
        #[Autowire(service: 'limiter.user_actions')]
        private readonly RateLimiterFactory $userActionsLimiter,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->getMethod() !== 'POST') {
            return;
        }

        $path = $request->getPathInfo();
        $limiterFactory = $this->pickLimiter($path);
        if ($limiterFactory === null) {
            return;
        }

        $key = $this->limiterKey($request->getClientIp() ?? 'unknown');
        $limiter = $limiterFactory->create($key);
        $limit = $limiter->consume();

        if ($limit->isAccepted()) {
            return;
        }

        $this->logger->warning('rate_limit.exceeded', [
            'path' => $path,
            'key' => $key,
            'remaining' => $limit->getRemainingTokens(),
            'retry_after' => $limit->getRetryAfter()
                ->getTimestamp() - time(),
        ]);

        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
        $event->setResponse(new JsonResponse(
            [
                'error' => 'Too many requests',
                'retry_after' => $retryAfter,
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
            [
                'Retry-After' => (string) $retryAfter,
            ],
        ));
    }

    private function pickLimiter(string $path): ?RateLimiterFactory
    {
        if (str_starts_with($path, '/_components/')) {
            return $this->lcActionsLimiter;
        }

        // User mutation routes — POST-only, matched by suffix patterns.
        if (preg_match('#^/users/(gamification/toggle|badge/equip/\d+|difficulty/update)$#', $path) === 1) {
            return $this->userActionsLimiter;
        }

        if (preg_match('#^/spicymatch/history/\d+/(rename|favorite/toggle)$#', $path) === 1) {
            return $this->userActionsLimiter;
        }

        return null;
    }

    /**
     * Prefer user id as the rate-limit key; fall back to client IP for anonymous hits.
     */
    private function limiterKey(string $clientIp): string
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if ($user instanceof Users && $user->getId() !== null) {
            return 'user:' . $user->getId();
        }

        return 'ip:' . $clientIp;
    }
}
