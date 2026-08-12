<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Twig\Components\Education\ChronoGame;
use App\Twig\Components\Education\GuessWhoGame;
use App\Twig\Components\Education\HangmanGame;
use App\Twig\Components\Education\IntrusGame;
use App\Twig\Components\Education\SurvivalGame;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EducationLiveComponentAccessTest extends TestCase
{
    /**
     * @return iterable<string, array{0: class-string}>
     */
    public static function componentClassProvider(): iterable
    {
        yield 'SurvivalGame' => [SurvivalGame::class];
        yield 'IntrusGame' => [IntrusGame::class];
        yield 'GuessWhoGame' => [GuessWhoGame::class];
        yield 'HangmanGame' => [HangmanGame::class];
        yield 'ChronoGame' => [ChronoGame::class];
    }

    #[DataProvider('componentClassProvider')]
    public function testComponentRequiresRoleUser(string $componentClass): void
    {
        $reflection = new \ReflectionClass($componentClass);
        $attributes = $reflection->getAttributes(IsGranted::class);

        self::assertNotEmpty($attributes, $componentClass.' must carry #[IsGranted] to close the /_components/ firewall gap');
        self::assertSame('ROLE_USER', $attributes[0]->newInstance()->attribute);
    }
}
