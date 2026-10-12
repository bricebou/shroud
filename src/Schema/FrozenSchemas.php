<?php

declare(strict_types=1);

namespace Shroud\Schema;

use Shroud\Exception\InvalidSchemaException;

/**
 * Holds the frozen schemas of a bare platform, one JSON file per version in a directory.
 *
 * A file exists only for a version whose schema differs from the previous one: the version of
 * a site therefore resolves to the file of the nearest version below. The files are named
 * `<prefix>-<version>.json`. A site whose tables carry another prefix than the frozen ones reads
 * them renamed: `spip_auteurs` frozen, `site_auteurs` loaded.
 */
final readonly class FrozenSchemas
{
    /**
     * @param string $frozenTablePrefix the prefix of the table names in the frozen files
     * @param string $tablePrefix the prefix of the table names of the site, which replaces it when loading
     */
    public function __construct(
        private string $directory,
        private string $prefix,
        private string $frozenTablePrefix = '',
        private string $tablePrefix = '',
    ) {
    }

    /**
     * Lists the frozen versions, from the oldest to the latest.
     *
     * @return list<string>
     */
    public function versions(): array
    {
        $versions = [];
        $files = glob($this->directory . '/' . $this->prefix . '-*.json');
        foreach ($files === false ? [] : $files as $file) {
            $versions[] = substr(basename($file, '.json'), strlen($this->prefix) + 1);
        }
        // The natural order compares the numbers of each segment: 4.4.9 before 4.4.28.
        sort($versions, SORT_NATURAL);

        return $versions;
    }

    /**
     * Returns the latest frozen version, or refuses when nothing is frozen.
     */
    public function latest(): string
    {
        $versions = $this->versions();

        return $versions[array_key_last($versions)] ?? throw $this->nothingFrozen();
    }

    /**
     * Returns the frozen version that applies to a version of the platform: its own, else the
     * nearest below, else the oldest known.
     */
    public function nearest(string $version): string
    {
        $nearest = null;
        foreach ($this->versions() as $frozen) {
            if (version_compare($frozen, $version, '<=')) {
                $nearest = $frozen;
            }
        }

        return $nearest ?? $this->versions()[0] ?? throw $this->nothingFrozen();
    }

    /**
     * Loads the frozen schema of a version.
     */
    public function load(string $version): Schema
    {
        $schema = Schema::fromJsonFile($this->path($version));

        return $this->frozenTablePrefix === $this->tablePrefix ? $schema : $schema->withTablePrefix($this->frozenTablePrefix, $this->tablePrefix);
    }

    /**
     * Returns the path of the file of a version, whether it exists or not.
     */
    public function path(string $version): string
    {
        $directory = realpath($this->directory);

        return ($directory === false ? $this->directory : $directory) . '/' . $this->prefix . '-' . $version . '.json';
    }

    private function nothingFrozen(): InvalidSchemaException
    {
        return new InvalidSchemaException('no frozen schema in ' . $this->directory);
    }
}
