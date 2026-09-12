<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginWithTargetQueryParamRedirectsToTargetAfterSuccess(): void
    {
        $client = static::createClient();
        $target = '/fr/education/briefing?mode=survival&difficulty=easy';

        $crawler = $client->request('GET', '/login?target=' . urlencode($target));
        self::assertResponseIsSuccessful();

        $hiddenTarget = $crawler->filter('#login-form input[name="_target_path"]')
            ->attr('value');
        self::assertSame($target, $hiddenTarget, 'Login page must prefill the hidden field with the validated target');

        $form = $crawler->filter('#login-form')
            ->form();
        $form['username'] = 'alice';
        $form['password'] = 'Alice1234!';
        $form['_target_path'] = $target;

        $client->submit($form);

        self::assertResponseRedirects($target);
    }

    public function testLoginWithUnsafeTargetQueryParamIsIgnored(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login?target=' . urlencode('https://evil.com'));
        self::assertResponseIsSuccessful();

        $hiddenTarget = $crawler->filter('#login-form input[name="_target_path"]')
            ->attr('value');
        self::assertSame('', $hiddenTarget, 'Unsafe target must never be reflected into the hidden field');
    }

    public function testLoginWithoutTargetRedirectsHome(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('#login-form')
            ->form();
        $form['username'] = 'alice';
        $form['password'] = 'Alice1234!';

        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect(), 'A successful login without a target must redirect');
        $location = $client->getResponse()
            ->headers->get('Location');
        self::assertMatchesRegularExpression('#^/[a-z]{2}/$#', (string) $location, 'Home fallback must be the locale-prefixed home route, not the gated target');
    }
}
