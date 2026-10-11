<?php

declare(strict_types=1);

namespace Shroud\Strategy\Reference;

use Shroud\Context;
use Shroud\Strategy\StrategyInterface;

/**
 * Renumbers the id of an object of a fixed type, as a primary key or a foreign key, wherever it is.
 *
 * Declaring this strategy on a column declares the type renumbered. A column that sometimes
 * carries something else than an id hands those values to `Fallback`.
 */
final readonly class RenumberedId implements StrategyInterface
{
    public function __construct(
        public string $type,
    ) {
    }

    /**
     * Returns the permuted id, of the same type as the value received.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        return Renumber::value($value, $this->type, $context);
    }
}
