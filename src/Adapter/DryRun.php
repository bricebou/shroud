<?php

declare(strict_types=1);

namespace Shroud\Adapter;

use Shroud\Exception\InvalidValueException;
use Shroud\Exception\ShroudException;

/**
 * Tallies what a dry run over a base finds: the rows read, and the refusals by table, column and reason.
 *
 * A sample of a few rows per table does not find the malformed data of an old base; only the
 * full pass does, and it must not stop at the first refusal.
 */
final class DryRun
{
    /**
     * @var array<string, int>
     */
    private array $rows = [];

    /**
     * @var array<string, array<string, array<string, int>>>
     */
    private array $refusals = [];

    /**
     * @var list<string>
     */
    private array $undeclaredTables = [];

    /**
     * Counts a row read in a table.
     */
    public function countRow(string $table): void
    {
        $this->rows[$table] = ($this->rows[$table] ?? 0) + 1;
    }

    /**
     * Notes a refusal of a column, under the reason of the exception.
     */
    public function noteRefusal(string $table, string $column, ShroudException $exception): void
    {
        $reason = $exception instanceof InvalidValueException ? $exception->reason : $exception->getMessage();
        $this->refusals[$table][$column][$reason] = ($this->refusals[$table][$column][$reason] ?? 0) + 1;
    }

    /**
     * Notes a table of the base that the declaration does not know.
     */
    public function noteUndeclaredTable(string $table): void
    {
        $this->undeclaredTables[] = $table;
    }

    /**
     * Tells whether every row of every table passed.
     */
    public function isClean(): bool
    {
        return $this->refusals === [] && $this->undeclaredTables === [];
    }

    /**
     * Returns the number of rows read in a table, or in the whole base.
     */
    public function rows(?string $table = null): int
    {
        return $table === null ? array_sum($this->rows) : ($this->rows[$table] ?? 0);
    }

    /**
     * Returns the refusals, by table, column and reason.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    public function refusals(): array
    {
        return $this->refusals;
    }

    /**
     * Returns the report, one line per table, column and reason, then a summary.
     *
     * @return list<string>
     */
    public function report(): array
    {
        $lines = [];
        foreach ($this->undeclaredTables as $table) {
            $lines[] = sprintf('%s: undeclared table', $table);
        }
        foreach ($this->refusals as $table => $columns) {
            foreach ($columns as $column => $reasons) {
                foreach ($reasons as $reason => $count) {
                    $lines[] = sprintf('%s.%s: %s (%d row%s)', $table, $column, $reason, $count, $count > 1 ? 's' : '');
                }
            }
        }
        $lines[] = $this->summary();

        return $lines;
    }

    /**
     * Returns the summary in one line.
     */
    public function summary(): string
    {
        $refused = 0;
        foreach ($this->refusals as $columns) {
            foreach ($columns as $reasons) {
                $refused += array_sum($reasons);
            }
        }
        if ($this->isClean()) {
            return sprintf('Dry run: %d rows in %d tables, every one passes.', $this->rows(), count($this->rows));
        }

        return sprintf(
            'Dry run: %d rows in %d tables, %d refusal%s in %d column%s%s.',
            $this->rows(),
            count($this->rows),
            $refused,
            $refused > 1 ? 's' : '',
            $columns = array_sum(array_map(count(...), $this->refusals)),
            $columns > 1 ? 's' : '',
            $this->undeclaredTables === [] ? '' : sprintf(', %d undeclared table%s', count($this->undeclaredTables), count($this->undeclaredTables) > 1 ? 's' : ''),
        );
    }
}
