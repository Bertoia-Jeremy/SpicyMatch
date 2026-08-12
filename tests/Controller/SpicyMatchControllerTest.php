<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpicyMatchControllerTest extends WebTestCase
{
    public function testAnonymousUserCanAccessLabIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/spicymatch/');

        self::assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserCanAccessLabIndex(): void
    {
        $client = static::createClient();
        $this->loginFirstUser($client);

        $client->request('GET', '/fr/spicymatch/');

        self::assertResponseIsSuccessful();
    }

    public function testAnonymousUserIsRedirectedToLoginOnViewRoute(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/spicymatch/view/1');

        self::assertResponseRedirects('/login');
    }

    private function loginFirstUser(KernelBrowser $client): void
    {
        $user = static::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user, 'Fixtures must provide at least one user');
        $client->loginUser($user);
    }
}
