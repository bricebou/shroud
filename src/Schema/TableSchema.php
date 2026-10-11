<?php

declare(strict_types=1);

namespace Shroud\Schema;

/**
 * Describes a table as the base does: its name, its primary key and its typed columns.
 */
final readonly class TableSchema
{
    /**
     * @param list<string> $primaryKey
     * @param array<string, string> $columns column name to SQL type, in table order
     */
    public function __construct(
        public string $name,
        public array $primaryKey,
        public array $columns,
    ) {
    }

    /**
     * Tells whether the table carries this column.
     */
    public function hasColumn(string $column): bool
    {
        return isset($this->columns[$column]);
    }

    /**
     * Lists the names of the columns, in table order.
     *
     * @return list<string>
     */
    public function columnNames(): array
    {
        return array_keys($this->columns);
    }
}
