<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\SpicyMatch as EntitySpicyMatch;
use App\Factory\SpicyMatchHistoryFactory;
use App\Repository\SpicesRepository;
use App\Service\SpiceMatchmakerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SpicyMatchHistory extends AbstractController
{
    use DefaultActionTrait;

    public EntitySpicyMatch $spicyMatch;
    public array $spices;

    #[LiveProp(writable: true)]
    public array $preparationChosen = [];
    
    #[LiveProp(writable: true)]
    public array $cookingChosen = [];

    public function __construct(
        private SpicesRepository $spicesRepository,
        private SpiceMatchmakerService $spiceMatchmakerService
    ) {}

    public function getResults(): array
    {
        return [
            'selectedSpices' => $groupByAromaticGroup ?? $this->spices['selectedSpices'],
            'compatibleSpices' => $compatibleSpices ?? 'todo',
        ];
    }

    #[LiveAction]
    public function nextStep(
        EntityManagerInterface $entityManager,
        SpicyMatchHistoryFactory $spicyMatchHistoryFactory
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $spicyMatchHistoryFactory = $spicyMatchHistoryFactory->create();

        $spicyMatchHistoryFactory->setSpicyMatchId($this->spicyMatch)
            ->setPreparationTipsIds(json_encode($this->preparationChosen))
            ->setCookingTipsIds(json_encode($this->cookingChosen));

        $entityManager->persist($spicyMatchHistoryFactory);
        $entityManager->flush();

        return $this->redirectToRoute('view_spicy_match_history', [
           'id' => $spicyMatchHistoryFactory->getId(),
        ]);
    }
}
