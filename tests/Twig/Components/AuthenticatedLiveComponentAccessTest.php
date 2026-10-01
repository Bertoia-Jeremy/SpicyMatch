<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Security\Voter\GameAccessVoter;
use App\Twig\Components\Education\ChronoGame;
use App\Twig\Components\Education\GuessWhoGame;
use App\Twig\Components\Education\HangmanGame;
use App\Twig\Components\Education\IntrusGame;
use App\Twig\Components\Education\SurvivalGame;
use App\Twig\Components\EnginePreferences;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AuthenticatedLiveComponentAccessTest extends TestCase
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
        yield 'EnginePreferences' => [EnginePreferences::class];
    }

    #[DataProvider('componentClassProvider')]
    public function testComponentRequiresRoleUser(string $componentClass): void
    {
        $reflection = new \ReflectionClass($componentClass);
        $attributes = $reflection->getAttributes(IsGranted::class);

        self::assertNotEmpty($attributes, $componentClass . ' must carry #[IsGranted] to close the /_components/ firewall gap');
        self::assertSame('ROLE_USER', $attributes[0]->newInstance()->attribute);
    }

    /**
     * @return iterable<string, array{0: class-string}>
     */
    public static function gameComponentProvider(): iterable
    {
        yield 'SurvivalGame' => [SurvivalGame::class];
        yield 'IntrusGame' => [IntrusGame::class];
        yield 'GuessWhoGame' => [GuessWhoGame::class];
        yield 'HangmanGame' => [HangmanGame::class];
        yield 'ChronoGame' => [ChronoGame::class];
    }

    #[DataProvider('gameComponentProvider')]
    public function testGameComponentRequiresGameAccess(string $componentClass): void
    {
        $granted = array_map(
            static fn (\ReflectionAttribute $attribute): mixed => $attribute->newInstance()
                ->attribute,
            new \ReflectionClass($componentClass)
                ->getAttributes(IsGranted::class),
        );

        self::assertContains(GameAccessVoter::PLAY, $granted);
    }
}
