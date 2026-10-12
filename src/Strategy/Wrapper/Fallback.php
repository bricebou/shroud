<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;

/**
 * Applies a strategy, and another one to the values that the first refuses.
 *
 * Only an invalid value falls back: old bases carry malformed data that a column-wide strategy
 * cannot read. A missing reference stays an error of the declaration, and is not caught. The
 * observation goes to the first strategy, the one that transforms the readable values.
 */
final readonly class Fallback implements RelayingObserverInterface
{
    public function __construct(
        private StrategyInterface $strategy,
        private StrategyInterface $fallback,
    ) {
    }

    public function apply(mixed $value, Context $context): mixed
    {
        try {
            return $this->strategy->apply($value, $context);
        } catch (InvalidValueException) {
            return $this->fallback->apply($value, $context);
        }
    }

    public function observe(mixed $value, Context $context): void
    {
        Observation::relay($this->strategy, $value, $context);
    }

    public function innerStrategies(): iterable
    {
        return [$this->strategy, $this->fallback];
    }
}
