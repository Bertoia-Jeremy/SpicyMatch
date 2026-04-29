<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Enum\AchievementTrigger;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Lookup registry for TriggerEvaluatorInterface implementations — one per trigger.
 * Iterates the tagged services once at construction and indexes them by trigger
 * and by event type.
 */
final class TriggerEvaluatorRegistry
{
    /**
     * @var array<string, TriggerEvaluatorInterface>
     */
    private readonly array $byTrigger;

    /**
     * @var array<string, list<TriggerEvaluatorInterface>>
     */
    private readonly array $byEvent;

    /**
     * @param iterable<TriggerEvaluatorInterface> $evaluators
     */
    public function __construct(#[AutowireIterator('gamification.trigger_evaluator')] iterable $evaluators)
    {
        $indexed = [];
        $byEvent = [];
        foreach ($evaluators as $evaluator) {
            $key = $evaluator->trigger()
                ->value;
            if (isset($indexed[$key])) {
                throw new \LogicException(sprintf(
                    'Duplicate TriggerEvaluator for %s: %s and %s',
                    $key,
                    $indexed[$key]::class,
                    $evaluator::class,
                ));
            }
            $indexed[$key] = $evaluator;

            foreach ($evaluator->eventTypes() as $eventType) {
                $byEvent[$eventType][] = $evaluator;
            }
        }
        $this->byTrigger = $indexed;
        $this->byEvent = $byEvent;
    }

    public function for(AchievementTrigger $trigger): ?TriggerEvaluatorInterface
    {
        return $this->byTrigger[$trigger->value] ?? null;
    }

    /**
     * @return list<TriggerEvaluatorInterface>
     */
    public function forEvent(string $eventType): array
    {
        return $this->byEvent[$eventType] ?? [];
    }

    /**
     * @return list<AchievementTrigger>
     */
    public function triggersForEvent(string $eventType): array
    {
        return array_map(
            static fn (TriggerEvaluatorInterface $e): AchievementTrigger => $e->trigger(),
            $this->forEvent($eventType),
        );
    }
}
