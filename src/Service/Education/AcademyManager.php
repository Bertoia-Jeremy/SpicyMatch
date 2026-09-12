<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\SpicesRepository;
use App\Service\Match\CompatibleSpiceFinder;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AcademyManager
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibleSpiceFinder $compatibleSpiceFinder,
        private readonly CacheInterface $cache,
        private readonly TranslatorInterface $translator,
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

                return $this->spicesRepository->findAllActive();
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

        $locale = $this->currentLocale();

        return $this->cache->get('academy.compatible.' . $locale . '.' . $id, function (ItemInterface $item) use (
            $id
        ): array {
            $item->expiresAfter(3600);

            return $this->compatibleSpiceFinder->findCompatible(new MortarIds([$id]), 100, new CulinaryContext());
        });
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

    /**
     * @param list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}> $summaries
     *
     * @return list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>
     */
    public function localizeSpiceSummaries(array $summaries): array
    {
        return $this->localizedOptions($summaries);
    }

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
    ): ?array {
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
            $compatibles = $this->rankedCompatibles($baseSpice);
            $intruders = $this->findIntruders(
                $baseSpice,
                [...$excludeBaseIds, ...array_map(static fn (array $c) => (int) $c['id'], $compatibles)],
            );

            $question = $inverted
                ? $this->buildInvertedIntrusQuestion($baseSpice, $compatibles, $intruders, $difficulty)
                : null;

            $question ??= $this->buildClassicIntrusQuestion($baseSpice, $compatibles, $intruders, $difficulty);

            if ($question !== null) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rankedCompatibles(Spices $baseSpice): array
    {
        $seen = [];
        $ranked = [];

        foreach ($this->findCompatibleSpices($baseSpice) as $entry) {
            $id = (int) $entry['id'];

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $ranked[] = $entry;
        }

        usort($ranked, static fn (array $a, array $b) => (int) $b['score'] <=> (int) $a['score']);

        return $ranked;
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
                $options[] = $this->toOption($member);
            }

            $options[] = $this->toOption($intruder);

            shuffle($options);

            return [
                'type' => 'intrus_group',
                'prompt' => $this->translator->trans('ui.edu.prompt.intrus_group', [
                    '%group%' => $groupName,
                ]),
                'baseSpice' => [
                    'id' => 0,
                    'name' => '',
                ],
                'options' => $this->localizedOptions($options),
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

        $usedFlipped = array_flip($usedIds);
        $compatibles = array_values(array_filter($compatibles, fn (array $c) => ! isset($usedFlipped[$c['id']])));

        if (empty($compatibles)) {
            return [];
        }

        $filtered = $this->filterByDifficulty($compatibles, $difficulty);

        if (empty($filtered)) {
            $filtered = $compatibles;
        }

        $optionCount = match ($difficulty) {
            GameDifficulty::EASY => 6,
            GameDifficulty::MEDIUM => 5,
            GameDifficulty::HARD => 4,
        };

        shuffle($filtered);
        $correctOptions = array_slice($filtered, 0, max(1, (int) ceil($optionCount * 0.6)));

        $intruders = $this->findIntruders($current, $usedIds);
        shuffle($intruders);
        $trapCount = $optionCount - count($correctOptions);
        $traps = array_slice($intruders, 0, $trapCount);

        $options = [];
        $compatibleFlags = [];

        foreach ($correctOptions as $c) {
            $options[] = $this->toOption($c);
            $compatibleFlags[] = true;
        }

        foreach ($traps as $trap) {
            $options[] = $this->toOption($trap);
            $compatibleFlags[] = false;
        }

        $survivalOptions = [];

        foreach ($this->localizedOptions($options) as $index => $option) {
            $survivalOptions[] = [
                'id' => $option['id'],
                'name' => $option['name'],
                'file' => $option['file'],
                'color' => $option['color'],
                'groupName' => $option['groupName'],
                'isCompatible' => $compatibleFlags[$index],
            ];
        }

        shuffle($survivalOptions);

        return array_slice($survivalOptions, 0, $optionCount);
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
                'label' => $this->translator->trans('ui.edu.clue.flavors'),
                'value' => implode(', ', $flavors),
            ];
        }

        // 2. Group name
        if (! empty($spiceCard['aromaticGroup']['name'])) {
            $clues[] = [
                'type' => 'group_name',
                'label' => $this->translator->trans('ui.edu.clue.group'),
                'value' => $spiceCard['aromaticGroup']['name'],
            ];
        }

        // 3. Spicy type
        if (! empty($spiceCard['spicyType'])) {
            $clues[] = [
                'type' => 'spicy_type',
                'label' => $this->translator->trans('ui.edu.clue.type'),
                'value' => $spiceCard['spicyType'],
            ];
        }

        // 4. Cooking tip
        $cookingTips = $spiceCard['cookingTips'] ?? [];

        if (! empty($cookingTips)) {
            $tip = $cookingTips[0];
            $clues[] = [
                'type' => 'cooking_tip',
                'label' => $this->translator->trans('ui.edu.clue.cooking_tip'),
                'value' => $tip['title'] ?? $tip['cookingStep'] ?? '',
            ];
        }

        // 5. Main compound names
        $mainCompounds = $spiceCard['mainCompounds'] ?? [];

        if (! empty($mainCompounds)) {
            $clues[] = [
                'type' => 'main_compounds',
                'label' => $this->translator->trans('ui.edu.clue.main_compounds'),
                'value' => implode(', ', $mainCompounds),
            ];
        }

        // 6. Description
        if (! empty($spiceCard['description'])) {
            $clues[] = [
                'type' => 'description',
                'label' => $this->translator->trans('ui.edu.clue.description'),
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
     * Get the rules/consignes for a given game mode (displayed in the briefing).
     *
     * @return string[]
     */
    public function getRulesFor(GameMode $mode): array
    {
        $keys = match ($mode) {
            GameMode::QCM => ['ui.edu.rule.qcm_0', 'ui.edu.rule.qcm_1', 'ui.edu.rule.qcm_2'],
            GameMode::SURVIVAL => ['ui.edu.rule.survival_0', 'ui.edu.rule.survival_1', 'ui.edu.rule.survival_2'],
            GameMode::GUESS_WHO => ['ui.edu.rule.guess_who_0', 'ui.edu.rule.guess_who_1', 'ui.edu.rule.guess_who_2'],
            GameMode::INTRUS => ['ui.edu.rule.intrus_0', 'ui.edu.rule.intrus_1', 'ui.edu.rule.intrus_2'],
            GameMode::HANGMAN => ['ui.edu.rule.hangman_0', 'ui.edu.rule.hangman_1', 'ui.edu.rule.hangman_2'],
            GameMode::CHRONO => ['ui.edu.rule.chrono_0', 'ui.edu.rule.chrono_1', 'ui.edu.rule.chrono_2'],
        };

        return array_map(fn (string $key): string => $this->translator->trans($key), $keys);
    }

    private function currentLocale(): string
    {
        return $this->translator instanceof LocaleAwareInterface ? $this->translator->getLocale() : 'fr';
    }

    /**
     * @param list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}> $options
     *
     * @return list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>
     */
    private function localizedOptions(array $options): array
    {
        $locale = $this->currentLocale();

        if ($options === [] || $locale === 'fr') {
            return $options;
        }

        $enriched = $this->spicesRepository->findEnrichedByIds(
            array_map(static fn (array $option) => $option['id'], $options),
            $locale,
        );

        $byId = array_column($enriched, null, 'id');

        return array_map(static function (array $option) use ($byId): array {
            $row = $byId[$option['id']] ?? null;

            if ($row === null) {
                return $option;
            }

            $option['name'] = (string) $row['name'];
            $option['groupName'] = $row['groupName'] !== null ? (string) $row['groupName'] : null;

            return $option;
        }, $options);
    }

    /**
     * @param Spices|array<string, mixed> $source
     *
     * @return array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}
     */
    private function toOption(Spices|array $source): array
    {
        if ($source instanceof Spices) {
            return [
                'id' => (int) $source->getId(),
                'name' => (string) $source->getName(),
                'file' => $source->getFile(),
                'color' => $source->getAromaticGroups()?->getColor(),
                'groupName' => $source->getAromaticGroups()?->getName(),
            ];
        }

        return [
            'id' => (int) $source['id'],
            'name' => (string) $source['name'],
            'file' => null !== ($source['file'] ?? null) ? (string) $source['file'] : null,
            'color' => null !== ($source['color'] ?? null) ? (string) $source['color'] : null,
            'groupName' => null !== ($source['groupName'] ?? null) ? (string) $source['groupName'] : null,
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
     * @param list<array<string, mixed>> $compatibles
     * @param list<Spices>               $intruders
     *
     * @return array{type: string, prompt: string, baseSpice: array<string, mixed>, options: array<int, array<string, mixed>>, correctAnswerId: int, isInverted: bool, metadata: array<string, mixed>}|null
     */
    private function buildClassicIntrusQuestion(
        Spices $baseSpice,
        array $compatibles,
        array $intruders,
        GameDifficulty $difficulty,
    ): ?array {
        if (count($compatibles) < 3) {
            return null;
        }

        $eligible = $this->eligibleIntruders($compatibles, $intruders);

        if ($eligible === []) {
            return null;
        }

        $window = $this->preferSameSpicyType(
            OrdinalWindow::select($eligible, $difficulty, 1),
            $baseSpice,
            $difficulty,
        );
        shuffle($window);
        $intruder = $window[0];

        $companions = OrdinalWindow::select(array_slice($compatibles, 0, $intruder['cut']), $difficulty, 3);
        shuffle($companions);

        $options = [];

        foreach (array_slice($companions, 0, 3) as $companion) {
            $options[] = $this->toOption($companion);
        }

        $options[] = $intruder['option'];

        shuffle($options);

        return $this->intrusQuestion(
            $baseSpice,
            'ui.edu.prompt.intrus_classic',
            $options,
            $intruder['option']['id'],
            false,
            $difficulty,
        );
    }

    /**
     * @param list<array<string, mixed>> $compatibles
     * @param list<Spices>               $intruders
     *
     * @return array{type: string, prompt: string, baseSpice: array<string, mixed>, options: array<int, array<string, mixed>>, correctAnswerId: int, isInverted: bool, metadata: array<string, mixed>}|null
     */
    private function buildInvertedIntrusQuestion(
        Spices $baseSpice,
        array $compatibles,
        array $intruders,
        GameDifficulty $difficulty,
    ): ?array {
        $scores = array_map(static fn (array $entry) => (int) $entry['score'], $compatibles);
        $intruderCount = count($intruders);
        $eligibleAnswers = [];

        foreach ($compatibles as $index => $entry) {
            $below = $intruderCount + count(array_filter($scores, static fn (int $s) => $s < $scores[$index]));

            if ($below >= 3) {
                $eligibleAnswers[] = $entry;
            }
        }

        if ($eligibleAnswers === []) {
            return null;
        }

        $answerWindow = OrdinalWindow::select($eligibleAnswers, $difficulty, 1);
        shuffle($answerWindow);
        $answer = $answerWindow[0];
        $answerScore = (int) $answer['score'];

        $decoys = [];

        foreach ($intruders as $intruder) {
            $decoys[] = $this->toOption($intruder);
        }

        for ($index = count($compatibles) - 1; $index >= 0; --$index) {
            if ($scores[$index] < $answerScore) {
                $decoys[] = $this->toOption($compatibles[$index]);
            }
        }

        $decoyWindow = OrdinalWindow::select($decoys, $difficulty, 3);
        shuffle($decoyWindow);

        $options = array_slice($decoyWindow, 0, 3);
        $options[] = $this->toOption($answer);

        shuffle($options);

        return $this->intrusQuestion(
            $baseSpice,
            'ui.edu.prompt.intrus_compatible',
            $options,
            (int) $answer['id'],
            true,
            $difficulty,
        );
    }

    /**
     * @param list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}> $options
     *
     * @return array{type: string, prompt: string, baseSpice: array<string, mixed>, options: array<int, array<string, mixed>>, correctAnswerId: int, isInverted: bool, metadata: array<string, mixed>}
     */
    private function intrusQuestion(
        Spices $baseSpice,
        string $promptKey,
        array $options,
        int $correctAnswerId,
        bool $inverted,
        GameDifficulty $difficulty,
    ): array {
        return [
            'type' => 'intrus',
            'prompt' => $this->translator->trans($promptKey, [
                '%spice%' => $baseSpice->getName(),
            ]),
            'baseSpice' => [
                'id' => $baseSpice->getId(),
                'name' => $baseSpice->getName(),
            ],
            'options' => $this->localizedOptions($options),
            'correctAnswerId' => $correctAnswerId,
            'isInverted' => $inverted,
            'metadata' => [
                'difficulty' => $difficulty->value,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $compatibles
     * @param list<Spices>               $intruders
     *
     * @return list<array{option: array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}, cut: int, spicyTypeId: ?int}>
     */
    private function eligibleIntruders(array $compatibles, array $intruders): array
    {
        $total = count($compatibles);
        $eligible = [];

        foreach ($intruders as $intruder) {
            $eligible[] = [
                'option' => $this->toOption($intruder),
                'cut' => $total,
                'spicyTypeId' => $intruder->getSpicyType()?->getId(),
            ];
        }

        $boundaries = $this->strictBoundaries($compatibles);

        for ($index = $total - 1; $index >= 0; --$index) {
            if ($boundaries[$index] < 3) {
                continue;
            }

            $spicyTypeId = $compatibles[$index]['stId'] ?? null;

            $eligible[] = [
                'option' => $this->toOption($compatibles[$index]),
                'cut' => $boundaries[$index],
                'spicyTypeId' => $spicyTypeId !== null ? (int) $spicyTypeId : null,
            ];
        }

        return $eligible;
    }

    /**
     * @param list<array<string, mixed>> $compatibles
     *
     * @return list<int>
     */
    private function strictBoundaries(array $compatibles): array
    {
        $firstOfScore = [];
        $boundaries = [];

        foreach ($compatibles as $index => $entry) {
            $score = (int) $entry['score'];
            $firstOfScore[$score] ??= $index;
            $boundaries[] = $firstOfScore[$score];
        }

        return $boundaries;
    }

    /**
     * @param list<array{option: array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}, cut: int, spicyTypeId: ?int}> $window
     *
     * @return list<array{option: array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}, cut: int, spicyTypeId: ?int}>
     */
    private function preferSameSpicyType(array $window, Spices $baseSpice, GameDifficulty $difficulty): array
    {
        $baseTypeId = $baseSpice->getSpicyType()?->getId();

        if ($difficulty !== GameDifficulty::HARD || $baseTypeId === null) {
            return $window;
        }

        $sameType = array_values(array_filter($window, static fn (array $e) => $e['spicyTypeId'] === $baseTypeId));

        return $sameType === [] ? $window : $sameType;
    }
}
