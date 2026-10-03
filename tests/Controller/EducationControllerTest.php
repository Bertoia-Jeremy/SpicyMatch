<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Repository\UsersRepository;
use App\Service\GamificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class EducationControllerTest extends WebTestCase
{
    public function testStartWithLockedModeRedirectsToIndexWithFlash(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $this->setUserXp($user, 0);

        $client->request('POST', '/fr/education/start', [
            'mode' => 'chrono',
            'difficulty' => 'easy',
            '_token' => $this->csrfToken($client),
        ]);

        self::assertResponseRedirects('/fr/education/');
        $session = $client->getRequest()
            ->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        $flashes = $session->getFlashBag()
            ->peek('warning');
        self::assertNotEmpty($flashes, 'Expected a warning flash about the required level.');
        self::assertStringContainsString('8', $flashes[0]);
    }

    public function testStartWithUnlockedModeProceedsToPlayLive(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $this->setUserXp($user, 100_000);

        $client->request('POST', '/fr/education/start', [
            'mode' => 'chrono',
            'difficulty' => 'hard',
            '_token' => $this->csrfToken($client),
        ]);

        self::assertResponseRedirects('/fr/education/play-live/chrono?difficulty=hard');

        $this->setUserXp($user, 0);
    }

    public function testPlayLiveDirectUrlOnLockedModeRedirectsToIndex(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $this->setUserXp($user, 0);

        $client->request('GET', '/fr/education/play-live/chrono');

        self::assertResponseRedirects('/fr/education/');
    }

    public function testBriefingPreselectsDifficultyAndOffersLaunch(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $this->setUserXp($user, 100_000);

        try {
            $crawler = $client->request('GET', '/fr/education/briefing?mode=chrono&difficulty=hard');

            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('input[name="difficulty"][value="hard"][checked]'));
            self::assertCount(3, $crawler->filter('input[type="radio"][name="difficulty"]'));
            self::assertCount(1, $crawler->filter('form[action*="/education/start"] button[type="submit"]'));
            self::assertSame('60', trim($crawler->filter('.brief-fact-value span')->first()->text()));
        } finally {
            $this->setUserXp($user, 0);
        }
    }

    public function testBriefingFallsBackToPreferredDifficultyAndInterpolatesRules(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $previous = $user->getPreferredDifficulty();
        $user->setPreferredDifficulty(GameDifficulty::MEDIUM);
        $em->flush();

        try {
            $crawler = $client->request('GET', '/fr/education/briefing?mode=qcm');

            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('input[name="difficulty"][value="medium"][checked]'));
            self::assertStringContainsString('parmi 4 propositions', $crawler->filter('.brief-rule')->first()->text());
        } finally {
            $user->setPreferredDifficulty($previous);
            $em->flush();
        }
    }

    public function testBriefingOnLockedModeHidesLaunchAndShowsLevelHint(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $this->setUserXp($user, 0);

        $crawler = $client->request('GET', '/fr/education/briefing?mode=chrono');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action*="/education/start"] button[type="submit"]'));
        self::assertStringContainsString('8', $crawler->filter('.brief-blocked[role="status"]')->text());
    }

    public function testBriefingRedirectsWhenGamificationDisabled(): void
    {
        $client = static::createClient();
        $user = $this->loginUserByUsername($client, 'bob');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $progression = static::getContainer()->get(GamificationManager::class)->getOrCreateProgression($user);
        $progression->disableGamification();
        $em->flush();

        try {
            $client->request('GET', '/fr/education/briefing?mode=qcm');

            self::assertResponseRedirects('/fr/education/');
            $session = $client->getRequest()
                ->getSession();
            self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
            self::assertNotEmpty($session->getFlashBag()->peek('warning'));
        } finally {
            $progression->enableGamification();
            $em->flush();
        }
    }

    private function loginUserByUsername(KernelBrowser $client, string $username): Users
    {
        $user = static::getContainer()->get(UsersRepository::class)->findOneBy([
            'username' => $username,
        ]);
        self::assertNotNull($user, \sprintf('Fixtures must provide a user named "%s"', $username));
        $client->loginUser($user);

        return $user;
    }

    private function setUserXp(Users $user, int $xp): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $progression = static::getContainer()->get(GamificationManager::class)->getOrCreateProgression($user);

        $reflection = new \ReflectionProperty($progression, 'xp');
        $reflection->setValue($progression, $xp);
        $em->flush();
    }

    private function csrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/fr/education/briefing?mode=qcm');

        return $crawler->filter('form[action*="/education/start"] input[name="_token"]')
            ->attr('value');
    }
}
