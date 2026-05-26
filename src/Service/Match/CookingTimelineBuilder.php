<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Entity\AromaticCompound;
use App\Enum\AromaKinetics;
use App\Repository\CompoundPhysicalRepository;
use App\ValueObject\Match\CulinaryContext;

/**
 * Classe les composés aromatiques d'un mortier selon leur cinétique d'évaporation
 * (HEAD / HEART / BASE) et calcule leur rétention sous le contexte culinaire donné.
 *
 * Permet d'afficher au chef la "fenêtre de cuisson" : quels arômes survivent à
 * la cuisson, quels arômes s'évaporent rapidement → ordre d'ajout recommandé.
 */
final readonly class CookingTimelineBuilder
{
    public function __construct(
        private CompoundPhysicalRepository $compoundPhysicalRepository,
        private OavPartitionCalculator $partitionCalculator,
    ) {
    }

    /**
     * @param iterable<AromaticCompound> $compounds Composés du mortier (déjà chargés)
     *
     * @return array{
     *   head: list<array{id: int, name: string, retention: ?float, kinetics: 'head'}>,
     *   heart: list<array{id: int, name: string, retention: ?float, kinetics: 'heart'}>,
     *   base: list<array{id: int, name: string, retention: ?float, kinetics: 'base'}>,
     *   unknown: list<array{id: int, name: string, retention: null, kinetics: null}>,
     * }
     */
    public function build(iterable $compounds, CulinaryContext $ctx): array
    {
        $indexed = [];
        foreach ($compounds as $compound) {
            $id = $compound->getId();
            if ($id === null) {
                continue;
            }
            $indexed[$id] = $compound;
        }

        if ($indexed === []) {
            return $this->emptyBuckets();
        }

        $physicals = $this->compoundPhysicalRepository->loadByCompoundIds(array_keys($indexed));

        $buckets = $this->emptyBuckets();

        foreach ($indexed as $id => $compound) {
            $physical = $physicals[$id] ?? null;
            $kinetics = $physical?->aromaKinetics();
            $retention = $physical !== null ? $this->partitionCalculator->correctionFactor($physical, $ctx) : null;

            $entry = [
                'id' => $id,
                'name' => $compound->getName() ?? '?',
                'retention' => $retention,
                'kinetics' => $kinetics?->value,
            ];

            $key = match ($kinetics) {
                AromaKinetics::HEAD => 'head',
                AromaKinetics::HEART => 'heart',
                AromaKinetics::BASE => 'base',
                null => 'unknown',
            };

            $buckets[$key][] = $entry;
        }

        // Tri intra-bucket : rétention décroissante (plus solide d'abord)
        foreach (['head', 'heart', 'base', 'unknown'] as $key) {
            usort(
                $buckets[$key],
                static fn (array $a, array $b) => ($b['retention'] ?? -1.0) <=> ($a['retention'] ?? -1.0),
            );
        }

        return $buckets;
    }

    /**
     * @return array{head: list<array<string, mixed>>, heart: list<array<string, mixed>>, base: list<array<string, mixed>>, unknown: list<array<string, mixed>>}
     */
    private function emptyBuckets(): array
    {
        return [
            'head' => [],
            'heart' => [],
            'base' => [],
            'unknown' => [],
        ];
    }
}
