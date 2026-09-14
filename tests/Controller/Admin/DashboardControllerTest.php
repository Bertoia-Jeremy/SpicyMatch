<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAdminRouteRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');
    }

    public function testAdminGamificationStatsRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/admin/gamification/stats');
        self::assertResponseRedirects('/login');
    }

    public function testAdminEducationStatsRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/admin/education/stats');
        self::assertResponseRedirects('/login');
    }

    public function testAdminOnboardingStatsRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/admin/onboarding/stats');
        self::assertResponseRedirects('/login');
    }

    public function testAdminDiscoveryStatsRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/admin/discovery/stats');
        self::assertResponseRedirects('/login');
    }
}
