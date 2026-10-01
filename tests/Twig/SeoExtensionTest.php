<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Seo\JsonLd\SchemaProviderInterface;
use App\Twig\Extension\SeoExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SeoExtensionTest extends TestCase
{
    private const string HOSTILE = '</script><script>alert("x")</script>&\'';

    public function testJsonLdCannotBreakOutOfTheScriptElement(): void
    {
        $output = (string) $this->extension([
            $this->provider([
                'name' => self::HOSTILE,
            ])])->jsonLd(new \stdClass());

        self::assertStringNotContainsString('<', $output);
        self::assertStringNotContainsString('&', $output);
        self::assertStringNotContainsString("'", $output);
        self::assertSame([
            '@context' => 'https://schema.org',
            'name' => self::HOSTILE,
        ], json_decode($output, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testSeveralSchemasAreGroupedInAGraph(): void
    {
        $output = (string) $this->extension([
            $this->provider([
                '@type' => 'BreadcrumbList',
            ]),
            $this->provider([
                '@type' => 'Thing',
            ]),
        ])->jsonLd(new \stdClass());

        $document = json_decode($output, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame([[
            '@type' => 'BreadcrumbList',
        ], [
            '@type' => 'Thing',
        ]], $document['@graph']);
    }

    public function testUnsupportedSubjectRendersNothing(): void
    {
        self::assertSame('', (string) $this->extension([])->jsonLd(new \stdClass()));
    }

    /**
     * @param list<SchemaProviderInterface> $providers
     */
    private function extension(array $providers): SeoExtension
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return new SeoExtension($providers, $requestStack);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function provider(array $schema): SchemaProviderInterface
    {
        return new readonly class($schema) implements SchemaProviderInterface {
            /**
             * @param array<string, mixed> $schema
             */
            public function __construct(
                private array $schema,
            ) {
            }

            public function supports(?object $subject): bool
            {
                return $subject !== null;
            }

            public function build(?object $subject, string $locale): array
            {
                return $this->schema;
            }
        };
    }
}
