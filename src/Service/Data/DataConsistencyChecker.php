<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Enum\CookingMoment;

final class DataConsistencyChecker
{
    private const float OAV_PLAUSIBLE_MAX = 1.0e9;

    private const float CONCENTRATION_SUM_IMPOSSIBLE_PPM = 1_000_000.0;

    private const float CONCENTRATION_SUM_IMPLAUSIBLE_PPM = 200_000.0;

    /**
     * @param list<array{spice_id: int, aromatic_compound_id: int, matrix: string, oav_value: float}> $rows
     * @return list<array{severity: string, message: string}>
     */
    public function checkOavValues(array $rows): array
    {
        $violations = [];

        foreach ($rows as $r) {
            $oav = (float) $r['oav_value'];
            $loc = \sprintf('épice %d / composé %d / %s', $r['spice_id'], $r['aromatic_compound_id'], $r['matrix']);

            if ($oav <= 1.0) {
                $violations[] = [
                    'severity' => 'error',
                    'message' => \sprintf('OAV ≤ 1 (%g) — invariant cassé : %s.', $oav, $loc),
                ];
            } elseif ($oav > self::OAV_PLAUSIBLE_MAX) {
                $violations[] = [
                    'severity' => 'warning',
                    'message' => \sprintf('OAV %g > plafond plausible (%g) : %s.', $oav, self::OAV_PLAUSIBLE_MAX, $loc),
                ];
            }
        }

        return $violations;
    }

    /**
     * @param array<int, float>  $sumBySpiceId spice_id => Σ ppm
     * @param array<int, string> $spiceNames
     * @return list<array{severity: string, message: string}>
     */
    public function checkConcentrationSums(array $sumBySpiceId, array $spiceNames = []): array
    {
        $violations = [];

        foreach ($sumBySpiceId as $spiceId => $sum) {
            $name = $spiceNames[$spiceId] ?? ('épice ' . $spiceId);

            if ($sum > self::CONCENTRATION_SUM_IMPOSSIBLE_PPM) {
                $violations[] = [
                    'severity' => 'error',
                    'message' => \sprintf(
                        '%s : Σ concentrations = %g ppm > 10^6 (impossible, > 100 %%).',
                        $name,
                        $sum,
                    ),
                ];
            } elseif ($sum > self::CONCENTRATION_SUM_IMPLAUSIBLE_PPM) {
                $violations[] = [
                    'severity' => 'warning',
                    'message' => \sprintf('%s : Σ concentrations = %g ppm > 20 %% — vérifier.', $name, $sum),
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array{id: int, name: string}> $compoundsWithoutAirOdt
     * @return list<array{severity: string, message: string}>
     */
    public function checkMissingAirOdt(array $compoundsWithoutAirOdt): array
    {
        $violations = [];

        foreach ($compoundsWithoutAirOdt as $c) {
            $violations[] = [
                'severity' => 'warning',
                'message' => \sprintf(
                    'Composé #%d "%s" utilisé en concentration mais sans ODT air — jamais OAV-actif en air.',
                    $c['id'],
                    $c['name'],
                ),
            ];
        }

        return $violations;
    }

    /**
     * @param list<array{id: int, step: int}> $cookingTips
     * @return list<array{severity: string, message: string}>
     */
    public function checkCookingMoments(array $cookingTips): array
    {
        $violations = [];

        foreach ($cookingTips as $tip) {
            if (CookingMoment::tryFrom($tip['step']) === null) {
                $violations[] = [
                    'severity' => 'error',
                    'message' => \sprintf('Conseil de cuisson #%d : step %d hors énumération CookingMoment.', $tip['id'], $tip['step']),
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array{id: int, prep_spice_id: int, cook_spice_id: int}> $duos
     * @return list<array{severity: string, message: string}>
     */
    public function checkSpiceDuoSpices(array $duos): array
    {
        $violations = [];

        foreach ($duos as $duo) {
            if ($duo['prep_spice_id'] !== $duo['cook_spice_id']) {
                $violations[] = [
                    'severity' => 'error',
                    'message' => \sprintf(
                        'Duo #%d : conseil de préparation (épice %d) et conseil de cuisson (épice %d) d\'épices différentes.',
                        $duo['id'],
                        $duo['prep_spice_id'],
                        $duo['cook_spice_id'],
                    ),
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array{spice_id: int, preparation_method_id: int, total: int}> $groups
     * @return list<array{severity: string, message: string}>
     */
    public function checkDuplicatePreparationTips(array $groups): array
    {
        return array_map(static fn (array $g): array => [
            'severity' => 'error',
            'message' => \sprintf(
                'Épice %d : %d conseils de préparation pour la même méthode %d (rattachement de duo ambigu).',
                $g['spice_id'],
                $g['total'],
                $g['preparation_method_id'],
            ),
        ], $groups);
    }

    /**
     * @param list<array{spice_id: int, step: int, total: int}> $groups
     * @return list<array{severity: string, message: string}>
     */
    public function checkDuplicateCookingMoments(array $groups): array
    {
        return array_map(static fn (array $g): array => [
            'severity' => 'error',
            'message' => \sprintf(
                'Épice %d : %d conseils de cuisson pour le même moment %d (rattachement de duo ambigu).',
                $g['spice_id'],
                $g['total'],
                $g['step'],
            ),
        ], $groups);
    }
}
