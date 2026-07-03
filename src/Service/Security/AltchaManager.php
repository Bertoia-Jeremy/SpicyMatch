<?php

declare(strict_types=1);

namespace App\Service\Security;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\Solution;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Psr\Cache\CacheItemPoolInterface;

final readonly class AltchaManager
{
    public const COST = 10;

    public const KEY_PREFIX_BYTES = 2;

    public const TTL_SECONDS = 600;

    public function __construct(
        private string $altchaHmacKey,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function createChallenge(): array
    {
        return $this->altcha()
            ->createChallenge(new CreateChallengeOptions(
                algorithm: new Sha(),
                cost: self::COST,
                keyPrefix: bin2hex(random_bytes(self::KEY_PREFIX_BYTES)),
                expiresAt: time() + self::TTL_SECONDS,
            ))
            ->toArray();
    }

    public function verify(string $base64Payload): bool
    {
        $payload = $this->parsePayload($base64Payload);

        if (null === $payload) {
            return false;
        }

        if ($this->isReplay($base64Payload)) {
            return false;
        }

        return $this->altcha()
            ->verifySolution(new VerifySolutionOptions(payload: $payload, algorithm: new Sha()))
            ->verified;
    }

    private function parsePayload(string $base64Payload): ?Payload
    {
        $json = base64_decode($base64Payload, true);

        if (false === $json) {
            return null;
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            return null;
        }

        $challengeData = $data['challenge'] ?? null;
        $solutionData = $data['solution'] ?? null;

        if (! is_array($challengeData) || ! is_array($solutionData)) {
            return null;
        }

        $parameters = $challengeData['parameters'] ?? null;
        $signature = $challengeData['signature'] ?? null;
        $counter = $solutionData['counter'] ?? null;
        $derivedKey = $solutionData['derivedKey'] ?? null;

        if (! is_array($parameters) || ! is_string($signature) || ! is_int($counter) || ! is_string($derivedKey)) {
            return null;
        }

        return new Payload(
            new Challenge(ChallengeParameters::fromArray($parameters), $signature),
            new Solution($counter, $derivedKey),
        );
    }

    private function isReplay(string $base64Payload): bool
    {
        $item = $this->cache->getItem('altcha.used.'.hash('sha256', $base64Payload));

        if ($item->isHit()) {
            return true;
        }

        $item->set(true)
            ->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);

        return false;
    }

    private function altcha(): Altcha
    {
        return new Altcha(hmacSignatureSecret: $this->altchaHmacKey);
    }
}
