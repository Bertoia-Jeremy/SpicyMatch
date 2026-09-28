<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Users;
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
            'difficulty' => 'easy',
            '_token' => $this->csrfToken($client),
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/education/play-live/chrono', (string) $client->getResponse()->headers->get('Location'));

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
