<?php

declare(strict_types=1);

namespace App\Service\Match;

final class SpiceDuoMapBuilder
{
    /**
     * @param list<array{prepId: int, cookId: int, rank: int}> $rows
     * @return array{byPrep: array<int, list<array{c: int, r: int}>>, byCook: array<int, list<array{p: int, r: int}>>}
     */
    public function build(array $rows): array
    {
        $byPrep = [];
        $byCook = [];

        foreach ($rows as $row) {
            $byPrep[$row['prepId']][] = [
                'c' => $row['cookId'],
                'r' => $row['rank'],
            ];
            $byCook[$row['cookId']][] = [
                'p' => $row['prepId'],
                'r' => $row['rank'],
            ];
        }

        return [
            'byPrep' => $byPrep,
            'byCook' => $byCook,
        ];
    }

    /**
     * @param list<array{prepId: int, cookId: int, prepTitle: string|null, title: string, effect: string}> $rows
     * @return array{byPrep: array<int, list<array{p: int, c: int, method: string|null, title: string, effect: string}>>, byCook: array<int, list<array{p: int, c: int, method: string|null, title: string, effect: string}>>}
     */
    public function tooltips(array $rows): array
    {
        $byPrep = [];
        $byCook = [];

        foreach ($rows as $row) {
            $tip = [
                'p' => $row['prepId'],
                'c' => $row['cookId'],
                'method' => $row['prepTitle'],
                'title' => $row['title'],
                'effect' => $row['effect'],
            ];
            $byPrep[$row['prepId']][] = $tip;
            $byCook[$row['cookId']][] = $tip;
        }

        return [
            'byPrep' => $byPrep,
            'byCook' => $byCook,
        ];
    }
}
