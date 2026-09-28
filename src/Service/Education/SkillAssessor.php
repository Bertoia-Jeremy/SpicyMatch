<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;

final class SkillAssessor
{
    public const int WINDOW = 5;

    public const int MIN_SAMPLES = 3;

    public const float PROMOTE_MEAN = 80.0;

    public const float PROMOTE_FLOOR = 60.0;

    public const float DEMOTE_MEAN = 40.0;

    public const float DEMOTE_CEILING = 60.0;

    /**
     * @param list<float> $accuracies
     */
    public function assess(GameDifficulty $current, array $accuracies): ?SkillAssessment
    {
        $window = \array_slice($accuracies, 0, self::WINDOW);
        $count = \count($window);

        if ($count < self::MIN_SAMPLES) {
            return null;
        }

        $mean = round(array_sum($window) / $count, 1);

        $harder = $current->harder();

        if ($harder !== null && $mean >= self::PROMOTE_MEAN && min($window) >= self::PROMOTE_FLOOR) {
            return new SkillAssessment($current, $harder, $mean, $count);
        }

        $easier = $current->easier();

        if ($easier !== null && $mean <= self::DEMOTE_MEAN && max($window) <= self::DEMOTE_CEILING) {
            return new SkillAssessment($current, $easier, $mean, $count);
        }

        return null;
    }
}
