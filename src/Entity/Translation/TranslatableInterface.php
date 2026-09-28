<?php

declare(strict_types=1);

namespace App\Entity\Translation;

interface TranslatableInterface
{
    public function getTranslation(string $locale): ?TranslationInterface;
}
