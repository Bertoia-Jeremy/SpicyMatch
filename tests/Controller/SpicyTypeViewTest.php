<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Spices;
use App\Entity\SpicyType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpicyTypeViewTest extends WebTestCase
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

    public function testGroupsSpecimensByFamilyUnderTheTypeIcon(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $type = $this->richestType();
        $members = $em->getRepository(Spices::class)
            ->findBy([
                'spicyType' => $type,
                'deleted_at' => null,
            ]);
        $families = array_unique(array_map(
            static fn (Spices $spice): string => (string) $spice->getAromaticGroups()?->getId(),
            $members,
        ));
        $siblings = \count($em->getRepository(SpicyType::class)->findBy([
            'deleted_at' => null,
        ])) - 1;

        $crawler = $client->request('GET', '/en/spices/spice-types/' . $type->getLocalizedSlug('en'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', (string) $type->getLocalizedName('en'));
        self::assertSame($type->getIcon(), $crawler->filter('.kind-medal i')->attr('class'));
        self::assertCount(\count($members), $crawler->filter('.kind-specimens a.spice-row-link[href^="/en/spices/"]'));
        self::assertCount(\count($families), $crawler->filter('.kind-bucket'));
        self::assertCount($siblings, $crawler->filter('.kind-other'));
        self::assertCount(0, $crawler->filter(sprintf('.kind-other[href$="/%s"]', $type->getLocalizedSlug('en'))));
    }

    public function testUnexpectedIconFallsBackToTheDefaultOne(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $type = $this->richestType();
        $type->setIcon('fa-solid fa-leaf" onclick="x');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', '/fr/epices/types-epices/' . $type->getLocalizedSlug('fr'));

        self::assertResponseIsSuccessful();
        self::assertSame('fa-solid fa-tags', $crawler->filter('.kind-medal i')->attr('class'));
        self::assertStringNotContainsString('onclick', (string) $client->getResponse()->getContent());
    }

    private function richestType(): SpicyType
    {
        $types = self::getContainer()->get(EntityManagerInterface::class)->getRepository(SpicyType::class)->findBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertNotEmpty($types);
        usort($types, static fn (SpicyType $a, SpicyType $b): int => $b->getSpices()->count() <=> $a->getSpices()->count());

        return $types[0];
    }
}
