<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\MortarIds;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Profil OAV mortier agrégé par max(OAV) par composé. TTL par matrice (MatrixStrategy).
 * Clé : "match.mortar.{matrix}.{ids sorted}". Invalidation : SpiceConcentrationChangedListener.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3.2 + §4.4
 */
class MortarProfileBuilder
{
    /**
     * Sentinel "vide" : court-circuit DB 5 min, sans verrouiller un import à venir.
     */
    private const CACHE_TTL_EMPTY = 300;

    public function __construct(
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private readonly CacheItemPoolInterface $matchMortarProfileCache,
    ) {
    }

    /**
     * @return array<int, float>|null compound_id => OAV max ; null = pas de données pour cette matrice
     */
    public function build(MortarIds $mortar, OdtMatrix $matrix): ?array
    {
        $cacheKey = $this->buildCacheKey($mortar, $matrix);
        $cacheItem = $this->matchMortarProfileCache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var array<int, float>|array{} $cached */
            $cached = $cacheItem->get();

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

    public function invalidate(MortarIds $mortar): void
    {
        foreach (OdtMatrix::cases() as $matrix) {
            $this->matchMortarProfileCache->deleteItem($this->buildCacheKey($mortar, $matrix));
        }
    }

    /**
     * Appelé après rebuild global de spice_active_compound.
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
        return $matrix->strategy()
            ->cacheTtlSeconds();
    }

    /**
     * @param list<int> $sortedIds
     *
     * @return array<int, float>
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
