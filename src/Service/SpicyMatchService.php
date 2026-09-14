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

class SpicyMatchService
{
    public function __construct(
        private readonly SpicyMatchFactory $factory,
        private readonly SpicesRepository $spicesRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
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

        foreach ($this->spicesRepository->findBy([
            'id' => $selectedIds,
        ]) as $spice) {
            $spicyMatch->addSpice($spice);
        }

        if (! $isManual && $compatibleSpices !== []) {
            $compatibleIds = array_column($compatibleSpices, 'id');
            $scoreBySpiceId = array_column($compatibleSpices, 'score', 'id');

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
