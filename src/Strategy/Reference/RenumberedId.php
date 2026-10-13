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
 *
 * The width bounds the renumbered ids, from 1 to 2^bits - 1: 31 bits by default, as far as a
 * signed INT goes. A narrower width leaves room above for the rows a copy will create, and
 * fits a narrower column; it holds for the type, declared once or the same everywhere.
 */
final readonly class RenumberedId implements StrategyInterface
{
    /**
     * @param ?int $bits the width of the renumbered ids of the type, from 1 to 31; null leaves it to another column, or to the default
     */
    public function __construct(
        public string $type,
        public ?int $bits = null,
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
