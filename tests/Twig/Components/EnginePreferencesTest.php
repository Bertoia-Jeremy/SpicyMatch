<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\OdtMatrix;
use App\Repository\SpicesRepository;
use App\Twig\Components\EnginePreferences;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class EnginePreferencesTest extends TestCase
{
    public function testSelectMatrixPersistsAndUpdatesProp(): void
    {
        $user = new Users();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $component = $this->makeComponent($user, $em);
        $component->selectMatrix('oil');

        self::assertSame(OdtMatrix::OIL, $user->getDefaultMatrix());
        self::assertSame('oil', $component->matrix);
    }

    public function testSelectMatrixIgnoresInvalidValue(): void
    {
        $user = new Users();
        $user->setDefaultMatrix(OdtMatrix::WATER);
        $em = $this->createStub(EntityManagerInterface::class);

        $component = $this->makeComponent($user, $em);
        $component->selectMatrix('plasma');

        self::assertSame(OdtMatrix::WATER, $user->getDefaultMatrix());
    }

    public function testToggleSpiceAddsThenRemoves(): void
    {
        $user = new Users();
        $spice = new Spices();
        (new \ReflectionProperty(Spices::class, 'id'))->setValue($spice, 7);

        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(SpicesRepository::class);
        $repo->method('find')
            ->willReturn($spice);

        $component = $this->makeComponent($user, $em, $repo);

        $component->toggleSpice(7);
        self::assertCount(1, $user->getExcludedSpices());

        $component->toggleSpice(7);
        self::assertCount(0, $user->getExcludedSpices());
    }

    public function testToggleSpiceIgnoresUnknownId(): void
    {
        $user = new Users();
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(SpicesRepository::class);
        $repo->method('find')
            ->willReturn(null);

        $component = $this->makeComponent($user, $em, $repo);
        $component->toggleSpice(999);

        self::assertCount(0, $user->getExcludedSpices());
    }

    private function makeComponent(
        Users $user,
        EntityManagerInterface $em,
        ?SpicesRepository $repo = null,
    ): EnginePreferences {
        $security = $this->createStub(Security::class);
        $security->method('getUser')
            ->willReturn($user);

        $component = new EnginePreferences($security, $em, $repo ?? $this->createStub(SpicesRepository::class));
        $component->matrix = $user->getDefaultMatrix()
            ->value;

        return $component;
    }
}
