<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use App\Entity\FaqQuestion;
use App\ValueObject\FaqEntries;

final readonly class FaqPageSchemaProvider implements SchemaProviderInterface
{
    public function supports(?object $subject): bool
    {
        return $subject instanceof FaqEntries && ! $subject->isEmpty();
    }

    public function build(?object $subject, string $locale): array
    {
        \assert($subject instanceof FaqEntries);

        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (FaqQuestion $question): array => [
                '@type' => 'Question',
                'name' => trim((string) $question->getLocalizedQuestion($locale)),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim((string) $question->getLocalizedAnswer($locale)),
                ],
            ], $subject->questions),
        ];
    }
}
