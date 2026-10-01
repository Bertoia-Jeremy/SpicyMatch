<?php

declare(strict_types=1);

namespace App\Service\Text;

final class SearchNormalizer
{
    private readonly \Transliterator $transliterator;

    public function __construct()
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if (! $transliterator instanceof \Transliterator) {
            throw new \LogicException('ICU transliterator "Any-Latin; Latin-ASCII; Lower()" is unavailable.');
        }

        $this->transliterator = $transliterator;
    }

    public function normalize(string $text): string
    {
        $folded = $this->transliterator->transliterate($text);
        if ($folded === false) {
            $folded = mb_strtolower($text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $folded));
    }

    public function matches(string $haystack, string $needle): bool
    {
        $normalizedNeedle = $this->normalize($needle);

        return $normalizedNeedle === '' || str_contains($this->normalize($haystack), $normalizedNeedle);
    }
}
