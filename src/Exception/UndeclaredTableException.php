<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a table that the declaration does not know.
 */
final class UndeclaredTableException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $table,
    ) {
        parent::__construct(sprintf('Undeclared table: %s', $table));
    }
}
