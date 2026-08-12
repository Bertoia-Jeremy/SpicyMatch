<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    public function testRegisterPagePrefillsTargetFromQueryParam(): void
    {
        $client = static::createClient();
        $target = '/fr/education/briefing?mode=survival&difficulty=easy';

        $crawler = $client->request('GET', '/register?target='.urlencode($target));

        self::assertResponseIsSuccessful();
        self::assertSame($target, $crawler->filter('#register-form input[name="_target_path"]')->attr('value'));
    }

    public function testRegisterPageIgnoresUnsafeTarget(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register?target='.urlencode('https://evil.com'));

        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->filter('#register-form input[name="_target_path"]')->attr('value'));
    }
}
