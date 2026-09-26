<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\CookingMoment;

final readonly class SpiceDuoRow
{
    public const array COLUMNS = ['spice_slug', 'method_slug', 'moment', 'rank', 'title', 'effect', 'science', 'example'];

    public function __construct(
        public string $spiceSlug,
        public string $methodSlug,
        public CookingMoment $moment,
        public int $rank,
        public string $title,
        public string $effect,
        public string $science,
        public string $example,
    ) {
    }

    public function pairKey(): string
    {
        return \sprintf('%s|%s|%d', $this->spiceSlug, $this->methodSlug, $this->moment->value);
    }

    /**
     * @param array<string, string|null> $data
     */
    public static function fromArray(array $data): self
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            $value = trim((string) ($data[$column] ?? ''));
            if ($value === '') {
                throw new \InvalidArgumentException(\sprintf('Colonne "%s" vide ou absente.', $column));
            }
            $values[$column] = $value;
        }

        return new self(
            $values['spice_slug'],
            $values['method_slug'],
            self::parseMoment($values['moment']),
            self::parseRank($values['rank']),
            $values['title'],
            $values['effect'],
            $values['science'],
            $values['example'],
        );
    }

    private static function parseMoment(string $raw): CookingMoment
    {
        if (ctype_digit($raw)) {
            return CookingMoment::tryFrom((int) $raw)
                ?? throw new \InvalidArgumentException(\sprintf('Moment "%s" inconnu.', $raw));
        }

        foreach (CookingMoment::cases() as $case) {
            if (strcasecmp($case->name, $raw) === 0) {
                return $case;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Moment "%s" inconnu.', $raw));
    }

    private static function parseRank(string $raw): int
    {
        if (! ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 9) {
            throw new \InvalidArgumentException(\sprintf('Rang "%s" invalide (1 à 9).', $raw));
        }

        return (int) $raw;
    }
}
