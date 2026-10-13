<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Keep;
use Shroud\Text\Phones;

/**
 * Replaces the phone numbers of a text kept as is, or transformed by another strategy.
 *
 * A number that a `Phone` column holds for an object takes the digits of its pseudonym: the
 * person keeps one number, in their columns and in the texts that quote them. Any other number
 * takes digits drawn from itself, the same everywhere. The separators, the dialling code and
 * the trunk prefix stay; the rest of the text does not change. What a number is, and is not, is
 * told in `Phones`.
 */
final readonly class WithoutPhones implements RelayingObserverInterface
{
    public function __construct(
        private StrategyInterface $inner = new Keep(),
    ) {
    }

    /**
     * Applies the wrapped strategy, then replaces the numbers of what it returns when that is a string.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        $result = $this->inner->apply($value, $context);
        if (! is_string($result) || Phones::countDigits($result) < 8) {
            return $result;
        }

        return Phones::replaceIn($result, static fn (string $number): string => Phones::lay($number, self::digitsOf($number, $context)));
    }

    public function observe(mixed $value, Context $context): void
    {
        Observation::relay($this->inner, $value, $context);
    }

    public function innerStrategies(): iterable
    {
        return [$this->inner];
    }

    /**
     * Declares every listed column kept without its phone numbers.
     *
     * @param list<string> $columns
     *
     * @return array<string, self>
     */
    public static function forColumns(array $columns): array
    {
        return array_fill_keys($columns, new self());
    }

    /**
     * Returns the fake digits of a number: those of the object that holds it, or drawn from the number.
     */
    private static function digitsOf(string $number, Context $context): string
    {
        $key = Phones::key($number);
        $holder = $context->generator->holderOf('phone', $key);

        return $holder instanceof \Shroud\Identity\Reference
            ? $context->generator->pseudonymFor($holder)->phoneDigits
            : $context->generator->draw('phone', $key)->numerify(str_repeat('#', 15));
    }
}
