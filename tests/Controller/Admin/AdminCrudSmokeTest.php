<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\CookingTipsCrudController;
use App\Controller\Admin\SpiceDuoCrudController;
use App\Controller\Admin\SpicesCrudController;
use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminCrudSmokeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $admin = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Users::class)->findOneBy([
            'username' => 'admin',
        ]);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);
    }

    public function testSpiceDuoIndex(): void
    {
        $this->client->request('GET', $this->url(SpiceDuoCrudController::class, 'index'));
        self::assertResponseIsSuccessful();
    }

    public function testSpiceDuoRowListedAndEditable(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $prep = $em->getRepository(PreparationTips::class)->findAll()[0];
        $cook = $em->getRepository(CookingTips::class)->findOneBy([
            'spice' => $prep->getSpice(),
        ]);
        self::assertNotNull($cook);
        $duo = (new SpiceDuo())->setPreparationTip($prep)
            ->setCookingTip($cook)
            ->setTitle('smoke')
            ->setEffect('e')
            ->setScience('s')
            ->setExample('x')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $em->persist($duo);
        $em->flush();
        $id = (int) $duo->getId();

        try {
            $this->client->request('GET', $this->url(SpiceDuoCrudController::class, 'index'));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'smoke');
            $crawler = $this->client->request('GET', $this->url(SpiceDuoCrudController::class, 'edit', $id));
            self::assertResponseIsSuccessful();
            $form = $crawler->filter('form[name="SpiceDuo"]')
                ->form();
            $form['SpiceDuo[title]'] = 'smoke-edited';
            $this->client->submit($form);
            self::assertResponseRedirects();
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            self::assertSame('smoke-edited', $em->find(SpiceDuo::class, $id)?->getTitle());
        } finally {
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            $leftover = $em->find(SpiceDuo::class, $id);
            $leftover !== null && $em->remove($leftover);
            $em->flush();
        }
    }

    public function testSpiceDuoNew(): void
    {
        $this->client->request('GET', $this->url(SpiceDuoCrudController::class, 'new'));
        self::assertResponseIsSuccessful();
    }

    public function testDashboardRenders(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statsPages(): iterable
    {
        yield 'gamification' => ['/admin/gamification/stats'];
        yield 'education' => ['/admin/education/stats'];
        yield 'onboarding' => ['/admin/onboarding/stats'];
        yield 'discovery' => ['/admin/discovery/stats'];
    }

    #[DataProvider('statsPages')]
    public function testStatsPageRenders(string $path): void
    {
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
    }

    public function testSpicesIndexRenders(): void
    {
        $this->client->request('GET', $this->url(SpicesCrudController::class, 'index'));
        self::assertResponseIsSuccessful();
    }

    public function testCookingTipsIndex(): void
    {
        $this->client->request('GET', $this->url(CookingTipsCrudController::class, 'index'));
        self::assertResponseIsSuccessful();
    }

    public function testCookingTipsEdit(): void
    {
        $id = $this->firstId(CookingTips::class);
        $this->client->request('GET', $this->url(CookingTipsCrudController::class, 'edit', $id));
        self::assertResponseIsSuccessful();
    }

    public function testCookingTipsNew(): void
    {
        $this->client->request('GET', $this->url(CookingTipsCrudController::class, 'new'));
        self::assertResponseIsSuccessful();
    }

    private function url(string $crud, string $action, ?int $id = null): string
    {
        $slug = match ($crud) {
            SpiceDuoCrudController::class => 'spice-duo',
            CookingTipsCrudController::class => 'cooking-tips',
            default => 'spices',
        };

        return match ($action) {
            'index' => '/admin/' . $slug,
            'new' => '/admin/' . $slug . '/new',
            default => '/admin/' . $slug . '/' . $id . '/edit',
        };
    }

    private function firstId(string $entity): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $em->getRepository($entity)
            ->findOneBy([]);
        self::assertNotNull($row);

        return (int) $row->getId();
    }
}
