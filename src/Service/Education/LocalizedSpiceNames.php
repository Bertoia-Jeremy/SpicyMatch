<?php

declare(strict_types=1);

namespace App\Service\Education;

final class LocalizedSpiceNames
{
    /**
     * @param array<int, array<string, mixed>> $cards
     *
     * @return array<int, array{canonical: string, localized: string, groupName: ?string}>
     */
    public static function build(array $cards, AcademyManager $academyManager): array
    {
        $map = [];
        $summaries = [];

        foreach ($cards as $card) {
            $id = (int) $card['id'];
            $name = (string) $card['name'];
            $groupName = $card['aromaticGroup']['name'] ?? null;
            $groupName = $groupName !== null ? (string) $groupName : null;

            $map[$id] = [
                'canonical' => $name,
                'localized' => $name,
                'groupName' => $groupName,
            ];

            $summaries[] = [
                'id' => $id,
                'name' => $name,
                'file' => null,
                'color' => null,
                'groupName' => $groupName,
            ];
        }

        foreach ($academyManager->localizeSpiceSummaries($summaries) as $summary) {
            $id = $summary['id'];

            if (! isset($map[$id])) {
                continue;
            }

            $map[$id]['localized'] = $summary['name'];
            $map[$id]['groupName'] = $summary['groupName'];
        }

        return $map;
    }

    /**
     * @param array<int, array{canonical: string, localized: string, groupName: ?string}> $nameMap
     */
    public static function matches(string $given, string $expected, int $expectedId, array $nameMap): bool
    {
        if ($given === $expected) {
            return true;
        }

        if ($expectedId === 0) {
            return false;
        }

        return $given === ($nameMap[$expectedId]['localized'] ?? $expected);
    }

    /**
     * @param array<int, array{canonical: string, localized: string, groupName: ?string}> $nameMap
     *
     * @return array<string, string>
     */
    public static function labelIndex(array $nameMap): array
    {
        /** @var array<string, string> $labels */
        $labels = array_column($nameMap, 'localized', 'canonical');

        return $labels;
    }

    /**
     * @param array<string, string> $labelIndex
     */
    public static function label(string $canonical, array $labelIndex): string
    {
        return $labelIndex[$canonical] ?? $canonical;
    }
}
