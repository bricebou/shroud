<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Refuses a project configuration that cannot be read or makes no sense.
 */
final class InvalidConfigurationException extends \RuntimeException implements ShroudException
{
    public function __construct(
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Invalid configuration: %s', $reason), previous: $previous);
    }
}
