<?php

declare(strict_types=1);

namespace App\Service\Icon;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class IconSubsetManifest
{
    public const string PATH = 'public/lib/fontawesome-subset/manifest.json';

    /**
     * @var array{styles: array<string, list<string>>, ignored: list<string>}|null
     */
    private ?array $manifest = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<string>
     */
    public function iconsForStyle(string $style): array
    {
        return $this->load()['styles'][$style] ?? [];
    }

    /**
     * @return list<string>
     */
    public function knownTokens(): array
    {
        $manifest = $this->load();

        return array_values(array_unique(array_merge($manifest['ignored'], ...array_values($manifest['styles']))));
    }

    /**
     * @return array<string, string>
     */
    public function solidChoices(): array
    {
        $choices = [];
        foreach ($this->iconsForStyle('solid') as $icon) {
            $choices['fa-solid fa-' . $icon] = 'fa-solid fa-' . $icon;
        }

        return $choices;
    }

    /**
     * @return array{styles: array<string, list<string>>, ignored: list<string>}
     */
    private function load(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = $this->projectDir . '/' . self::PATH;
        if (! is_file($path)) {
            throw new \RuntimeException(\sprintf('Font Awesome subset manifest missing: %s (run yarn icons:subset).', self::PATH));
        }

        /** @var array{styles: array<string, list<string>>, ignored: list<string>} $manifest */
        $manifest = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);

        return $this->manifest = $manifest;
    }
}
