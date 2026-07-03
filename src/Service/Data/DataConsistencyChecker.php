<?php

declare(strict_types=1);

namespace App\Service\Data;

/**
 * Règles de cohérence cross-tables, pures (sans I/O). Délégué par app:check:data.
 * Violations : ['severity' => 'error'|'warning', 'message' => string].
 */
final class DataConsistencyChecker
{
    /**
     * Au-delà, donnée probablement erronée (eugénol pur en air ≈ 10^8).
     */
    private const float OAV_PLAUSIBLE_MAX = 1.0e9;

    /**
     * > 100 % masse, impossible.
     */
    private const float CONCENTRATION_SUM_IMPOSSIBLE_PPM = 1_000_000.0;

    /**
     * > 20 % masse — implausible (HE ≈ 3-10 % typique).
     */
    private const float CONCENTRATION_SUM_IMPLAUSIBLE_PPM = 200_000.0;

    /**
     * OAV > 1 (perceptibilité van Gemert) et < plafond plausible.
     *
     * @param list<array{spice_id: int, aromatic_compound_id: int, matrix: string, oav_value: float}> $rows
     *
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
     *
     * @return list<array{severity: string, message: string}>
     */
    public function checkConcentrationSums(array $sumBySpiceId, array $spiceNames = []): array
    {
        $violations = [];

        foreach ($sumBySpiceId as $spiceId => $sum) {
            $name = $spiceNames[$spiceId] ?? ('épice '.$spiceId);

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
     * Composé concentré sans ODT air = trou silencieux (jamais OAV-actif en air).
     *
     * @param list<array{id: int, name: string}> $compoundsWithoutAirOdt
     *
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
}
