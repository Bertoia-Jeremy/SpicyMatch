<?php

declare(strict_types=1);

namespace App\Tests\Gamification;

use App\Enum\ContentKind;
use App\Gamification\ContentReadTicket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ContentReadTicketTest extends TestCase
{
    /**
     * @return iterable<string, array{int, ContentKind, int, int, bool}>
     */
    public static function redemptionProvider(): iterable
    {
        yield 'read long enough' => [5, ContentKind::SPICE, 7, 42, true];
        yield 'left too early' => [4, ContentKind::SPICE, 7, 42, false];
        yield 'ticket expired' => [86401, ContentKind::SPICE, 7, 42, false];
        yield 'other user' => [10, ContentKind::SPICE, 8, 42, false];
        yield 'other content' => [10, ContentKind::SPICE, 7, 43, false];
        yield 'other kind' => [10, ContentKind::COMPOUND, 7, 42, false];
    }

    #[DataProvider('redemptionProvider')]
    public function testTicketIsRedeemableOnlyForItsOwnerAndContentAfterTheMinimumReadTime(
        int $elapsedSeconds,
        ContentKind $kind,
        int $userId,
        int $contentId,
        bool $expected,
    ): void {
        $clock = new MockClock('2026-10-01 12:00:00');
        $ticket = new ContentReadTicket('secret', $clock);
        $value = $ticket->issue(ContentKind::SPICE, 7, 42);

        $clock->sleep($elapsedSeconds);

        self::assertSame($expected, $ticket->isRedeemable($value, $kind, $userId, $contentId));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no signature' => ['1790000000'];
        yield 'non numeric timestamp' => ['abc.def'];
        yield 'forged signature' => ['1790000000.deadbeef'];
    }

    #[DataProvider('malformedProvider')]
    public function testMalformedTicketIsRejected(string $value): void
    {
        $clock = new MockClock('2026-10-01 12:00:00');

        self::assertFalse(new ContentReadTicket('secret', $clock)->isRedeemable($value, ContentKind::SPICE, 7, 42));
    }
}
