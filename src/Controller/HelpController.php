<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/help', defaults: [
    '_locale' => 'fr',
])]
final class HelpController extends AbstractController
{
    private const KNOWN_TOPICS = ['gamification', 'academie', 'easter-eggs'];

    #[Route('', name: 'help_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('help/index.html.twig');
    }

    #[Route('/{topic}', name: 'help_topic', methods: ['GET'], requirements: [
        'topic' => 'gamification|academie|easter-eggs',
    ])]
    public function topic(string $topic): Response
    {
        if (! \in_array($topic, self::KNOWN_TOPICS, true)) {
            throw $this->createNotFoundException();
        }

        return $this->render(sprintf('help/%s.html.twig', $topic));
    }
}
