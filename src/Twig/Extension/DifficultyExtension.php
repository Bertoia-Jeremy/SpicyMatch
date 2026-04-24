<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Service\Education\DifficultyRuleApplier;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Expose la difficulté courante de l'utilisateur (et ses dérivées) à tous les templates Twig
 * via des variables globales — évite l'oubli silencieux d'un paramètre `monochrome` sur les macros.
 */
final class DifficultyExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly DifficultyRuleApplier $rules,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $difficulty = $this->resolveDifficulty();

        return [
            'currentDifficulty' => $difficulty,
            'isMonochrome' => $this->rules->isMonochrome($difficulty),
            'difficultyLabel' => $this->rules->label($difficulty),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('difficulty_label', fn (GameDifficulty $d): string => $this->rules->label($d)),
            new TwigFunction('is_monochrome', fn (GameDifficulty $d): bool => $this->rules->isMonochrome($d)),
        ];
    }

    private function resolveDifficulty(): GameDifficulty
    {
        $user = $this->security->getUser();

        return $user instanceof Users ? $user->getPreferredDifficulty() : GameDifficulty::EASY;
    }
}
