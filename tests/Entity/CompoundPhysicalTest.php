<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\AromaKinetics;
use PHPUnit\Framework\TestCase;

final class CompoundPhysicalTest extends TestCase
{
    private CompoundPhysical $physical;

    protected function setUp(): void
    {
        $this->physical = new CompoundPhysical((new AromaticCompound())->setName('Eugenol'));
    }

    // ── octanolWaterPartition (K_ow = 10^logP) ─────────────────────────────────

    public function testOctanolWaterPartitionReturnsNullWhenLogPNull(): void
    {
        self::assertNull($this->physical->octanolWaterPartition());
    }

    public function testOctanolWaterPartitionForEugenol(): void
    {
        $this->physical->setLogP(2.27);

        self::assertEqualsWithDelta(186.21, $this->physical->octanolWaterPartition(), 0.1);
    }

    public function testOctanolWaterPartitionForLimonene(): void
    {
        $this->physical->setLogP(4.57);

        self::assertEqualsWithDelta(37154.0, $this->physical->octanolWaterPartition(), 5.0);
    }

    public function testOctanolWaterPartitionForLogPZero(): void
    {
        $this->physical->setLogP(0.0);

        self::assertSame(1.0, $this->physical->octanolWaterPartition());
    }

    // ── aromaKinetics : délégation vers AromaKinetics::fromBoilingPoint ────────

    public function testAromaKineticsReturnsNullWhenBoilingPointMissing(): void
    {
        self::assertNull($this->physical->aromaKinetics());
    }

    public function testAromaKineticsDelegatesToEnum(): void
    {
        $this->physical->setBoilingPointCelsius(254);

        self::assertSame(AromaKinetics::BASE, $this->physical->aromaKinetics());
    }
}
