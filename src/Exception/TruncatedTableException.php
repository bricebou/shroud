<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a row of a table declared truncated: no row of it is to be transformed.
 */
final class TruncatedTableException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $table,
    ) {
        parent::__construct(sprintf('Truncated table: %s has no row to transform', $table));
    }
}
