<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\Match\CompatibleSpiceFinder;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Symfony\Contracts\Translation\TranslatorInterface;

class QcmQuestionGenerator implements QuestionGeneratorInterface
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibleSpiceFinder $compatibleSpiceFinder,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(GameMode $mode): bool
    {
        return GameMode::QCM === $mode;
    }

    public function generate(GameDifficulty $difficulty, array $excludeSpiceIds = []): ?array
    {
        // Pick a random base spice (excluding already-used ones)
        $allSpices = $this->spicesRepository->findAllSpices();
        $excludeFlipped = array_flip($excludeSpiceIds);
        $candidates = array_values(array_filter(
            $allSpices,
            fn (array $s) => ! isset($excludeFlipped[(int) $s['id']]),
        ));

        if (count($candidates) < 5) {
            return null;
        }

        // Shuffle and try bases until we find one with enough compatible results
        shuffle($candidates);

        foreach ($candidates as $baseData) {
            $scored = $this->compatibleSpiceFinder->findCompatible(
                new MortarIds([(int) $baseData['id']]),
                100,
                new CulinaryContext(),
            );
            if (count($scored) < 4) {
                continue;
            }

            $topPool = array_slice($scored, 0, max(1, (int) ceil(count($scored) * 0.3)));
            shuffle($topPool);

            $correct = null;
            $dominated = [];

            foreach ($topPool as $candidateAnswer) {
                $strictlyBelow = $this->strictlyBelow($scored, $candidateAnswer, $baseData);

                if (count($strictlyBelow) >= 3) {
                    $correct = $candidateAnswer;
                    $dominated = $strictlyBelow;

                    break;
                }
            }

            if (null === $correct) {
                continue;
            }

            $distractors = $this->pickDistractors($difficulty, $correct, $dominated);

            $options = array_merge(
                [[
                    'id' => (int) $correct['id'],
                    'name' => (string) $correct['name'],
                ]],
                array_map(
                    fn (array $s) => [
                        'id' => (int) $s['id'],
                        'name' => (string) $s['name'],
                    ],
                    array_slice($distractors, 0, 3)
                )
            );
            shuffle($options);

            return [
                'type' => 'qcm',
                'prompt' => $this->translator->trans('ui.edu.prompt.qcm_best_match', [
                    '%spice%' => (string) $baseData['name'],
                ]),
                'baseSpice' => [
                    'id' => (int) $baseData['id'],
                    'name' => (string) $baseData['name'],
                ],
                'options' => $options,
                'correctAnswer' => (string) $correct['name'],
                'metadata' => [
                    'correctScore' => $correct['score'],
                    'difficulty' => $difficulty->value,
                ],
            ];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $scored
     * @param array<string, mixed>       $correct
     * @param array<string, mixed>       $baseData
     *
     * @return list<array<string, mixed>>
     */
    private function strictlyBelow(array $scored, array $correct, array $baseData): array
    {
        $correctScore = (int) $correct['score'];

        return array_values(array_filter(
            $scored,
            static fn (array $s) => (int) $s['score'] < $correctScore && $s['id'] !== $baseData['id'],
        ));
    }

    /**
     * @param array<string, mixed>       $correct
     * @param list<array<string, mixed>> $dominated
     *
     * @return list<array<string, mixed>>
     */
    private function pickDistractors(GameDifficulty $difficulty, array $correct, array $dominated): array
    {
        $window = OrdinalWindow::select(array_reverse($dominated), $difficulty, 3);

        if (GameDifficulty::EASY === $difficulty) {
            $window = $this->preferDistinctGroup($window, $correct['groupName'] ?? null);
        }

        shuffle($window);

        return array_slice($window, 0, 3);
    }

    /**
     * @param list<array<string, mixed>> $window
     *
     * @return list<array<string, mixed>>
     */
    private function preferDistinctGroup(array $window, mixed $correctGroupName): array
    {
        $distinct = array_values(array_filter(
            $window,
            static fn (array $s) => ($s['groupName'] ?? null) !== $correctGroupName,
        ));

        return count($distinct) >= 3 ? $distinct : $window;
    }
}
