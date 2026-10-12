<?php

declare(strict_types=1);

namespace Shroud\Strategy;

use Shroud\Context;

/**
 * Tells whether a strategy observes the batch, at any depth, and relays an observation to it.
 */
final class Observation
{
    /**
     * Tells whether the strategy observes: an observer of its own, or a composite that carries one at any depth.
     */
    public static function observes(StrategyInterface $strategy): bool
    {
        if ($strategy instanceof RelayingObserverInterface) {
            foreach ($strategy->innerStrategies() as $inner) {
                if (self::observes($inner)) {
                    return true;
                }
            }

            return false;
        }

        return $strategy instanceof ObserverInterface;
    }

    /**
     * Submits a value to a strategy when it observes, and does nothing otherwise.
     */
    public static function relay(StrategyInterface $strategy, mixed $value, Context $context): void
    {
        if ($strategy instanceof ObserverInterface && self::observes($strategy)) {
            $strategy->observe($value, $context);
        }
    }
}
