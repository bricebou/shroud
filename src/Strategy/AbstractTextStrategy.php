<?php

declare(strict_types=1);

namespace Shroud\Strategy;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;

/**
 * Grounds the strategies of strings: NULL and the empty string pass through unchanged.
 */
abstract class AbstractTextStrategy implements StrategyInterface
{
    /**
     * Lets NULL and the empty string through, transforms any other string, refuses the rest.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        // NULL and the empty string can be states, not values: they pass through as they are.
        if ($value === null || $value === '') {
            return $value;
        }
        if (! is_string($value)) {
            throw new InvalidValueException($context->table, $context->column, 'string expected, ' . get_debug_type($value) . ' given');
        }

        return $this->transform($value, $context);
    }

    /**
     * Transforms a non-empty string.
     */
    abstract protected function transform(string $value, Context $context): string;
}
