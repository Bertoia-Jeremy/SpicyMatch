<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Achievement;
use App\Enum\AchievementTrigger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AchievementTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function makeValidAchievement(): Achievement
    {
        return (new Achievement())
            ->setSlug('first-match')
            ->setName('Premier mélange')
            ->setDescription('Réalise ton premier mélange.')
            ->setTrigger(AchievementTrigger::FIRST_MATCH);
    }

    public function testValidAchievementHasNoViolations(): void
    {
        $violations = $this->validator->validate($this->makeValidAchievement());

        self::assertCount(0, $violations);
    }

    public function testBlankSlugViolatesNotBlank(): void
    {
        $achievement = $this->makeValidAchievement()
            ->setSlug('');

        $violations = $this->validator->validate($achievement);

        self::assertGreaterThan(0, $violations->count());
    }

    public function testInvalidSlugFormatViolatesRegex(): void
    {
        $achievement = $this->makeValidAchievement()
            ->setSlug('Not A Slug!');

        $violations = $this->validator->validate($achievement);

        self::assertGreaterThan(0, $violations->count());
    }

    public function testBlankNameViolatesNotBlank(): void
    {
        $achievement = $this->makeValidAchievement()
            ->setName('');

        $violations = $this->validator->validate($achievement);

        self::assertGreaterThan(0, $violations->count());
    }

    public function testBlankDescriptionViolatesNotBlank(): void
    {
        $achievement = $this->makeValidAchievement()
            ->setDescription('');

        $violations = $this->validator->validate($achievement);

        self::assertGreaterThan(0, $violations->count());
    }
}
