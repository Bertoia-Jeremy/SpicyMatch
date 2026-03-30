<?php

declare(strict_types=1);

namespace App\Twig\Components\Education;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SurvivalGame extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $difficulty = 'easy';

    #[LiveProp]
    public string $gameToken = '';

    #[LiveProp]
    public bool $isStarted = false;

    #[LiveProp]
    public int $currentSpiceId = 0;

    #[LiveProp]
    public string $currentSpiceName = '';

    #[LiveProp]
    public ?string $currentSpiceFile = null;

    #[LiveProp]
    public ?string $currentSpiceColor = null;

    #[LiveProp]
    public ?string $currentSpiceGroupName = null;

    /**
     * @var array<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>
     */
    #[LiveProp]
    public array $options = [];

    #[LiveProp]
    public int $chainLength = 0;

    #[LiveProp]
    public bool $isGameOver = false;

    #[LiveProp]
    public bool $isVictory = false;

    #[LiveProp]
    public string $lastPickedName = '';

    #[LiveProp]
    public int $startedAt = 0;

    /**
     * @var int[]
     */
    #[LiveProp]
    public array $usedIds = [];

    /**
     * @var array<array{id: int, name: string}>
     */
    #[LiveProp]
    public array $startingSpices = [];

    public function __construct(
        private readonly AcademyManager $academyManager,
        private readonly GameSessionManager $sessionManager,
        private readonly RequestStack $requestStack,
        private readonly SpicesRepository $spicesRepository,
    ) {
    }

    public function mount(string $difficulty = 'easy'): void
    {
        $this->difficulty = $difficulty;
        $this->gameToken = bin2hex(random_bytes(8));
        $this->startedAt = time();
        $this->loadStartingSpices();
    }

    #[LiveAction]
    public function start(int $spiceId): void
    {
        if ($this->isStarted) {
            return;
        }

        $spice = $this->findSpiceById($spiceId);

        if ($spice === null) {
            return;
        }

        $this->isStarted = true;
        $this->setCurrentSpice($spice);
        $this->usedIds[] = $spiceId;
        $this->generateOptions();
    }

    #[LiveAction]
    public function pick(int $spiceId): void
    {
        if (! $this->isStarted || $this->isGameOver || $this->isVictory) {
            return;
        }

        // Validate server-side from session secret
        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $compatibleIds = $secret['compatibleIds'] ?? [];

        $isCompatible = in_array($spiceId, $compatibleIds, true);

        // Find the picked option's name
        $pickedName = '';

        foreach ($this->options as $opt) {
            if ($opt['id'] === $spiceId) {
                $pickedName = $opt['name'];

                break;
            }
        }

        $this->lastPickedName = $pickedName;

        if (! $isCompatible) {
            $this->isGameOver = true;

            return;
        }

        ++$this->chainLength;
        $this->usedIds[] = $spiceId;

        $spice = $this->findSpiceById($spiceId);

        if ($spice === null) {
            $this->isGameOver = true;

            return;
        }

        $this->setCurrentSpice($spice);
        $this->generateOptions();

        // Pool exhaustion → victory
        if (empty($this->options)) {
            $this->isVictory = true;
        }
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::SURVIVAL,
            GameDifficulty::from($this->difficulty),
            $this->chainLength,
            max($this->chainLength, 1),
            $durationSeconds,
        );

        $this->requestStack->getSession()
            ->remove('game_' . $this->gameToken);

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function loadStartingSpices(): void
    {
        $cards = $this->academyManager->getAllSpiceCards();
        $spices = [];

        foreach ($cards as $card) {
            $spices[] = [
                'id' => $card['id'],
                'name' => $card['name'],
                'file' => $card['file'],
                'color' => $card['aromaticGroup']['color'] ?? null,
                'groupName' => $card['aromaticGroup']['name'] ?? null,
            ];
        }

        shuffle($spices);
        $this->startingSpices = array_slice($spices, 0, 12);
    }

    private function generateOptions(): void
    {
        $spice = $this->spicesRepository->find($this->currentSpiceId);

        if ($spice === null) {
            $this->options = [];

            return;
        }

        $gameDifficulty = GameDifficulty::from($this->difficulty);
        $options = $this->academyManager->generateSurvivalOptions($spice, $gameDifficulty, $this->usedIds);

        $this->options = array_map(fn (array $o) => [
            'id' => $o['id'],
            'name' => $o['name'],
            'file' => $o['file'],
            'color' => $o['color'],
            'groupName' => $o['groupName'] ?? null,
        ], $options);

        // Store compatible IDs in session for server-side validation
        $compatibleIds = [];

        foreach ($options as $o) {
            if ($o['isCompatible']) {
                $compatibleIds[] = $o['id'];
            }
        }

        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'compatibleIds' => $compatibleIds,
            ]);
    }

    /**
     * @param array{id: int, name: string, file: ?string, color: ?string, groupName: ?string} $spiceData
     */
    private function setCurrentSpice(array $spiceData): void
    {
        $this->currentSpiceId = $spiceData['id'];
        $this->currentSpiceName = $spiceData['name'];
        $this->currentSpiceFile = $spiceData['file'] ?? null;
        $this->currentSpiceColor = $spiceData['color'] ?? null;
        $this->currentSpiceGroupName = $spiceData['groupName'] ?? null;
    }

    /**
     * @return array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}|null
     */
    private function findSpiceById(int $id): ?array
    {
        $cards = $this->academyManager->getAllSpiceCards();

        if (! isset($cards[$id])) {
            return null;
        }

        $card = $cards[$id];

        return [
            'id' => $card['id'],
            'name' => $card['name'],
            'file' => $card['file'],
            'color' => $card['aromaticGroup']['color'] ?? null,
            'groupName' => $card['aromaticGroup']['name'] ?? null,
        ];
    }
}
