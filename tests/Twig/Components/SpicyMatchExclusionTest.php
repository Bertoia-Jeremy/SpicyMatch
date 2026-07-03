<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\Spices;
use App\Entity\Users;
use PHPUnit\Framework\TestCase;

final class SpicyMatchExclusionTest extends TestCase
{
    public function testExcludedSpiceIdsContractReadsScalarIds(): void
    {
        $spice = new Spices();
        (new \ReflectionProperty(Spices::class, 'id'))->setValue($spice, 42);

        $user = new Users();
        $user->addExcludedSpice($spice);

        $ids = array_values($user->getExcludedSpices()
            ->map(static fn (Spices $s): ?int => $s->getId())
            ->filter(static fn (?int $id): bool => null !== $id)
            ->getValues());

        self::assertSame([42], $ids);
    }
}
