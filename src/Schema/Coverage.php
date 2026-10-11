<?php

declare(strict_types=1);

namespace Shroud\Schema;

use Shroud\Declaration\Declaration;

/**
 * Measures the gap between the schema of a base and a declaration, both ways.
 *
 * A complete declaration covers every table and every column of the base, and names nothing
 * the base does not have. A truncated table is an exception both ways: its columns do not
 * count, nor does its absence from the base, since nothing of it is kept.
 *
 * Against a baseline, the bare platform at the version of the site, the gap splits: what
 * belongs to the site, its extensions and its columns, and what belongs to the version of the
 * platform, which the bare declaration declares for another version. This is the mandatory
 * coverage of a project: a column that a migration adds must be classified in the same commit.
 */
final readonly class Coverage
{
    /**
     * @param list<string> $undeclaredTables tables of the base, specific to the site, absent from the declaration
     * @param array<string, list<string>> $undeclaredColumns by table, columns of the base, specific to the site, absent from the declaration
     * @param list<string> $missingTables declared tables absent from the base
     * @param array<string, list<string>> $missingColumns by table, declared columns absent from the base
     * @param list<string> $platformUndeclaredTables tables of the bare platform at this version that its declaration does not declare
     * @param array<string, list<string>> $platformUndeclaredColumns by table, columns of the bare platform at this version that its declaration does not declare
     * @param list<string> $otherVersionTables tables declared by the bare declaration for another version of the platform
     * @param array<string, list<string>> $otherVersionColumns by table, columns declared by the bare declaration for another version of the platform
     */
    private function __construct(
        public ?Baseline $baseline,
        public array $undeclaredTables,
        public array $undeclaredColumns,
        public array $missingTables,
        public array $missingColumns,
        public array $platformUndeclaredTables = [],
        public array $platformUndeclaredColumns = [],
        public array $otherVersionTables = [],
        public array $otherVersionColumns = [],
    ) {
    }

    /**
     * Compares a schema to a declaration, against a baseline when there is one.
     */
    public static function compare(Schema $schema, Declaration $declaration, ?Baseline $baseline = null): self
    {
        $undeclaredTables = [];
        $undeclaredColumns = [];
        $platformTables = [];
        $platformColumns = [];
        foreach ($schema->tables() as $table) {
            if (! $declaration->has($table->name)) {
                if ($baseline?->hasTable($table->name) ?? false) {
                    $platformTables[] = $table->name;
                } else {
                    $undeclaredTables[] = $table->name;
                }
                continue;
            }
            $declared = $declaration->getTable($table->name);
            if ($declared->truncated) {
                continue;
            }
            foreach (array_diff($table->columnNames(), array_keys($declared->columns)) as $column) {
                if ($baseline?->hasColumn($table->name, $column) ?? false) {
                    $platformColumns[$table->name][] = $column;
                } else {
                    $undeclaredColumns[$table->name][] = $column;
                }
            }
        }

        $missingTables = [];
        $missingColumns = [];
        $otherTables = [];
        $otherColumns = [];
        foreach ($declaration->tables() as $declared) {
            if ($declared->truncated) {
                continue;
            }
            if (! $schema->has($declared->name)) {
                // A table that neither the site nor its bare platform has, but that the bare
                // declaration declares, comes from another version of the platform: no effect here.
                if ($baseline instanceof Baseline && ! $baseline->hasTable($declared->name) && $baseline->declaresTable($declared->name)) {
                    $otherTables[] = $declared->name;
                } else {
                    $missingTables[] = $declared->name;
                }
                continue;
            }
            $present = $schema->getTable($declared->name);
            foreach (array_diff(array_keys($declared->columns), $present->columnNames()) as $column) {
                if ($baseline instanceof Baseline && ! $baseline->hasColumn($declared->name, $column) && $baseline->declaresColumn($declared->name, $column)) {
                    $otherColumns[$declared->name][] = $column;
                } else {
                    $missingColumns[$declared->name][] = $column;
                }
            }
        }

        return new self($baseline, $undeclaredTables, $undeclaredColumns, $missingTables, $missingColumns, $platformTables, $platformColumns, $otherTables, $otherColumns);
    }

    /**
     * Tells whether the declaration covers the schema exactly; the version gap of the platform does not count.
     */
    public function isComplete(): bool
    {
        return $this->undeclaredTables === [] && $this->undeclaredColumns === []
            && $this->missingTables === [] && $this->missingColumns === []
            && $this->platformUndeclaredTables === [] && $this->platformUndeclaredColumns === [];
    }

    /**
     * Tells whether something of the base awaits a declaration, specific to the site or not.
     */
    public function hasUndeclared(): bool
    {
        return $this->undeclaredTables !== [] || $this->undeclaredColumns !== []
            || $this->platformUndeclaredTables !== [] || $this->platformUndeclaredColumns !== [];
    }

    /**
     * Returns the report of the gap, as numbered sections then a summary, ready to print.
     *
     * @return list<string>
     */
    public function report(): array
    {
        if ($this->isComplete() && $this->otherVersionTables === [] && $this->otherVersionColumns === []) {
            return ['Complete declaration: every table and every column of the base is declared, and nothing more.'];
        }
        $bare = $this->baseline instanceof Baseline ? sprintf('a bare %s %s', $this->baseline->platform, $this->baseline->version) : '';
        $sections = [];
        if ($this->undeclaredTables !== [] || $this->undeclaredColumns !== []) {
            $sections[] = [
                'Specific to the site: to declare',
                $bare === ''
                    ? 'The base carries this, which the declaration ignores; without a baseline, nothing tells whether it belongs to the site or to the platform.'
                    : sprintf('The base carries this, which %s does not have: extensions, added columns.', $bare),
                'The draft at the end of the output declares it.',
                $this->undeclaredTables,
                $this->undeclaredColumns,
            ];
        }
        if ($this->platformUndeclaredTables !== [] || $this->platformUndeclaredColumns !== []) {
            $sections[] = [
                'Missing from the bare declaration of the platform: to declare, and to report',
                sprintf('%s carries this, which the bare declaration of the platform does not declare.', ucfirst($bare)),
                'The draft at the end of the output declares it too, until the platform package is updated; report it there.',
                $this->platformUndeclaredTables,
                $this->platformUndeclaredColumns,
            ];
        }
        if ($this->missingTables !== [] || $this->missingColumns !== []) {
            $sections[] = [
                'Declared without existing in the base: to remove from the declaration',
                'The declaration names this, which the base does not have.',
                'Remove it, unless the base is incomplete; the draft recalls it as a todo.',
                $this->missingTables,
                $this->missingColumns,
            ];
        }
        if ($this->otherVersionTables !== [] || $this->otherVersionColumns !== []) {
            $sections[] = [
                'No effect: declared by the bare declaration of the platform for another version',
                sprintf('The bare declaration of the platform declares this for another version than %s, and the base does not have it.', $this->baseline->version ?? '?'),
                'A declared column that the base does not have hinders neither the dump nor the transformation: nothing to do.',
                $this->otherVersionTables,
                $this->otherVersionColumns,
            ];
        }

        $width = $this->width();
        $lines = [];
        foreach ($sections as $number => [$title, $explanation, $action, $tables, $columns]) {
            $lines[] = sprintf('%d. %s', $number + 1, $title);
            $lines[] = '   ' . $explanation;
            $lines[] = '   ' . $action;
            $lines[] = '';
            array_push($lines, ...$this->items($tables, $columns, $width));
            $lines[] = '';
        }
        $lines[] = $this->summary();

        return $lines;
    }

    /**
     * Returns the summary in one line: what is to declare, to remove, without effect.
     */
    public function summary(): string
    {
        $count = static fn (array $tables, array $columns): int => count($tables) + array_sum(array_map(count(...), $columns));
        $toDeclare = $count($this->undeclaredTables, $this->undeclaredColumns) + $count($this->platformUndeclaredTables, $this->platformUndeclaredColumns);
        $toRemove = $count($this->missingTables, $this->missingColumns);
        $noEffect = $count($this->otherVersionTables, $this->otherVersionColumns);
        $parts = [];
        if ($toDeclare === 0 && $toRemove === 0) {
            $parts[] = 'complete declaration';
        }
        foreach ([[$toDeclare, 'to declare'], [$toRemove, 'to remove'], [$noEffect, 'with no effect']] as [$number, $label]) {
            if ($number > 0) {
                $parts[] = $number . ' ' . $label;
            }
        }

        return 'Summary: ' . implode(', ', $parts) . '.';
    }

    /**
     * Returns the width of the longest table name of the report, to align the columns.
     */
    private function width(): int
    {
        $names = [
            ...$this->undeclaredTables, ...array_keys($this->undeclaredColumns),
            ...$this->platformUndeclaredTables, ...array_keys($this->platformUndeclaredColumns),
            ...$this->missingTables, ...array_keys($this->missingColumns),
            ...$this->otherVersionTables, ...array_keys($this->otherVersionColumns),
        ];

        return max(0, ...array_map(strlen(...), $names));
    }

    /**
     * Lays out whole tables and columns by table, one line each, aligned.
     *
     * @param list<string> $tables
     * @param array<string, list<string>> $columns
     *
     * @return list<string>
     */
    private function items(array $tables, array $columns, int $width): array
    {
        $lines = [];
        foreach ($tables as $table) {
            $lines[] = sprintf('     %s  whole table', str_pad($table, $width));
        }
        foreach ($columns as $table => $names) {
            $lines[] = sprintf('     %s  %s', str_pad($table, $width), implode(', ', $names));
        }

        return $lines;
    }
}
