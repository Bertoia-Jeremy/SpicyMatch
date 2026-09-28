<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Entity\CookingTips;
use App\Entity\PreparationMethods;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\Spices;
use App\ValueObject\SpiceDuoRow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class SpiceDuoImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {
    }

    public function upsert(SpiceDuoRow $row): bool
    {
        $spice = $this->resolve(
            Spices::class,
            [
                'slug' => $row->spiceSlug,
            ],
            \sprintf('Épice "%s" introuvable.', $row->spiceSlug),
            \sprintf('Épice "%s" ambiguë.', $row->spiceSlug),
        );

        $method = $this->resolve(
            PreparationMethods::class,
            [
                'slug' => $row->methodSlug,
            ],
            \sprintf('Méthode "%s" introuvable.', $row->methodSlug),
            \sprintf('Méthode "%s" ambiguë.', $row->methodSlug),
        );

        $prepTip = $this->resolve(
            PreparationTips::class,
            [
                'spice' => $spice,
                'preparationMethod' => $method,
            ],
            \sprintf('Aucune préparation "%s" pour l\'épice "%s".', $row->methodSlug, $row->spiceSlug),
            \sprintf('Plusieurs préparations "%s" pour l\'épice "%s" : rattachement ambigu.', $row->methodSlug, $row->spiceSlug),
        );

        $cookTip = $this->resolve(
            CookingTips::class,
            [
                'spice' => $spice,
                'moment' => $row->moment,
            ],
            \sprintf('Aucun moment "%s" pour l\'épice "%s".', $row->moment->name, $row->spiceSlug),
            \sprintf('Plusieurs conseils au moment "%s" pour l\'épice "%s" : rattachement ambigu.', $row->moment->name, $row->spiceSlug),
        );

        $duo = $this->em->getRepository(SpiceDuo::class)->findOneBy([
            'preparationTip' => $prepTip,
            'cookingTip' => $cookTip,
        ]);
        $created = ! $duo instanceof SpiceDuo;
        if (! $duo instanceof SpiceDuo) {
            $duo = new SpiceDuo()
                ->setPreparationTip($prepTip)
                ->setCookingTip($cookTip)
                ->setCreatedAt(new \DateTimeImmutable());
        }

        $duo
            ->setRank($row->rank)
            ->setTitle($row->title)
            ->setEffect($row->effect)
            ->setScience($row->science)
            ->setExample($row->example)
            ->setUpdatedAt(new \DateTimeImmutable());

        $violations = $this->validator->validate($duo);
        if (\count($violations) > 0) {
            throw new \RuntimeException((string) $violations->get(0)->getMessage());
        }

        $this->em->persist($duo);
        $this->em->flush();

        return $created;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $criteria
     * @return T
     */
    private function resolve(string $class, array $criteria, string $missing, string $ambiguous): object
    {
        $found = $this->em->getRepository($class)
            ->findBy([
                ...$criteria,
                'deleted_at' => null,
            ], null, 2);

        return match (\count($found)) {
            0 => throw new \RuntimeException($missing),
            1 => $found[0],
            default => throw new \RuntimeException($ambiguous),
        };
    }
}
