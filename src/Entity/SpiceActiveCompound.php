<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OdtMatrix;
use App\Repository\SpiceActiveCompoundRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vue matérialisée des composés OAV-actifs (OAV > 1) par épice et par matrice.
 *
 * ⚠️  Cette table est gérée exclusivement via DBAL brut (shadow table atomique).
 *     Ne jamais persister des instances de cette entité via l'EntityManager.
 *     Son seul rôle est de permettre à doctrine:schema:update de créer la table.
 *
 * ⚠️  Pas de FK vers `spices` ni `aromatic_compound` intentionnellement :
 *     un FK bloquerait l'opération RENAME TABLE du rebuild shadow table.
 *
 * OAV = concentration_ppm / odt_ppm. Seuls les composés avec OAV > 1 sont stockés
 * (seuil de perceptibilité olfactive humaine). La contrainte est enforced par le SQL
 * de rebuild (WHERE clause), pas par un CHECK DB.
 *
 * Rebuild déclenché via RecomputeOavTableMessage (Symfony Messenger).
 * Stratégie : shadow table (CREATE + INSERT × 3 matrices + RENAME TABLE atomique + DROP).
 * Les 3 INSERT sont wrappés dans une transaction InnoDB ; DDL hors transaction (commit implicite MariaDB).
 *
 * Indexes :
 *   - PK (spice_id, aromatic_compound_id, matrix) : unicité par triplet
 *   - idx_spice_matrix (spice_id, matrix) : filtre rapide par épice+matrice
 *   - idx_compound_spice (aromatic_compound_id, spice_id, matrix) : self-join du veto biparti
 *   - idx_spice_cover (spice_id, matrix, aromatic_compound_id, oav_value) : index couvrant pour loadOavProfilesBatch
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §5.3
 */
#[ORM\Entity(repositoryClass: SpiceActiveCompoundRepository::class)]
#[ORM\Table(name: 'spice_active_compound')]
#[ORM\Index(columns: ['spice_id', 'matrix'], name: 'idx_spice_matrix')]
#[ORM\Index(columns: ['aromatic_compound_id', 'spice_id', 'matrix'], name: 'idx_compound_spice')]
#[ORM\Index(columns: ['spice_id', 'matrix', 'aromatic_compound_id', 'oav_value'], name: 'idx_spice_cover')]
class SpiceActiveCompound
{
    /**
     * ID de l'épice (int brut, pas de FK Doctrine — voir note de classe).
     */
    #[ORM\Id]
    #[ORM\Column(name: 'spice_id', type: 'integer')]
    private int $spiceId;

    /**
     * ID du composé aromatique (int brut, pas de FK Doctrine — voir note de classe).
     */
    #[ORM\Id]
    #[ORM\Column(name: 'aromatic_compound_id', type: 'integer')]
    private int $aromaticCompoundId;

    /**
     * Matrice ODT : air | water | oil.
     * Troisième composante de la PK — un même composé a des OAV différents selon le milieu.
     * DEFAULT 'air' : migration sûre des lignes existantes lors du schema:update.
     */
    #[ORM\Id]
    #[ORM\Column(name: 'matrix', type: 'string', length: 5, enumType: OdtMatrix::class, options: [
        'default' => 'air',
    ])]
    private OdtMatrix $matrix;

    /**
     * Valeur OAV = concentration_ppm / odt_ppm. Toujours > 1 (enforced par rebuild SQL).
     *
     * Type DOUBLE (float Doctrine) :
     *   - Arithmétique native CPU → comparaisons ~3× plus rapides que DECIMAL
     *   - Précision ~15 chiffres significatifs — suffisante pour des ratios expérimentaux
     *   - Safranal OAV ~100M → représentable exactement en DOUBLE (entiers jusqu'à 2^53)
     */
    #[ORM\Column(name: 'oav_value', type: 'float')]
    private float $oavValue;

    public function __construct(
        int $spiceId,
        int $aromaticCompoundId,
        float $oavValue,
        OdtMatrix $matrix = OdtMatrix::AIR,
    ) {
        $this->spiceId = $spiceId;
        $this->aromaticCompoundId = $aromaticCompoundId;
        $this->oavValue = $oavValue;
        $this->matrix = $matrix;
    }

    public function getSpiceId(): int
    {
        return $this->spiceId;
    }

    public function getAromaticCompoundId(): int
    {
        return $this->aromaticCompoundId;
    }

    public function getMatrix(): OdtMatrix
    {
        return $this->matrix;
    }

    public function getOavValue(): float
    {
        return $this->oavValue;
    }
}
