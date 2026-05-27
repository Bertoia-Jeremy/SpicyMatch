<?php

declare(strict_types=1);

namespace App\Service\Data;

/**
 * Règles de cohérence des données du moteur OAV (Levier 5).
 *
 * Logique pure (sans I/O) → testable unitairement. La commande app:check:data
 * fournit les lignes depuis la base et délègue ici l'évaluation des règles.
 *
 * Chaque méthode retourne une liste de violations :
 *   { severity: 'error'|'warning', message: string }
 *
 * - error   : invariant physique cassé (bloque la CI / l'import)
 * - warning : valeur implausible mais non bloquante
 */
final class DataConsistencyChecker
{
    /**
     * Plafond d'OAV plausible. Au-delà, donnée probablement erronée (concentration
     * surestimée ou ODT sous-estimé). L'eugénol pur en air culmine ~10^8.
     */
    private const float OAV_PLAUSIBLE_MAX = 1.0e9;

    /**
     * Somme de concentrations strictement impossible : > 1 000 000 ppm = > 100 % de la masse.
     */
    private const float CONCENTRATION_SUM_IMPOSSIBLE_PPM = 1_000_000.0;

    /**
     * Somme implausible : > 200 000 ppm = > 20 % de la masse en composés volatils
     * (la plupart des épices < 10 % d'huile essentielle).
     */
    private const float CONCENTRATION_SUM_IMPLAUSIBLE_PPM = 200_000.0;

    /**
     * Règle : tout OAV matérialisé doit être > 1 (van Gemert — perceptibilité).
     * Un OAV ≤ 1 viole l'invariant du rebuild → erreur dure.
     * Un OAV > plafond → warning (donnée suspecte).
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
     * Règle : la somme des concentrations d'une épice ne peut excéder 10^6 ppm (100 %).
     *
     * @param array<int, float>  $sumBySpiceId spice_id => somme des concentration_ppm
     * @param array<int, string> $spiceNames   spice_id => nom (pour message)
     *
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
                        $sum
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
     * Règle : un composé présent en concentration mais sans ODT air ne peut jamais
     * être OAV-actif en air → trou silencieux dans le moteur.
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
