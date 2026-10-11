<?php

declare(strict_types=1);

namespace Shroud\Strategy;

use Shroud\Context;

/**
 * Marks a strategy that must see the whole batch before it is transformed, to note a fact of
 * shape that other columns, or other tables, will read back: `FirstName` notes how many words
 * the first name of an object counts, `FullName` serves as many to its full name.
 */
interface ObserverInterface extends StrategyInterface
{
    /**
     * Notes what the shape of the value tells about the referenced object, without transforming it.
     */
    public function observe(mixed $value, Context $context): void;
}
