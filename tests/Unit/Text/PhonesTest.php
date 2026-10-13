<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shroud\Text\Phones;

final class PhonesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function numbers(): iterable
    {
        yield 'french, blanks' => ['06 12 34 56 78'];
        yield 'french, dots' => ['06.12.34.56.78'];
        yield 'dialling code' => ['+33 6 12 34 56 78'];
        yield 'dialling code, glued' => ['+33612345678'];
        yield 'dialling code, hyphens' => ['+1-701-407-3347'];
        yield 'dialling code, then parentheses' => ['+1 (231) 287-6354'];
        yield 'parentheses' => ['(555) 123-4567'];
        yield 'three, three, four' => ['555.123.4567'];
        yield 'area code, then blanks' => ['08-61 99 10'];
        yield 'area code, then blanks again' => ['0413-735 61'];
        yield 'groups of three after hyphens' => ['257-325-535'];
        yield 'four and four' => ['1840-3232'];
        yield 'national toll-free' => ['0 800 123 456'];
        yield 'british' => ['+44 20 7946 0958'];
        yield 'dialling code in parentheses' => ['(+33) 6 12 34 56 78'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notNumbers(): iterable
    {
        yield 'too short' => ['12 34 56'];
        yield 'glued digits, an id' => ['1234567890'];
        yield 'iso date' => ['2026-01-01 12'];
        yield 'french date' => ['12.03.2026'];
        yield 'date with blanks' => ['12 03 2026'];
        yield 'range of years' => ['2019-2020'];
        yield 'amount' => ['12 345 678'];
        yield 'company number' => ['123 456 789'];
        yield 'version with a zero' => ['001.002.003'];
        yield 'ipv4' => ['192.168.1.10'];
        yield 'mixed separators' => ['12.34-56.78'];
        yield 'coordinates' => ['48.8566 2.3522'];
        yield 'more than six groups' => ['1.2.3.4.5.6.7.8'];
        yield 'too long' => ['1234 5678 9012 3456'];
    }

    #[DataProvider('numbers')]
    public function testANumberIsFound(string $number): void
    {
        self::assertSame("Call [{$number}].", Phones::replaceIn("Call {$number}.", static fn (string $found): string => "[{$found}]"));
    }

    #[DataProvider('notNumbers')]
    public function testWhatLooksLikeANumberIsLeftAlone(string $value): void
    {
        self::assertSame("See {$value}.", Phones::replaceIn("See {$value}.", static fn (string $found): string => "[{$found}]"));
    }

    public function testWhatSurroundsAGroupOfDigitsCounts(): void
    {
        $replace = static fn (string $found): string => "[{$found}]";

        self::assertSame('order-oct-12-2026-0800-am', Phones::replaceIn('order-oct-12-2026-0800-am', $replace), 'a part of a word');
        self::assertSame('color: rgb(255 255 255)', Phones::replaceIn('color: rgb(255 255 255)', $replace), 'the arguments of a function');
    }

    public function testTheKeyIgnoresHowTheNumberIsWritten(): void
    {
        self::assertSame(Phones::key('06 12 34 56 78'), Phones::key('+33 6 12 34 56 78'));
        self::assertSame('612345678', Phones::key('+33612345678'));
    }

    public function testDigitsAreLaidFromTheLastKeepingThePrefix(): void
    {
        self::assertSame('09 87 65 43 21', Phones::lay('06 12 34 56 78', '123456789'));
        self::assertSame('+33 9 87 65 43 21', Phones::lay('+33 6 12 34 56 78', '123456789'));
        self::assertSame('(12) 1', Phones::lay('(12) 3', '12'), 'the digits start over');
        self::assertSame('(+33) 9 87 65 43 21', Phones::lay('(+33) 6 12 34 56 78', '123456789'), 'a dialling code in parentheses');
    }
}
