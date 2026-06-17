<?php

declare(strict_types=1);

namespace App\Entity\Translation;

interface Sluggable
{
    public function getName(): ?string;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): static;
}
