<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Enum\AchievementTrigger;
use App\Enum\ContentKind;
use App\Gamification\Evaluator\AllContentKindsReadEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AllContentKindsReadEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{list<ContentKind>, int, bool}>
     */
    public static function kindsProvider(): iterable
    {
        yield 'nothing read' => [[], 0, false];
        yield 'spices only' => [[ContentKind::SPICE, ContentKind::SPICE], 1, false];
        yield 'flavor missing' => [[ContentKind::SPICE, ContentKind::COMPOUND], 2, false];
        yield 'one of each kind' => [[ContentKind::FLAVOR, ContentKind::SPICE, ContentKind::COMPOUND], 3, true];
    }

    /**
     * @param list<ContentKind> $kinds
     */
    #[DataProvider('kindsProvider')]
    public function testBadgeRequiresOneReadOfEachContentKind(array $kinds, int $expectedProgress, bool $expectedMet): void
    {
        $stats = new UserStat();
        foreach ($kinds as $kind) {
            $stats->recordReadContentKind($kind);
        }
        $user = new Users();
        $user->setStats($stats);
        $progression = new UserProgression();
        $progression->setUser($user);
        $evaluator = new AllContentKindsReadEvaluator();
        $achievement = new Achievement()
            ->setTrigger(AchievementTrigger::ALL_CONTENT_KINDS_READ)
            ->setTriggerValue(3);

        self::assertSame($expectedProgress, $evaluator->currentValue($progression, []));
        self::assertSame($expectedMet, $evaluator->isMet($achievement, $progression, []));
    }

    public function testBadgeIsNotMetWithoutStats(): void
    {
        $progression = new UserProgression();
        $progression->setUser(new Users());

        self::assertFalse(new AllContentKindsReadEvaluator()->isMet(new Achievement(), $progression, []));
    }
}
