<?php

declare(strict_types=1);

namespace App\Entity\Translation;

interface TranslationInterface
{
    public function getLocale(): string;

    public function setLocale(string $locale): static;

    public function isReviewed(): bool;

    public function setReviewed(bool $reviewed): static;
}
