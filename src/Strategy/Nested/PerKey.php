<?php

declare(strict_types=1);

namespace Shroud\Strategy\Nested;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;

/**
 * Transforms an array key by key: each declared key with its strategy, the others with a common one.
 */
final readonly class PerKey implements RelayingObserverInterface
{
    /**
     * @param array<int|string, StrategyInterface> $keys
     */
    public function __construct(
        private array $keys,
        private StrategyInterface $others,
    ) {
    }

    /**
     * Applies to each key of the array its strategy, or the common one; NULL passes through.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new InvalidValueException($context->table, $context->column, 'array expected, ' . get_debug_type($value) . ' given');
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = ($this->keys[$key] ?? $this->others)->apply($item, $context);
        }

        return $result;
    }

    /**
     * Relays the observation of each item to the strategy of its key; a value that is not an array is not observed.
     */
    public function observe(mixed $value, Context $context): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            Observation::relay($this->keys[$key] ?? $this->others, $item, $context);
        }
    }

    public function innerStrategies(): iterable
    {
        return [...array_values($this->keys), $this->others];
    }
}
