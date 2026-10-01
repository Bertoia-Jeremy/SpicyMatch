<?php

declare(strict_types=1);

namespace App\Tests\Service\Guest;

use App\Service\Guest\GuestHistoryRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class GuestHistoryRegistryTest extends TestCase
{
    private GuestHistoryRegistry $registry;

    protected function setUp(): void
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->registry = new GuestHistoryRegistry(new RequestStack([$request]));
    }

    public function testOwnsOnlyRememberedHistories(): void
    {
        $this->registry->remember(7);

        self::assertTrue($this->registry->owns(7));
        self::assertFalse($this->registry->owns(8));
    }

    public function testKeepsOnlyTheMostRecentHistories(): void
    {
        foreach (range(1, GuestHistoryRegistry::MAX_HISTORIES + 1) as $id) {
            $this->registry->remember($id);
        }

        self::assertFalse($this->registry->owns(1));
        self::assertTrue($this->registry->owns(GuestHistoryRegistry::MAX_HISTORIES + 1));
    }

    public function testPendingFavoriteRequiresOwnership(): void
    {
        $this->registry->remember(7);
        $this->registry->markFavoritePending(7);
        $this->registry->markFavoritePending(8);

        self::assertSame([
            'histories' => [7],
            'favorites' => [7],
        ], $this->registry->pull());
    }

    public function testPullEmptiesTheRegistry(): void
    {
        $this->registry->remember(7);
        $this->registry->pull();

        self::assertFalse($this->registry->owns(7));
        self::assertSame([
            'histories' => [],
            'favorites' => [],
        ], $this->registry->pull());
    }

    public function testRequestWithoutSessionOwnsNothing(): void
    {
        $registry = new GuestHistoryRegistry(new RequestStack([Request::create('/')]));
        $registry->remember(7);

        self::assertFalse($registry->owns(7));
    }
}
