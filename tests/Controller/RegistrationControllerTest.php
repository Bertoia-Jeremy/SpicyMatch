<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Service\Security\AltchaManager;
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

    public function testEmbeddedModalRegistrationRedirectsToTargetAfterSuccess(): void
    {
        $client = static::createClient();
        $target = '/fr/epices/';

        $crawler = $client->request('GET', '/fr/education/');
        $form = $crawler->filter('#gate-register-form')
            ->form();
        $formName = $form->getName();

        $client->request('POST', '/register', [
            $formName => [
                'username' => 'gatereg'.random_int(1000, 9999),
                'mail' => '',
                'plainPassword' => 'Password1!',
                'altcha' => $this->solvedAltchaPayload(),
                'Valider' => '',
                '_token' => (string) $form[$formName.'[_token]']->getValue(),
            ],
            '_target_path' => $target,
        ]);

        self::assertResponseRedirects($target);
    }

    private function solvedAltchaPayload(): string
    {
        $altchaManager = static::getContainer()->get(AltchaManager::class);
        $challengeData = $altchaManager->createChallenge();

        $challenge = new Challenge(
            ChallengeParameters::fromArray($challengeData['parameters']),
            $challengeData['signature'],
        );

        $solution = new Altcha(hmacSignatureSecret: 'irrelevant-for-solving')
            ->solveChallenge(new SolveChallengeOptions(
                algorithm: new Sha(),
                challenge: $challenge,
            ));

        return new Payload($challenge, $solution)
            ->toBase64();
    }
}
