<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\GameQuestion;
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
}
