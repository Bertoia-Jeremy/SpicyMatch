<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\MortarIds;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Construit le profil OAV agrégé du mortier (Π_M*) pour une matrice donnée.
 *
 * Pour chaque molécule i : OAV_i^M = max_{s ∈ M} OAV_i^s
 * Le max est préféré à la moyenne : la note dominante du mortier détermine sa signature.
 *
 * Clé de cache : "match.mortar.{matrix}.{id1},{id2},…" (IDs triés).
 * TTL par matrice :
 *   - air   : 86400s (24h) — données matures, stables
 *   - water : 3600s  (1h)  — données en cours de collecte, plus susceptibles d'évoluer
 *   - oil   : 3600s  (1h)  — idem
 *
 * Invalidé par SpiceConcentrationChangedListener sur toute modification de
 * SpiceCompoundConcentration ou CompoundOdt.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3.2 + §4.4
 */
class MortarProfileBuilder
{
    private const CACHE_TTL_AIR = 86400; // 24h — données air matures

    private const CACHE_TTL_SHORT = 3600; // 1h — water/oil encore en cours de collecte

    /**
     * TTL court pour le sentinel "OAV vide" : évite de retaper la DB à chaque hit
     * tout en laissant l'import suivant être visible dans un délai raisonnable.
     */
    private const CACHE_TTL_EMPTY = 300; // 5 min

    public function __construct(
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private readonly CacheItemPoolInterface $matchMortarProfileCache,
    ) {
    }

    /**
     * Construit ou récupère depuis le cache le profil OAV agrégé du mortier pour la matrice donnée.
     *
     * Retourne null si aucune donnée OAV n'est disponible pour ce mortier+matrice.
     * Le pipeline interprète null comme "mode dégradé" (fallback présence-only).
     * Un profil null n'est pas mis en cache : la table peut être peuplée dès le prochain
     * rebuild, et on ne veut pas verrouiller 24h une réponse "pas de données".
     *
     * @return array<int, float>|null compound_id => OAV max agrégé, ou null si pas de données
     */
    public function build(MortarIds $mortar, OdtMatrix $matrix = OdtMatrix::AIR): ?array
    {
        $cacheKey = $this->buildCacheKey($mortar, $matrix);
        $cacheItem = $this->matchMortarProfileCache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var array<int, float>|array{} $cached */
            $cached = $cacheItem->get();

            // PERF-7 : sentinel "OAV vide" — tableau vide caché = pas de données pour
            // cette matrice. Permet de court-circuiter loadOavProfilesBatch sur le path
            // chaud sans bloquer 24h en cas d'import à venir (TTL court).
            return $cached === [] ? null : $cached;
        }

        $profile = $this->computeProfile($mortar->toArray(), $matrix);

        if ($profile === []) {
            // Cache court de l'état vide : le prochain build pour ce mortier+matrice
            // n'ira pas en DB pendant 5 min, mais un rebuild OAV invalidera le pool entier.
            $cacheItem->set([]);
            $cacheItem->expiresAfter(self::CACHE_TTL_EMPTY);
            $this->matchMortarProfileCache->save($cacheItem);

            return null;
        }

        $cacheItem->set($profile);
        $cacheItem->expiresAfter($this->getCacheTtl($matrix));
        $this->matchMortarProfileCache->save($cacheItem);

        return $profile;
    }

    /**
     * Invalide le cache pour un mortier donné sur toutes les matrices.
     */
    public function invalidate(MortarIds $mortar): void
    {
        foreach (OdtMatrix::cases() as $matrix) {
            $this->matchMortarProfileCache->deleteItem($this->buildCacheKey($mortar, $matrix));
        }
    }

    /**
     * Invalide tout le cache de profils mortier (toutes matrices, tous mortiers).
     * Appelé lors d'un rebuild global de spice_active_compound.
     */
    public function invalidateAll(): void
    {
        $this->matchMortarProfileCache->clear();
    }

    private function buildCacheKey(MortarIds $mortar, OdtMatrix $matrix): string
    {
        return 'match.mortar.' . $matrix->value . '.' . implode(',', $mortar->sorted());
    }

    private function getCacheTtl(OdtMatrix $matrix): int
    {
        return $matrix === OdtMatrix::AIR ? self::CACHE_TTL_AIR : self::CACHE_TTL_SHORT;
    }

    /**
     * @param list<int> $sortedIds
     *
     * @return array<int, float> compound_id => OAV max du mortier
     */
    private function computeProfile(array $sortedIds, OdtMatrix $matrix): array
    {
        $profiles = $this->spiceActiveCompoundRepository->loadOavProfilesBatch($sortedIds, $matrix);

        $mortarProfile = [];
        foreach ($profiles as $compoundOavs) {
            foreach ($compoundOavs as $compoundId => $oav) {
                if (! isset($mortarProfile[$compoundId]) || $oav > $mortarProfile[$compoundId]) {
                    $mortarProfile[$compoundId] = $oav;
                }
            }
        }

        return $mortarProfile;
    }
}
