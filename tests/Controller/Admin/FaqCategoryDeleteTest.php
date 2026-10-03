<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\FaqCategory;
use App\Entity\FaqQuestion;
use App\Entity\Users;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FaqCategoryDeleteTest extends WebTestCase
{
    private KernelBrowser $client;

    private Connection $connection;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $this->em->getRepository(Users::class)->findOneBy([
            'username' => 'admin',
        ]);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testCategoryWithQuestionsKeepsItsRowsAndShowsAFlash(): void
    {
        $empty = $this->category('faq-delete-empty');
        $used = $this->category('faq-delete-used');
        $this->em->persist(new FaqQuestion()->setCategory($used)->setQuestion('Q ?')->setAnswer('R.'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/faq-category');
        self::assertCount(1, $crawler->filter(sprintf('tr[data-id="%d"] .action-delete', $empty->getId())));
        self::assertCount(0, $crawler->filter(sprintf('tr[data-id="%d"] .action-delete', $used->getId())));
        $token = (string) $crawler->filter('input[name="token"]')
            ->first()
            ->attr('value');

        $this->client->request('POST', sprintf('/admin/faq-category/%d/delete', $used->getId()), [
            'token' => $token,
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('#flash-messages .alert-danger');

        $this->em->clear();
        self::assertNotNull($this->em->find(FaqCategory::class, $used->getId()));
    }

    private function category(string $code): FaqCategory
    {
        $category = new FaqCategory()
            ->setCode($code)
            ->setName($code);
        $this->em->persist($category);

        return $category;
    }
}
