-- ============================================================
-- SpicyMatch — Vidage sélectif des tables épices
-- Conserve : users, gamification, game_session, game_question
-- Vide    : épices, composés, groupes, historique matchs
-- ============================================================
-- Exécuter avec :
--   docker exec -i mysql mysql -uroot -proot spicymatch < data/truncate_spice_tables.sql
-- ============================================================

-- Neutralise les FK qui référencent les épices dans les tables CONSERVÉES
UPDATE game_session SET target_spice_id = NULL WHERE target_spice_id IS NOT NULL;
UPDATE achievement SET context_aromatic_group_id = NULL WHERE context_aromatic_group_id IS NOT NULL;

SET FOREIGN_KEY_CHECKS = 0;

-- ── Tables OAV (pas de FK, safe en premier) ─────────────────
TRUNCATE TABLE spice_active_compound;

-- ── Tables de jointure ──────────────────────────────────────
TRUNCATE TABLE spicy_match_history_preparation_tips;
TRUNCATE TABLE spicy_match_history_cooking_tips;
TRUNCATE TABLE spicy_match_spices;
TRUNCATE TABLE discovery_spices;
TRUNCATE TABLE spices_aromatic_compound;
TRUNCATE TABLE secondary_spices_aromatic_compound;
TRUNCATE TABLE aromatic_compound_alchemy_flavors;

-- ── Données scientifiques OAV ───────────────────────────────
TRUNCATE TABLE spice_compound_concentration;
TRUNCATE TABLE compound_odt;

-- ── Données utilisateur liées aux épices ────────────────────
TRUNCATE TABLE spice_view;
TRUNCATE TABLE spicy_match_result;
TRUNCATE TABLE spicy_match_history;
TRUNCATE TABLE spicy_match;
TRUNCATE TABLE discovery;

-- ── Contenu éditorial épices ────────────────────────────────
TRUNCATE TABLE cooking_tips;
TRUNCATE TABLE preparation_tips;
TRUNCATE TABLE preparation_methods;
TRUNCATE TABLE spices;

-- ── Entités de référence (composés, groupes, types) ─────────
TRUNCATE TABLE aromatic_compound;
TRUNCATE TABLE alchemy_flavors;
TRUNCATE TABLE aromatic_groups;
TRUNCATE TABLE spicy_type;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Vidage terminé — tables épices purgées, données utilisateur préservées.' AS status;
