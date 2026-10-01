<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SchemaProviderInterface
{
    public function supports(?object $subject): bool;

    /**
     * @return array<string, mixed>
     */
    public function build(?object $subject, string $locale): array;
}
