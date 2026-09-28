<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use App\Factory\UsersFactory;
use App\Twig\Extension\RegistrationFormExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

final class RegistrationFormExtensionTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $request = Request::create('/fr/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')
            ->push($request);
    }

    public function testEmbeddedRegistrationFormExposesExpectedFields(): void
    {
        $container = self::getContainer();

        $extension = new RegistrationFormExtension(
            $container->get('form.factory'),
            $container->get(UsersFactory::class),
        );

        $view = $extension->embeddedRegistrationForm();

        self::assertArrayHasKey('username', $view->children);
        self::assertArrayHasKey('mail', $view->children);
        self::assertArrayHasKey('plainPassword', $view->children);
        self::assertArrayHasKey('altcha', $view->children);
    }

    public function testEachCallBuildsAFreshFormInstance(): void
    {
        $container = self::getContainer();

        $extension = new RegistrationFormExtension(
            $container->get('form.factory'),
            $container->get(UsersFactory::class),
        );

        $first = $extension->embeddedRegistrationForm();
        $second = $extension->embeddedRegistrationForm();

        self::assertNotSame($first, $second, 'Rendering the modal on two different pages must not share form state');
    }

    public function testTwigExposesEmbeddedRegistrationForm(): void
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        self::assertNotNull($twig->getFunction('embedded_registration_form'));
    }
}
