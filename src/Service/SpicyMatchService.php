<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchResult;
use App\Entity\Users;
use App\Factory\SpicyMatchFactory;
use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Business logic for creating and persisting a SpicyMatch from a user selection.
 *
 * Extracted from the SpicyMatch LiveComponent to remove the EntityManager
 * dependency from the component and centralise persistence concerns.
 */
class SpicyMatchService
{
    public function __construct(
        private readonly SpicyMatchFactory $factory,
        private readonly SpicesRepository $spicesRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Build, persist and return a SpicyMatch from a flat list of selected spice IDs.
     *
     * In auto mode, $compatibleSpices must be the scored array produced by
     * CompatibleSpiceFinder::findCompatible() — each entry must contain 'id' and 'score'.
     * In manual mode, pass an empty array (no results are stored).
     *
     * Le contexte culinaire ($ctx) est persisté avec l'entité pour permettre la
     * restitution exacte du ranking lors de la consultation historique.
     *
     * @param list<int>                          $selectedIds      Flat list of selected spice IDs
     * @param list<array{id: int, score: mixed}> $compatibleSpices Scored compatible spices (auto mode only)
     * @param CulinaryContext                    $ctx              Contexte culinaire — défaut neutre
     */
    public function createFromSelection(
        ?Users $user,
        array $selectedIds,
        bool $isManual,
        array $compatibleSpices,
        CulinaryContext $ctx,
    ): SpicyMatch {
        $spicyMatch = $this->factory->create();
        $spicyMatch->setUser($user);
        $spicyMatch->setIsManual($isManual);
        $spicyMatch->setCulinaryContext($ctx);

        // Batch load selected spices — 1 SELECT IN
        foreach ($this->spicesRepository->findBy([
            'id' => $selectedIds,
        ]) as $spice) {
            $spicyMatch->addSpice($spice);
        }

        // Auto mode: persist scored results for history/reference
        if (! $isManual && [] !== $compatibleSpices) {
            $compatibleIds = array_column($compatibleSpices, 'id');
            $scoreBySpiceId = array_column($compatibleSpices, 'score', 'id');

            // Batch load compatible spices — 1 SELECT IN
            foreach ($this->spicesRepository->findBy([
                'id' => $compatibleIds,
            ]) as $spice) {
                $result = new SpicyMatchResult();
                $result->setSpice($spice);
                $result->setScore((int) ($scoreBySpiceId[$spice->getId()] ?? 0));
                $spicyMatch->addResult($result);
            }
        }

        $this->em->persist($spicyMatch);
        $this->em->flush();

        return $spicyMatch;
    }
}
