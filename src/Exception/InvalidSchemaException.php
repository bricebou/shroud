<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a database schema, live or frozen, that cannot be read or is malformed.
 */
final class InvalidSchemaException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Invalid schema: %s', $reason), previous: $previous);
    }
}
