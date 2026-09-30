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

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final readonly class RateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.lc_actions')]
        private RateLimiterFactory $lcActionsLimiter,
        #[Autowire(service: 'limiter.user_actions')]
        private RateLimiterFactory $userActionsLimiter,
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $logger,
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
        if (! $limiterFactory instanceof RateLimiterFactory) {
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

        if (preg_match('#^/users/(gamification/toggle|badge/equip/\d+|difficulty/update)$#', $path) === 1) {
            return $this->userActionsLimiter;
        }

        if (preg_match('#^/(?:[a-z]{2}/)?spicymatch/history/\d+/(rename|favorite)$#', $path) === 1) {
            return $this->userActionsLimiter;
        }

        return null;
    }

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
