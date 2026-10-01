<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RobotsController extends AbstractController
{
    private const array DISALLOWED_PATHS = ['/admin', '/api/', '/locale/', '/_components/'];

    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = 'Disallow: ' . $path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $this->generateUrl('PrestaSitemapBundle_index', [
            '_format' => 'xml',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
