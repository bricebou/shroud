<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a stored value that its strategy cannot interpret.
 */
final class InvalidValueException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Invalid value in %s.%s: %s', $table, $column, $reason), previous: $previous);
    }
}
