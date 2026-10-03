<?php

declare(strict_types=1);

namespace App\Tests\Seo\JsonLd;

use App\Entity\FaqQuestion;
use App\Entity\FaqQuestionTranslation;
use App\Seo\JsonLd\FaqPageSchemaProvider;
use App\ValueObject\FaqEntries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FaqPageSchemaProviderTest extends TestCase
{
    public function testBuildsLocalizedQuestionsWithFrenchFallback(): void
    {
        $translated = new FaqQuestion()
            ->setQuestion('Est-ce gratuit ?')
            ->setAnswer("Oui.\n")
            ->addTranslation(new FaqQuestionTranslation()
                ->setLocale('en')
                ->setQuestion(' Is it free? ')
                ->setAnswer('Yes.'));
        $untranslated = new FaqQuestion()
            ->setQuestion('Faut-il un compte ?')
            ->setAnswer('Non.');

        self::assertSame([
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'Is it free?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Yes.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Faut-il un compte ?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Non.',
                    ],
                ],
            ],
        ], new FaqPageSchemaProvider()
            ->build(new FaqEntries([$translated, $untranslated]), 'en'));
    }

    /**
     * @return iterable<string, array{?object, bool}>
     */
    public static function subjects(): iterable
    {
        yield 'entries' => [new FaqEntries([new FaqQuestion()]), true];
        yield 'empty entries' => [new FaqEntries([]), false];
        yield 'other object' => [new \stdClass(), false];
        yield 'no subject' => [null, false];
    }

    #[DataProvider('subjects')]
    public function testSupportsOnlyNonEmptyEntries(?object $subject, bool $expected): void
    {
        self::assertSame($expected, new FaqPageSchemaProvider()->supports($subject));
    }
}
