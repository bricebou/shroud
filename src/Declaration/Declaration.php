<?php

declare(strict_types=1);

namespace Shroud\Declaration;

use Shroud\Exception\InvalidConfigurationException;
use Shroud\Exception\UndeclaredTableException;
use Shroud\Strategy\CompositeInterface;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\StrategyInterface;

/**
 * Declares every table of the base, and knows which object types are renumbered.
 *
 * Declarations stack: the platform declares its bare distribution, a site replaces or adds
 * tables, a local file overrides again. A table absent from the declaration stops the run.
 */
final class Declaration
{
    /**
     * The width of the renumbered ids of a type that declares none: up to a signed INT.
     */
    public const int DEFAULT_WIDTH = 31;

    /**
     * @var array<string, Table>
     */
    private array $tables = [];

    /**
     * The types whose ids a column, somewhere, renumbers.
     *
     * @var array<string, true>
     */
    private array $renumberedTypes = [];

    /**
     * The width of the renumbered ids, by type, where a column declares one.
     *
     * @var array<string, int>
     */
    private array $widths = [];

    /**
     * @param iterable<Table> $tables
     */
    public function __construct(iterable $tables)
    {
        foreach ($tables as $table) {
            $this->tables[$table->name] = $table;
            foreach ($table->columns as $strategy) {
                $this->collectRenumberedTypes($strategy);
            }
        }
    }

    /**
     * Lists the declared tables, in declaration order.
     *
     * @return list<Table>
     */
    public function tables(): array
    {
        return array_values($this->tables);
    }

    /**
     * Returns the table declared under this name, or refuses.
     */
    public function getTable(string $name): Table
    {
        return $this->tables[$name] ?? throw new UndeclaredTableException($name);
    }

    /**
     * Tells whether a table is declared.
     */
    public function has(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    /**
     * Tells whether the ids of this type are renumbered somewhere in the declaration.
     */
    public function isRenumbered(string $type): bool
    {
        return isset($this->renumberedTypes[$type]);
    }

    /**
     * Returns the width of the renumbered ids of a type: the declared one, 31 bits otherwise.
     */
    public function renumberingWidth(string $type): int
    {
        return $this->widths[$type] ?? self::DEFAULT_WIDTH;
    }

    /**
     * Lists the width of the renumbered ids of each renumbered type.
     *
     * @return array<string, int>
     */
    public function renumberingWidths(): array
    {
        $widths = [];
        foreach (array_keys($this->renumberedTypes) as $type) {
            $widths[$type] = $this->renumberingWidth($type);
        }

        return $widths;
    }

    /**
     * Lists the renumbered types, in the order they were met.
     *
     * @return list<string>
     */
    public function renumberedTypes(): array
    {
        return array_keys($this->renumberedTypes);
    }

    /**
     * Returns a declaration where these tables replace, by name, the declared ones, or are added to them.
     *
     * The renumbered types are computed again on the whole.
     */
    public function with(Table ...$tables): self
    {
        $merged = $this->tables;
        foreach ($tables as $table) {
            $merged[$table->name] = $table;
        }

        return new self(array_values($merged));
    }

    /**
     * Returns the merge of this declaration and another one, which wins table by table.
     */
    public function merge(self $override): self
    {
        return $this->with(...$override->tables());
    }

    /**
     * Notes the type of a renumbered id, looking through the strategies that carry others.
     */
    private function collectRenumberedTypes(StrategyInterface $strategy): void
    {
        if ($strategy instanceof RenumberedId) {
            $this->renumberedTypes[$strategy->type] = true;
            if ($strategy->bits !== null) {
                $width = $this->widths[$strategy->type] ?? $strategy->bits;
                if ($width !== $strategy->bits) {
                    throw new InvalidConfigurationException(sprintf('the ids of %s are renumbered on %d and %d bits: one width per type', $strategy->type, $width, $strategy->bits));
                }
                $this->widths[$strategy->type] = $width;
            }
        }
        if ($strategy instanceof CompositeInterface) {
            foreach ($strategy->innerStrategies() as $inner) {
                $this->collectRenumberedTypes($inner);
            }
        }
    }
}
