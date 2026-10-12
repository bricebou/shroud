<?php

declare(strict_types=1);

namespace Shroud\Strategy\Value;

use Shroud\Context;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Identity\Reference;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Shifts an SQL date or datetime by a number of days specific to the referenced object, or to the row.
 *
 * The zero dates of SQL (`0000-00-00`) and the values that are not dates stay as they are.
 */
final class ShiftedDate extends AbstractTextStrategy
{
    public function __construct(
        private readonly int $maxDays = 365,
    ) {
        // Below one day, Faker swaps the bounds of its draw and a date could keep its value.
        if ($maxDays < 1) {
            throw new InvalidConfigurationException(sprintf('the shift of a date must reach at least one day, %d given', $maxDays));
        }
    }

    protected function transform(string $value, Context $context): string
    {
        if (str_starts_with($value, '0000-00-00')) {
            return $value;
        }

        // A date or a datetime in SQL format; a value that does not read back identically is not a date.
        $format = strlen($value) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d';
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date === false || $date->format($format) !== $value) {
            return $value;
        }

        // The shift is specific to the object when the row references one:
        // all its dates slide together, and the gaps between them remain.
        $faker = $context->reference instanceof Reference
            ? $context->generator->seedFaker('shift', $context->reference->type, $context->reference->id)
            : $context->seedFaker();
        $days = $faker->numberBetween(1, $this->maxDays) * ($faker->boolean() ? 1 : -1);

        return $date->modify(sprintf('%+d days', $days))->format($format);
    }
}
