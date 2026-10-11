<?php

declare(strict_types=1);

namespace Shroud\Schema;

use Shroud\Declaration\Declaration;
use Shroud\Platform\PlatformInterface;

/**
 * Holds what a bare platform at a given version brings: its frozen schema and its bare declaration.
 *
 * Comparing a base to its declaration against a baseline tells apart what belongs to the site,
 * its extensions and its columns, from the gap between its version of the platform and the one
 * the bare declaration was written for.
 */
final readonly class Baseline
{
    /**
     * @param string $platform the name of the platform, for the reports
     * @param string $version the frozen version the baseline rests on
     */
    public function __construct(
        public string $platform,
        public string $version,
        public Schema $schema,
        public Declaration $declaration,
    ) {
    }

    /**
     * Returns the baseline of a platform at a version, taken at the nearest frozen schema.
     */
    public static function of(PlatformInterface $platform, string $version): self
    {
        $frozen = $platform->frozenSchemas()->nearest($version);

        return new self($platform->name(), $frozen, $platform->frozenSchemas()->load($frozen), $platform->bareDeclaration());
    }

    /**
     * Tells whether the bare platform carries this table.
     */
    public function hasTable(string $table): bool
    {
        return $this->schema->has($table);
    }

    /**
     * Tells whether the bare platform carries this column.
     */
    public function hasColumn(string $table, string $column): bool
    {
        return $this->schema->has($table) && $this->schema->getTable($table)->hasColumn($column);
    }

    /**
     * Tells whether the bare declaration declares this table.
     */
    public function declaresTable(string $table): bool
    {
        return $this->declaration->has($table);
    }

    /**
     * Tells whether the bare declaration declares this column.
     */
    public function declaresColumn(string $table, string $column): bool
    {
        return $this->declaration->has($table) && isset($this->declaration->getTable($table)->columns[$column]);
    }
}
