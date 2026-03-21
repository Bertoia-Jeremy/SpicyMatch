<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\CompatibilityScoreService;

class QcmQuestionGenerator implements QuestionGeneratorInterface
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibilityScoreService $compatibilityScoreService,
    ) {
    }

    public function supports(GameMode $mode): bool
    {
        return $mode === GameMode::QCM;
    }

    public function generate(GameDifficulty $difficulty, array $excludeSpiceIds = []): ?array
    {
        // Pick a random base spice (excluding already-used ones)
        $allSpices = $this->spicesRepository->findAllSpices();
        $candidates = array_values(array_filter(
            $allSpices,
            fn (array $s) => ! in_array($s['id'], $excludeSpiceIds, true)
        ));

        if (count($candidates) < 5) {
            return null;
        }

        // Shuffle and try bases until we find one with enough compatible results
        shuffle($candidates);

        foreach ($candidates as $baseData) {
            $baseEntity = $this->spicesRepository->find($baseData['id']);
            if ($baseEntity === null) {
                continue;
            }

            $scored = $this->compatibilityScoreService->findCompatible([$baseEntity]);
            if (count($scored) < 4) {
                continue;
            }

            // Split into high-score (correct) and low-score (distractors) pools
            $total = count($scored);
            $topCutoff = (int) ceil($total * 0.3);
            $bottomCutoff = (int) floor($total * 0.7);

            $topPool = array_slice($scored, 0, max(1, $topCutoff));
            $bottomPool = array_slice($scored, $bottomCutoff);

            // Pick correct answer from top pool
            $correctIdx = array_rand($topPool);
            $correct = $topPool[$correctIdx];

            // Pick distractors based on difficulty
            $distractors = $this->pickDistractors($difficulty, $correct, $bottomPool, $scored, $baseData);
            if (count($distractors) < 3) {
                continue;
            }

            // Build options (1 correct + 3 distractors), shuffle
            $options = array_merge(
                [[
                    'id' => $correct['id'],
                    'name' => $correct['name'],
                ]],
                array_map(
                    fn (array $s) => [
                        'id' => $s['id'],
                        'name' => $s['name'],
                    ],
                    array_slice($distractors, 0, 3)
                )
            );
            shuffle($options);

            return [
                'type' => 'qcm',
                'prompt' => sprintf('Quelle épice se marie le mieux avec %s ?', $baseData['name']),
                'baseSpice' => [
                    'id' => $baseData['id'],
                    'name' => $baseData['name'],
                ],
                'options' => $options,
                'correctAnswer' => $correct['name'],
                'metadata' => [
                    'correctScore' => $correct['score'],
                    'difficulty' => $difficulty->value,
                ],
            ];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pickDistractors(
        GameDifficulty $difficulty,
        array $correct,
        array $bottomPool,
        array $allScored,
        array $baseData,
    ): array {
        $correctGroupName = $correct['groupName'] ?? null;
        $distractors = [];

        if ($difficulty === GameDifficulty::EASY) {
            // EASY: distractors from different aromatic groups than the correct answer
            foreach ($bottomPool as $s) {
                if (($s['groupName'] ?? null) !== $correctGroupName && $s['id'] !== $correct['id']) {
                    $distractors[] = $s;
                }
            }
        } elseif ($difficulty === GameDifficulty::HARD) {
            // HARD: distractors with scores close to the correct answer (medium range)
            $correctScore = $correct['score'];
            $candidates = array_filter(
                $allScored,
                fn (array $s) => $s['id'] !== $correct['id']
                    && $s['id'] !== $baseData['id']
                    && abs($s['score'] - $correctScore) <= 20
            );
            usort(
                $candidates,
                fn (array $a, array $b) => abs($a['score'] - $correctScore) <=> abs($b['score'] - $correctScore)
            );
            $distractors = array_values($candidates);
        } else {
            // MEDIUM: distractors from same group but lower scores
            foreach ($bottomPool as $s) {
                if ($s['id'] !== $correct['id']) {
                    $distractors[] = $s;
                }
            }
        }

        // Fallback: if not enough distractors, fill from bottom pool
        if (count($distractors) < 3) {
            foreach ($allScored as $s) {
                if ($s['id'] !== $correct['id'] && ! in_array($s['id'], array_column($distractors, 'id'), true)) {
                    $distractors[] = $s;
                }
                if (count($distractors) >= 3) {
                    break;
                }
            }
        }

        shuffle($distractors);

        return $distractors;
    }
}
