<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Keep;
use Shroud\Text\Emails;

/**
 * Replaces the email addresses of a text kept as is, or transformed by another strategy.
 *
 * Each address becomes a fake address on a reserved domain, the same everywhere for the same
 * address, whatever the table: a public content that quotes a person quotes them the same way
 * in every row. The rest of the text does not change.
 */
final readonly class WithoutEmails implements RelayingObserverInterface
{
    public function __construct(
        private StrategyInterface $inner = new Keep(),
    ) {
    }

    /**
     * Applies the wrapped strategy, then replaces the addresses of what it returns when that is a string.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        $result = $this->inner->apply($value, $context);
        if (! is_string($result)) {
            return $result;
        }

        return Emails::replaceIn($result, static fn (string $address): string => Emails::disguise($address, $context->generator));
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
     * Declares every listed column kept without its addresses.
     *
     * @param list<string> $columns
     *
     * @return array<string, self>
     */
    public static function forColumns(array $columns): array
    {
        return array_fill_keys($columns, new self());
    }
}
