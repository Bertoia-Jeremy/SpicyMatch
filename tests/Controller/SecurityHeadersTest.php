<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression guard: the Nelmio security bundle must attach the expected headers
 * on every HTTP response. Dropping the bundle or misconfiguring CSP would
 * silently reopen clickjacking / XSS surface.
 */
final class SecurityHeadersTest extends WebTestCase
{
    /**
     * @dataProvider publicRoutesProvider
     */
    public function testResponseExposesSecurityHeaders(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $response = $client->getResponse();
        self::assertTrue($response->isSuccessful() || $response->isRedirect(), 'Route should reach a response');

        $headers = $response->headers;
        self::assertTrue($headers->has('X-Frame-Options'), "Missing X-Frame-Options on {$path}");
        self::assertSame('DENY', $headers->get('X-Frame-Options'), "X-Frame-Options must be DENY on {$path}");

        self::assertTrue($headers->has('X-Content-Type-Options'), "Missing X-Content-Type-Options on {$path}");
        self::assertSame(
            'nosniff',
            $headers->get('X-Content-Type-Options'),
            "X-Content-Type-Options must be nosniff on {$path}"
        );

        self::assertTrue($headers->has('Referrer-Policy'), "Missing Referrer-Policy on {$path}");

        // CSP — enforced only when bundle is enabled (test env has a relaxed policy).
        self::assertTrue($headers->has('Content-Security-Policy'), "Missing Content-Security-Policy on {$path}");
    }

    /**
     * @return array<array{0: string}>
     */
    public static function publicRoutesProvider(): array
    {
        return [['/'], ['/login'], ['/spices']];
    }
}
