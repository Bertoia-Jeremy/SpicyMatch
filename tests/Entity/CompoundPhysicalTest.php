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

    private AromaticCompound $compound;

    protected function setUp(): void
    {
        $this->compound = (new AromaticCompound())->setName('Eugenol');
        $this->physical = new CompoundPhysical($this->compound);
    }

    // ── Construction & relation ───────────────────────────────────────────────

    public function testConstructorBindsCompound(): void
    {
        self::assertSame($this->compound, $this->physical->getCompound());
    }

    public function testConstructorSetsCreatedAtAndUpdatedAt(): void
    {
        self::assertEqualsWithDelta(time(), $this->physical->getCreatedAt()->getTimestamp(), 2);
        self::assertEqualsWithDelta(time(), $this->physical->getUpdatedAt()->getTimestamp(), 2);
    }

    public function testIdIsNullBeforePersist(): void
    {
        self::assertNull($this->physical->getId());
    }

    // ── logP ───────────────────────────────────────────────────────────────────

    public function testLogPDefaultsToNull(): void
    {
        self::assertNull($this->physical->getLogP());
    }

    public function testSetLogPIsFluent(): void
    {
        $result = $this->physical->setLogP(2.27);

        self::assertSame($this->physical, $result);
        self::assertSame(2.27, $this->physical->getLogP());
    }

    public function testSetLogPUpdatesUpdatedAt(): void
    {
        $before = $this->physical->getUpdatedAt();
        usleep(10_000); // 10 ms — assure que la valeur change
        $this->physical->setLogP(4.57);

        self::assertGreaterThan($before, $this->physical->getUpdatedAt());
    }

    public function testSetLogPAcceptsNull(): void
    {
        $this->physical->setLogP(3.5);
        $this->physical->setLogP(null);

        self::assertNull($this->physical->getLogP());
    }

    // ── boilingPointCelsius ───────────────────────────────────────────────────

    public function testBoilingPointDefaultsToNull(): void
    {
        self::assertNull($this->physical->getBoilingPointCelsius());
    }

    public function testSetBoilingPointIsFluent(): void
    {
        $result = $this->physical->setBoilingPointCelsius(254);

        self::assertSame($this->physical, $result);
        self::assertSame(254, $this->physical->getBoilingPointCelsius());
    }

    // ── vaporPressurePa ───────────────────────────────────────────────────────

    public function testVaporPressureDefaultsToNull(): void
    {
        self::assertNull($this->physical->getVaporPressurePa());
    }

    public function testSetVaporPressureIsFluent(): void
    {
        $result = $this->physical->setVaporPressurePa(2.93);

        self::assertSame($this->physical, $result);
        self::assertSame(2.93, $this->physical->getVaporPressurePa());
    }

    // ── octanolWaterPartition (K_ow = 10^logP) ─────────────────────────────────

    public function testOctanolWaterPartitionReturnsNullWhenLogPNull(): void
    {
        self::assertNull($this->physical->octanolWaterPartition());
    }

    public function testOctanolWaterPartitionForEugenol(): void
    {
        // Eugenol logP = 2.27 → K_ow = 10^2.27 ≈ 186.21
        $this->physical->setLogP(2.27);

        self::assertEqualsWithDelta(186.21, $this->physical->octanolWaterPartition(), 0.1);
    }

    public function testOctanolWaterPartitionForLimonene(): void
    {
        // Limonène logP = 4.57 → K_ow = 10^4.57 ≈ 37153.5 (très lipophile)
        $this->physical->setLogP(4.57);

        self::assertEqualsWithDelta(37154.0, $this->physical->octanolWaterPartition(), 5.0);
    }

    public function testOctanolWaterPartitionForLogPZero(): void
    {
        // logP = 0 → K_ow = 1 (équipartition octanol/eau, ex: méthanol)
        $this->physical->setLogP(0.0);

        self::assertSame(1.0, $this->physical->octanolWaterPartition());
    }

    // ── aromaKinetics (dérivée du point d'ébullition) ─────────────────────────

    public function testAromaKineticsReturnsNullWhenBoilingPointMissing(): void
    {
        self::assertNull($this->physical->aromaKinetics());
    }

    public function testAromaKineticsHeadForLowBoilingPoint(): void
    {
        // Limonène bp = 176 °C → HEART en réalité ; ici on teste un HEAD pur
        $this->physical->setBoilingPointCelsius(100);

        self::assertSame(AromaKinetics::HEAD, $this->physical->aromaKinetics());
    }

    public function testAromaKineticsHeartForLinalool(): void
    {
        $this->physical->setBoilingPointCelsius(198);

        self::assertSame(AromaKinetics::HEART, $this->physical->aromaKinetics());
    }

    public function testAromaKineticsBaseForEugenol(): void
    {
        $this->physical->setBoilingPointCelsius(254);

        self::assertSame(AromaKinetics::BASE, $this->physical->aromaKinetics());
    }
}
