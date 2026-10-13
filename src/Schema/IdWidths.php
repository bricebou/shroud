<?php

declare(strict_types=1);

namespace Shroud\Schema;

use Shroud\Declaration\Declaration;
use Shroud\Strategy\CompositeInterface;
use Shroud\Strategy\Nested\Delimited;
use Shroud\Strategy\Nested\Json;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Reference\RenumberedPolymorphicId;
use Shroud\Strategy\StrategyInterface;

/**
 * Checks that every column receiving renumbered ids can hold the largest one its type can take.
 *
 * A renumbered id runs up to 2^width - 1, whatever the real one was: a narrower integer column,
 * or a string column too short for its digits, would refuse it at import, or clip it to its
 * maximum where the SQL mode is not strict, which breaks the links without a word. The check
 * reads the types of the schema before any row is written.
 */
final class IdWidths
{
    /**
     * The bytes of each integer type.
     *
     * @var array<string, int>
     */
    private const array INTEGER_BYTES = [
        'tinyint' => 1,
        'smallint' => 2,
        'mediumint' => 3,
        'int' => 4,
        'integer' => 4,
        'bigint' => 8,
    ];

    /**
     * Lists the columns too narrow for the renumbered ids they receive, one sentence each, with the width to declare.
     *
     * @return list<string>
     */
    public static function check(Schema $schema, Declaration $declaration): array
    {
        $problems = [];
        foreach ($declaration->tables() as $table) {
            if (! $schema->has($table->name)) {
                continue;
            }
            $columns = $schema->getTable($table->name)->columns;
            foreach ($table->columns as $column => $strategy) {
                if (! isset($columns[$column])) {
                    continue;
                }
                foreach (self::typesOf($strategy, $declaration) as $type) {
                    $problem = self::problem($table->name . '.' . $column, $columns[$column], $type, $declaration->renumberingWidth($type));
                    if ($problem !== null) {
                        $problems[] = $problem;
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * Lists the renumbered types whose ids a strategy writes as the whole value of its column.
     *
     * The containers, a blob, a document, a list, carry their ids among other characters: their
     * column has no integer type to overflow.
     *
     * @return list<string>
     */
    private static function typesOf(StrategyInterface $strategy, Declaration $declaration): array
    {
        if ($strategy instanceof Serialized || $strategy instanceof Json || $strategy instanceof PerKey || $strategy instanceof Delimited) {
            return [];
        }
        if ($strategy instanceof RenumberedId) {
            return $declaration->isRenumbered($strategy->type) ? [$strategy->type] : [];
        }
        // A polymorphic link may carry an id of any renumbered type.
        if ($strategy instanceof RenumberedPolymorphicId) {
            return $declaration->renumberedTypes();
        }
        $types = [];
        if ($strategy instanceof CompositeInterface) {
            foreach ($strategy->innerStrategies() as $inner) {
                array_push($types, ...self::typesOf($inner, $declaration));
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Describes why a column cannot hold the ids of a type, or returns null when it can.
     */
    private static function problem(string $column, string $sqlType, string $type, int $width): ?string
    {
        $largest = (1 << $width) - 1;
        $capacity = self::capacity(strtolower($sqlType));
        if ($capacity === null || $capacity >= $largest) {
            return null;
        }
        $fitting = (int) floor(log($capacity + 1, 2));

        return sprintf(
            'the ids of %s are renumbered on %d bits, up to %d, but %s is %s, up to %d: declare RenumberedId(\'%s\', bits: %d) or less',
            $type,
            $width,
            $largest,
            $column,
            $sqlType,
            $capacity,
            $type,
            $fitting,
        );
    }

    /**
     * Returns the largest id a column type holds, or null when it sets no bound on ids.
     */
    private static function capacity(string $sqlType): ?int
    {
        if (preg_match('/^(tinyint|smallint|mediumint|integer|int|bigint)\b/', $sqlType, $integer) === 1) {
            $bits = 8 * self::INTEGER_BYTES[$integer[1]] - (str_contains($sqlType, 'unsigned') ? 0 : 1);

            return $bits >= 63 ? PHP_INT_MAX : (1 << $bits) - 1;
        }
        if (preg_match('/^(?:decimal|numeric)\((\d+)(?:,\s*0)?\)/', $sqlType, $decimal) === 1) {
            return (int) $decimal[1] >= 18 ? PHP_INT_MAX : 10 ** (int) $decimal[1] - 1;
        }
        // A string column holds as many digits as characters.
        if (preg_match('/^(?:var)?char\((\d+)\)/', $sqlType, $string) === 1) {
            return (int) $string[1] >= 18 ? PHP_INT_MAX : 10 ** (int) $string[1] - 1;
        }

        return null;
    }
}
