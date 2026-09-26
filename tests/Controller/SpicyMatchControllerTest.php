<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\SpicyMatch;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testViewRendersDuoTooltipsAndMap(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $client->loginUser($user);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $lastMessageId = (int) $connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        $match = null;
        $duo = null;
        foreach ($em->getRepository(PreparationTips::class)->findAll() as $prep) {
            $spice = $prep->getSpice();
            $cook = $spice === null ? null : $em->getRepository(CookingTips::class)->findOneBy([
                'spice' => $spice,
            ]);
            if ($spice === null || ! $cook instanceof CookingTips) {
                continue;
            }

            $match = (new SpicyMatch())->setUser($user)
                ->addSpice($spice);
            $duo = (new SpiceDuo())
                ->setPreparationTip($prep)
                ->setCookingTip($cook)
                ->setTitle('Duo test chef')
                ->setEffect('Effet test chef')
                ->setScience('science')
                ->setExample('exemple')
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($match);
            $em->persist($duo);
            $em->flush();
            break;
        }
        self::assertNotNull($match, 'Fixtures must provide a spice with both tips');
        $matchId = $match->getId();
        $duoId = $duo?->getId();

        try {
            $crawler = $client->request('GET', '/fr/spicymatch/view/' . $matchId);

            self::assertResponseIsSuccessful();
            self::assertStringContainsString('"byPrep"', (string) $crawler->filter('[data-duo-map]')->attr('data-duo-map'));
            self::assertGreaterThanOrEqual(2, $crawler->filter('[role="tooltip"]')->count());
            self::assertStringContainsString('Duo test chef', $crawler->filter('[role="tooltip"]')->first()->text());
        } finally {
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            $duoToRemove = $em->find(SpiceDuo::class, $duoId);
            $matchToRemove = $em->find(SpicyMatch::class, $matchId);
            $duoToRemove !== null && $em->remove($duoToRemove);
            $matchToRemove !== null && $em->remove($matchToRemove);
            $em->flush();
            $em->getConnection()
                ->executeStatement('DELETE FROM messenger_messages WHERE id > :id', [
                    'id' => $lastMessageId,
                ]);
        }
    }

    private function loginFirstUser(KernelBrowser $client): void
    {
        $user = static::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user, 'Fixtures must provide at least one user');
        $client->loginUser($user);
    }
}
