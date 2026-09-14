<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Users;
use App\Entity\UserStat;
use App\Message\EasterEggFoundEvent;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

class EasterEggService
{
    private const array KNOWN_SLUGS = [
        'grain_de_sel',
        'perdu_dans_le_souk',
        'alchimiste_de_l_ombre',
        'temps_de_l_infusion',
        'equilibre_des_contraires',
        'secret_du_curry',
        'le_poids_de_l_or',
        'la_recette_perdue',
    ];

    private const string BURNING_GROUP_SLUG = 'capsaicinoides-alcaloides';

    private const string UNCLASSIFIED_GROUP_SLUG = 'a-reviser';

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SpicesRepository $spicesRepository,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleEgg(Users $user, string $slug, array $payload = []): bool
    {
        if (! \in_array($slug, self::KNOWN_SLUGS, true)) {
            $this->logger->warning('easter_egg.unknown_slug', [
                'userId' => $user->getId(),
                'slug' => $slug,
            ]);

            return false;
        }

        $stats = $this->getOrCreateStats($user);
        if ($stats->hasFoundEgg($slug)) {
            return true;
        }

        if (! $this->validateCondition($user, $slug, $payload)) {
            $this->logger->info('easter_egg.validation_failed', [
                'userId' => $user->getId(),
                'slug' => $slug,
            ]);

            return false;
        }

        $stats->recordFoundEgg($slug);
        $stats->incrementEasterEggsFound();
        $this->em->persist($stats);
        $this->em->flush();

        $this->bus->dispatch(new EasterEggFoundEvent($user->getId(), $slug));

        $this->logger->info('easter_egg.found', [
            'userId' => $user->getId(),
            'slug' => $slug,
        ]);

        return true;
    }

    private function getOrCreateStats(Users $user): UserStat
    {
        $stats = $user->getStats();
        if ($stats === null) {
            $stats = new UserStat();
            $stats->setUser($user);
            $user->setStats($stats);
            $this->em->persist($stats);
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateCondition(Users $user, string $slug, array $payload): bool
    {
        return match ($slug) {
            'grain_de_sel' => true,
            'perdu_dans_le_souk' => true,
            'alchimiste_de_l_ombre' => $this->validateAlchimisteCount(),
            'temps_de_l_infusion' => $this->validateTempsInfusion(),
            'equilibre_des_contraires' => $this->validateEquilibre($user, $payload),
            'secret_du_curry' => $this->validateSecretDuCurry($user),
            'le_poids_de_l_or' => $this->validateLePoidsDeLOr($user, $payload),
            'la_recette_perdue' => $this->validateLaRecettePerdue($payload),
            default => false,
        };
    }

    private function validateAlchimisteCount(): bool
    {
        $session = $this->requestStack->getSession();
        $count = (int) $session->get('easter_egg.alchimiste_count', 0);

        return $count >= 5;
    }

    private function validateTempsInfusion(): bool
    {
        $session = $this->requestStack->getSession();
        $startedAt = $session->get('easter_egg.infusion_started_at');
        if (! \is_int($startedAt)) {
            return false;
        }

        return (time() - $startedAt) >= 260;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateLePoidsDeLOr(Users $user, array $payload): bool
    {
        $spiceId = $payload['spiceId'] ?? null;
        if (! $spiceId) {
            return false;
        }

        $spice = $this->spicesRepository->find($spiceId);

        return $spice?->getSlug() === 'poivre_noir';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateLaRecettePerdue(array $payload): bool
    {
        $keywords = $payload['keywords'] ?? [];
        $expected = ['cannelle', 'cardamome', 'clou_girofle', 'muscade'];

        return count(array_intersect($expected, $keywords)) === 4;
    }

    private function validateSecretDuCurry(Users $user): bool
    {
        $stats = $user->getStats();
        if (! $stats) {
            return false;
        }

        $history = $stats->getLastVisitedSpices();
        if (count($history) < 3) {
            return false;
        }

        $recent = array_slice($history, -3);

        $ids = $this->getSpiceIds(['curcuma', 'cumin', 'gingembre']);
        if (count($ids) !== 3) {
            return false;
        }

        return $recent === array_values($ids);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateEquilibre(Users $user, array $payload): bool
    {
        $spiceId1 = $payload['spice1'] ?? null;
        $spiceId2 = $payload['spice2'] ?? null;

        if (! $spiceId1 || ! $spiceId2) {
            return false;
        }

        $spice1 = $this->spicesRepository->find($spiceId1);
        $spice2 = $this->spicesRepository->find($spiceId2);

        if (! $spice1 || ! $spice2) {
            return false;
        }

        $group1 = $spice1->getAromaticGroups()?->getSlug();
        $group2 = $spice2->getAromaticGroups()?->getSlug();

        if ($group1 === null || $group2 === null || $group1 === $group2) {
            return false;
        }

        $pair = [$group1, $group2];

        return \in_array(self::BURNING_GROUP_SLUG, $pair, true)
            && ! \in_array(self::UNCLASSIFIED_GROUP_SLUG, $pair, true);
    }

    /**
     * @param string[] $slugs
     * @return array<int>
     */
    private function getSpiceIds(array $slugs): array
    {
        $bySlug = [];
        foreach ($this->spicesRepository->findBy([
            'slug' => $slugs,
        ]) as $spice) {
            $bySlug[$spice->getSlug()] = $spice->getId();
        }

        $ids = [];
        foreach ($slugs as $slug) {
            if (isset($bySlug[$slug])) {
                $ids[] = $bySlug[$slug];
            }
        }

        return $ids;
    }
}
