<?php

declare(strict_types=1);

namespace Shroud\Adapter;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Exception\ShroudException;
use Shroud\Strategy\Wrapper\FallbackListenerInterface;

/**
 * Tallies what a dry run over a base finds: the tables and rows read, the refusals by table, column
 * and reason, and the values that a `Fallback` took over.
 *
 * A sample of a few rows per table does not find the malformed data of an old base; only the
 * full pass does, and it must not stop at the first refusal. A value taken over by a fallback is
 * no refusal, but the count tells how much of a column the declaration did not foresee.
 */
final class DryRun implements FallbackListenerInterface
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
     * @var array<string, array<string, array<string, int>>>
     */
    private array $fallbacks = [];

    /**
     * Notes a declared table read, even an empty one.
     */
    public function noteTable(string $table): void
    {
        $this->rows[$table] ??= 0;
    }

    /**
     * Notes that a fallback took over a value, under the reason of the refusal.
     */
    public function fellBack(Context $context, InvalidValueException $refusal): void
    {
        $this->fallbacks[$context->table][$context->column][$refusal->reason] = ($this->fallbacks[$context->table][$context->column][$refusal->reason] ?? 0) + 1;
    }

    /**
     * Returns the values taken over by a fallback, by table, column and reason.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    public function fallbacks(): array
    {
        return $this->fallbacks;
    }

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
     * Returns the report: the undeclared tables, the refusals, then the values a fallback took over, and a summary.
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
                    $lines[] = sprintf('%s.%s: %s (%s)', $table, $column, $reason, $this->plural($count, 'row'));
                }
            }
        }
        foreach ($this->fallbacks as $table => $columns) {
            foreach ($columns as $column => $reasons) {
                foreach ($reasons as $reason => $count) {
                    $lines[] = sprintf('%s.%s: %s (%s taken over by the fallback)', $table, $column, $reason, $this->plural($count, 'value'));
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
        $parts = [sprintf('Dry run: %s in %s', $this->plural($this->rows(), 'row'), $this->plural(count($this->rows), 'table'))];
        if ($this->isClean()) {
            $parts[] = 'every one passes';
        } else {
            $parts[] = sprintf('%s in %s', $this->plural($this->total($this->refusals), 'refusal'), $this->plural(array_sum(array_map(count(...), $this->refusals)), 'column'));
        }
        if ($this->undeclaredTables !== []) {
            $parts[] = $this->plural(count($this->undeclaredTables), 'undeclared table');
        }
        if ($this->fallbacks !== []) {
            $parts[] = sprintf('%s taken over by a fallback', $this->plural($this->total($this->fallbacks), 'value'));
        }

        return implode(', ', $parts) . '.';
    }

    /**
     * Sums the counts of a tally by table, column and reason.
     *
     * @param array<string, array<string, array<string, int>>> $tally
     */
    private function total(array $tally): int
    {
        $total = 0;
        foreach ($tally as $columns) {
            foreach ($columns as $reasons) {
                $total += array_sum($reasons);
            }
        }

        return $total;
    }

    private function plural(int $count, string $noun): string
    {
        return sprintf('%d %s%s', $count, $noun, $count === 1 ? '' : 's');
    }
}
