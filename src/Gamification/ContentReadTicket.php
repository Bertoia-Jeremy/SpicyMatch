<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Enum\ContentKind;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ContentReadTicket
{
    public const int MIN_READ_SECONDS = 5;

    private const int MAX_AGE_SECONDS = 86400;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private ClockInterface $clock,
    ) {
    }

    public function issue(ContentKind $kind, int $userId, int $contentId): string
    {
        $issuedAt = $this->clock->now()
            ->getTimestamp();

        return $issuedAt . '.' . $this->sign($kind, $userId, $contentId, $issuedAt);
    }

    public function isRedeemable(string $ticket, ContentKind $kind, int $userId, int $contentId): bool
    {
        [$issuedAt, $signature] = explode('.', $ticket, 2) + [
            1 => '',
        ];
        if (! ctype_digit($issuedAt) || ! hash_equals($this->sign($kind, $userId, $contentId, (int) $issuedAt), $signature)) {
            return false;
        }

        $elapsed = $this->clock->now()
            ->getTimestamp() - (int) $issuedAt;

        return $elapsed >= self::MIN_READ_SECONDS && $elapsed <= self::MAX_AGE_SECONDS;
    }

    private function sign(ContentKind $kind, int $userId, int $contentId, int $issuedAt): string
    {
        return hash_hmac('sha256', sprintf('content_read|%s|%d|%d|%d', $kind->value, $userId, $contentId, $issuedAt), $this->secret);
    }
}
