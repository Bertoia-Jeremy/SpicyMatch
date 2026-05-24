<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\MortarIds;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Construit le profil OAV agrégé du mortier (Π_M*).
 *
 * Pour chaque molécule i : OAV_i^M = max_{s ∈ M} OAV_i^s
 * Le max est préféré à la moyenne : la note dominante du mortier détermine sa signature.
 *
 * Résultat mis en cache 24h. Clé = "match.mortar.{id1},{id2},…" (IDs triés).
 * Invalidé par SpiceConcentrationChangedListener sur toute modification de
 * SpiceCompoundConcentration ou CompoundOdt.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3.2 + §4.4
 */
class MortarProfileBuilder
{
    private const CACHE_TTL = 86400; // 24h

    public function __construct(
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private readonly CacheItemPoolInterface $matchMortarProfileCache,
    ) {
    }

    /**
     * Construit ou récupère depuis le cache le profil OAV agrégé du mortier.
     *
     * Retourne null si aucune donnée OAV n'est disponible pour ce mortier.
     * Le pipeline interprète null comme "mode dégradé" (fallback présence-only).
     * Un profil null n'est pas mis en cache : la table peut être peuplée dès le prochain
     * rebuild (app:recompute:oav), l'invalidation se ferait alors correctement via
     * invalidateAll(), mais il vaut mieux ne pas verrouiller 24h une réponse "pas de données".
     *
     * @return array<int, float>|null compound_id => OAV max agrégé, ou null si pas de données
     */
    public function build(MortarIds $mortar): ?array
    {
        $cacheKey = 'match.mortar.' . implode(',', $mortar->sorted());
        $cacheItem = $this->matchMortarProfileCache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var array<int, float> $cached */
            $cached = $cacheItem->get();

            return $cached;
        }

        $profile = $this->computeProfile($mortar->toArray());

        // Ne pas mettre en cache un profil vide : les données peuvent être peuplées
        // juste après un import + recompute:oav sans passer par invalidateAll().
        if ($profile === []) {
            return null;
        }

        $cacheItem->set($profile);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->matchMortarProfileCache->save($cacheItem);

        return $profile;
    }

    /**
     * Invalide le cache pour un mortier donné (appelé lors d'un changement de données OAV).
     */
    public function invalidate(MortarIds $mortar): void
    {
        $this->matchMortarProfileCache->deleteItem('match.mortar.' . implode(',', $mortar->sorted()));
    }

    /**
     * Invalide tout le cache de profils mortier.
     * Appelé lors d'un rebuild global de spice_active_compound.
     */
    public function invalidateAll(): void
    {
        $this->matchMortarProfileCache->clear();
    }

    /**
     * @param list<int> $sortedIds
     *
     * @return array<int, float> compound_id => OAV max du mortier
     */
    private function computeProfile(array $sortedIds): array
    {
        $profiles = $this->spiceActiveCompoundRepository->loadOavProfilesBatch($sortedIds);

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
