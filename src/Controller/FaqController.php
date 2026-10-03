<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FaqCategory;
use App\Entity\FaqQuestion;
use App\Repository\FaqQuestionRepository;
use App\ValueObject\FaqEntries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FaqController extends AbstractController
{
    #[Route('/{_locale}/faq', name: 'faq_index', defaults: [
        '_locale' => 'fr',
    ], methods: ['GET'])]
    public function index(Request $request, FaqQuestionRepository $questions): Response
    {
        $published = $questions->findPublished($request->getLocale());

        return $this->render('faq/index.html.twig', [
            'sections' => $this->sections($published),
            'faq' => new FaqEntries($published),
        ]);
    }

    /**
     * @param list<FaqQuestion> $questions
     * @return list<array{category: FaqCategory, questions: list<FaqQuestion>}>
     */
    private function sections(array $questions): array
    {
        $sections = [];
        foreach ($questions as $question) {
            $category = $question->getCategory();
            \assert($category instanceof FaqCategory);
            $sections[(int) $category->getId()]['category'] = $category;
            $sections[(int) $category->getId()]['questions'][] = $question;
        }

        return array_values($sections);
    }
}
