<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\FaqCategory;
use App\Entity\FaqCategoryTranslation;
use App\Entity\FaqQuestion;
use App\Entity\FaqQuestionTranslation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FaqValidationTest extends KernelTestCase
{
    public function testRejectsBlankAndDuplicateQuestionTranslations(): void
    {
        $question = new FaqQuestion()
            ->setCategory(new FaqCategory())
            ->setQuestion('Question ?')
            ->setAnswer('Réponse.')
            ->addTranslation(new FaqQuestionTranslation()
                ->setLocale('en')
                ->setQuestion('Question?')
                ->setAnswer('Answer.'))
            ->addTranslation(new FaqQuestionTranslation()
                ->setLocale('en')
                ->setQuestion('Again?')
                ->setAnswer(''));

        self::assertSame(['translations[1].answer', 'translations[1].locale'], $this->violations($question));
    }

    public function testRejectsDuplicateCategoryTranslations(): void
    {
        $category = new FaqCategory()
            ->setCode('general')
            ->setName('Général')
            ->addTranslation(new FaqCategoryTranslation()
                ->setLocale('es')
                ->setName('General'))
            ->addTranslation(new FaqCategoryTranslation()
                ->setLocale('es')
                ->setName(''));

        self::assertSame(['translations[1].locale', 'translations[1].name'], $this->violations($category));
    }

    /**
     * @return list<string>
     */
    private function violations(object $subject): array
    {
        $paths = array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array(self::getContainer()->get(ValidatorInterface::class)->validate($subject)),
        );
        sort($paths);

        return array_values(array_filter($paths, static fn (string $path): bool => str_starts_with($path, 'translations')));
    }
}
