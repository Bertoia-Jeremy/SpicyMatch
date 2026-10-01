<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Entity\SpiceView;
use App\Entity\Users;
use App\Enum\ContentKind;
use App\Gamification\ContentReadTicket;
use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class ContentReadControllerTest extends WebTestCase
{
    public function testSpiceReadIsRecordedOnceTheTicketIsOldEnough(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        $spice = $this->first(Spices::class);
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();

        try {
            $connection->delete('spice_view', [
                'user_id' => $user->getId(),
                'spice_id' => $spice->getId(),
            ]);

            $this->postRead($client, ContentKind::SPICE, (int) $spice->getId(), $this->ticket(ContentKind::SPICE, $user, (int) $spice->getId(), '-10 seconds'));

            self::assertResponseStatusCodeSame(204);
            self::assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(SpiceView::class)->count([
                'user' => $user,
                'spice' => $spice,
            ]));
        } finally {
            $connection->rollBack();
        }
    }

    public function testCompoundReadIsAcceptedOnceTheTicketIsOldEnough(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        $compoundId = (int) $this->first(AromaticCompound::class)->getId();
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();

        try {
            $this->postRead($client, ContentKind::COMPOUND, $compoundId, $this->ticket(ContentKind::COMPOUND, $user, $compoundId, '-10 seconds'));

            self::assertResponseStatusCodeSame(204);
        } finally {
            $connection->rollBack();
        }
    }

    public function testReadIsRejectedBeforeTheMinimumReadTime(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        $spiceId = (int) $this->first(Spices::class)->getId();

        $this->postRead($client, ContentKind::SPICE, $spiceId, $this->ticket(ContentKind::SPICE, $user, $spiceId, '-2 seconds'));

        self::assertResponseStatusCodeSame(403);
    }

    private function login(KernelBrowser $client): Users
    {
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([
            'username' => 'bob',
        ]);
        self::assertInstanceOf(Users::class, $user);
        $client->loginUser($user);

        return $user;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function first(string $class): object
    {
        $entity = self::getContainer()->get(EntityManagerInterface::class)->getRepository($class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertInstanceOf($class, $entity);

        return $entity;
    }

    private function ticket(ContentKind $kind, Users $user, int $contentId, string $issuedAt): string
    {
        $secret = self::getContainer()->getParameter('kernel.secret');
        self::assertIsString($secret);

        return new ContentReadTicket($secret, new MockClock($issuedAt))
            ->issue($kind, (int) $user->getId(), $contentId);
    }

    private function postRead(KernelBrowser $client, ContentKind $kind, int $id, string $ticket): void
    {
        $client->request('POST', sprintf('/api/gamification/read/%s/%d', $kind->value, $id), server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'ticket' => $ticket,
        ], JSON_THROW_ON_ERROR));
    }
}
