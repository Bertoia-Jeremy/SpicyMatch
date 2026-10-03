<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\FaqCategory;
use App\Entity\FaqQuestion;
use App\Entity\Spices;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class SpiceFaqTest extends WebTestCase
{
    private KernelBrowser $client;

    private Connection $connection;

    private Spices $spice;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $spice = self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery(sprintf('SELECT s FROM %s s WHERE s.deleted_at IS NULL AND NOT EXISTS (SELECT q.id FROM %s q WHERE s MEMBER OF q.spices) ORDER BY s.id ASC', Spices::class, FaqQuestion::class))
            ->setMaxResults(1)
            ->getOneOrNullResult();
        self::assertInstanceOf(Spices::class, $spice);
        $this->spice = $spice;
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testBoundPublishedQuestionsRenderASectionWithItsSchema(): void
    {
        $question = $this->bind('Se conserve-t-elle longtemps ?', true);

        $crawler = $this->view();

        self::assertCount(1, $crawler->filter('nav.spc-anchors a[href="#spc-faq"]'));
        self::assertCount(1, $crawler->filter(sprintf('#spc-faq #faq-%d-q', $question->getId())));
        self::assertSame(['Se conserve-t-elle longtemps ?'], array_column($this->faqPages($crawler)[0]['mainEntity'] ?? [], 'name'));
    }

    public function testNothingAboutFaqIsRenderedWithoutPublishedQuestions(): void
    {
        $this->bind('Brouillon ?', false);

        $crawler = $this->view();

        self::assertCount(0, $crawler->filter('#spc-faq, .faq-accordion, a[href="#spc-faq"]'));
        self::assertSame([], $this->faqPages($crawler));
    }

    private function bind(string $text, bool $published): FaqQuestion
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $category = new FaqCategory()
            ->setCode('spice-faq-test')
            ->setName('Test');
        $question = new FaqQuestion()
            ->setCategory($category)
            ->setQuestion($text)
            ->setAnswer('Réponse.')
            ->setPublished($published)
            ->addSpice($this->spice);
        $em->persist($category);
        $em->persist($question);
        $em->flush();

        return $question;
    }

    private function view(): Crawler
    {
        $crawler = $this->client->request('GET', '/fr/epices/' . $this->spice->getSlug());
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function faqPages(Crawler $crawler): array
    {
        $schemas = $crawler->filter('script[type="application/ld+json"]')
            ->each(static fn (Crawler $node): array => json_decode($node->text(), true, flags: \JSON_THROW_ON_ERROR));

        return array_values(array_filter($schemas, static fn (array $schema): bool => ($schema['@type'] ?? null) === 'FAQPage'));
    }
}
