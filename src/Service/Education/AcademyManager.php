<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\CookingTips;
use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\Match\CompatibleSpiceFinder;
use App\ValueObject\Match\MortarIds;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AcademyManager
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibleSpiceFinder $compatibleSpiceFinder,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Memoization intra-requête (évite un double fetch dans la même requête HTTP).
     * La mise en cache inter-requêtes est gérée par le pool Symfony Cache (academy.all_spices).
     *
     * @var list<Spices>|null
     */
    private ?array $allSpicesCache = null;

    /**
     * @return list<Spices>
     */
    private function getAllSpices(): array
    {
        return $this->allSpicesCache ??= $this->cache->get(
            'academy.all_spices',
            function (ItemInterface $item): array {
                $item->expiresAfter(3600);

                return $this->spicesRepository->findAll();
            },
        );
    }

    private ?\Transliterator $transliterator = null;

    private function getTransliterator(): \Transliterator
    {
        if ($this->transliterator === null) {
            $this->transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        }

        return $this->transliterator ?? throw new \RuntimeException('ICU transliterator unavailable');
    }

    // ──────────────────────────────────────────────
    // Compatibilité (Survival, Intrus)
    // ──────────────────────────────────────────────

    /**
     * @return list<array{id: int, name: string, file: ?string, agId: ?int, color: ?string, groupName: ?string, stId: ?int, typeName: ?string, score: int}>
     */
    public function findCompatibleSpices(Spices $spice): array
    {
        $id = $spice->getId();

        if ($id === null) {
            return [];
        }

        return $this->compatibleSpiceFinder->findCompatible(new MortarIds([$id]), 100);
    }

    /**
     * Find spices with 0 compatibility (no shared aromatic compound at all).
     *
     * @param list<int> $excludeIds
     *
     * @return list<Spices>
     */
    public function findIntruders(Spices $baseSpice, array $excludeIds = []): array
    {
        $cacheKey = 'academy.intruders.' . $baseSpice->getId();

        $allIntruders = $this->cache->get($cacheKey, function (ItemInterface $item) use ($baseSpice): array {
            $item->expiresAfter(3600);

            return $this->spicesRepository->findIncompatibleWith($baseSpice);
        });

        if (empty($excludeIds)) {
            return $allIntruders;
        }

        $excludeFlipped = array_flip($excludeIds);

        return array_values(array_filter($allIntruders, fn (Spices $s) => ! isset($excludeFlipped[$s->getId()])));
    }

    /**
     * Check if candidate is compatible with base (score > 0).
     */
    public function isCompatible(Spices $base, Spices $candidate): bool
    {
        $results = $this->findCompatibleSpices($base);

        foreach ($results as $r) {
            if ($r['id'] === $candidate->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter scored spices by difficulty threshold.
     *
     * @param array<array{score: int}> $scoredSpices Already sorted by score desc
     *
     * @return list<array<string, mixed>>
     */
    public function filterByDifficulty(array $scoredSpices, GameDifficulty $difficulty): array
    {
        $total = count($scoredSpices);

        if ($total === 0) {
            return [];
        }

        $keep = match ($difficulty) {
            GameDifficulty::EASY => (int) ceil($total * 0.5),
            GameDifficulty::MEDIUM => (int) ceil($total * 0.7),
            GameDifficulty::HARD => $total,
        };

        return array_slice($scoredSpices, 0, $keep);
    }

    // ──────────────────────────────────────────────
    // Cartes épices cachées (Chrono, Guess Who)
    // ──────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>> Indexed by spice ID
     */
    public function getAllSpiceCards(): array
    {
        return $this->cache->get('academy.spice_cards', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->buildAllSpiceCards();
        });
    }

    /**
     * Pick a random spice card, excluding given IDs.
     *
     * @param list<int> $excludeIds
     *
     * @return array<string, mixed>|null
     */
    public function getRandomSpiceCard(array $excludeIds = []): ?array
    {
        $cards = $this->getAllSpiceCards();

        if (! empty($excludeIds)) {
            $excludeFlipped = array_flip($excludeIds);
            $cards = array_filter($cards, fn (array $c) => ! isset($excludeFlipped[$c['id']]));
        }

        if (empty($cards)) {
            return null;
        }

        return $cards[array_rand($cards)];
    }

    // ──────────────────────────────────────────────
    // Normalisation texte (Hangman)
    // ──────────────────────────────────────────────

    /**
     * Strip accents and uppercase a single character.
     */
    public function normalizeChar(string $char): string
    {
        return mb_strtoupper($this->getTransliterator()->transliterate($char));
    }

    /**
     * Build a masked word for hangman display.
     *
     * Spaces, hyphens, apostrophes and common French "tool words" (de, la, du, d', l')
     * are pre-revealed. Letters are masked unless their normalized version has been guessed.
     *
     * @param string[] $guessedLetters Normalized uppercase letters
     */
    public function buildMask(string $name, array $guessedLetters): string
    {
        $guessedFlipped = array_flip($guessedLetters);
        $mask = '';

        foreach (mb_str_split($name) as $char) {
            $normalized = $this->normalizeChar($char);

            if ($char === ' ' || $char === '-' || $char === '\'') {
                $mask .= $char;
            } elseif (isset($guessedFlipped[$normalized])) {
                $mask .= $char;
            } else {
                $mask .= '_';
            }
        }

        return $mask;
    }

    /**
     * Check if a letter is present in the word (accent-insensitive).
     */
    public function letterInWord(string $letter, string $word): bool
    {
        $normalizedLetter = $this->normalizeChar($letter);

        foreach (mb_str_split($word) as $char) {
            if ($this->normalizeChar($char) === $normalizedLetter) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────
    // Génération questions
    // ──────────────────────────────────────────────

    /**
     * Generate an Intrus question.
     *
     * Classic: 3 compatible + 1 intruder, find the intruder.
     * Inverted: 3 intruders + 1 compatible, find the compatible.
     *
     * @param list<int> $excludeBaseIds
     *
     * @return array{type: string, prompt: string, baseSpice: array<string, mixed>, options: array<int, array<string, mixed>>, correctAnswerId: int, isInverted: bool, metadata: array<string, mixed>}|null
     */
    public function generateIntrusQuestion(
        GameDifficulty $difficulty,
        array $excludeBaseIds = [],
        bool $inverted = false,
        bool $strict = false,
    ): ?array {
        // 50/50 chance of a group-based "hors-groupe" question (non-inverted only)
        if (! $inverted && random_int(0, 1) === 0) {
            $groupQuestion = $this->generateGroupIntrusQuestion($difficulty, $excludeBaseIds);
            if ($groupQuestion !== null) {
                return $groupQuestion;
            }
        }

        $allSpices = $this->getAllSpices();
        $excludeBaseFlipped = array_flip($excludeBaseIds);
        $candidates = array_filter($allSpices, fn (Spices $s) => ! isset($excludeBaseFlipped[$s->getId()]));

        if (count($candidates) < 5) {
            return null;
        }

        shuffle($candidates);

        foreach ($candidates as $baseSpice) {
            $compatibles = $this->findCompatibleSpices($baseSpice);
            $intruders = $strict
                ? $this->findStrictIntruders($baseSpice, $excludeBaseIds)
                : $this->findIntruders($baseSpice, $excludeBaseIds);

            // Use a local copy so the original $inverted is never mutated across iterations.
            $effectiveInverted = $inverted;

            if ($effectiveInverted) {
                // Need ≥1 compatible + ≥3 intruders
                if (count($compatibles) < 1 || count($intruders) < 3) {
                    // Fallback to classic mode for this candidate
                    if (count($compatibles) >= 3 && count($intruders) >= 1) {
                        $effectiveInverted = false;
                    } else {
                        continue;
                    }
                }
            } else {
                // Need ≥3 compatibles + ≥1 intruder
                if (count($compatibles) < 3 || count($intruders) < 1) {
                    continue;
                }
            }

            return $this->buildIntrusQuestion($baseSpice, $compatibles, $intruders, $difficulty, $effectiveInverted);
        }

        return null;
    }

    /**
     * Generate a "hors-groupe" intrus question: 3 spices from the same aromatic group + 1 outsider.
     * The outsider (intruder) is a spice that belongs to a different aromatic group.
     *
     * @param list<int> $excludeBaseIds
     *
     * @return array{type: string, prompt: string, baseSpice: array<string, mixed>, options: array<int, array<string, mixed>>, correctAnswerId: int, isInverted: bool, metadata: array<string, mixed>}|null
     */
    private function generateGroupIntrusQuestion(GameDifficulty $difficulty, array $excludeBaseIds = []): ?array
    {
        $allSpices = $this->getAllSpices();

        // Group spices by aromatic group ID — only keep spices that have a group
        $byGroup = [];
        foreach ($allSpices as $spice) {
            $group = $spice->getAromaticGroups();
            if ($group === null) {
                continue;
            }

            $byGroup[$group->getId()][] = $spice;
        }

        // Keep only groups with at least 4 spices (3 same-group + 1 intruder pool)
        $eligibleGroups = array_filter($byGroup, fn (array $spices) => count($spices) >= 4);

        if (count($eligibleGroups) < 2) {
            return null;
        }

        // Shuffle for randomness, then try each group
        $groupIds = array_keys($eligibleGroups);
        shuffle($groupIds);

        foreach ($groupIds as $groupId) {
            $groupSpices = $eligibleGroups[$groupId];
            shuffle($groupSpices);

            /** @var Spices $sample */
            $sample = $groupSpices[0];
            $groupName = $sample->getAromaticGroups()?->getName() ?? 'ce groupe';

            $excludeFlipped = array_flip($excludeBaseIds);
            $groupSpices = array_values(array_filter(
                $groupSpices,
                fn (Spices $s) => ! isset($excludeFlipped[$s->getId()]),
            ));

            if (\count($groupSpices) < 3) {
                continue;
            }

            // Pick 3 spices from this group as the "non-intruders"
            $members = array_slice($groupSpices, 0, 3);

            // Pick 1 intruder from any other group (reuse $excludeFlipped defined above)
            $outsiders = [];
            foreach ($allSpices as $spice) {
                if ($spice->getAromaticGroups()?->getId() !== $groupId && ! isset($excludeFlipped[$spice->getId()])) {
                    $outsiders[] = $spice;
                }
            }

            if (empty($outsiders)) {
                continue;
            }

            /** @var Spices $intruder */
            $intruder = $outsiders[array_rand($outsiders)];

            $options = [];
            foreach ($members as $member) {
                $options[] = $this->spiceToOption($member);
            }

            $options[] = $this->spiceToOption($intruder);

            shuffle($options);

            return [
                'type' => 'intrus_group',
                'prompt' => sprintf('Quelle épice n\'appartient pas au groupe « %s » ?', $groupName),
                'baseSpice' => [
                    'id' => 0,
                    'name' => '',
                ],
                'options' => $options,
                'correctAnswerId' => $intruder->getId(),
                'isInverted' => false,
                'metadata' => [
                    'difficulty' => $difficulty->value,
                ],
            ];
        }

        return null;
    }

    /**
     * Generate options for the next survival pick.
     *
     * @param list<int> $usedIds
     *
     * @return list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string, isCompatible: bool}>
     */
    public function generateSurvivalOptions(
        Spices $current,
        GameDifficulty $difficulty,
        array $usedIds = [],
    ): array {
        $compatibles = $this->findCompatibleSpices($current);

        // Exclude already used spices
        $usedFlipped = array_flip($usedIds);
        $compatibles = array_values(array_filter($compatibles, fn (array $c) => ! isset($usedFlipped[$c['id']])));

        if (empty($compatibles)) {
            return []; // Pool exhaustion → victory
        }

        // Filter by difficulty
        $filtered = $this->filterByDifficulty($compatibles, $difficulty);

        if (empty($filtered)) {
            $filtered = $compatibles;
        }

        // Pick some compatible ones
        $optionCount = match ($difficulty) {
            GameDifficulty::EASY => 6,
            GameDifficulty::MEDIUM => 5,
            GameDifficulty::HARD => 4,
        };

        shuffle($filtered);
        $correctOptions = array_slice($filtered, 0, max(1, (int) ceil($optionCount * 0.6)));

        // Add some incompatible traps
        $intruders = $this->findIntruders($current, $usedIds);
        shuffle($intruders);
        $trapCount = $optionCount - count($correctOptions);
        $traps = array_slice($intruders, 0, $trapCount);

        $options = [];

        foreach ($correctOptions as $c) {
            $options[] = [
                'id' => $c['id'],
                'name' => $c['name'],
                'file' => $c['file'],
                'color' => $c['color'],
                'groupName' => $c['groupName'],
                'isCompatible' => true,
            ];
        }

        foreach ($traps as $trap) {
            $options[] = [
                'id' => $trap->getId(),
                'name' => $trap->getName(),
                'file' => $trap->getFile(),
                'color' => $trap->getAromaticGroups()?->getColor(),
                'groupName' => $trap->getAromaticGroups()?->getName(),
                'isCompatible' => false,
            ];
        }

        shuffle($options);

        return array_slice($options, 0, $optionCount);
    }

    /**
     * Generate an ordered sequence of clues for Guess Who.
     *
     * @param array<string, mixed> $spiceCard From getAllSpiceCards()
     *
     * @return array<array{type: string, label: string, value: string}>
     */
    public function generateGuessWhoClues(array $spiceCard, GameDifficulty $difficulty): array
    {
        $clues = [];

        // 1. Alchemy flavors
        $flavors = $spiceCard['alchemyFlavors'] ?? [];

        if (! empty($flavors)) {
            $clues[] = [
                'type' => 'flavors',
                'label' => 'Saveurs',
                'value' => implode(', ', $flavors),
            ];
        }

        // 2. Group name
        if (! empty($spiceCard['aromaticGroup']['name'])) {
            $clues[] = [
                'type' => 'group_name',
                'label' => 'Famille aromatique',
                'value' => $spiceCard['aromaticGroup']['name'],
            ];
        }

        // 3. Spicy type
        if (! empty($spiceCard['spicyType'])) {
            $clues[] = [
                'type' => 'spicy_type',
                'label' => 'Type',
                'value' => $spiceCard['spicyType'],
            ];
        }

        // 4. Cooking tip
        $cookingTips = $spiceCard['cookingTips'] ?? [];

        if (! empty($cookingTips)) {
            $tip = $cookingTips[0];
            $clues[] = [
                'type' => 'cooking_tip',
                'label' => 'Conseil de cuisson',
                'value' => $tip['title'] ?? $tip['cookingStep'] ?? '',
            ];
        }

        // 5. Main compound names
        $mainCompounds = $spiceCard['mainCompounds'] ?? [];

        if (! empty($mainCompounds)) {
            $clues[] = [
                'type' => 'main_compounds',
                'label' => 'Composés principaux',
                'value' => implode(', ', $mainCompounds),
            ];
        }

        // 6. Description
        if (! empty($spiceCard['description'])) {
            $clues[] = [
                'type' => 'description',
                'label' => 'Description',
                'value' => mb_substr($spiceCard['description'], 0, 120) . '…',
            ];
        }

        // Limit by difficulty
        $maxClues = match ($difficulty) {
            GameDifficulty::EASY => 6,
            GameDifficulty::MEDIUM => 4,
            GameDifficulty::HARD => 3,
        };

        return array_slice($clues, 0, $maxClues);
    }

    /**
     * Count available clue types for a spice card (used to filter eligible spices for Guess Who).
     *
     * @param array<string, mixed> $spiceCard
     */
    public function countAvailableClues(array $spiceCard): int
    {
        $count = 0;

        if (! empty($spiceCard['description'])) {
            ++$count;
        }

        if (! empty($spiceCard['alchemyFlavors'])) {
            ++$count;
        }

        if (! empty($spiceCard['mainCompounds'])) {
            ++$count;
        }

        if (! empty($spiceCard['spicyType'])) {
            ++$count;
        }

        if (! empty($spiceCard['aromaticGroup']['name'])) {
            ++$count;
        }

        if (! empty($spiceCard['cookingTips'])) {
            ++$count;
        }

        return $count;
    }

    /**
     * Get the number of guess options for Guess Who based on difficulty.
     */
    public function getGuessWhoOptionsCount(GameDifficulty $difficulty): int
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 2,
            GameDifficulty::MEDIUM => 3,
            GameDifficulty::HARD => 4,
        };
    }

    /**
     * Get the global time limit in seconds for Chrono mode.
     */
    public function getChronoTimeLimit(GameDifficulty $difficulty): int
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 90,
            GameDifficulty::MEDIUM => 75,
            GameDifficulty::HARD => 60,
        };
    }

    /**
     * Get the number of name options for Chrono mode.
     */
    public function getChronoOptionsCount(GameDifficulty $difficulty): int
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 4,
            GameDifficulty::MEDIUM => 6,
            GameDifficulty::HARD => 8,
        };
    }

    /**
     * Get max errors for Hangman based on difficulty.
     */
    public function getHangmanMaxErrors(GameDifficulty $difficulty): int
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 6,
            GameDifficulty::MEDIUM => 5,
            GameDifficulty::HARD => 4,
        };
    }

    /**
     * Pick a random spice suitable for hangman.
     * EASY prefers shorter names (≤ 12 chars).
     *
     * @param list<int> $excludeIds
     */
    public function pickHangmanSpice(GameDifficulty $difficulty, array $excludeIds = []): ?Spices
    {
        $allSpices = $this->getAllSpices();
        $excludeFlipped = array_flip($excludeIds);
        $candidates = array_filter($allSpices, fn (Spices $s) => ! isset($excludeFlipped[$s->getId()]));

        if (empty($candidates)) {
            return null;
        }

        if ($difficulty === GameDifficulty::EASY) {
            $short = array_filter($candidates, fn (Spices $s) => mb_strlen($s->getName()) <= 12);

            if (! empty($short)) {
                $candidates = $short;
            }
        }

        $candidates = array_values($candidates);

        return $candidates[array_rand($candidates)];
    }

    /**
     * Generate distractor name options for Chrono or Guess Who.
     *
     * @param list<string> $excludeNames
     *
     * @return list<string> Shuffled array of spice names including the correct one
     */
    public function generateNameOptions(string $correctName, int $optionsCount, array $excludeNames = []): array
    {
        $cards = $this->getAllSpiceCards();
        $allNames = array_column($cards, 'name');
        $excludeNamesFlipped = array_flip($excludeNames);
        $available = array_filter(
            $allNames,
            fn (string $n) => $n !== $correctName && ! isset($excludeNamesFlipped[$n]),
        );
        $available = array_values($available);
        shuffle($available);

        $distractors = array_slice($available, 0, $optionsCount - 1);
        $options = [...$distractors, $correctName];
        shuffle($options);

        return $options;
    }

    // ──────────────────────────────────────────────
    // Briefing — Plan de Travail
    // ──────────────────────────────────────────────

    /**
     * Pick a target spice for the briefing screen.
     * Returns null for QCM/INTRUS (they don't need a pre-selected target).
     * Excludes recently visited spices (FIFO 10 from UserStat) for variety.
     */
    public function pickTargetSpice(GameMode $mode, GameDifficulty $difficulty, Users $user): ?Spices
    {
        if ($mode === GameMode::QCM || $mode === GameMode::INTRUS) {
            return null;
        }

        $excludeIds = $user->getStats()?->getLastVisitedSpices() ?? [];
        $allSpices = $this->getAllSpices();

        $excludeFlipped = array_flip($excludeIds);
        $candidates = array_filter($allSpices, fn (Spices $s) => ! isset($excludeFlipped[$s->getId()]));

        // For Hangman EASY, prefer shorter names
        if ($mode === GameMode::HANGMAN && $difficulty === GameDifficulty::EASY) {
            $short = array_filter($candidates, fn (Spices $s) => mb_strlen($s->getName()) <= 12);
            if (! empty($short)) {
                $candidates = $short;
            }
        }

        if (empty($candidates)) {
            // Fallback: ignore exclusions
            $candidates = $allSpices;
        }

        $candidates = array_values($candidates);

        return $candidates[array_rand($candidates)];
    }

    /**
     * Pick a random CookingTip for the given spice (used in the briefing infographic).
     */
    public function randomCookingTipFor(Spices $spice): ?CookingTips
    {
        $tips = $spice->getCookingTips()
            ->toArray();

        if (empty($tips)) {
            return null;
        }

        return $tips[array_rand($tips)];
    }

    /**
     * Get the rules/consignes for a given game mode (displayed in the briefing).
     *
     * @return string[]
     */
    public function getRulesFor(GameMode $mode): array
    {
        return match ($mode) {
            GameMode::QCM => [
                'Trouve l\'épice la plus compatible parmi 4 propositions.',
                'Chaque bonne réponse te rapporte des XP.',
                '7 questions au total — pas de retour en arrière.',
            ],
            GameMode::SURVIVAL => [
                'Enchaîne les épices compatibles avec l\'épice de départ.',
                'Une seule erreur et la partie est terminée.',
                'Plus tu avances, plus tu gagnes d\'XP.',
            ],
            GameMode::GUESS_WHO => [
                'Des indices apparaissent un par un pour identifier l\'épice mystère.',
                'Moins tu utilises d\'indices, plus tu gagnes de points.',
                '7 épices à deviner au total.',
            ],
            GameMode::INTRUS => [
                'Parmi 4 épices, trouve celle qui n\'a rien en commun avec les autres.',
                'Certaines questions sont inversées : trouve la compatible !',
                '7 questions — attention aux pièges visuels.',
            ],
            GameMode::HANGMAN => [
                'Devine le nom de l\'épice lettre par lettre.',
                'Les accents sont ignorés — tape la lettre de base.',
                'Trop d\'erreurs et le pendu est complet.',
            ],
            GameMode::CHRONO => [
                'Identifie chaque épice le plus vite possible.',
                'Tu vois la photo et les caractéristiques, mais pas le nom.',
                'Le temps est compté — chaque seconde compte.',
            ],
        };
    }

    // ──────────────────────────────────────────────
    // Intrus — mode strict (Chef de Partie)
    // ──────────────────────────────────────────────

    /**
     * Find intruders for strict mode (Chef de Partie).
     * Instead of 0-compatibility, returns spices with low but non-zero score (1-15/100).
     * These are trickier to spot as intruders.
     *
     * @param list<int> $excludeIds
     *
     * @return list<Spices>
     */
    public function findStrictIntruders(Spices $baseSpice, array $excludeIds = []): array
    {
        $cacheKey = 'academy.intruders.strict.' . $baseSpice->getId();

        $allStrictIntruders = $this->cache->get($cacheKey, function (ItemInterface $item) use ($baseSpice): array {
            $item->expiresAfter(3600);

            $id = $baseSpice->getId();
            $compatibles = $id !== null ? $this->compatibleSpiceFinder->findCompatible(new MortarIds([$id]), 100) : [];

            // Keep only scores 1–15 : barely compatible = hard to distinguish
            $lowScored = array_filter($compatibles, fn (array $c) => $c['score'] >= 1 && $c['score'] <= 15);

            if (empty($lowScored)) {
                // Fallback: widen to 1–25
                $lowScored = array_filter($compatibles, fn (array $c) => $c['score'] >= 1 && $c['score'] <= 25);
            }

            if (empty($lowScored)) {
                // Ultimate fallback: true intruders
                return $this->spicesRepository->findIncompatibleWith($baseSpice);
            }

            // Load full entities
            $ids = array_column($lowScored, 'id');

            return $this->spicesRepository->createQueryBuilder('s')
                ->addSelect('ag')
                ->leftJoin('s.aromaticGroups', 'ag')
                ->where('s.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        });

        if (empty($excludeIds)) {
            return $allStrictIntruders;
        }

        $excludeFlipped = array_flip($excludeIds);

        return array_values(array_filter(
            $allStrictIntruders,
            fn (Spices $s) => ! isset($excludeFlipped[$s->getId()]),
        ));
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * @return array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}
     */
    private function spiceToOption(Spices $spice): array
    {
        return [
            'id' => $spice->getId(),
            'name' => $spice->getName(),
            'file' => $spice->getFile(),
            'color' => $spice->getAromaticGroups()?->getColor(),
            'groupName' => $spice->getAromaticGroups()?->getName(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAllSpiceCards(): array
    {
        $spices = $this->spicesRepository->createQueryBuilder('s')
            ->addSelect('ag', 'st', 'mainAc', 'secAc', 'af', 'ct', 'pt', 'pm')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->leftJoin('s.spicyType', 'st')
            ->leftJoin('s.aromaticsCompounds', 'mainAc')
            ->leftJoin('s.secondary_aromatics_compounds', 'secAc')
            ->leftJoin('mainAc.alchemyFlavors', 'af')
            ->leftJoin('s.cookingTips', 'ct')
            ->leftJoin('s.preparationTips', 'pt')
            ->leftJoin('pt.preparationMethod', 'pm')
            ->where('s.deleted_at IS NULL')
            ->getQuery()
            ->getResult();

        $cards = [];

        /** @var Spices $spice */
        foreach ($spices as $spice) {
            $flavors = [];

            foreach ($spice->getAromaticsCompounds() as $compound) {
                foreach ($compound->getAlchemyFlavors() as $flavor) {
                    $flavors[$flavor->getName()] = true;
                }
            }

            foreach ($spice->getSecondaryAromaticsCompounds() as $compound) {
                foreach ($compound->getAlchemyFlavors() as $flavor) {
                    $flavors[$flavor->getName()] = true;
                }
            }

            $cookingTips = [];

            foreach ($spice->getCookingTips() as $tip) {
                $cookingTips[] = [
                    'title' => $tip->getTitle(),
                    'cookingStep' => $tip->getCookingStep(),
                ];
            }

            $preparationTips = [];

            foreach ($spice->getPreparationTips() as $tip) {
                $preparationTips[] = [
                    'title' => $tip->getTitle(),
                    'method' => $tip->getPreparationMethod()?->getName(),
                ];
            }

            $cards[$spice->getId()] = [
                'id' => $spice->getId(),
                'name' => $spice->getName(),
                'slug' => $spice->getSlug(),
                'file' => $spice->getFile(),
                'aromaticGroup' => [
                    'name' => $spice->getAromaticGroups()?->getName(),
                    'color' => $spice->getAromaticGroups()?->getColor(),
                ],
                'spicyType' => $spice->getSpicyType()?->getName(),
                'mainCompounds' => array_map(
                    fn ($c) => $c->getName(),
                    $spice->getAromaticsCompounds()
                        ->toArray(),
                ),
                'secondaryCompounds' => array_map(
                    fn ($c) => $c->getName(),
                    $spice->getSecondaryAromaticsCompounds()
                        ->toArray(),
                ),
                'alchemyFlavors' => array_keys($flavors),
                'cookingTips' => $cookingTips,
                'preparationTips' => $preparationTips,
                'description' => $spice->getDescription(),
                'benefits' => $spice->getBenefits(),
            ];
        }

        return $cards;
    }

    /**
     * @param list<array<string, mixed>>|list<Spices> $compatibles
     * @param list<Spices>                            $intruders
     *
     * @return array{type: string, prompt: string, baseSpice: array<string, mixed>, options: array<int, array<string, mixed>>, correctAnswerId: int, isInverted: bool, metadata: array<string, mixed>}
     */
    private function buildIntrusQuestion(
        Spices $baseSpice,
        array $compatibles,
        array $intruders,
        GameDifficulty $difficulty,
        bool $inverted,
    ): array {
        if ($inverted) {
            // 3 intruders + 1 compatible
            shuffle($intruders);
            $pickedIntruders = array_slice($intruders, 0, 3);

            // Pick best compatible based on difficulty
            $filteredCompatibles = $this->filterCompatiblesForIntrus($compatibles, $difficulty);
            $correctEntry = $filteredCompatibles[array_rand($filteredCompatibles)];

            $options = [];

            foreach ($pickedIntruders as $intruder) {
                $options[] = $this->spiceToOption($intruder);
            }

            $options[] = [
                'id' => $correctEntry['id'],
                'name' => $correctEntry['name'],
                'file' => $correctEntry['file'],
                'color' => $correctEntry['color'],
            ];

            shuffle($options);

            return [
                'type' => 'intrus',
                'prompt' => sprintf('Quelle épice est compatible avec %s ?', $baseSpice->getName()),
                'baseSpice' => [
                    'id' => $baseSpice->getId(),
                    'name' => $baseSpice->getName(),
                ],
                'options' => $options,
                'correctAnswerId' => $correctEntry['id'],
                'isInverted' => true,
                'metadata' => [
                    'difficulty' => $difficulty->value,
                ],
            ];
        }

        // Classic: 3 compatibles + 1 intruder
        $filteredCompatibles = $this->filterCompatiblesForIntrus($compatibles, $difficulty);

        shuffle($filteredCompatibles);
        $pickedCompatibles = array_slice($filteredCompatibles, 0, 3);

        shuffle($intruders);
        $intruder = $intruders[0];

        // For HARD, try to pick an intruder from same SpicyType (visual trap)
        if ($difficulty === GameDifficulty::HARD && $baseSpice->getSpicyType() !== null) {
            $sameTypeIntruders = array_filter(
                $intruders,
                fn (Spices $s) => $s->getSpicyType()?->getId() === $baseSpice->getSpicyType()
                    ->getId(),
            );

            if (! empty($sameTypeIntruders)) {
                $sameTypeIntruders = array_values($sameTypeIntruders);
                $intruder = $sameTypeIntruders[array_rand($sameTypeIntruders)];
            }
        }

        $options = [];

        foreach ($pickedCompatibles as $c) {
            $options[] = [
                'id' => $c['id'],
                'name' => $c['name'],
                'file' => $c['file'],
                'color' => $c['color'],
            ];
        }

        $options[] = $this->spiceToOption($intruder);

        shuffle($options);

        return [
            'type' => 'intrus',
            'prompt' => sprintf('Quelle épice est l\'intrus par rapport à %s ?', $baseSpice->getName()),
            'baseSpice' => [
                'id' => $baseSpice->getId(),
                'name' => $baseSpice->getName(),
            ],
            'options' => $options,
            'correctAnswerId' => $intruder->getId(),
            'isInverted' => false,
            'metadata' => [
                'difficulty' => $difficulty->value,
            ],
        ];
    }

    /**
     * Filter compatible spices based on difficulty for Intrus mode.
     *
     * EASY: score > 70, MEDIUM: 40-70, HARD: 20-50
     *
     * @param list<array<string, mixed>> $compatibles
     *
     * @return list<array<string, mixed>>
     */
    private function filterCompatiblesForIntrus(array $compatibles, GameDifficulty $difficulty): array
    {
        $filtered = match ($difficulty) {
            GameDifficulty::EASY => array_filter($compatibles, fn (array $c) => $c['score'] > 70),
            GameDifficulty::MEDIUM => array_filter(
                $compatibles,
                fn (array $c) => $c['score'] >= 40 && $c['score'] <= 70
            ),
            GameDifficulty::HARD => array_filter($compatibles, fn (array $c) => $c['score'] >= 20 && $c['score'] <= 50),
        };

        $filtered = array_values($filtered);

        // Fallback: if no spices match the range, use all compatibles
        if (count($filtered) < 3) {
            return $compatibles;
        }

        return $filtered;
    }
}
