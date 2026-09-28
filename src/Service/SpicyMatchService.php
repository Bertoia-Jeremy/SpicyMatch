<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SpicyMatchHistory;
use App\Entity\SpicyMatchResult;
use App\Entity\Users;
use App\Exception\Match\InvalidMortarException;
use App\Factory\SpicyMatchFactory;
use App\Factory\SpicyMatchHistoryFactory;
use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use Doctrine\ORM\EntityManagerInterface;

class SpicyMatchService
{
    public function __construct(
        private readonly SpicyMatchFactory $factory,
        private readonly SpicesRepository $spicesRepository,
        private readonly EntityManagerInterface $em,
        private readonly SpicyMatchHistoryFactory $historyFactory,
    ) {
    }

    /**
     * @param list<int>                          $selectedIds
     * @param list<array{id: int, score: mixed}> $compatibleSpices
     *
     * @throws InvalidMortarException
     */
    public function start(
        Users $user,
        array $selectedIds,
        bool $isManual,
        array $compatibleSpices,
        CulinaryContext $ctx,
    ): SpicyMatchHistory {
        $selected = $selectedIds === [] ? [] : $this->spicesRepository->findBy([
            'id' => $selectedIds,
        ]);
        if ($selected === []) {
            throw InvalidMortarException::emptySelection();
        }

        $spicyMatch = $this->factory->create();
        $spicyMatch->setUser($user);
        $spicyMatch->setIsManual($isManual);
        $spicyMatch->setCulinaryContext($ctx);

        foreach ($selected as $spice) {
            $spicyMatch->addSpice($spice);
        }

        if (! $isManual && $compatibleSpices !== []) {
            $scoreBySpiceId = array_column($compatibleSpices, 'score', 'id');

            foreach ($this->spicesRepository->findBy([
                'id' => array_column($compatibleSpices, 'id'),
            ]) as $spice) {
                $result = new SpicyMatchResult();
                $result->setSpice($spice);
                $result->setScore((int) ($scoreBySpiceId[$spice->getId()] ?? 0));
                $spicyMatch->addResult($result);
            }
        }

        $history = $this->historyFactory->create($spicyMatch);

        $this->em->persist($spicyMatch);
        $this->em->persist($history);
        $this->em->flush();

        return $history;
    }
}
