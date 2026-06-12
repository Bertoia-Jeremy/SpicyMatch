<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\OdtMatrix;
use PHPUnit\Framework\TestCase;

final class UsersPreferencesTest extends TestCase
{
    public function testDefaultMatrixDefaultsToAir(): void
    {
        self::assertSame(OdtMatrix::AIR, new Users()->getDefaultMatrix());
    }

    public function testSetDefaultMatrix(): void
    {
        $user = new Users();
        $user->setDefaultMatrix(OdtMatrix::OIL);

        self::assertSame(OdtMatrix::OIL, $user->getDefaultMatrix());
    }

    public function testExcludedSpicesStartsEmpty(): void
    {
        self::assertCount(0, new Users()->getExcludedSpices());
    }

    public function testAddAndRemoveExcludedSpiceIsIdempotent(): void
    {
        $user = new Users();
        $spice = new Spices();

        $user->addExcludedSpice($spice);
        $user->addExcludedSpice($spice);
        self::assertCount(1, $user->getExcludedSpices());

        $user->removeExcludedSpice($spice);
        self::assertCount(0, $user->getExcludedSpices());
    }
}
