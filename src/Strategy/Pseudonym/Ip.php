<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Exception\MissingReferenceException;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Text\Ips;

/**
 * Replaces an IP address by the one of the referenced object: one address per object, IPv4 or
 * IPv6 as the original, whatever the addresses it came from.
 *
 * Two objects of a type never share an address; an address of `240.0.0.0/4` or of the
 * documentation range of IPv6 points at no host. Loopback, private and reserved addresses
 * identify nobody and stay. A value that is not an address is refused: the caller decides,
 * through `Fallback`.
 */
final class Ip extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        $reference = $context->reference ?? throw new MissingReferenceException($context->table, $context->column);
        if (! Ips::isAddress($value)) {
            throw new InvalidValueException($context->table, $context->column, 'not an IP address');
        }
        // An IPv4 address written as IPv6 is an IPv4 address, whose prefix the range test rejects.
        $mapped = Ips::mappedIpv4($value);
        if ($mapped !== null) {
            return '::ffff:' . $this->transform($mapped, $context);
        }
        if (! Ips::isRoutable($value)) {
            return $value;
        }

        return Ips::forObject($reference, str_contains($value, ':'), $context->generator);
    }
}
