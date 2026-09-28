<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpicyMatchControllerTest extends WebTestCase
{
    public function testAnonymousUserCanAccessLabIndex(): void
    {
        $client = self::createClient();

        $client->request('GET', '/fr/spicymatch/');

        self::assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserCanAccessLabIndex(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/fr/spicymatch/');

        self::assertResponseIsSuccessful();
    }
}
