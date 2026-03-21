<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Spices;
use App\Entity\Users;
use App\Repository\SpicesRepository;
use App\Service\EasterEggService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class EasterEggServiceTest extends TestCase
{
    private MessageBusInterface $bus;
    private SpicesRepository $spicesRepository;
    private EntityManagerInterface $em;
    private EasterEggService $service;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->spicesRepository = $this->createMock(SpicesRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new EasterEggService($this->bus, $this->spicesRepository, $this->em);

        // Default mock for bus dispatch
        $this->bus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
    }

    public function testHandleEggDispatchesEvent(): void
    {
        $user = $this->makeUser(123);
        $result = $this->service->handleEgg($user, 'grain_de_sel');
        self::assertTrue($result);
    }

    public function testValidateAlchimisteDeLOmbre(): void
    {
        $user = $this->makeUser(123);

        self::assertFalse($this->service->handleEgg($user, 'alchimiste_de_l_ombre', [
            'count' => 4,
        ]));
        self::assertTrue($this->service->handleEgg($user, 'alchimiste_de_l_ombre', [
            'count' => 5,
        ]));
    }

    public function testValidateTempsDeLInfusion(): void
    {
        $user = $this->makeUser(123);

        self::assertFalse($this->service->handleEgg($user, 'temps_de_l_infusion', [
            'duration' => 259,
        ]));
        self::assertTrue($this->service->handleEgg($user, 'temps_de_l_infusion', [
            'duration' => 260,
        ]));
    }

    public function testValidateLePoidsDeLOr(): void
    {
        $user = $this->makeUser(123);
        $spice = new Spices();
        $spice->setSlug('poivre_noir');

        $this->spicesRepository->method('find')
            ->willReturn($spice);

        self::assertTrue($this->service->handleEgg($user, 'le_poids_de_l_or', [
            'spiceId' => 1,
        ]));
    }

    public function testValidateLaRecettePerdue(): void
    {
        $user = $this->makeUser(123);

        $keywords = ['cannelle', 'cardamome', 'clou_girofle', 'muscade'];
        self::assertTrue($this->service->handleEgg($user, 'la_recette_perdue', [
            'keywords' => $keywords,
        ]));

        self::assertFalse($this->service->handleEgg($user, 'la_recette_perdue', [
            'keywords' => ['cannelle'],
        ]));
    }

    public function testHandleEggReturnsFalseForUnknownSlug(): void
    {
        $user = $this->makeUser(123);
        self::assertFalse($this->service->handleEgg($user, 'this_slug_does_not_exist'));
    }

    public function testValidateEquilibreWithDouceAndBrulante(): void
    {
        $user = $this->makeUser(123);

        $spice1 = $this->createMock(Spices::class);
        $type1 = $this->createMock(\App\Entity\SpicyType::class);
        $type1->method('getName')
            ->willReturn('Douce');
        $spice1->method('getSpicyType')
            ->willReturn($type1);

        $spice2 = $this->createMock(Spices::class);
        $type2 = $this->createMock(\App\Entity\SpicyType::class);
        $type2->method('getName')
            ->willReturn('Brulante');
        $spice2->method('getSpicyType')
            ->willReturn($type2);

        $this->spicesRepository->method('find')
            ->willReturnCallback(fn (int $id) => match ($id) {
                1 => $spice1,
                2 => $spice2,
                default => null,
            });

        self::assertTrue($this->service->handleEgg($user, 'equilibre_des_contraires', [
            'spice1' => 1,
            'spice2' => 2,
        ]));
    }

    public function testValidateEquilibreFailsWithSameTypes(): void
    {
        $user = $this->makeUser(123);

        $spice1 = $this->createMock(Spices::class);
        $type1 = $this->createMock(\App\Entity\SpicyType::class);
        $type1->method('getName')
            ->willReturn('Douce');
        $spice1->method('getSpicyType')
            ->willReturn($type1);

        $spice2 = $this->createMock(Spices::class);
        $type2 = $this->createMock(\App\Entity\SpicyType::class);
        $type2->method('getName')
            ->willReturn('Douce');
        $spice2->method('getSpicyType')
            ->willReturn($type2);

        $this->spicesRepository->method('find')
            ->willReturnCallback(fn (int $id) => match ($id) {
                1 => $spice1,
                2 => $spice2,
                default => null,
            });

        self::assertFalse($this->service->handleEgg($user, 'equilibre_des_contraires', [
            'spice1' => 1,
            'spice2' => 2,
        ]));
    }

    public function testValidateEquilibreFailsMissingSpiceIds(): void
    {
        $user = $this->makeUser(123);
        self::assertFalse($this->service->handleEgg($user, 'equilibre_des_contraires', []));
    }

    public function testValidateEquilibreFailsWhenSpiceNotFound(): void
    {
        $user = $this->makeUser(123);
        $this->spicesRepository->method('find')
            ->willReturn(null);

        self::assertFalse($this->service->handleEgg($user, 'equilibre_des_contraires', [
            'spice1' => 1,
            'spice2' => 2,
        ]));
    }

    public function testValidateSecretDuCurryWithCorrectSequence(): void
    {
        $user = $this->makeUser(123);
        $stats = new \App\Entity\UserStat();
        // Record the sequence: curcuma(10), cumin(20), gingembre(30)
        $stats->recordVisitedSpice(10);
        $stats->recordVisitedSpice(20);
        $stats->recordVisitedSpice(30);
        $user->setStats($stats);

        $this->spicesRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                $spice = $this->createMock(Spices::class);
                $id = match ($criteria['slug']) {
                    'curcuma' => 10,
                    'cumin' => 20,
                    'gingembre' => 30,
                    default => null,
                };
                $spice->method('getId')
                    ->willReturn($id);

                return $spice;
            });

        self::assertTrue($this->service->handleEgg($user, 'secret_du_curry'));
    }

    public function testValidateSecretDuCurryFailsWithWrongSequence(): void
    {
        $user = $this->makeUser(123);
        $stats = new \App\Entity\UserStat();
        // Wrong order: gingembre, cumin, curcuma
        $stats->recordVisitedSpice(30);
        $stats->recordVisitedSpice(20);
        $stats->recordVisitedSpice(10);
        $user->setStats($stats);

        $this->spicesRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                $spice = $this->createMock(Spices::class);
                $id = match ($criteria['slug']) {
                    'curcuma' => 10,
                    'cumin' => 20,
                    'gingembre' => 30,
                    default => null,
                };
                $spice->method('getId')
                    ->willReturn($id);

                return $spice;
            });

        self::assertFalse($this->service->handleEgg($user, 'secret_du_curry'));
    }

    public function testValidateSecretDuCurryFailsWithTooFewVisits(): void
    {
        $user = $this->makeUser(123);
        $stats = new \App\Entity\UserStat();
        $stats->recordVisitedSpice(10);
        $stats->recordVisitedSpice(20);
        // Only 2 visits, need 3
        $user->setStats($stats);

        self::assertFalse($this->service->handleEgg($user, 'secret_du_curry'));
    }

    public function testValidateSecretDuCurryFailsWithoutStats(): void
    {
        $user = $this->makeUser(123);
        // No stats set → getStats() returns null
        self::assertFalse($this->service->handleEgg($user, 'secret_du_curry'));
    }

    public function testHandleEggUpdatesEasterEggsFoundInStats(): void
    {
        $user = $this->makeUser(123);
        $stats = new \App\Entity\UserStat();
        $user->setStats($stats);

        self::assertSame(0, $stats->getEasterEggsFound());

        $this->service->handleEgg($user, 'grain_de_sel');

        self::assertSame(1, $stats->getEasterEggsFound());
    }

    public function testLaRecettePerdueMissingKeywordsReturnsFalse(): void
    {
        $user = $this->makeUser(123);
        self::assertFalse($this->service->handleEgg($user, 'la_recette_perdue', []));
    }

    private function makeUser(int $id): Users
    {
        $user = new Users();
        $ref = new \ReflectionProperty(Users::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
