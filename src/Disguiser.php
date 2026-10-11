<?php

declare(strict_types=1);

namespace Shroud;

use Shroud\Declaration\Declaration;
use Shroud\Exception\TruncatedTableException;
use Shroud\Strategy\ObserverInterface;

/**
 * Transforms rows: rows come in, disguised rows come out.
 *
 * It knows no SQL and no CMS, only arrays of rows, a declaration and a generator. The adapters
 * bring it the rows of their medium, a dump or a live base, and take them back.
 *
 * A batch of tables is first observed as a whole, then transformed: what a column learns about
 * an object, the number of words of a first name, is available everywhere, whatever the order of
 * the tables. A row disguised alone has no such observation.
 */
final readonly class Disguiser
{
    public function __construct(
        private Declaration $declaration,
        private Generator $generator,
    ) {
    }

    /**
     * Transforms one row of a declared table, column by column.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function disguiseRow(string $tableName, array $row): array
    {
        $table = $this->declaration->getTable($tableName);
        if ($table->truncated) {
            throw new TruncatedTableException($tableName);
        }
        $key = $table->computeKey($row);
        $reference = $table->reference?->resolveIn($row, $this->generator);

        $result = [];
        foreach ($row as $column => $value) {
            $context = new Context($table->name, $column, $key, $row, $reference, $this->generator, $this->declaration);
            $result[$column] = $table->getStrategyFor($column)->apply($value, $context);
        }

        return $result;
    }

    /**
     * Submits each value of the row to the strategies that observe, without transforming anything.
     *
     * An adapter that reads in a stream calls this on the whole batch before calling `disguiseRow()`.
     *
     * @param array<string, mixed> $row
     */
    public function observeRow(string $tableName, array $row): void
    {
        $table = $this->declaration->getTable($tableName);
        if ($table->truncated || ! $table->hasObservers()) {
            return;
        }
        $key = $table->computeKey($row);
        $reference = $table->reference?->resolveIn($row, $this->generator);

        foreach ($row as $column => $value) {
            $strategy = $table->getStrategyFor($column);
            if ($strategy instanceof ObserverInterface) {
                $strategy->observe($value, new Context($table->name, $column, $key, $row, $reference, $this->generator, $this->declaration));
            }
        }
    }

    /**
     * Transforms a set of tables, row by row, after observing it as a whole.
     *
     * @param array<string, list<array<string, mixed>>> $tables
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function disguiseTables(array $tables): array
    {
        // First pass: observe the whole batch, so that what a table learns about an object
        // serves the others, whatever the order of the tables.
        foreach ($tables as $tableName => $rows) {
            foreach ($rows as $row) {
                $this->observeRow($tableName, $row);
            }
        }

        // Second pass: transform. A truncated table comes out without a row.
        $result = [];
        foreach ($tables as $tableName => $rows) {
            if ($this->declaration->getTable($tableName)->truncated) {
                $result[$tableName] = [];
                continue;
            }
            $result[$tableName] = array_map(
                fn (array $row): array => $this->disguiseRow($tableName, $row),
                $rows,
            );
        }

        return $result;
    }
}
