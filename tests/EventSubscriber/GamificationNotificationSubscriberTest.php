<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\PendingGamificationNotification;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\EventSubscriber\GamificationNotificationSubscriber;
use App\Repository\PendingGamificationNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
final class GamificationNotificationSubscriberTest extends TestCase
{
    private TokenStorageInterface&MockObject $tokenStorage;
    private PendingGamificationNotificationRepository&MockObject $notifRepository;
    private EntityManagerInterface&MockObject $em;
    private Environment&MockObject $twig;
    private GamificationNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->notifRepository = $this->createMock(PendingGamificationNotificationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->subscriber = new GamificationNotificationSubscriber(
            $this->tokenStorage,
            $this->notifRepository,
            $this->em,
            $this->twig,
        );
    }

    public function testSkipsTurboFrameRequests(): void
    {
        $request = new Request();
        $request->headers->set('Turbo-Frame', 'some-frame');
        $response = new Response('<html><body>x</body></html>');
        $response->headers->set('Content-Type', 'text/html');

        // Expect: no notification lookup at all when inside a Turbo Frame — the toast
        // would otherwise land inside the frame and be lost.
        $this->notifRepository->expects(self::never())->method('findUndeliveredForUser');

        $this->subscriber->onKernelResponse($this->makeEvent($request, $response));
    }

    public function testSkipsResponsesWithoutClosingBodyTag(): void
    {
        $request = new Request();
        $response = new Response('<html><head></head>no body</html>');
        $response->headers->set('Content-Type', 'text/html');

        $this->tokenStorage->method('getToken')
            ->willReturn(null);
        $this->notifRepository->expects(self::never())->method('findUndeliveredForUser');

        $this->subscriber->onKernelResponse($this->makeEvent($request, $response));
    }

    public function testSkipsWhenUserHasOptedOutOfGamification(): void
    {
        $request = new Request();
        $response = new Response('<html><body></body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $user = new Users();
        $progression = new UserProgression();
        $progression->disableGamification();
        $user->setProgression($progression);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')
            ->willReturn($user);
        $this->tokenStorage->method('getToken')
            ->willReturn($token);

        // Opt-out MUST short-circuit BEFORE hitting the notification repo.
        $this->notifRepository->expects(self::never())->method('findUndeliveredForUser');

        $this->subscriber->onKernelResponse($this->makeEvent($request, $response));
    }

    public function testInjectsTurboStreamAndMarksNotificationAsDelivered(): void
    {
        $request = new Request();
        $response = new Response('<html><body>page</body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $user = new Users();
        $progression = new UserProgression();
        $user->setProgression($progression);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')
            ->willReturn($user);
        $this->tokenStorage->method('getToken')
            ->willReturn($token);

        $notif = $this->createMock(PendingGamificationNotification::class);
        $notif->method('getType')
            ->willReturn('xp_gained');
        $notif->method('getPayload')
            ->willReturn([
                'amount' => 10,
            ]);
        $notif->expects(self::once())->method('markDelivered');

        $this->notifRepository->method('findUndeliveredForUser')
            ->willReturn([$notif]);
        $this->twig->method('render')
            ->willReturn('<turbo-stream></turbo-stream>');

        $this->subscriber->onKernelResponse($this->makeEvent($request, $response));

        self::assertStringContainsString('<turbo-stream></turbo-stream>', $response->getContent() ?: '');
    }

    private function makeEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }
}
