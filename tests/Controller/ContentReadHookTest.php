<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContentReadHookTest extends WebTestCase
{
    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function contentProvider(): iterable
    {
        yield 'spice' => [Spices::class, 'view_spice', 'spice'];
        yield 'compound' => [AromaticCompound::class, 'view_aromatic_compound', 'compound'];
        yield 'flavor' => [AlchemyFlavors::class, 'view_alchemy_flavors', 'flavor'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('contentProvider')]
    public function testLoggedUserGetsADeferredReadTicketOnEachContentPage(string $class, string $route, string $kind): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([
            'username' => 'bob',
        ]);
        self::assertNotNull($user);
        $client->loginUser($user);
        $content = self::getContainer()->get(EntityManagerInterface::class)->getRepository($class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertTrue(method_exists($content, 'getId') && method_exists($content, 'getSlug'));

        $crawler = $client->request('GET', self::getContainer()->get(UrlGeneratorInterface::class)->generate($route, [
            '_locale' => 'fr',
            'slug' => $content->getSlug(),
        ]));

        self::assertResponseIsSuccessful();
        $hook = $crawler->filter('[x-data="contentRead"]');
        self::assertCount(1, $hook);
        self::assertSame(sprintf('/api/gamification/read/%s/%d', $kind, $content->getId()), $hook->attr('data-read-url'));
        self::assertNotEmpty($hook->attr('data-read-ticket'));
    }
}
