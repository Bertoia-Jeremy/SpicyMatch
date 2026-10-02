<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountDeletionTest extends WebTestCase
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

    public function testDeletionSoftDeletesAndLogsOut(): void
    {
        [$client, $user] = $this->loggedInClient();

        $client->request('POST', '/fr/users/' . $user->getId(), [
            '_token' => $this->deleteToken($client, $user),
        ]);

        self::assertResponseRedirects('/fr/');
        self::assertNotNull($this->reload($user)->getDeletedAt());
        $client->request('GET', '/fr/users/profile');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testInvalidTokenKeepsAccountAndSession(): void
    {
        [$client, $user] = $this->loggedInClient();

        $client->request('POST', '/fr/users/' . $user->getId(), [
            '_token' => 'invalid',
        ]);

        self::assertResponseRedirects('/fr/users/');
        self::assertNull($this->reload($user)->getDeletedAt());
        $client->request('GET', '/fr/users/profile');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{KernelBrowser, Users}
     */
    private function loggedInClient(): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([
            'username' => 'alice',
        ]);
        self::assertNotNull($user);
        $client->loginUser($user);

        return [$client, $user];
    }

    private function deleteToken(KernelBrowser $client, Users $user): string
    {
        $crawler = $client->request('GET', '/fr/users/profile/tab/lab');
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('form[action="/fr/users/' . $user->getId() . '"] input[name="_token"]')->attr('value');
    }

    private function reload(Users $user): Users
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $fresh = $em->find(Users::class, $user->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }
}
