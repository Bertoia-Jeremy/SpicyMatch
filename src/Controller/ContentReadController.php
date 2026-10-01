<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\ContentKind;
use App\Gamification\ContentReadTicket;
use App\Message\ContentReadEvent;
use App\Message\SpiceReadEvent;
use App\Repository\SpiceViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/gamification')]
#[IsGranted('ROLE_USER')]
class ContentReadController extends AbstractController
{
    #[Route('/read/{kind}/{id}', name: 'api_gamification_content_read', requirements: [
        'kind' => new EnumRequirement(ContentKind::class),
        'id' => '\d+',
    ], methods: ['POST'])]
    public function __invoke(
        ContentKind $kind,
        int $id,
        Request $request,
        EntityManagerInterface $em,
        SpiceViewRepository $spiceViewRepository,
        ContentReadTicket $ticket,
        MessageBusInterface $bus,
        #[CurrentUser]
        Users $user,
    ): Response {
        $content = $em->find(match ($kind) {
            ContentKind::SPICE => Spices::class,
            ContentKind::COMPOUND => AromaticCompound::class,
            ContentKind::FLAVOR => AlchemyFlavors::class,
        }, $id);
        if ($content === null || $content->getDeletedAt() !== null) {
            return new JsonResponse([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $value = $request->toArray()['ticket'] ?? '';
        } catch (\Throwable) {
            $value = '';
        }

        $userId = (int) $user->getId();
        if (! is_string($value) || ! $ticket->isRedeemable($value, $kind, $userId, $id)) {
            return new JsonResponse([
                'error' => 'Invalid ticket',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($content instanceof Spices) {
            $isNew = $spiceViewRepository->recordView($user, $content);
            $bus->dispatch(new SpiceReadEvent($userId, $id, $isNew));
        } else {
            $bus->dispatch(new ContentReadEvent($userId, $kind));
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
