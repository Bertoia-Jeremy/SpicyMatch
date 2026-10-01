<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Spices;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testSpiceQueryParameterPreselectsItInTheLab(): void
    {
        $client = self::createClient();
        $spice = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Spices::class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertInstanceOf(Spices::class, $spice);

        $crawler = $client->request('GET', '/fr/spicymatch/?spice=' . $spice->getSlug());

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter(sprintf('input[data-model="spices.selectedSpices"][value="%d"][checked]', $spice->getId()))->count());
    }
}
