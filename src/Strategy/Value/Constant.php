<?php

declare(strict_types=1);

namespace Shroud\Strategy\Value;

use Shroud\Context;
use Shroud\Strategy\StrategyInterface;

/**
 * Replaces the value by a constant, whatever it is.
 */
final readonly class Constant implements StrategyInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function apply(mixed $value, Context $context): mixed
    {
        return $this->value;
    }
}
