<?php

declare(strict_types=1);

namespace App\Service\Slug;

use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

final class SlugGenerator
{
    private readonly SluggerInterface $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function slugify(string $name): string
    {
        $slug = $this->slugger->slug($name)
            ->lower()
            ->toString();

        return $slug === '' ? 'n' : $slug;
    }

    /**
     * @param callable(string): bool $exists renvoie true si le slug est déjà pris
     */
    public function unique(string $name, callable $exists): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 2;

        while ($exists($slug)) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }
}
