<?php

declare(strict_types=1);

namespace Shroud\Schema;

use Shroud\Exception\InvalidSchemaException;

/**
 * Describes a base: its tables, read from a server or from a versioned JSON file.
 *
 * The JSON file freezes the schema of a bare platform at a given version, so that the coverage
 * of its declaration can be checked without a base.
 */
final readonly class Schema
{
    /**
     * @var array<string, TableSchema>
     */
    private array $tables;

    /**
     * @param iterable<TableSchema> $tables
     */
    public function __construct(iterable $tables)
    {
        $indexed = [];
        foreach ($tables as $table) {
            $indexed[$table->name] = $table;
        }
        ksort($indexed);
        $this->tables = $indexed;
    }

    /**
     * Reads a frozen schema from a JSON file.
     */
    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidSchemaException(sprintf('schema file not found: %s', $path));
        }
        try {
            $data = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidSchemaException(sprintf('%s: %s', $path, $exception->getMessage()), $exception);
        }
        if (! is_array($data)) {
            throw new InvalidSchemaException(sprintf('%s does not hold a JSON object', $path));
        }

        return self::fromArray($data);
    }

    /**
     * Rebuilds a schema from its array form, that of the JSON.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tables = [];
        foreach ($data as $name => $table) {
            if (! is_string($name) || ! is_array($table) || ! is_array($table['columns'] ?? null)) {
                throw new InvalidSchemaException(sprintf('malformed table: %s', is_string($name) ? $name : '?'));
            }
            $columns = [];
            foreach ($table['columns'] as $column => $type) {
                if (! is_string($column) || ! is_string($type)) {
                    throw new InvalidSchemaException(sprintf('malformed column in %s', $name));
                }
                $columns[$column] = $type;
            }
            $primaryKey = [];
            foreach (is_array($table['primaryKey'] ?? []) ? $table['primaryKey'] ?? [] : [null] as $keyColumn) {
                if (! is_string($keyColumn)) {
                    throw new InvalidSchemaException(sprintf('malformed primary key in %s', $name));
                }
                $primaryKey[] = $keyColumn;
            }
            $tables[] = new TableSchema($name, $primaryKey, $columns);
        }

        return new self($tables);
    }

    /**
     * Returns the schema in its array form, ready to serialize.
     *
     * @return array<string, array{primaryKey: list<string>, columns: array<string, string>}>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->tables as $name => $table) {
            $data[$name] = [
                'primaryKey' => $table->primaryKey,
                'columns' => $table->columns,
            ];
        }

        return $data;
    }

    /**
     * Returns the schema as readable JSON, as it is versioned.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Returns the schema with the prefix of its table names replaced, `spip_` by `site_` for instance.
     *
     * A table whose name does not start with the prefix keeps it.
     */
    public function withTablePrefix(string $from, string $to): self
    {
        $tables = [];
        foreach ($this->tables as $name => $table) {
            $renamed = str_starts_with($name, $from) ? $to . substr($name, strlen($from)) : $name;
            $tables[] = new TableSchema($renamed, $table->primaryKey, $table->columns);
        }

        return new self($tables);
    }

    /**
     * Lists the tables, in alphabetical order.
     *
     * @return list<TableSchema>
     */
    public function tables(): array
    {
        return array_values($this->tables);
    }

    /**
     * Tells whether the base carries this table.
     */
    public function has(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    /**
     * Returns a table of the schema, or refuses.
     */
    public function getTable(string $name): TableSchema
    {
        return $this->tables[$name] ?? throw new InvalidSchemaException(sprintf('table absent from the schema: %s', $name));
    }
}
