<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\AvatarCatalogService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AvatarExtension extends AbstractExtension
{
    public function __construct(
        private readonly AvatarCatalogService $avatarCatalog,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('avatar_data', $this->getAvatarData(...))];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAvatarData(?string $slug): array
    {
        if ($slug !== null) {
            $avatar = $this->avatarCatalog->getAvatar($slug);
            if ($avatar !== null) {
                return $avatar;
            }
        }

        return $this->avatarCatalog->getDefaultAvatar();
    }
}
