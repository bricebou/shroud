<?php

declare(strict_types=1);

namespace Shroud\Strategy;

use Shroud\Context;

/**
 * Transforms the values of one column.
 */
interface StrategyInterface
{
    /**
     * Transforms a value in its context.
     */
    public function apply(mixed $value, Context $context): mixed;
}
