<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\RecomputeOavTableMessage;
use App\MessageHandler\RecomputeOavTableHandler;
use App\Service\Match\MortarProfileBuilder;
use App\Tests\Support\IntegrationTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class RecomputeOavTableHandlerTest extends IntegrationTestCase
{
    private const REBUILD_LOCK = 'spicymatch_oav_rebuild';

    private const SENTINEL_ID = 999999;

    private const MAX_REBUILD_ATTEMPTS = 3;

    private Connection $connection;

    /**
     * @var list<array{object, list<object>}>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->em->getConnection();
        $this->dispatched = [];
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_tmp');
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_old');
        $this->connection->executeStatement('DELETE FROM spice_active_compound WHERE spice_id = ?', [self::SENTINEL_ID]);
        $this->connection->executeStatement('SELECT RELEASE_LOCK(?)', [self::REBUILD_LOCK]);

        parent::tearDown();
    }

    public function testRebuildRepopulatesShadowTableAndReleasesLock(): void
    {
        $expected = $this->countOavActiveSourceRows();
        self::assertGreaterThan(0, $expected, 'Base de test non seedée : concentrations + ODT requis');

        $handler = $this->createHandler($this->createBusRejectingDispatch());

        self::assertTrue($handler(new RecomputeOavTableMessage('test-nominal')));
        self::assertSame($expected, $this->countShadowRows());
        self::assertFalse($this->tableExists('spice_active_compound_tmp'));
        self::assertFalse($this->tableExists('spice_active_compound_old'));
        self::assertTrue($this->rebuildLockIsFree());
    }

    public function testRebuildIsSkippedWithoutExceptionWhenLockIsHeldElsewhere(): void
    {
        $this->insertSentinelRow();
        $holder = $this->openSecondConnection();
        $handler = $this->createHandler($this->createBusRecordingSingleDispatch());

        try {
            self::assertSame('1', (string) $holder->fetchOne('SELECT GET_LOCK(?, ?)', [self::REBUILD_LOCK, 0]));

            self::assertFalse($handler(new RecomputeOavTableMessage('test-lock-held')));

            self::assertTrue($this->sentinelRowSurvived(), 'La table a été reconstruite malgré le verrou tenu');
            self::assertFalse($this->tableExists('spice_active_compound_tmp'));
            self::assertFalse($this->tableExists('spice_active_compound_old'));
            self::assertFalse($this->rebuildLockIsFree(), 'Le handler a relâché un verrou qu\'il ne détenait pas');
        } finally {
            $holder->executeStatement('SELECT RELEASE_LOCK(?)', [self::REBUILD_LOCK]);
            $holder->close();
        }
    }

    public function testAbandonedRebuildIsRescheduledWithADelayedRetry(): void
    {
        $holder = $this->openSecondConnection();
        $handler = $this->createHandler($this->createBusRecordingSingleDispatch());

        try {
            self::assertSame('1', (string) $holder->fetchOne('SELECT GET_LOCK(?, ?)', [self::REBUILD_LOCK, 0]));

            self::assertFalse($handler(new RecomputeOavTableMessage('test-retry')));
        } finally {
            $holder->executeStatement('SELECT RELEASE_LOCK(?)', [self::REBUILD_LOCK]);
            $holder->close();
        }

        self::assertCount(1, $this->dispatched);

        [$retried, $stamps] = $this->dispatched[0];
        self::assertInstanceOf(RecomputeOavTableMessage::class, $retried);
        self::assertSame('test-retry', $retried->reason);
        self::assertSame(2, $retried->attempt);

        $delays = array_filter($stamps, static fn (object $stamp): bool => $stamp instanceof DelayStamp);
        self::assertCount(1, $delays);
        self::assertGreaterThan(0, array_values($delays)[0]->getDelay());
    }

    public function testAbandonedRebuildIsNotRescheduledOnceAttemptsAreExhausted(): void
    {
        $holder = $this->openSecondConnection();
        $handler = $this->createHandler($this->createBusRejectingDispatch());
        $lastAttempt = new RecomputeOavTableMessage('test-exhausted', self::MAX_REBUILD_ATTEMPTS);

        try {
            self::assertSame('1', (string) $holder->fetchOne('SELECT GET_LOCK(?, ?)', [self::REBUILD_LOCK, 0]));

            self::assertFalse($handler($lastAttempt));
        } finally {
            $holder->executeStatement('SELECT RELEASE_LOCK(?)', [self::REBUILD_LOCK]);
            $holder->close();
        }

        self::assertSame([], $this->dispatched);
    }

    public function testRebuildLockIsReleasedWhenRebuildThrows(): void
    {
        $failure = new \RuntimeException('invalidation du cache mortier en échec');
        $mortarProfileBuilder = $this->createMock(MortarProfileBuilder::class);
        $mortarProfileBuilder->expects(self::once())->method('invalidateAll')->willThrowException($failure);

        $handler = $this->createHandler($this->createBusRejectingDispatch(), $mortarProfileBuilder);

        $caught = null;

        try {
            $handler(new RecomputeOavTableMessage('test-rebuild-throws'));
        } catch (\Throwable $thrown) {
            $caught = $thrown;
        }

        self::assertSame($failure, $caught);
        self::assertTrue(
            $this->rebuildLockIsFree(),
            'Verrou fuité après échec : tous les rebuilds suivants seraient abandonnés'
        );
    }

    private function createHandler(
        MessageBusInterface $messageBus,
        ?MortarProfileBuilder $mortarProfileBuilder = null,
    ): RecomputeOavTableHandler {
        return new RecomputeOavTableHandler(
            $this->connection,
            $mortarProfileBuilder ?? $this->createStub(MortarProfileBuilder::class),
            new NullLogger(),
            $messageBus,
            new NullLogger(),
        );
    }

    private function createBusRejectingDispatch(): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        return $bus;
    }

    private function createBusRecordingSingleDispatch(): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message, array $stamps): Envelope {
                $this->dispatched[] = [$message, array_values($stamps)];

                return new Envelope($message, $stamps);
            });

        return $bus;
    }

    private function countShadowRows(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM spice_active_compound');
    }

    private function countOavActiveSourceRows(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM spice_compound_concentration scc
             JOIN compound_odt odt ON odt.aromatic_compound_id = scc.aromatic_compound_id
             WHERE odt.odt_ppm > 0
               AND scc.concentration_ppm / odt.odt_ppm > 1'
        );
    }

    private function insertSentinelRow(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO spice_active_compound (spice_id, aromatic_compound_id, matrix, oav_value)
             VALUES (?, ?, ?, ?)',
            [self::SENTINEL_ID, self::SENTINEL_ID, 'air', 42.0]
        );
    }

    private function sentinelRowSurvived(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM spice_active_compound WHERE spice_id = ?',
            [self::SENTINEL_ID]
        );
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }

    private function rebuildLockIsFree(): bool
    {
        return (string) $this->connection->fetchOne('SELECT IS_FREE_LOCK(?)', [self::REBUILD_LOCK]) === '1';
    }

    private function openSecondConnection(): Connection
    {
        return DriverManager::getConnection($this->connection->getParams());
    }
}
