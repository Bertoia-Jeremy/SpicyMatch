<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\BriefingFacts;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\IdentityTranslator;

final class BriefingFactsTest extends TestCase
{
    private BriefingFacts $facts;

    protected function setUp(): void
    {
        $this->facts = new BriefingFacts(new AcademyManager(
            $this->createStub(SpicesRepository::class),
            $this->createStub(CompatibleSpiceFinder::class),
            new ArrayAdapter(),
            new IdentityTranslator(),
        ));
    }

    /**
     * @return iterable<string, array{GameMode}>
     */
    public static function modeProvider(): iterable
    {
        foreach (GameMode::cases() as $mode) {
            yield $mode->value => [$mode];
        }
    }

    #[DataProvider('modeProvider')]
    public function testEveryModeExposesFactsForEachDifficulty(GameMode $mode): void
    {
        $facts = $this->facts->for($mode);

        self::assertNotEmpty($facts);
        foreach ($facts as $fact) {
            self::assertMatchesRegularExpression('/^fa-solid fa-[a-z0-9]+(-[a-z0-9]+)*$/', $fact->icon);
            self::assertStringStartsWith('ui.edu.fact.', $fact->label);
            self::assertSame(['easy', 'medium', 'hard'], array_keys($fact->values));
        }
    }

    public function testChronoFactsFollowDifficultyBounds(): void
    {
        [$time, $options, $fastBonus] = $this->facts->for(GameMode::CHRONO);

        self::assertSame([
            'easy' => 90,
            'medium' => 75,
            'hard' => 60,
        ], $time->values);
        self::assertSame('ui.edu.fact.unit_seconds', $time->unit);
        self::assertSame([
            'easy' => 4,
            'medium' => 6,
            'hard' => 8,
        ], $options->values);
        self::assertSame(16, $fastBonus->valueFor(GameDifficulty::HARD));
    }
}
