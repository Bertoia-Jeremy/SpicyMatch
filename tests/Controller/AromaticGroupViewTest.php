<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AromaticGroupViewTest extends WebTestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->connection = null;
        parent::tearDown();
    }

    public function testRendersPaletteMembersAndSiblingFamilies(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $group = $this->firstGroup();
        $members = $em->getRepository(Spices::class)
            ->count([
                'aromaticGroups' => $group,
                'deleted_at' => null,
            ]);
        $siblings = \count($em->getRepository(AromaticGroups::class)->findBy([
            'deleted_at' => null,
        ])) - 1;

        $crawler = $client->request('GET', '/en/spices/aromatic-groups/' . $group->getLocalizedSlug('en'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', (string) $group->getLocalizedName('en'));
        self::assertStringContainsString('--fam-color: ' . $group->getColor(), (string) $crawler->filter('.fam-page')->attr('style'));
        self::assertCount($members, $crawler->filter('.fam-members a.spice-row-link[href^="/en/spices/"]'));
        self::assertCount($siblings, $crawler->filter('.fam-other'));
        self::assertCount(0, $crawler->filter(sprintf('.fam-other[href$="/%s"]', $group->getLocalizedSlug('en'))));
        self::assertLessThanOrEqual(6, $crawler->filter('.fam-molecule[href^="/en/spices/aromatic-compounds/"]')->count());
    }

    public function testInvalidColourIsNeverInlined(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $group = $this->firstGroup();
        $group->setColor('red;background:url(x)');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', '/fr/epices/groupes-aromatiques/' . $group->getLocalizedSlug('fr'));

        self::assertResponseIsSuccessful();
        self::assertNull($crawler->filter('.fam-page')->attr('style'));
        self::assertStringNotContainsString('url(x)', (string) $client->getResponse()->getContent());
    }

    private function firstGroup(): AromaticGroups
    {
        $group = self::getContainer()->get(EntityManagerInterface::class)->getRepository(AromaticGroups::class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertInstanceOf(AromaticGroups::class, $group);

        return $group;
    }
}
