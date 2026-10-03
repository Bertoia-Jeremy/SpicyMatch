<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\FaqCategory;
use App\Entity\FaqQuestion;
use App\Entity\FaqQuestionTranslation;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FaqControllerTest extends WebTestCase
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

    public function testRendersAccessibleAccordionAndASingleFaqPageSchema(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $category = new FaqCategory()
            ->setCode('ctrl-test')
            ->setName('Test')
            ->setPosition(-100);
        $visible = new FaqQuestion()
            ->setCategory($category)
            ->setQuestion('Visible ?')
            ->setAnswer('Oui.')
            ->setPublished(true)
            ->addTranslation(new FaqQuestionTranslation()
                ->setLocale('en')
                ->setQuestion('Visible <b>here</b>?')
                ->setAnswer('Yes.'));
        $draft = new FaqQuestion()
            ->setCategory($category)
            ->setQuestion('Brouillon secret ?')
            ->setAnswer('Non.')
            ->setPublished(false);
        $em->persist($category);
        $em->persist($visible);
        $em->persist($draft);
        $em->flush();

        $crawler = $client->request('GET', '/en/faq');

        self::assertResponseIsSuccessful();
        $id = $visible->getId();
        $button = $crawler->filter(sprintf('#faq-%d-q', $id));
        self::assertSame(sprintf('faq-%d-a', $id), $button->attr('aria-controls'));
        self::assertSame('false', $button->attr('aria-expanded'));
        self::assertSame(sprintf('faq-%d-q', $id), $crawler->filter(sprintf('#faq-%d-a', $id))->attr('aria-labelledby'));
        self::assertSame('ctrl-test', $crawler->filter('.faq-section')->first()->attr('data-category'));
        self::assertCount(0, $crawler->filter(sprintf('[data-faq-id="%d"]', $draft->getId())));

        $schemas = $crawler->filter('script[type="application/ld+json"]')
            ->each(static fn ($node): array => json_decode($node->text(), true, flags: \JSON_THROW_ON_ERROR));
        $faqPages = array_values(array_filter($schemas, static fn (array $schema): bool => ($schema['@type'] ?? null) === 'FAQPage'));
        self::assertCount(1, $faqPages);
        $names = array_column($faqPages[0]['mainEntity'], 'name');
        self::assertSame('Visible <b>here</b>?', $names[0]);
        self::assertNotContains('Brouillon secret ?', $names);
        self::assertStringNotContainsString('<b>here</b>', (string) $client->getResponse()->getContent());
    }
}
