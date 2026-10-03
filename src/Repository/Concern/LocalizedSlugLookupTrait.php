<?php

declare(strict_types=1);

namespace App\Repository\Concern;

/**
 * @mixin \Doctrine\ORM\EntityRepository<object>
 */
trait LocalizedSlugLookupTrait
{
    protected function lookupByLocalizedSlug(string $slug, string $locale): ?object
    {
        if ($locale !== 'fr') {
            $translated = $this->findOneByTranslationSlug($slug, $locale);
            if ($translated !== null) {
                return $translated;
            }
        }

        return $this->findOneBy([
            'slug' => $slug,
        ])
            ?? $this->findOneByTranslationSlug($slug, null)
            ?? (ctype_digit($slug) ? $this->find((int) $slug) : null);
    }

    /**
     * @return list<object>
     */
    protected function findAllWithTranslation(string $locale): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($locale === 'fr') {
            $qb->orderBy('e.name');
        } else {
            $qb->leftJoin('e.translations', 'lt', 'WITH', 'lt.locale = :loc')
                ->addSelect('lt')
                ->addSelect('COALESCE(lt.name, e.name) AS HIDDEN localizedName')
                ->setParameter('loc', $locale)
                ->orderBy('localizedName');
        }

        return array_values(array_filter(
            $qb->getQuery()
                ->getResult(),
            \is_object(...),
        ));
    }

    /**
     * @return list<array{name: string, slug: string}>
     */
    public function findSitePlanLinks(string $locale): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.deleted_at IS NULL')
            ->andWhere('e.slug IS NOT NULL');

        if ($locale === 'fr') {
            $qb->select('e.name AS name', 'e.slug AS slug');
        } else {
            $qb->select("COALESCE(NULLIF(lt.name, ''), e.name) AS name", "COALESCE(NULLIF(lt.slug, ''), e.slug) AS slug")
                ->leftJoin('e.translations', 'lt', 'WITH', 'lt.locale = :loc')
                ->setParameter('loc', $locale);
        }

        $links = [];
        foreach ($qb->orderBy('name')->getQuery()->getArrayResult() as $row) {
            $links[] = [
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }

        return $links;
    }

    /**
     * @return list<array{slugs: array<string, string>, updatedAt: \DateTimeInterface|null}>
     */
    public function findSitemapRows(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.id', 'e.slug', 'e.updated_at AS updatedAt', 't.locale', 't.slug AS translatedSlug')
            ->leftJoin('e.translations', 't')
            ->andWhere('e.deleted_at IS NULL')
            ->andWhere('e.slug IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $entries = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            $entries[$id] ??= [
                'slugs' => [
                    'fr' => (string) $row['slug'],
                ],
                'updatedAt' => $row['updatedAt'] instanceof \DateTimeInterface ? $row['updatedAt'] : null,
            ];
            if (\is_string($row['locale']) && \is_string($row['translatedSlug']) && $row['translatedSlug'] !== '') {
                $entries[$id]['slugs'][$row['locale']] = $row['translatedSlug'];
            }
        }

        return array_values($entries);
    }

    private function findOneByTranslationSlug(string $slug, ?string $locale): ?object
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.translations', 't', 'WITH', 't.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('t.id')
            ->setMaxResults(1);

        if ($locale !== null) {
            $qb->andWhere('t.locale = :loc')
                ->setParameter('loc', $locale);
        }

        $result = $qb->getQuery()
            ->getOneOrNullResult();

        return \is_object($result) ? $result : null;
    }
}
