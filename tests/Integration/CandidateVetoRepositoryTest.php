<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Spices;
use App\Enum\OdtMatrix;
use App\Repository\CandidateVetoRepository;
use App\Tests\Support\IntegrationTestCase;
use App\ValueObject\Match\MortarIds;

final class CandidateVetoRepositoryTest extends IntegrationTestCase
{
    private CandidateVetoRepository $veto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->veto = static::getContainer()->get(CandidateVetoRepository::class);
    }

    public function testOavVetoReturnsSurvivorsSharingCompoundsWithWholeMortar(): void
    {
        $mortar = new MortarIds([$this->spiceId('Thym Commun'), $this->spiceId('Origan Méditerranéen')]);

        $survivors = $this->veto->findSurvivors($mortar, OdtMatrix::AIR);

        self::assertNotEmpty($survivors, 'Thym + Origan doivent avoir des survivants OAV en air');
    }

    public function testOavVetoExcludesMortarSpices(): void
    {
        $thym = $this->spiceId('Thym Commun');
        $origan = $this->spiceId('Origan Méditerranéen');

        $survivors = $this->veto->findSurvivors(new MortarIds([$thym, $origan]), OdtMatrix::AIR);

        self::assertNotContains($thym, $survivors);
        self::assertNotContains($origan, $survivors);
    }

    public function testOavVetoIncludesKnownRelatedSpice(): void
    {
        $mortar = new MortarIds([$this->spiceId('Thym Commun'), $this->spiceId('Origan Méditerranéen')]);

        $survivors = $this->veto->findSurvivors($mortar, OdtMatrix::AIR);

        self::assertContains(
            $this->spiceId('Marjolaine'),
            $survivors,
            'Marjolaine partage thymol/carvacrol avec le mortier → survit au veto OAV',
        );
    }

    public function testPresenceVetoProjectsPluralJoinColumnAndReturnsSurvivors(): void
    {
        $mortar = new MortarIds([$this->spiceId('Thym Commun'), $this->spiceId('Origan Méditerranéen')]);

        $survivors = $this->veto->findSurvivorsWithPresence($mortar);

        self::assertNotEmpty($survivors, 'Le mode présence doit renvoyer des survivants (SQL spices_id AS spice_id)');
        foreach ($survivors as $id) {
            self::assertGreaterThan(0, $id);
        }
    }

    public function testPresenceVetoExcludesMortarSpices(): void
    {
        $thym = $this->spiceId('Thym Commun');
        $origan = $this->spiceId('Origan Méditerranéen');

        $survivors = $this->veto->findSurvivorsWithPresence(new MortarIds([$thym, $origan]));

        self::assertNotContains($thym, $survivors);
        self::assertNotContains($origan, $survivors);
    }

    public function testSingleSpiceMortarPresenceVetoReturnsSharingSpices(): void
    {
        $survivors = $this->veto->findSurvivorsWithPresence(new MortarIds([$this->spiceId('Thym Commun')]));

        self::assertNotEmpty($survivors);
        self::assertNotContains($this->spiceId('Thym Commun'), $survivors);
    }

    private function spiceId(string $name): int
    {
        $spice = $this->em->getRepository(Spices::class)->findOneBy([
            'name' => $name,
        ]);
        self::assertInstanceOf(Spices::class, $spice, "Fixture spice '{$name}' introuvable");

        return $spice->getId();
    }
}
