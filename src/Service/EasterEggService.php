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
    /**
     * Whitelist of known egg slugs — any other slug is rejected.
     */
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
        // 1. Whitelist check
        if (! \in_array($slug, self::KNOWN_SLUGS, true)) {
            $this->logger->warning('easter_egg.unknown_slug', [
                'userId' => $user->getId(),
                'slug' => $slug,
            ]);

            return false;
        }

        // 2. Idempotence — already found → silent success, no dispatch, no double-count.
        $stats = $user->getStats();
        if ($stats instanceof UserStat && $stats->hasFoundEgg($slug)) {
            return true;
        }

        // 3. Server-side validation
        if (! $this->validateCondition($user, $slug, $payload)) {
            $this->logger->info('easter_egg.validation_failed', [
                'userId' => $user->getId(),
                'slug' => $slug,
            ]);

            return false;
        }

        // 4. Dispatch event
        $this->bus->dispatch(new EasterEggFoundEvent($user->getId(), $slug));

        // 5. Record + increment counter (idempotent thanks to recordFoundEgg guard)
        if ($stats instanceof UserStat) {
            $stats->recordFoundEgg($slug);
            $stats->incrementEasterEggsFound();
            $this->em->persist($stats);
            $this->em->flush();
        }

        $this->logger->info('easter_egg.found', [
            'userId' => $user->getId(),
            'slug' => $slug,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateCondition(Users $user, string $slug, array $payload): bool
    {
        return match ($slug) {
            'grain_de_sel' => true, // Purely interaction-based
            'perdu_dans_le_souk' => true, // 404 page interaction
            'alchimiste_de_l_ombre' => $this->validateAlchimisteCount(),
            'temps_de_l_infusion' => $this->validateTempsInfusion(),
            'equilibre_des_contraires' => $this->validateEquilibre($user, $payload),
            'secret_du_curry' => $this->validateSecretDuCurry($user),
            'le_poids_de_l_or' => $this->validateLePoidsDeLOr($user, $payload),
            'la_recette_perdue' => $this->validateLaRecettePerdue($payload),
            default => false,
        };
    }

    /**
     * Count stored server-side in session (incremented by the toggle endpoint),
     * so the client cannot forge `{"count": 999}`.
     */
    private function validateAlchimisteCount(): bool
    {
        $session = $this->requestStack->getSession();
        $count = (int) $session->get('easter_egg.alchimiste_count', 0);

        return $count >= 5;
    }

    /**
     * Duration computed from a server-issued timestamp in the session
     * (set when the user opens the target page), not from the client payload.
     */
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

        return 'poivre_noir' === $spice?->getSlug();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateLaRecettePerdue(array $payload): bool
    {
        $keywords = $payload['keywords'] ?? [];
        // Expecting 4 specific keywords (order doesn't matter for this one or specific sequence)
        $expected = ['cannelle', 'cardamome', 'clou_girofle', 'muscade'];

        return 4 === count(array_intersect($expected, $keywords));
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

        // Expected sequence: Curcuma -> Cumin -> Gingembre (in reverse order of history: Gingembre, Cumin, Curcuma)
        // History is appended, so end of array is most recent.
        $recent = array_slice($history, -3);

        // Fetch IDs by slug (assuming slugs are standard)
        $ids = $this->getSpiceIds(['curcuma', 'cumin', 'gingembre']);
        if (3 !== count($ids)) {
            return false; // Spices not found
        }

        // Recent: [Curcuma_ID, Cumin_ID, Gingembre_ID]
        return $recent === array_values($ids);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateEquilibre(Users $user, array $payload): bool
    {
        // Payload should contain the two spice IDs being compared
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

        $type1 = $spice1->getSpicyType()?->getName();
        $type2 = $spice2->getSpicyType()?->getName();

        if (! $type1 || ! $type2) {
            return false;
        }

        // Check if one is "Douce" and the other is "Brulante" (or similar strong type)
        // Adjust strings based on actual DB values if known, assuming 'Douce' and 'Brulante'.
        $pair = [\mb_strtolower($type1), \mb_strtolower($type2)];

        // Use loose matching or specific values
        return \in_array('douce', $pair, true) && \in_array('brulante', $pair, true);
    }

    /**
     * @param string[] $slugs
     *
     * @return array<int> Ordered list of IDs
     */
    private function getSpiceIds(array $slugs): array
    {
        $ids = [];
        foreach ($slugs as $slug) {
            $spice = $this->spicesRepository->findOneBy([
                'slug' => $slug,
            ]);
            if ($spice) {
                $ids[] = $spice->getId();
            }
        }

        return $ids;
    }
}
