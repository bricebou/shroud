<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a column that the declaration of its table does not know.
 */
final class UndeclaredColumnException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
    ) {
        parent::__construct(sprintf('Undeclared column: %s.%s', $table, $column));
    }
}
