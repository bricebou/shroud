<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a row whose strategy needs an object reference that the row does not carry.
 */
final class MissingReferenceException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
    ) {
        parent::__construct(sprintf(
            'The strategy of %s.%s needs an object reference, but the row carries none',
            $table,
            $column,
        ));
    }
}
