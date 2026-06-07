<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\Match\CompatibleSpiceFinder;
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
        return $mode === GameMode::QCM;
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
            $scored = $this->compatibleSpiceFinder->findCompatible(new MortarIds([(int) $baseData['id']]), 100);
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
     * @param array<string, mixed>       $correct
     * @param list<array<string, mixed>> $bottomPool
     * @param list<array<string, mixed>> $allScored
     * @param array<string, mixed>       $baseData
     *
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
            $distractors = $candidates;
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
            $distractorIds = array_flip(array_column($distractors, 'id'));
            foreach ($allScored as $s) {
                if ($s['id'] !== $correct['id'] && ! isset($distractorIds[$s['id']])) {
                    $distractors[] = $s;
                    $distractorIds[$s['id']] = true;
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
