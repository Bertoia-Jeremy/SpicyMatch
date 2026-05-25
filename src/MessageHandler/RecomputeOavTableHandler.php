<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OdtMatrix;
use App\Exception\Match\OavRebuildFailedException;
use App\Message\RecomputeOavTableMessage;
use App\Service\Match\MortarProfileBuilder;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Rebuild atomique de spice_active_compound (vue matérialisée OAV).
 *
 * Formule : OAV = concentration_ppm / odt_ppm
 * Seuls les composés avec OAV > 1 sont insérés (filtre dans le WHERE).
 * La matrice ODT (air/water/oil) est portée par le message — défaut : air.
 *
 * Stratégie shadow table (zéro downtime) :
 *   1. DROP TABLE IF EXISTS spice_active_compound_tmp
 *   2. CREATE TABLE spice_active_compound_tmp LIKE spice_active_compound
 *   3. INSERT INTO spice_active_compound_tmp ... (calcul OAV, filtre matrice)
 *   4. RENAME TABLE spice_active_compound → _old, spice_active_compound_tmp → spice_active_compound (atomique InnoDB)
 *   5. DROP TABLE spice_active_compound_old
 *
 * RENAME TABLE est atomique sur InnoDB → aucune fenêtre où la table est vide.
 * Les requêtes /api/match concurrentes voient soit l'ancienne, soit la nouvelle table.
 *
 * Après rebuild, invalide le cache des profils mortier (MortarProfileBuilder).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §5 + §4.5
 */
#[AsMessageHandler]
final class RecomputeOavTableHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MortarProfileBuilder $mortarProfileBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecomputeOavTableMessage $message): void
    {
        // Sanitize reason pour éviter les log injections (CRLF)
        $reason = preg_replace('/[\r\n]/', ' ', $message->reason) ?? 'manual';
        $matrix = $message->matrix;

        $this->logger->info('[OAV] Début du rebuild spice_active_compound', [
            'reason' => $reason,
            'matrix' => $matrix->value,
        ]);

        $start = microtime(true);

        try {
            $this->doRebuild($reason, $matrix);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('[OAV] Rebuild échoué — exception DBAL', [
                'reason' => $reason,
                'matrix' => $matrix->value,
                'exception' => $e->getMessage(),
            ]);

            throw OavRebuildFailedException::fromDbalException($e);
        }

        $elapsed = round((microtime(true) - $start) * 1000);
        $this->logger->info('[OAV] Rebuild terminé', [
            'elapsed_ms' => $elapsed,
            'matrix' => $matrix->value,
        ]);
    }

    private function doRebuild(string $reason, OdtMatrix $matrix): void
    {
        // ── Étape 1 : nettoyer toute table orpheline d'un run précédent ────────
        // spice_active_compound_old peut rester si un run précédent a crashé entre
        // l'étape 4 (RENAME) et l'étape 5 (DROP) — sans ce DROP, l'étape 4 lève
        // ERROR 1050 "Table already exists" et bloque le handler indéfiniment.
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_tmp');
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_old');

        // ── Étape 2 : créer la table shadow (copie structure + index) ───────────
        $this->connection->executeStatement('CREATE TABLE spice_active_compound_tmp LIKE spice_active_compound');

        // ── Étape 3 : remplir la table shadow (calcul OAV = C / ODT, matrice paramétrée) ──
        // NULLIF(odt.odt_ppm, 0) + odt.odt_ppm > 0 : garde contre division par zéro.
        // MariaDB retourne NULL (pas d'exception) sur division par zéro → perte silencieuse de données.
        $inserted = $this->connection->executeStatement(
            <<<SQL
            INSERT INTO spice_active_compound_tmp (spice_id, aromatic_compound_id, oav_value)
            SELECT
                scc.spice_id,
                scc.aromatic_compound_id,
                scc.concentration_ppm / NULLIF(odt.odt_ppm, 0) AS oav
            FROM spice_compound_concentration scc
            JOIN compound_odt odt
                ON odt.aromatic_compound_id = scc.aromatic_compound_id
                AND odt.matrix = :matrix
                AND odt.odt_ppm > 0
            WHERE scc.concentration_ppm / NULLIF(odt.odt_ppm, 0) > 1
            SQL
            ,
            [
                'matrix' => $matrix->value,
            ],
        );

        $this->logger->info('[OAV] Shadow table peuplée', [
            'rows_inserted' => $inserted,
            'matrix' => $matrix->value,
            'reason' => $reason,
        ]);

        // ── Étape 4 : swap atomique (RENAME est atomique sur InnoDB) ─────────────
        // Les requêtes /api/match concurrentes voient soit l'ancienne, soit la nouvelle table.
        $this->connection->executeStatement(
            'RENAME TABLE spice_active_compound TO spice_active_compound_old,
                          spice_active_compound_tmp TO spice_active_compound'
        );

        // ── Étape 5 : supprimer l'ancienne table ─────────────────────────────────
        $this->connection->executeStatement('DROP TABLE spice_active_compound_old');

        // ── Invalide le cache des profils mortier (les scores sont périmés) ──────
        $this->mortarProfileBuilder->invalidateAll();
    }
}
