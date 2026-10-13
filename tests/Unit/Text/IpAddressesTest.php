<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Text\IpAddresses;

final class IpAddressesTest extends TestCase
{
    private const string SALT = 'test salt';

    public function testOnlyARoutableAddressIdentifies(): void
    {
        $generator = new Generator(self::SALT);

        foreach (['127.0.0.1', '10.1.2.3', '192.168.0.1', '100.64.0.1', '192.0.2.1', '::1', 'fe80::1', '2001:db8::1'] as $address) {
            self::assertFalse(IpAddresses::isRoutable($address), $address);
            self::assertSame($address, IpAddresses::disguise($address, $generator), $address);
        }
        self::assertTrue(IpAddresses::isRoutable('203.0.114.7'));
        self::assertTrue(IpAddresses::isRoutable('2a00:1450:4007:80e::200e'));
    }

    public function testAnIpv4AddressBecomesAnotherRoutableOneTheSameEverywhere(): void
    {
        $fake = IpAddresses::disguise('203.0.114.7', new Generator(self::SALT));

        self::assertNotSame('203.0.114.7', $fake);
        self::assertTrue(IpAddresses::isRoutable($fake));
        self::assertSame($fake, IpAddresses::disguise('203.0.114.7', new Generator(self::SALT)));
        self::assertNotSame($fake, IpAddresses::disguise('203.0.114.7', new Generator('another salt')));
        self::assertSame('::ffff:' . $fake, IpAddresses::disguise('::ffff:203.0.114.7', new Generator(self::SALT)), 'written as IPv6');
    }

    /**
     * A bijection without fixed point: no two addresses meet, none keeps its value.
     */
    public function testNoTwoIpv4AddressesShareAFakeAndNoneKeepsItsValue(): void
    {
        $generator = new Generator(self::SALT);
        $fakes = [];
        for ($i = 1; $i <= 3000; ++$i) {
            $address = long2ip(0x2D000000 + $i * 7919);
            $fake = IpAddresses::disguise($address, $generator);
            self::assertNotSame($address, $fake);
            $fakes[$fake] = true;
        }

        self::assertCount(3000, $fakes);
    }

    public function testAnIpv6AddressBecomesADocumentationOne(): void
    {
        $fake = IpAddresses::disguise('2a00:1450:4007:80e::200e', new Generator(self::SALT));

        self::assertStringStartsWith('2001:db8:', $fake);
        self::assertGreaterThanOrEqual(0x80, ord(((string) inet_pton($fake))[4]), 'the values in the upper half');
    }

    public function testAnObjectHasOneAddressPerVersionOutsideTheValues(): void
    {
        $generator = new Generator(self::SALT);
        $ipv4 = IpAddresses::forObject(new Reference('person', 42), ipv6: false, generator: $generator);
        $ipv6 = IpAddresses::forObject(new Reference('person', 42), ipv6: true, generator: $generator);

        self::assertSame($ipv4, IpAddresses::forObject(new Reference('person', '42'), ipv6: false, generator: $generator));
        self::assertMatchesRegularExpression('/^2(4\d|5[0-5])\./', $ipv4, 'in 240.0.0.0/4');
        self::assertNotSame('255.255.255.255', $ipv4);
        self::assertStringStartsWith('2001:db8:', $ipv6);
        self::assertLessThan(0x80, ord(((string) inet_pton($ipv6))[4]), 'the objects in the lower half');
        self::assertNotSame($ipv4, IpAddresses::forObject(new Reference('contact', 42), ipv6: false, generator: $generator), 'each type has its permutation');
        self::assertMatchesRegularExpression('/^2(4\d|5[0-5])\./', IpAddresses::forObject(new Reference('person', 'a-uuid'), ipv6: false, generator: $generator));
    }

    public function testNoTwoObjectsOfATypeShareAnAddress(): void
    {
        $generator = new Generator(self::SALT);
        $addresses = [];
        for ($id = 1; $id <= 3000; ++$id) {
            $addresses[IpAddresses::forObject(new Reference('person', $id), ipv6: false, generator: $generator)] = true;
        }

        self::assertCount(3000, $addresses);
    }

    public function testTheAddressesOfATextAreFoundAndNothingElse(): void
    {
        $found = [];
        $text = IpAddresses::replaceIn(
            'From 203.0.114.7 and 2a00:1450:4007:80e::200e, at 12:30:45, version 1.2.3.4.5, mac 00:1a:2b:3c:4d:5e, ::1.',
            static function (string $address) use (&$found): string {
                $found[] = $address;

                return 'X';
            },
        );

        self::assertSame(['203.0.114.7', '2a00:1450:4007:80e::200e', '::1'], $found);
        self::assertSame('From X and X, at 12:30:45, version 1.2.3.4.5, mac 00:1a:2b:3c:4d:5e, X.', $text);
    }
}
