<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleControllerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function targetProvider(): iterable
    {
        yield 'internal path' => ['/en/spices/?aromatic_group=x', '/en/spices/?aromatic_group=x'];
        yield 'protocol relative' => ['//evil.example', '/en/'];
        yield 'absolute url' => ['https://evil.example/', '/en/'];
        yield 'backslash trick' => ['/\\evil.example', '/en/'];
        yield 'header injection' => ["/fr\r\nLocation: https://evil.example", '/en/'];
        yield 'missing' => ['', '/en/'];
    }

    #[DataProvider('targetProvider')]
    public function testSwitchOnlyRedirectsToInternalTargets(string $target, string $location): void
    {
        $client = self::createClient();

        $client->request('GET', '/locale/en', [
            'target' => $target,
        ]);

        self::assertResponseRedirects($location);
    }
}
