<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileTabControllerTest extends WebTestCase
{
    public function testProfileShellRendersTabBar(): void
    {
        $client = static::createClient();
        $this->loginFirstUser($client);

        $client->request('GET', '/fr/users/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[role="tablist"]');
    }

    public function testInvalidTabFallsBackToDashboard(): void
    {
        $client = static::createClient();
        $this->loginFirstUser($client);

        $client->request('GET', '/fr/users/profile?tab=garbage');

        self::assertResponseIsSuccessful();
    }

    public function testEachTabFragmentReturnsTurboFrame(): void
    {
        $client = static::createClient();
        $this->loginFirstUser($client);

        foreach (['dashboard', 'grimoire', 'history', 'lab'] as $tab) {
            $client->request('GET', '/fr/users/profile/tab/'.$tab);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('turbo-frame#frame-'.$tab);
        }
    }

    public function testHistoryFilterFragmentsAreSuccessful(): void
    {
        $client = static::createClient();
        $this->loginFirstUser($client);

        foreach (['all', 'favorites', 'manual'] as $filter) {
            $client->request('GET', '/fr/users/profile/tab/history?filter='.$filter);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('turbo-frame#frame-history');
        }
    }

    public function testUnknownTabFragmentIs404(): void
    {
        $client = static::createClient();
        $this->loginFirstUser($client);

        $client->request('GET', '/fr/users/profile/tab/garbage');

        self::assertResponseStatusCodeSame(404);
    }

    private function loginFirstUser(KernelBrowser $client): void
    {
        $user = static::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user, 'Fixtures must provide at least one user');
        $client->loginUser($user);
    }
}
