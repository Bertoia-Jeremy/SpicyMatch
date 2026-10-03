<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\FaqCategory;
use App\Entity\FaqCategoryTranslation;
use App\Entity\FaqQuestion;
use App\Entity\FaqQuestionTranslation;
use App\Entity\Spices;
use App\Repository\FaqQuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FaqQuestionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testReturnsPublishedQuestionsOrderedByCategoryThenPositionWithTranslations(): void
    {
        $first = $this->category('repo-first', -20);
        $second = $this->category('repo-second', -10);
        $late = $this->question($first, 'Tardive', 20);
        $early = $this->question($first, 'Précoce', 10);
        $other = $this->question($second, 'Autre', 0);
        $hidden = $this->question($first, 'Brouillon', 5, false);
        $early->addTranslation(new FaqQuestionTranslation()
            ->setLocale('en')
            ->setQuestion('Early')
            ->setAnswer('Early answer'));
        $this->em->flush();
        $this->em->clear();

        $published = self::getContainer()->get(FaqQuestionRepository::class)->findPublished('en');

        $ids = array_map(static fn (FaqQuestion $question): ?int => $question->getId(), $published);
        self::assertSame([$early->getId(), $late->getId(), $other->getId()], \array_slice($ids, 0, 3));
        self::assertNotContains($hidden->getId(), $ids);
        self::assertSame('Early', $published[0]->getLocalizedQuestion('en'));
        self::assertSame('Tardive', $published[1]->getLocalizedQuestion('en'));
        self::assertSame('repo-first EN', $published[0]->getCategory()?->getLocalizedName('en'));
    }

    public function testFiltersPublishedQuestionsBoundToASpice(): void
    {
        /** @var list<Spices> $spices */
        $spices = $this->em->createQuery(sprintf('SELECT s FROM %s s WHERE NOT EXISTS (SELECT q.id FROM %s q WHERE s MEMBER OF q.spices) ORDER BY s.id ASC', Spices::class, FaqQuestion::class))
            ->setMaxResults(2)
            ->getResult();
        self::assertCount(2, $spices);
        [$spice, $otherSpice] = $spices;
        $category = $this->category('repo-spice', 0);
        $bound = $this->question($category, 'Liée', 0)
            ->addSpice($spice);
        $this->question($category, 'Brouillon liée', 0, false)
            ->addSpice($spice);
        $this->question($category, 'Autre épice', 0)
            ->addSpice($otherSpice);
        $this->em->flush();

        $found = self::getContainer()->get(FaqQuestionRepository::class)->findPublishedForSpice($spice, 'fr');

        self::assertSame(
            [$bound->getId()],
            array_map(static fn (FaqQuestion $question): ?int => $question->getId(), $found),
        );
    }

    private function category(string $code, int $position): FaqCategory
    {
        $category = new FaqCategory()
            ->setCode($code)
            ->setName($code)
            ->setPosition($position)
            ->addTranslation(new FaqCategoryTranslation()
                ->setLocale('en')
                ->setName($code . ' EN'));
        $this->em->persist($category);

        return $category;
    }

    private function question(FaqCategory $category, string $text, int $position, bool $published = true): FaqQuestion
    {
        $question = new FaqQuestion()
            ->setCategory($category)
            ->setQuestion($text)
            ->setAnswer($text . ' réponse')
            ->setPosition($position)
            ->setPublished($published);
        $this->em->persist($question);

        return $question;
    }
}
