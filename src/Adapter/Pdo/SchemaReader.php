<?php

declare(strict_types=1);

namespace Shroud\Adapter\Pdo;

use Shroud\Exception\InvalidSchemaException;
use Shroud\Schema\Schema;
use Shroud\Schema\TableSchema;

/**
 * Reads the schema of the current base of a MySQL or MariaDB connection, from `information_schema`.
 */
final readonly class SchemaReader
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    /**
     * Returns the tables of the base, with their primary key and their typed columns.
     */
    public function read(): Schema
    {
        $statement = $this->pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION',
        );
        if ($statement === false) {
            throw new InvalidSchemaException('information_schema cannot be read');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return self::fromColumnRows($rows);
    }

    /**
     * Builds the schema from the rows of `information_schema.COLUMNS`, one per column in table order.
     *
     * @param iterable<array<string, mixed>> $rows with TABLE_NAME, COLUMN_NAME, COLUMN_TYPE and COLUMN_KEY
     */
    public static function fromColumnRows(iterable $rows): Schema
    {
        $columns = [];
        $primaryKeys = [];
        foreach ($rows as $row) {
            $table = self::string($row, 'TABLE_NAME');
            $column = self::string($row, 'COLUMN_NAME');
            $columns[$table][$column] = self::string($row, 'COLUMN_TYPE');
            if (($row['COLUMN_KEY'] ?? null) === 'PRI') {
                $primaryKeys[$table][] = $column;
            }
        }

        $tables = [];
        foreach ($columns as $table => $typedColumns) {
            $tables[] = new TableSchema($table, $primaryKeys[$table] ?? [], $typedColumns);
        }

        return new Schema($tables);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidSchemaException(sprintf('information_schema row without %s', $field));
        }

        return $value;
    }
}
