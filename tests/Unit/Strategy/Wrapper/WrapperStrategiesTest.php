<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Exception\MissingReferenceException;
use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\Phone;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Constant;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\WithoutEmails;
use Shroud\Strategy\Wrapper\WithoutIdentifiers;
use Shroud\Strategy\Wrapper\WithoutIps;
use Shroud\Strategy\Wrapper\WithoutPhones;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;
use Shroud\Text\Ips;
use Shroud\Text\Phones;

final class WrapperStrategiesTest extends StrategyTestCase
{
    public function testFallbackKeepsTheResultOfTheStrategyWhenItAccepts(): void
    {
        $phone = new Fallback(new ShapedDigits(keepDiallingCode: true), new Clear());

        self::assertMatchesRegularExpression('/^\+33 \d \d{2}$/', self::applyToString($phone, '+33 1 23'));
        self::assertSame(self::apply(new ShapedDigits(keepDiallingCode: true), '+33 1 23'), self::apply($phone, '+33 1 23'));
    }

    public function testFallbackAppliesTheOtherStrategyToARefusedValue(): void
    {
        $phone = new Fallback(new ShapedDigits(), new Clear());

        self::assertSame('', self::apply($phone, 'zoe@example.test'), 'letters found');
        self::assertSame([], self::apply($phone, [
            'not' => 'a string',
        ]), 'not a string');
        self::assertSame('n/a', self::apply(new Fallback(new ShapedDigits(), new Constant('n/a')), "06 \xFF"), 'invalid UTF-8');
    }

    public function testFallbacksChain(): void
    {
        $strict = new class() implements StrategyInterface {
            public function apply(mixed $value, Context $context): mixed
            {
                throw new \Shroud\Exception\InvalidValueException($context->table, $context->column, 'always');
            }
        };

        self::assertSame('x', self::apply(new Fallback($strict, new Fallback($strict, new Constant('x'))), 'value'));
    }

    public function testAMissingReferenceIsNotCaught(): void
    {
        $needsPseudonym = new class() implements StrategyInterface {
            public function apply(mixed $value, Context $context): mixed
            {
                return $context->requirePseudonym()->email;
            }
        };

        $this->expectException(MissingReferenceException::class);

        self::apply(new Fallback($needsPseudonym, new Clear()), 'value', self::context(reference: null));
    }

    /**
     * The text stays, the addresses go: the same address gives the same fake address everywhere.
     */
    public function testWithoutEmailsReplacesEachAddressTheSameWayEverywhere(): void
    {
        $text = 'Écrire à zoe.kowalczyk@example-real.test ou à Zoe.Kowalczyk@example-real.test, pas à <a href="mailto:j@ex.test">j@ex.test</a>.';

        $result = self::applyToString(new WithoutEmails(), $text);

        self::assertStringNotContainsString('kowalczyk', $result);
        self::assertStringNotContainsString('j@ex.test', $result);
        self::assertStringStartsWith('Écrire à ', $result);
        preg_match_all('/[a-z0-9._-]+@example\.(?:org|com|net)/', $result, $fakes);
        self::assertCount(4, $fakes[0]);
        self::assertSame($fakes[0][0], $fakes[0][1], 'the case does not make two addresses');
        self::assertSame($fakes[0][2], $fakes[0][3], 'the link and its text stay in step');
        self::assertSame($result, self::apply(new WithoutEmails(), $text), 'deterministic');
        self::assertSame(
            self::apply(new WithoutEmails(), 'j@ex.test', self::context(column: 'other', key: '7', reference: null)),
            $fakes[0][2],
            'the same address elsewhere',
        );
    }

    public function testWithoutEmailsWrapsAnotherStrategy(): void
    {
        self::assertSame('x', self::apply(new WithoutEmails(new Constant('x')), 'a@b.test'));
        self::assertSame('nothing', self::apply(new WithoutEmails(), 'nothing'));
        self::assertSame(42, self::apply(new WithoutEmails(), 42));
        self::assertNull(self::apply(new WithoutEmails(), value: null));
        self::assertEquals([
            'body' => new WithoutEmails(),
        ], WithoutEmails::forColumns(['body']));
    }

    public function testWithoutEmailsFindsInternationalisedAddresses(): void
    {
        $result = self::applyToString(new WithoutEmails(), 'Écrire à zoé@exemple-réel.test ou à 张伟@例子.测试, ou à ZOÉ@EXEMPLE-RÉEL.TEST.');

        self::assertStringNotContainsString('zoé@', $result);
        self::assertStringNotContainsString('张伟', $result);
        preg_match_all('/[a-z0-9._-]+@example\.(?:org|com|net)/', $result, $fakes);
        self::assertCount(3, $fakes[0]);
        self::assertSame($fakes[0][0], $fakes[0][2], 'the case does not make two addresses');
    }

    public function testWithoutIpReplacesEachRoutableAddressTheSameWayEverywhere(): void
    {
        $generator = new Generator(self::SALT);
        $fake = Ips::disguise('203.0.114.7', $generator);

        self::assertSame("Seen from {$fake}, then {$fake} and 10.0.0.1.", self::apply(new WithoutIps(), 'Seen from 203.0.114.7, then 203.0.114.7 and 10.0.0.1.', self::context($generator)));
        self::assertSame($fake, self::apply(new WithoutIps(), '203.0.114.7', self::context($generator, column: 'other', key: '7', reference: null)), 'no reference needed');
        self::assertSame('x', self::apply(new WithoutIps(new Constant('x')), '203.0.114.7'));
        self::assertSame('no address, 12:30', self::apply(new WithoutIps(), 'no address, 12:30'));
        self::assertNull(self::apply(new WithoutIps(), value: null));
        self::assertEquals([
            'log' => new WithoutIps(),
        ], WithoutIps::forColumns(['log']));
    }

    /**
     * A number held by a person in a phone column takes the digits of their pseudonym in every text.
     */
    public function testWithoutPhonesGivesANumberTheDigitsOfItsHolder(): void
    {
        $generator = new Generator(self::SALT);
        new Phone()->observe('06 12 34 56 78', self::context($generator));
        $column = self::applyToString(new Phone(), '06 12 34 56 78', self::context($generator));
        $page = self::context($generator, column: 'body', key: '7', reference: null);

        self::assertSame("Call {$column} or +33 " . substr($column, 1) . '.', self::apply(new WithoutPhones(), 'Call 06 12 34 56 78 or +33 6 12 34 56 78.', $page));
    }

    public function testWithoutPhonesDrawsAnUnheldNumberFromItself(): void
    {
        $generator = new Generator(self::SALT);
        $fake = self::applyToString(new WithoutPhones(), 'Call 06 98 76 54 32.', self::context($generator, reference: null));

        self::assertMatchesRegularExpression('/^Call 0\d \d\d \d\d \d\d \d\d\.$/', $fake);
        self::assertNotSame('Call 06 98 76 54 32.', $fake);
        self::assertSame($fake, self::apply(new WithoutPhones(), 'Call 06 98 76 54 32.', self::context(new Generator(self::SALT), column: 'other', key: '9', reference: null)), 'the same everywhere');
        self::assertSame('Born 12.03.2026, ref 1234567890.', self::apply(new WithoutPhones(), 'Born 12.03.2026, ref 1234567890.'));
        self::assertSame('x', self::apply(new WithoutPhones(new Constant('x')), '06 12 34 56 78'));
        self::assertNull(self::apply(new WithoutPhones(), value: null));
        self::assertEquals([
            'notes' => new WithoutPhones(),
        ], WithoutPhones::forColumns(['notes']));
    }

    /**
     * A switchboard number held by several persons goes to the smallest reference, whatever the order of the reads.
     */
    public function testANumberHeldByManyGoesToTheSmallestReference(): void
    {
        $key = Phones::key('01 23 45 67 89');
        $first = new Generator(self::SALT);
        $first->noteHolder('phone', $key, new Reference('person', 7));
        $first->noteHolder('phone', $key, new Reference('person', 12));
        $second = new Generator(self::SALT);
        $second->noteHolder('phone', $key, new Reference('person', 12));
        $second->noteHolder('phone', $key, new Reference('person', 7));

        self::assertEquals($first->holderOf('phone', $key), $second->holderOf('phone', $key));
        self::assertNull($first->holderOf('phone', 'unknown'));
    }

    /**
     * The three wrappers combine on one text, in any order, and leave the rest of it alone.
     */
    public function testWithoutIdentifiersReplacesTheAddressesTheNumbersAndTheIps(): void
    {
        $text = 'Write to zoe.martin@example.test, call 06 12 34 56 78 or +33 1 23 45 67 89, seen from 203.0.114.7 and 2a00:1450:4007:80e::200e on 12.03.2026.';
        $context = self::context(new Generator(self::SALT), reference: null);
        $result = self::applyToString(new WithoutIdentifiers(), $text, $context);

        foreach (['zoe.martin@example.test', '12 34 56 78', '23 45 67 89', '203.0.114.7', '2a00:1450'] as $identifier) {
            self::assertStringNotContainsString($identifier, $result);
        }
        self::assertStringStartsWith('Write to ', $result);
        self::assertStringEndsWith(' on 12.03.2026.', $result);
        self::assertSame($result, self::apply(new WithoutIps(new WithoutPhones(new WithoutEmails())), $text, $context), 'in any order');
        self::assertSame('x', self::apply(new WithoutIdentifiers(new Constant('x')), $text));
        self::assertEquals([
            'body' => new WithoutIdentifiers(),
        ], WithoutIdentifiers::forColumns(['body']));
    }

    /**
     * An address held by a person in an email column becomes the address of their pseudonym in every text.
     */
    public function testWithoutEmailsGivesAnAddressTheEmailOfItsHolder(): void
    {
        $generator = new Generator(self::SALT);
        new Email()->observe(' Zoe.Martin@Example.test', self::context($generator));
        $email = $generator->pseudonymFor(new Reference('person', 42))->email;
        $page = self::context($generator, column: 'body', key: '7', reference: null);

        self::assertSame("Write to {$email}.", self::apply(new WithoutEmails(), 'Write to zoe.martin@example.test.', $page));
        self::assertNotSame("Write to {$email}.", self::apply(new WithoutEmails(), 'Write to someone@example.test.', $page), 'an address nobody holds');
    }
}
