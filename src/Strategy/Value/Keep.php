<?php

declare(strict_types=1);

namespace Shroud\Strategy\Value;

use Shroud\Context;
use Shroud\Strategy\StrategyInterface;

/**
 * Keeps the value as it is.
 */
final class Keep implements StrategyInterface
{
    public function apply(mixed $value, Context $context): mixed
    {
        return $value;
    }

    /**
     * Declares every listed column kept.
     *
     * @param list<string> $columns
     *
     * @return array<string, self>
     */
    public static function forColumns(array $columns): array
    {
        return array_fill_keys($columns, new self());
    }
}
