<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Exception\MissingReferenceException;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Constant;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\WithoutEmails;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

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
}
