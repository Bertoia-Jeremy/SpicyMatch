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
    /**
     * @var array<string, string>
     */
    public const array KNOWN_TOPICS = [
        'gamification' => 'ui.help.gamification',
        'academie' => 'ui.common.academy',
        'easter-eggs' => 'ui.help.easter_eggs',
    ];

    #[Route('', name: 'help_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('help/index.html.twig');
    }

    #[Route('/{topic}', name: 'help_topic', requirements: [
        'topic' => 'gamification|academie|easter-eggs',
    ], methods: ['GET'])]
    public function topic(string $topic): Response
    {
        if (! \array_key_exists($topic, self::KNOWN_TOPICS)) {
            throw $this->createNotFoundException();
        }

        return $this->render(sprintf('help/%s.html.twig', $topic));
    }
}
