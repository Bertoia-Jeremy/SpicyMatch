<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\SpiceDuoTranslation;
use App\Entity\Spices;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class SpiceDuoTest extends TestCase
{
    public function testSameSpiceRaisesNoViolation(): void
    {
        $spice = new Spices();
        $duo = $this->duoFor($spice, $spice);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $duo->validateSameSpice($context);
    }

    public function testCrossSpiceRaisesViolationOnCookingTip(): void
    {
        $duo = $this->duoFor(new Spices(), new Spices());

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('atPath')->with('cookingTip')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())->method('buildViolation')->willReturn($builder);

        $duo->validateSameSpice($context);
    }

    public function testLocalizedFieldsFallBackToFrenchWhenNoTranslation(): void
    {
        $duo = new SpiceDuo()
            ->setTitle('Fond doré')
            ->setEffect('e')
            ->setScience('s')
            ->setExample('x');

        self::assertSame('Fond doré', $duo->getLocalizedTitle('en'));
        self::assertSame('Fond doré', $duo->getLocalizedTitle('fr'));
    }

    public function testLocalizedFieldsPreferTranslationExceptInFrench(): void
    {
        $duo = new SpiceDuo()
            ->setTitle('Fond doré')
            ->setEffect('e')
            ->setScience('s')
            ->setExample('x');
        $duo->addTranslation(new SpiceDuoTranslation()->setLocale('en')->setTitle('Golden base'));

        self::assertSame('Golden base', $duo->getLocalizedTitle('en'));
        self::assertSame('e', $duo->getLocalizedEffect('en'));
        self::assertSame('Fond doré', $duo->getLocalizedTitle('fr'));
    }

    public function testCallbackConstraintIsDeclaredOnEntity(): void
    {
        $method = new \ReflectionMethod(SpiceDuo::class, 'validateSameSpice');

        self::assertCount(1, $method->getAttributes(Callback::class));
    }

    private function duoFor(Spices $prepSpice, Spices $cookSpice): SpiceDuo
    {
        return new SpiceDuo()
            ->setPreparationTip(new PreparationTips()->setSpice($prepSpice))
            ->setCookingTip(new CookingTips()->setSpice($cookSpice));
    }
}
