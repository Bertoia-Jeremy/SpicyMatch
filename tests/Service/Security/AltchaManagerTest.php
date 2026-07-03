<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Service\Security\AltchaManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class AltchaManagerTest extends TestCase
{
    private const HMAC_KEY = 'test-secret';

    private AltchaManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AltchaManager(self::HMAC_KEY, new ArrayAdapter());
    }

    public function testCreateChallengeExposesParametersAndSignature(): void
    {
        $challenge = $this->manager->createChallenge();

        $this->assertArrayHasKey('parameters', $challenge);
        $this->assertArrayHasKey('signature', $challenge);
        $this->assertIsArray($challenge['parameters']);
        $this->assertSame('SHA-256', $challenge['parameters']['algorithm']);
    }

    public function testVerifyAcceptsSolvedChallenge(): void
    {
        $this->assertTrue($this->manager->verify($this->solvedPayload()));
    }

    public function testVerifyRejectsReplayedPayload(): void
    {
        $payload = $this->solvedPayload();

        $this->assertTrue($this->manager->verify($payload));
        $this->assertFalse($this->manager->verify($payload));
    }

    public function testVerifyRejectsGarbage(): void
    {
        $this->assertFalse($this->manager->verify('not-base64-json'));
        $this->assertFalse($this->manager->verify(base64_encode('{"challenge":null,"solution":null}')));
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $data = json_decode(base64_decode($this->solvedPayload(), true) ?: '', true);
        $this->assertIsArray($data);

        $data['challenge']['signature'] = str_repeat('0', 64);

        $this->assertFalse($this->manager->verify(base64_encode((string) json_encode($data))));
    }

    private function solvedPayload(): string
    {
        $challengeData = $this->manager->createChallenge();

        $challenge = new Challenge(
            ChallengeParameters::fromArray($challengeData['parameters']),
            $challengeData['signature'],
        );

        $solution = new Altcha(hmacSignatureSecret: self::HMAC_KEY)->solveChallenge(new SolveChallengeOptions(
            algorithm: new Sha(),
            challenge: $challenge,
        ));

        $this->assertNotNull($solution);

        return new Payload($challenge, $solution)
            ->toBase64();
    }
}
