<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Enum\GameDifficulty;
use App\Service\Education\OrdinalWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrdinalWindowTest extends TestCase
{
    /**
     * @param list<int> $expectedIds
     */
    #[DataProvider('windowProvider')]
    public function testSelectReturnsTheOrdinalSlice(
        int $total,
        GameDifficulty $difficulty,
        int $need,
        array $expectedIds,
    ): void {
        $ordered = [];
        for ($index = 1; $index <= $total; ++$index) {
            $ordered[] = [
                'id' => $index,
            ];
        }

        $window = OrdinalWindow::select($ordered, $difficulty, $need);

        self::assertSame($expectedIds, array_column($window, 'id'));
    }

    /**
     * @return iterable<string, array{int, GameDifficulty, int, list<int>}>
     */
    public static function windowProvider(): iterable
    {
        yield 'facile = tête de liste' => [9, GameDifficulty::EASY, 3, [1, 2, 3]];
        yield 'moyen = tiers médian' => [9, GameDifficulty::MEDIUM, 3, [4, 5, 6]];
        yield 'difficile = queue de liste' => [9, GameDifficulty::HARD, 3, [7, 8, 9]];
        yield 'moyen et difficile se recouvrent sur liste courte' => [4, GameDifficulty::MEDIUM, 1, [3, 4]];
        yield 'fenêtre plus courte que le besoin, repli sur toute la liste' => [
            2,
            GameDifficulty::EASY,
            3,
            [1, 2],
        ];
        yield 'tiers médian vide, repli sur toute la liste' => [2, GameDifficulty::MEDIUM, 3, [1, 2]];
        yield 'liste vide' => [0, GameDifficulty::HARD, 3, []];
    }
}
