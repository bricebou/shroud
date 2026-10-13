<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Keep;
use Shroud\Text\Ips;

/**
 * Replaces the IP addresses of a text kept as is, or transformed by another strategy.
 *
 * Each routable address, IPv4 or IPv6, becomes a fake one, the same everywhere for the same
 * address, never the original, never shared with another: a log or a column of addresses
 * without reference keeps who came back from where. Loopback, private and reserved addresses
 * identify nobody and stay. The rest of the text does not change.
 */
final readonly class WithoutIps implements RelayingObserverInterface
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
        if (! is_string($result) || preg_match('/\d[.:]|:[0-9a-f]/i', $result) !== 1) {
            return $result;
        }

        return Ips::replaceIn($result, static fn (string $address): string => Ips::disguise($address, $context->generator));
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
