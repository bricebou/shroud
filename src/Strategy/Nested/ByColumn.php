<?php

declare(strict_types=1);

namespace Shroud\Strategy\Nested;

use Shroud\Context;
use Shroud\Strategy\StrategyInterface;

/**
 * Picks the strategy according to the value of another column of the row: the value of a setting
 * according to its name, the content of a field according to its type.
 */
final readonly class ByColumn implements StrategyInterface
{
    /**
     * @param array<int|string, StrategyInterface> $cases
     */
    public function __construct(
        private string $column,
        private array $cases,
        private StrategyInterface $default,
    ) {
    }

    /**
     * Applies the strategy that the value of the discriminating column designates, or the default one.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        // PHP array keys mix up '18' and 18: the value of the column, string or integer, finds its case.
        $discriminant = $context->row[$this->column] ?? null;
        $strategy = is_scalar($discriminant) ? ($this->cases[(string) $discriminant] ?? $this->default) : $this->default;

        return $strategy->apply($value, $context);
    }
}
