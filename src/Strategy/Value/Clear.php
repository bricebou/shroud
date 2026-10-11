<?php

declare(strict_types=1);

namespace Shroud\Strategy\Value;

use Shroud\Context;
use Shroud\Strategy\StrategyInterface;

/**
 * Empties the value according to its type, NULL staying NULL.
 */
final class Clear implements StrategyInterface
{
    public function apply(mixed $value, Context $context): mixed
    {
        return match (true) {
            $value === null => null,
            is_array($value) => [],
            is_int($value) => 0,
            is_float($value) => 0.0,
            is_bool($value) => false,
            default => '',
        };
    }
}
