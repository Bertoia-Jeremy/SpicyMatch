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
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SurvivalGame extends AbstractController
{
    use DefaultActionTrait;
    use GameSessionTrait;

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
    public function start(#[LiveArg] int $spiceId): void
    {
        $secret = $this->readSecret();

        // Replay guard: only honor start() if the session has no current spice yet.
        if (($secret['currentSpiceId'] ?? null) !== null) {
            return;
        }

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
    public function pick(#[LiveArg] int $spiceId): void
    {
        if (! $this->isStarted || $this->isGameOver || $this->isVictory) {
            return;
        }

        // Validate server-side from session secret
        $secret = $this->readSecret();
        $compatibleIds = $secret['compatibleIds'] ?? [];
        $sessionCurrent = $secret['currentSpiceId'] ?? null;

        // Replay guard: the server-side currentSpiceId must match the LiveProp.
        // Otherwise the client is replaying an old snapshot — reject silently.
        if ($sessionCurrent !== $this->currentSpiceId) {
            return;
        }

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
        // Mirror in session for authoritative finish() read.
        $secret['chainLength'] = $this->chainLength;
        $this->writeSecret($secret);
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

        // Authoritative chainLength from session, never LiveProp.
        $secret = $this->readSecret();
        $serverChain = (int) ($secret['chainLength'] ?? 0);

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::SURVIVAL,
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
            $serverChain,
            max($serverChain, 1),
            $durationSeconds,
        );

        $this->removeSecret();

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

        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;
        $options = $this->academyManager->generateSurvivalOptions($spice, $gameDifficulty, $this->usedIds);

        $this->options = array_map(fn (array $o) => [
            'id' => $o['id'],
            'name' => $o['name'],
            'file' => $o['file'],
            'color' => $o['color'],
            'groupName' => $o['groupName'] ?? null,
        ], $options);

        // Store compatible IDs + current spice anchor in session for server-side validation
        $compatibleIds = [];

        foreach ($options as $o) {
            if ($o['isCompatible']) {
                $compatibleIds[] = $o['id'];
            }
        }

        $this->writeSecret([
            'compatibleIds' => $compatibleIds,
            'currentSpiceId' => $this->currentSpiceId,
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
