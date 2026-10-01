<?php

declare(strict_types=1);

namespace App\Seo;

interface SitemapSourceInterface
{
    /**
     * @return list<array{slugs: array<string, string>, updatedAt: \DateTimeInterface|null}>
     */
    public function findSitemapRows(): array;
}
