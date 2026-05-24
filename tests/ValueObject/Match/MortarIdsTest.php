<?php

declare(strict_types=1);

namespace App\Tests\ValueObject\Match;

use App\Exception\Match\InvalidMortarException;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MortarIds::class)]
final class MortarIdsTest extends TestCase
{
    // ── Construction valide ────────────────────────────────────────────────────

    public function testConstructValidIds(): void
    {
        $mortar = new MortarIds([1, 2, 3]);

        self::assertSame([1, 2, 3], $mortar->toArray());
        self::assertSame(3, $mortar->count());
    }

    public function testConstructSingleId(): void
    {
        $mortar = new MortarIds([42]);

        self::assertSame([42], $mortar->toArray());
        self::assertSame(1, $mortar->count());
    }

    public function testConstructTenIds(): void
    {
        $mortar = new MortarIds([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        self::assertSame(10, $mortar->count());
    }

    // ── Normalisation silencieuse ─────────────────────────────────────────────

    public function testDeduplicatesIds(): void
    {
        // [1, 1, 2] → unique → [1, 2], count = 2
        $mortar = new MortarIds([1, 1, 2]);

        self::assertSame([1, 2], $mortar->toArray());
        self::assertSame(2, $mortar->count());
    }

    public function testFiltersZeroIds(): void
    {
        // ID 0 silencieusement écarté, comme dans le parsing HTTP
        $mortar = new MortarIds([0, 1, 2]);

        self::assertSame([1, 2], $mortar->toArray());
    }

    public function testFiltersNegativeIds(): void
    {
        // IDs négatifs silencieusement écartés
        $mortar = new MortarIds([-5, 1, 2]);

        self::assertSame([1, 2], $mortar->toArray());
    }

    public function testDeduplicateAndFilterCombined(): void
    {
        // Mix doublons + invalides → résultat propre
        $mortar = new MortarIds([0, 1, 1, -3, 2]);

        self::assertSame([1, 2], $mortar->toArray());
        self::assertSame(2, $mortar->count());
    }

    // ── Erreurs de validation ─────────────────────────────────────────────────

    public function testEmptyArrayThrows(): void
    {
        $this->expectException(InvalidMortarException::class);

        new MortarIds([]);
    }

    public function testAllZeroIdsThrows(): void
    {
        // Après filtrage → count = 0 < 1
        $this->expectException(InvalidMortarException::class);

        new MortarIds([0, 0]);
    }

    public function testElevenIdsThrows(): void
    {
        $this->expectException(InvalidMortarException::class);

        new MortarIds([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
    }

    public function testElevenUniqueIdsAfterDeduplicationStillThrows(): void
    {
        // 11 IDs distincts → dépasse le max
        $this->expectException(InvalidMortarException::class);

        new MortarIds(range(1, 11));
    }

    public function testElevenRawIdsDeduplicatedToTenIsValid(): void
    {
        // 12 IDs avec 2 doublons → 10 uniques → valide
        $mortar = new MortarIds([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 10, 5]);

        self::assertSame(10, $mortar->count());
    }

    // ── sorted() ─────────────────────────────────────────────────────────────

    public function testSortedReturnsSortedList(): void
    {
        $mortar = new MortarIds([3, 1, 2]);

        self::assertSame([1, 2, 3], $mortar->sorted());
    }

    public function testSortedDoesNotMutateToArray(): void
    {
        $mortar = new MortarIds([3, 1, 2]);

        $mortar->sorted(); // call it once
        self::assertSame([3, 1, 2], $mortar->toArray(), 'sorted() ne doit pas modifier l\'ordre de toArray()');
    }

    public function testSortedIsDeterministicRegardlessOfInputOrder(): void
    {
        $a = new MortarIds([3, 1, 2]);
        $b = new MortarIds([1, 2, 3]);

        // Les deux doivent produire la même clé de cache
        self::assertSame(
            implode(',', $a->sorted()),
            implode(',', $b->sorted()),
            'La clé de cache doit être identique quelle que soit l\'ordre des IDs d\'entrée',
        );
    }

    // ── contains() ───────────────────────────────────────────────────────────

    public function testContainsTrueForExistingId(): void
    {
        $mortar = new MortarIds([1, 2, 3]);

        self::assertTrue($mortar->contains(2));
    }

    public function testContainsFalseForAbsentId(): void
    {
        $mortar = new MortarIds([1, 2, 3]);

        self::assertFalse($mortar->contains(99));
    }

    // ── Immutabilité ─────────────────────────────────────────────────────────

    public function testToArrayReturnsSameValueEachCall(): void
    {
        $mortar = new MortarIds([5, 3, 1]);

        self::assertSame($mortar->toArray(), $mortar->toArray());
    }
}
