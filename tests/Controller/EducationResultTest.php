<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\GameQuestion;
use App\Entity\GameSession;
use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\UsersRepository;
use App\Service\GamificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EducationResultTest extends WebTestCase
{
    private const int QUERY_CEILING = 8;

    public function testResultRendersTierMissedSpicesAndAnswers(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        [$base, $expected, $given] = $this->spices();
        $session = $this->persistSession($user, GameMode::QCM, 10, [
            [true, $base, $expected, $expected],
            [false, $base, $expected, $given],
            [false, $base, $given, $base],
        ]);

        try {
            AdSlotPlacementTest::enableAds();
            $crawler = $client->request('GET', $this->url($session));

            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('h1'));
            self::assertSame('Premiers pas', trim($crawler->filter('h1')->text()));
            self::assertSame(
                ['/fr/epices/' . $expected->getSlug(), '/fr/epices/' . $given->getSlug()],
                $crawler->filter('a.result-pair')
                    ->each(static fn ($a): ?string => $a->attr('href')),
            );
            $toggle = $crawler->filter('#result-answers-toggle');
            self::assertSame('false', $toggle->attr('aria-expanded'));
            self::assertCount(1, $crawler->filter('#' . $toggle->attr('aria-controls') . ' ol > li:nth-child(3)'));
            self::assertCount(1, $crawler->filter('meter.result-meter'));
            self::assertCount(1, $crawler->filter('turbo-frame#result-xp[x-data="resultXpSync"] .result-xp[data-xp-pending]'));
            self::assertCount(1, $crawler->filter('.result-record'));
            self::assertCount(1, $crawler->filter('.result-hero [role="status"][aria-live="polite"]'));
            self::assertCount(1, $crawler->filter('[data-ad-slot]'));
            self::assertCount(0, $crawler->filter('script[src*="ethicalads"], script[src*="carbonads"]'));
        } finally {
            $this->remove($session);
        }
    }

    public function testResultStaysUnderQueryCeiling(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        [$base, $expected, $given] = $this->spices();
        $session = $this->persistSession($user, GameMode::QCM, 10, [
            [true, $base, $expected, $expected],
            [false, $base, $expected, $given],
            [false, $base, $given, $base],
        ]);

        try {
            $client->request('GET', $this->url($session));
            $client->enableProfiler();
            $client->request('GET', $this->url($session));

            self::assertResponseIsSuccessful();
            $profile = $client->getProfile();
            self::assertNotFalse($profile);
            $collector = $profile->getCollector('db');
            self::assertInstanceOf(DoctrineDataCollector::class, $collector);
            self::assertLessThanOrEqual(self::QUERY_CEILING, $collector->getQueryCount());
        } finally {
            $this->remove($session);
        }
    }

    public function testResultHidesProgressWhenGamificationIsDisabled(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        [$base, $expected, $given] = $this->spices();
        $session = $this->persistSession($user, GameMode::QCM, 10, [[false, $base, $expected, $given]]);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $progression = self::getContainer()->get(GamificationManager::class)->getOrCreateProgression($user);
        $progression->disableGamification();
        $em->flush();

        try {
            $crawler = $client->request('GET', $this->url($session));

            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('meter'));
            self::assertCount(0, $crawler->filter('.result-record'));
            self::assertCount(0, $crawler->filter('.result-xp'));
            self::assertCount(1, $crawler->filter('.result-top-solo'));
            self::assertCount(0, $crawler->filter('a[href*="/education/briefing"]'));
        } finally {
            $progression->enableGamification();
            $em->flush();
            $this->remove($session);
        }
    }

    public function testSurvivalResultShowsChainWithoutAnswers(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        $session = $this->persistSession($user, GameMode::SURVIVAL, 21, [], correct: 7);

        try {
            $crawler = $client->request('GET', $this->url($session));

            self::assertResponseIsSuccessful();
            self::assertSame('Fin nez', trim($crawler->filter('h1')->text()));
            self::assertStringContainsString('7', $crawler->filter('.result-ring')->text());
            self::assertCount(0, $crawler->filter('.result-acc'));
            self::assertCount(0, $crawler->filter('.result-rail'));
        } finally {
            $this->remove($session);
        }
    }

    public function testUnfinishedSessionRedirectsToPlay(): void
    {
        $client = self::createClient();
        $user = $this->login($client);
        $session = $this->persistSession($user, GameMode::QCM, 0, [], finished: false);

        try {
            $client->request('GET', $this->url($session));

            self::assertResponseRedirects('/fr/education/play/' . $session->getId());
        } finally {
            $this->remove($session);
        }
    }

    private function login(KernelBrowser $client): Users
    {
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([
            'username' => 'bob',
        ]);
        self::assertNotNull($user);
        $client->loginUser($user);

        return $user;
    }

    /**
     * @return array{Spices, Spices, Spices}
     */
    private function spices(): array
    {
        $spices = self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('SELECT s FROM App\Entity\Spices s WHERE s.deleted_at IS NULL AND s.slug IS NOT NULL ORDER BY s.id ASC')
            ->setMaxResults(3)
            ->getResult();
        self::assertCount(3, $spices);

        return [$spices[0], $spices[1], $spices[2]];
    }

    /**
     * @param list<array{bool, Spices, Spices, Spices}> $rows
     */
    private function persistSession(Users $user, GameMode $mode, int $score, array $rows, int $correct = 0, bool $finished = true): GameSession
    {
        $session = new GameSession()
            ->setUser($user)
            ->setGameMode($mode)
            ->setDifficulty(GameDifficulty::EASY)
            ->setScore($score)
            ->setTotalQuestions($rows === [] ? $correct : \count($rows));

        foreach ($rows as $index => [$isCorrect, $base, $expected, $given]) {
            $question = new GameQuestion()
                ->setQuestionIndex($index)
                ->setQuestionData([
                    'questionSpiceId' => $base->getId(),
                    'correctSpiceId' => $expected->getId(),
                    'givenSpiceId' => $given->getId(),
                ]);
            $question->answer((string) $given->getName(), $isCorrect);
            $session->addQuestion($question);
            if ($isCorrect) {
                $session->incrementCorrectAnswers();
            }
        }

        for ($i = 0; $i < $correct; ++$i) {
            $session->incrementCorrectAnswers();
        }

        if ($finished) {
            $session->finish();
        }

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($session);
        $em->flush();

        return $session;
    }

    private function url(GameSession $session): string
    {
        return '/fr/education/result/' . $session->getId();
    }

    private function remove(GameSession $session): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->remove($em->getReference(GameSession::class, $session->getId()));
        $em->flush();
    }
}
