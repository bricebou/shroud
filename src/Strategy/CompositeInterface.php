<?php

declare(strict_types=1);

namespace Shroud\Strategy;

/**
 * Marks a strategy that carries other strategies, so that a declaration can look through it.
 *
 * A wrapper carries the strategy it applies, a nested strategy those it applies to the parts of
 * the value. The declaration walks them to find, for instance, every renumbered type.
 */
interface CompositeInterface extends StrategyInterface
{
    /**
     * Lists the strategies carried, one level deep.
     *
     * @return iterable<StrategyInterface>
     */
    public function innerStrategies(): iterable;
}
