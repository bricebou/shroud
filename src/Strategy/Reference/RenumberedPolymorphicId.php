<?php

declare(strict_types=1);

namespace Shroud\Strategy\Reference;

use Shroud\Context;
use Shroud\Strategy\StrategyInterface;

/**
 * Renumbers the id of a polymorphic link, whose type is read from another column of the row.
 *
 * Only the types renumbered somewhere in the declaration are renumbered here: a link to an
 * object of another type keeps its id.
 */
final readonly class RenumberedPolymorphicId implements StrategyInterface
{
    public function __construct(
        private string $typeColumn,
    ) {
    }

    /**
     * Returns the permuted id when its type is renumbered, the value as it is otherwise.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        $type = $context->row[$this->typeColumn] ?? null;
        if (! is_string($type) || ! $context->declaration->isRenumbered($type)) {
            return $value;
        }

        return Renumber::value($value, $type, $context);
    }
}
