<?php

declare(strict_types=1);

namespace Shroud\Adapter\Pdo;

use Shroud\Adapter\DryRun;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Exception\InvalidSchemaException;
use Shroud\Exception\UndeclaredTableException;

/**
 * Reads a MySQL or MariaDB base through PDO and writes a pseudonymised SQL dump, table by table.
 *
 * Each declared table comes out with its schema, taken from `SHOW CREATE TABLE`, then its rows
 * transformed by batches of inserts; a truncated table comes out with its schema alone. A table
 * of the base absent from the declaration stops everything: nothing passes by default; a declared
 * table absent from the base is skipped, as the coverage allows. The tables that observe the
 * batch are read a first time, whole, before anything is written.
 */
final readonly class MysqlDump
{
    private const int ROWS_PER_INSERT = 200;

    public function __construct(
        private \PDO $pdo,
        private Declaration $declaration,
        private Disguiser $disguiser,
    ) {
    }

    /**
     * Writes the pseudonymised dump of the base into the stream open for writing.
     *
     * @param resource $output
     * @param callable(string, int): void|null $progress called after each table with its number of rows
     */
    public function write($output, ?callable $progress = null): void
    {
        $present = $this->tableNames();
        $this->refuseUndeclaredTables($present);
        $this->observe($present);

        fwrite($output, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
        foreach ($this->presentTables($present) as $table) {
            $count = $this->writeTable($output, $table);
            if ($progress !== null) {
                $progress($table->name, $count);
            }
        }
        fwrite($output, "SET FOREIGN_KEY_CHECKS = 1;\n");
    }

    /**
     * Reads the whole base and passes every row through the disguiser without writing, counting the refusals.
     *
     * @param callable(string, int): void|null $progress called after each table with its number of rows
     */
    public function dryRun(?callable $progress = null): DryRun
    {
        $dryRun = new DryRun();
        $present = $this->tableNames();
        foreach ($present as $name) {
            if (! $this->declaration->has($name)) {
                $dryRun->noteUndeclaredTable($name);
            }
        }
        $this->observe($present);

        foreach ($this->presentTables($present) as $table) {
            if ($table->truncated) {
                continue;
            }
            $dryRun->noteTable($table->name);
            foreach ($this->rows($table) as $row) {
                $dryRun->countRow($table->name);
                foreach ($this->disguiser->checkRow($table->name, $row, $dryRun) as $column => $refusal) {
                    $dryRun->noteRefusal($table->name, $column, $refusal);
                }
            }
            if ($progress !== null) {
                $progress($table->name, $dryRun->rows($table->name));
            }
        }

        return $dryRun;
    }

    /**
     * Lists the declared tables that the base carries, in declaration order.
     *
     * @param list<string> $present the tables of the base
     *
     * @return list<Table>
     */
    private function presentTables(array $present): array
    {
        return array_values(array_filter($this->declaration->tables(), static fn (Table $table): bool => in_array($table->name, $present, strict: true)));
    }

    /**
     * Reads once, whole, every table of the base that observes the batch.
     *
     * @param list<string> $present the tables of the base
     */
    private function observe(array $present): void
    {
        foreach ($this->presentTables($present) as $table) {
            if ($table->hasObservers() && ! $table->truncated) {
                foreach ($this->rows($table) as $row) {
                    $this->disguiser->observeRow($table->name, $row);
                }
            }
        }
    }

    /**
     * Writes the schema of a table, then its transformed rows; returns the number of rows written.
     *
     * @param resource $output
     */
    private function writeTable($output, Table $table): int
    {
        $quoted = $this->quoteIdentifier($table->name);
        fwrite($output, "DROP TABLE IF EXISTS {$quoted};\n" . $this->createStatement($table->name) . ";\n\n");
        if ($table->truncated) {
            return 0;
        }

        $count = 0;
        $batch = [];
        $columns = '';
        foreach ($this->rows($table) as $row) {
            $disguised = $this->disguiser->disguiseRow($table->name, $row);
            if ($columns === '') {
                $columns = implode(', ', array_map($this->quoteIdentifier(...), array_keys($disguised)));
            }
            $batch[] = '(' . implode(', ', array_map($this->quoteValue(...), $disguised)) . ')';
            ++$count;
            if (count($batch) === self::ROWS_PER_INSERT) {
                $this->writeInsert($output, $quoted, $columns, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->writeInsert($output, $quoted, $columns, $batch);
        }
        fwrite($output, "\n");

        return $count;
    }

    /**
     * Writes a grouped insert.
     *
     * @param resource $output
     * @param list<string> $batch
     */
    private function writeInsert($output, string $table, string $columns, array $batch): void
    {
        fwrite($output, "INSERT INTO {$table} ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    /**
     * Reads the rows of a table as a stream, without keeping them in memory.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function rows(Table $table): \Generator
    {
        $statement = $this->pdo->query('SELECT * FROM ' . $this->quoteIdentifier($table->name));
        if ($statement === false) {
            throw new InvalidSchemaException('cannot read ' . $table->name);
        }
        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /** @var array<string, mixed> $row */
            yield $row;
        }
        $statement->closeCursor();
    }

    /**
     * Refuses any table of the base that the declaration does not know: nothing passes by default.
     *
     * @param list<string> $present the tables of the base
     */
    private function refuseUndeclaredTables(array $present): void
    {
        foreach ($present as $name) {
            if (! $this->declaration->has($name)) {
                throw new UndeclaredTableException($name);
            }
        }
    }

    /**
     * Lists the tables of the base.
     *
     * @return list<string>
     */
    private function tableNames(): array
    {
        $statement = $this->pdo->query('SHOW TABLES');
        if ($statement === false) {
            throw new InvalidSchemaException('cannot list the tables');
        }

        return array_map(strval(...), array_values(array_filter($statement->fetchAll(\PDO::FETCH_COLUMN), is_scalar(...))));
    }

    /**
     * Returns the creation statement of a table, as the server gives it.
     */
    private function createStatement(string $table): string
    {
        $statement = $this->pdo->query('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
        $row = $statement === false ? false : $statement->fetch(\PDO::FETCH_NUM);
        // The connection streams its results: a cursor left open blocks the next query.
        if ($statement !== false) {
            $statement->closeCursor();
        }
        if ($row === false || ! isset($row[1]) || ! is_string($row[1])) {
            throw new InvalidSchemaException('cannot read the schema of ' . $table);
        }

        return $row[1];
    }

    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $this->pdo->quote(is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR));
    }
}
