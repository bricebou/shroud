<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Value;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Exception\InvalidValueException;
use Shroud\Generator;
use Shroud\Reference;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Constant;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Value\ShapedText;
use Shroud\Strategy\Value\ShiftedDate;
use Shroud\Tests\Unit\Platform\TestPlatform;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class ValueStrategiesTest extends StrategyTestCase
{
    public function testKeepReturnsTheValueAsIs(): void
    {
        self::assertSame('as is', self::apply(new Keep(), 'as is'));
        self::assertSame([
            'a' => 1,
        ], self::apply(new Keep(), [
            'a' => 1,
        ]));
        self::assertNull(self::apply(new Keep(), value: null));
        self::assertEquals([
            'id' => new Keep(),
            'name' => new Keep(),
        ], Keep::forColumns(['id', 'name']));
    }

    public function testClearEmptiesTheValueAccordingToItsType(): void
    {
        self::assertSame('', self::apply(new Clear(), 'secret'));
        self::assertSame([], self::apply(new Clear(), [
            'a' => 1,
        ]));
        self::assertSame(0, self::apply(new Clear(), 42));
        self::assertSame(0.0, self::apply(new Clear(), 4.2));
        self::assertFalse(self::apply(new Clear(), value: true));
        self::assertNull(self::apply(new Clear(), value: null));
    }

    public function testConstantReturnsItsValueWhateverTheInput(): void
    {
        self::assertSame('0000-00-00 00:00:00', self::apply(new Constant('0000-00-00 00:00:00'), '2026-09-29 15:49:44'));
        self::assertSame(0, self::apply(new Constant(0), value: null));
    }

    public function testTextStrategiesLetNullAndTheEmptyStringThrough(): void
    {
        self::assertNull(self::apply(new ShapedDigits(), value: null));
        self::assertSame('', self::apply(new ShapedDigits(), ''));
        self::assertSame('', self::apply(new ShiftedDate(), ''));
    }

    public function testATextStrategyRefusesAValueThatIsNotAString(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('people.value: string expected, array given');

        self::apply(new ShapedDigits(), [
            'not' => 'a string',
        ]);
    }

    public function testShapedDigitsReplacesEachDigitAndKeepsTheSeparators(): void
    {
        $postalCode = self::applyToString(new ShapedDigits(), '69002');
        self::assertMatchesRegularExpression('/^\d{5}$/', $postalCode);
        self::assertSame($postalCode, self::apply(new ShapedDigits(), '69002'), 'deterministic');

        $reference = self::applyToString(new ShapedDigits(), '(12) 34-56/78.90');
        self::assertMatchesRegularExpression('#^\(\d{2}\) \d{2}-\d{2}/\d{2}\.\d{2}$#', $reference);
    }

    public function testShapedDigitsCanKeepTheDiallingCode(): void
    {
        $number = self::applyToString(new ShapedDigits(keepDiallingCode: true), '+33 1 23 45 67 89');
        self::assertMatchesRegularExpression('/^\+33 \d \d{2} \d{2} \d{2} \d{2}$/', $number);
        self::assertNotSame('+33 1 23 45 67 89', $number);

        $compact = self::applyToString(new ShapedDigits(keepDiallingCode: true), '+33612345678');
        self::assertMatchesRegularExpression('/^\+336\d{8}$/', $compact);
        self::assertNotSame('+33612345678', $compact);

        self::assertMatchesRegularExpression('/^\d{2} \d{2}$/', self::applyToString(new ShapedDigits(keepDiallingCode: true), '06 12'), 'no dialling code to keep');
    }

    public function testShapedDigitsRefusesAValueWithLetters(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('people.value: letters found');

        self::apply(new ShapedDigits(), 'zoe@example.test 06 12');
    }

    public function testShapedDigitsRefusesInvalidUtf8(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('people.value: invalid UTF-8');

        self::apply(new ShapedDigits(), "06 \xFF 12");
    }

    public function testShiftedDateKeepsTheFormat(): void
    {
        $shifted = self::applyToString(new ShiftedDate(), '2008-03-02 00:00:00');
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} 00:00:00$/', $shifted);
        self::assertNotSame('2008-03-02 00:00:00', $shifted);

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', self::applyToString(new ShiftedDate(), '2008-03-02'));
    }

    public function testShiftedDateLeavesNullDatesAndUnreadableValues(): void
    {
        self::assertSame('0000-00-00 00:00:00', self::apply(new ShiftedDate(), '0000-00-00 00:00:00'));
        self::assertSame('0000-00-00', self::apply(new ShiftedDate(), '0000-00-00'));
        self::assertSame('not a date', self::apply(new ShiftedDate(), 'not a date'));
        self::assertSame('2008-02-30', self::apply(new ShiftedDate(), '2008-02-30'), 'not read back identically');
    }

    public function testShiftedDateStaysWithinTheMaximum(): void
    {
        foreach (range(1, 50) as $key) {
            $shifted = new \DateTimeImmutable(self::applyToString(new ShiftedDate(maxDays: 10), '2008-03-02', self::context(key: (string) $key, reference: null)));
            $days = new \DateTimeImmutable('2008-03-02')->diff($shifted)->days;

            self::assertGreaterThanOrEqual(1, $days);
            self::assertLessThanOrEqual(10, $days);
        }
    }

    /**
     * All the dates of one object slide together: the gaps between them remain.
     */
    public function testTheDatesOfOneObjectShiftByTheSameNumberOfDays(): void
    {
        $generator = new Generator(self::SALT);
        $created = self::context($generator, column: 'created', key: '1');
        $updated = self::context($generator, column: 'updated', key: '2');

        $shift = static fn (string $date, string $shifted): int => (int) new \DateTimeImmutable($date)->diff(new \DateTimeImmutable($shifted))->format('%r%a');

        self::assertSame(
            $shift('2008-03-02', self::applyToString(new ShiftedDate(), '2008-03-02', $created)),
            $shift('2012-11-20 10:00:00', self::applyToString(new ShiftedDate(), '2012-11-20 10:00:00', $updated)),
        );
    }

    public function testWithoutReferenceTheShiftIsThatOfTheRow(): void
    {
        $first = self::apply(new ShiftedDate(), '2008-03-02', self::context(key: '1', reference: null));

        self::assertSame($first, self::apply(new ShiftedDate(), '2008-03-02', self::context(key: '1', reference: null)));
        self::assertNotSame(
            self::apply(new ShiftedDate(), '2008-03-02', self::context(reference: new Reference('person', 1))),
            self::apply(new ShiftedDate(), '2008-03-02', self::context(reference: new Reference('person', 2))),
        );
    }

    public function testShapedTextKeepsTheShapeAndNothingElse(): void
    {
        $text = self::applyToString(new ShapedText(), 'Zoé Kowalczyk, <b>née</b> en 1984 (réf. AB12)');

        self::assertMatchesRegularExpression('/^\p{Lu}\p{Ll}+ \p{Lu}\p{Ll}+, <b>\p{Ll}+<\/b> \p{Ll}+ \d{4} \(\p{Ll}+\. \p{Lu}{2}\d{2}\)$/u', $text);
        self::assertStringNotContainsString('Kowalczyk', $text);
        self::assertStringNotContainsString('1984', $text);
        self::assertSame($text, self::apply(new ShapedText(), 'Zoé Kowalczyk, <b>née</b> en 1984 (réf. AB12)'), 'deterministic');
        self::assertNotSame($text, self::apply(new ShapedText(), 'Zoé Kowalczyk, <b>née</b> en 1984 (réf. AB12)', self::context(key: '43')), 'specific to the value');
    }

    public function testShapedTextNeedsNoReference(): void
    {
        self::assertMatchesRegularExpression('/^\p{Ll}+ \p{Ll}+$/u', self::applyToString(new ShapedText(), 'some words', self::context(reference: null)));
    }

    public function testShapedTextFollowsTheReferencesOfThePlatform(): void
    {
        $generator = new Generator(self::SALT, platform: new TestPlatform());
        $declaration = new Declaration([
            new Table('people', 'id', [
                'id' => new RenumberedId('person'),
            ])]);
        $person = $generator->permutationFor('person')->apply(12);

        self::assertMatchesRegularExpression("/^\\p{Lu}\\p{Ll}+ #person:{$person} {{name}}$/u", self::applyToString(new ShapedText(), 'See #person:12 {{name}}', self::context($generator, declaration: $declaration)));
    }

    public function testShapedTextRefusesInvalidUtf8(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('people.value: invalid UTF-8');

        self::apply(new ShapedText(), "caf\xE9");
    }
}
