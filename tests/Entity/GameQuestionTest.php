<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\GameQuestion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GameQuestionTest extends TestCase
{
    public function testAnswerSetsAllFields(): void
    {
        $question = new GameQuestion();
        $question->setQuestionIndex(0);
        $question->setQuestionData([
            'prompt' => 'Test?',
        ]);

        $question->answer('Cumin', true, 1500);

        self::assertSame('Cumin', $question->getAnswerGiven());
        self::assertTrue($question->isCorrect());
        self::assertSame(1500, $question->getTimeSpentMs());
        self::assertNotNull($question->getAnsweredAt());
    }

    public function testAnswerIncorrect(): void
    {
        $question = new GameQuestion();
        $question->setQuestionIndex(1);

        $question->answer('Poivre', false);

        self::assertSame('Poivre', $question->getAnswerGiven());
        self::assertFalse($question->isCorrect());
        self::assertNull($question->getTimeSpentMs());
    }

    #[DataProvider('clientTimings')]
    public function testTimeSpentIsClamped(?int $given, ?int $expected): void
    {
        $question = new GameQuestion();
        $question->setQuestionIndex(0);

        $question->answer('Cumin', true, $given);

        self::assertSame($expected, $question->getTimeSpentMs());
    }

    /**
     * @return iterable<string, array{?int, ?int}>
     */
    public static function clientTimings(): iterable
    {
        yield 'valeur plausible conservée' => [1500, 1500];
        yield 'négatif ramené à zéro' => [-5000, 0];
        yield 'au plafond' => [GameQuestion::MAX_TIME_SPENT_MS, GameQuestion::MAX_TIME_SPENT_MS];
        yield 'au-delà du plafond' => [\PHP_INT_MAX, GameQuestion::MAX_TIME_SPENT_MS];
        yield 'absent' => [null, null];
    }
}
