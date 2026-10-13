<?php

declare(strict_types=1);

namespace Shroud\Text;

use Shroud\Generator;
use Shroud\Identity\Reference;

/**
 * Finds the IP addresses of a text and draws the fake ones, IPv4 and IPv6.
 *
 * Only a globally routable address identifies: loopback, private, link-local, documentation
 * and reserved addresses stay as they are. A fake address never collides with another and
 * never equals its original:
 *
 * - an IPv4 address becomes another routable IPv4 address, through a permutation of the
 *   routable addresses keyed by the salt, shifted by one so that none is a fixed point;
 * - the address of an object, one per object, is drawn in `240.0.0.0/4`, reserved, from a
 *   permutation of its id: no routable address of a value can meet it;
 * - an IPv6 address becomes a digest of 95 bits in the documentation range `2001:db8::/32`,
 *   the values and the objects in two halves of it.
 */
final class IpAddresses
{
    /**
     * An IPv6 address, an IPv4 tail included, or an IPv4 address, glued to no word, colon or other number.
     */
    private const string PATTERN = '/(?<![\w:.])(?:(?:[0-9a-f]{0,4}:){2,7}(?:[0-9a-f]{1,4}|\d{1,3}(?:\.\d{1,3}){3})?|(?:\d{1,3}\.){3}\d{1,3})(?![\w:]|\.\d)/i';

    /**
     * The first address of the range of the objects, `240.0.0.0`.
     */
    private const int OBJECTS = 0xF0000000;

    /**
     * The last rank in the range of the objects: `255.255.255.255`, the broadcast, is set aside.
     */
    private const int OBJECT_COUNT = 0x0FFFFFFE;

    /**
     * Replaces each valid address of a text by what the callable returns for it.
     *
     * @param callable(string): string $replace
     */
    public static function replaceIn(string $text, callable $replace): string
    {
        return preg_replace_callback(
            self::PATTERN,
            static fn (array $match): string => filter_var($match[0], FILTER_VALIDATE_IP) === false ? $match[0] : $replace($match[0]),
            $text,
        ) ?? $text;
    }

    /**
     * Tells whether an address is valid.
     */
    public static function isAddress(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Tells whether an address is globally routable, the only kind that identifies.
     */
    public static function isRoutable(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    /**
     * Returns the fake address of an address, the same everywhere; a non-routable one comes back unchanged.
     */
    public static function disguise(string $address, Generator $generator): string
    {
        // An IPv4 address written as IPv6 is disguised as IPv4, before the range test that rejects its prefix.
        $mapped = self::mappedIpv4($address);
        if ($mapped !== null) {
            return '::ffff:' . self::disguise($mapped, $generator);
        }
        if (! self::isRoutable($address)) {
            return $address;
        }
        if (! str_contains($address, ':')) {
            return self::disguiseIpv4($address, $generator);
        }

        return self::documentationIpv6($generator->digest('ip', bin2hex((string) inet_pton($address))), values: true);
    }

    /**
     * Returns the address of an object, one per object and per version, distinct within its type.
     */
    public static function forObject(Reference $reference, bool $ipv6, Generator $generator): string
    {
        if ($ipv6) {
            return self::documentationIpv6($generator->digest('ip', $reference->type, $reference->id), values: false);
        }
        $id = $reference->id;
        $isRank = static fn (int $rank): bool => $rank >= 1 && $rank <= self::OBJECT_COUNT;
        if (is_int($id) || ctype_digit($id)) {
            $rank = (int) $id;
            if ($isRank($rank)) {
                return long2ip(self::OBJECTS + $generator->permutation(28, 'ip', $reference->type)->applyWithin($rank, $isRank));
            }
        }

        // An id that is not a number, or too large for the range: its digest, which two objects may share.
        return long2ip(self::OBJECTS + 1 + (int) hexdec(substr($generator->digest('ip', $reference->type, $reference->id), 0, 7)) % self::OBJECT_COUNT);
    }

    /**
     * Permutes a routable IPv4 address among the routable ones, then moves it one place in the
     * order of the permutation: a bijection without fixed point.
     */
    private static function disguiseIpv4(string $address, Generator $generator): string
    {
        $permutation = $generator->permutation(32, 'ip');
        $isRoutable = static fn (int $value): bool => self::isRoutable(long2ip($value));
        $rank = $permutation->invertWithin((int) ip2long($address), $isRoutable);
        do {
            $rank = ($rank + 1) & 0xFFFFFFFF;
        } while (! $isRoutable($rank));

        return long2ip($permutation->applyWithin($rank, $isRoutable));
    }

    /**
     * Returns the IPv4 address an IPv4-mapped IPv6 address carries, `::ffff:192.0.2.1`, or null.
     */
    public static function mappedIpv4(string $address): ?string
    {
        $bytes = inet_pton($address);
        if ($bytes === false || strlen($bytes) !== 16 || ! str_starts_with($bytes, str_repeat("\0", 10) . "\xFF\xFF")) {
            return null;
        }

        return (string) inet_ntop(substr($bytes, 12));
    }

    /**
     * Builds an address of `2001:db8::/32` from a digest: the values in its upper half, the objects in its lower half.
     */
    private static function documentationIpv6(string $digest, bool $values): string
    {
        $bytes = (string) hex2bin(substr($digest, 0, 24));
        $first = ord($bytes[0]);
        $bytes[0] = chr($values ? $first | 0x80 : $first & 0x7F);

        return (string) inet_ntop("\x20\x01\x0d\xb8" . $bytes);
    }
}
