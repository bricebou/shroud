<?php

declare(strict_types=1);

namespace Shroud\Declaration;

use Shroud\Exception\UndeclaredColumnException;
use Shroud\Strategy\ObserverInterface;
use Shroud\Strategy\StrategyInterface;

/**
 * Declares one table: its primary key, a strategy per column, and the columns that reference its object.
 *
 * A column absent from the declaration stops the transformation: nothing passes by default.
 */
final readonly class Table
{
    /**
     * @var list<string>
     */
    public array $primaryKey;

    /**
     * @param string|list<string> $primaryKey
     * @param array<string, StrategyInterface> $columns
     * @param ?ReferenceColumns $reference where a row references its object, when it does
     * @param bool $truncated no row comes out, the schema alone remains: statistics, search results, job queues
     */
    public function __construct(
        public string $name,
        string|array $primaryKey,
        public array $columns,
        public ?ReferenceColumns $reference = null,
        public bool $truncated = false,
    ) {
        $this->primaryKey = is_string($primaryKey) ? [$primaryKey] : $primaryKey;
    }

    /**
     * Declares a truncated table: its content has no test value, and its volume or its personal data forbid keeping it.
     */
    public static function truncated(string $name): self
    {
        return new self($name, [], [], truncated: true);
    }

    /**
     * Returns a copy of the table with these columns added to its own, or replacing them.
     *
     * A site takes a table of the platform and completes it with its own columns, or changes
     * the strategy of an existing one.
     *
     * @param array<string, StrategyInterface> $columns
     */
    public function withColumns(array $columns): self
    {
        return new self($this->name, $this->primaryKey, [...$this->columns, ...$columns], $this->reference, $this->truncated);
    }

    /**
     * Returns a copy of the table with other reference columns.
     */
    public function withReference(?ReferenceColumns $reference): self
    {
        return new self($this->name, $this->primaryKey, $this->columns, $reference, $this->truncated);
    }

    /**
     * Returns the strategy of a column, or refuses when it is not declared.
     */
    public function getStrategyFor(string $column): StrategyInterface
    {
        return $this->columns[$column] ?? throw new UndeclaredColumnException($this->name, $column);
    }

    /**
     * Tells whether a column of the table observes the batch before its transformation.
     */
    public function hasObservers(): bool
    {
        return array_any($this->columns, fn ($strategy) => $strategy instanceof ObserverInterface);
    }

    /**
     * Composes the primary key of a row into one string.
     *
     * @param array<string, mixed> $row
     */
    public function computeKey(array $row): string
    {
        $values = [];
        foreach ($this->primaryKey as $column) {
            $value = $row[$column] ?? '';
            $values[] = is_scalar($value) ? (string) $value : '';
        }

        return implode('|', $values);
    }
}
