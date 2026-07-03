<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\OdtMatrix;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class EnginePreferences
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $matrix = 'air';

    #[LiveProp(writable: true)]
    public string $search = '';

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly SpicesRepository $spicesRepository,
    ) {
    }

    public function mount(): void
    {
        $this->matrix = $this->user()
            ->getDefaultMatrix()
            ->value;
    }

    /**
     * @return list<OdtMatrix>
     */
    public function getMatrices(): array
    {
        return OdtMatrix::cases();
    }

    /**
     * @return list<Spices>
     */
    public function getExcludedSpices(): array
    {
        return $this->user()
            ->getExcludedSpices()
            ->getValues();
    }

    /**
     * @return list<Spices>
     */
    public function getSelectableSpices(): array
    {
        $needle = mb_strtolower(trim($this->search));
        if ('' === $needle) {
            return [];
        }

        $excludedIds = array_map(static fn (Spices $s): ?int => $s->getId(), $this->getExcludedSpices());

        return array_values(array_filter(
            $this->spicesRepository->findBy([], [
                'name' => 'ASC',
            ]),
            static fn (Spices $spice): bool => ! \in_array($spice->getId(), $excludedIds, true)
                && str_contains(mb_strtolower((string) $spice->getName()), $needle),
        ));
    }

    #[LiveAction]
    public function selectMatrix(#[LiveArg] string $value): void
    {
        $matrix = OdtMatrix::tryFrom($value);
        if (null === $matrix) {
            return;
        }

        $this->user()
            ->setDefaultMatrix($matrix);
        $this->matrix = $matrix->value;
        $this->em->flush();
    }

    #[LiveAction]
    public function toggleSpice(#[LiveArg] int $id): void
    {
        $spice = $this->spicesRepository->find($id);
        if (null === $spice) {
            return;
        }

        $user = $this->user();
        $user->getExcludedSpices()
            ->contains($spice)
            ? $user->removeExcludedSpice($spice)
            : $user->addExcludedSpice($spice);

        $this->em->flush();
    }

    private function user(): Users
    {
        $user = $this->security->getUser();
        \assert($user instanceof Users);

        return $user;
    }
}
