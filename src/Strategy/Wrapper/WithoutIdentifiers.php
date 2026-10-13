<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Keep;

/**
 * Replaces the identifiers a text quotes: its email addresses, phone numbers and IP addresses.
 *
 * It applies `WithoutIps`, `WithoutPhones` and `WithoutEmails` to what the wrapped strategy
 * returns, each as it would alone; their order changes nothing, since none of their fakes
 * looks like what another replaces. The names, the postal addresses and the rest of the text
 * stay: a kept text without identifiers is not a text without personal data.
 */
final readonly class WithoutIdentifiers implements RelayingObserverInterface
{
    /**
     * The three wrappers around the wrapped strategy.
     */
    private StrategyInterface $wrappers;

    public function __construct(
        private StrategyInterface $inner = new Keep(),
    ) {
        $this->wrappers = new WithoutEmails(new WithoutPhones(new WithoutIps($inner)));
    }

    /**
     * Applies the wrapped strategy, then replaces the identifiers of what it returns when that is a string.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        return $this->wrappers->apply($value, $context);
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
     * Declares every listed column kept without its identifiers.
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
