<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Shroud\Context;
use Shroud\Generator;
use Shroud\Reference;
use Shroud\Strategy\StrategyInterface;

/**
 * Applies a strategy to one value of one row, as the disguiser would.
 */
abstract class StrategyTestCase extends TestCase
{
    protected const string SALT = 'test salt';

    /**
     * Builds the context of the column `value` in the row 42 of the table `people`, which references the person 42.
     *
     * @param array<string, mixed> $row
     */
    protected static function context(
        ?Generator $generator = null,
        string $column = 'value',
        string $key = '42',
        array $row = [],
        ?Reference $reference = new Reference('person', 42),
    ): Context {
        return new Context('people', $column, $key, $row, $reference, $generator ?? new Generator(self::SALT));
    }

    protected static function apply(StrategyInterface $strategy, mixed $value, ?Context $context = null): mixed
    {
        return $strategy->apply($value, $context ?? self::context());
    }

    /**
     * Applies the strategy and asserts that it returns a string.
     */
    protected static function applyToString(StrategyInterface $strategy, mixed $value, ?Context $context = null): string
    {
        $result = self::apply($strategy, $value, $context);
        self::assertIsString($result);

        return $result;
    }
}
