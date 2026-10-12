<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Reference;

use Shroud\Exception\InvalidValueException;
use Shroud\Generator;
use Shroud\Identity\Permutation;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class RenumberedIdTest extends StrategyTestCase
{
    public function testTheIdIsPermutedForItsType(): void
    {
        $generator = new Generator(self::SALT);
        $author = $generator->permutationFor('author')->apply(4242);

        self::assertSame($author, self::apply(new RenumberedId('author'), 4242, self::context($generator)));
        self::assertSame((string) $author, self::apply(new RenumberedId('author'), '4242', self::context($generator)), 'the type is kept');
        self::assertSame($generator->permutationFor('contact')->apply(4242), self::apply(new RenumberedId('contact'), 4242, self::context($generator)));
    }

    /**
     * A primary key and a foreign key of the same type receive the same number, whatever the table.
     */
    public function testTheSameIdGivesTheSameNumberEverywhere(): void
    {
        $generator = new Generator(self::SALT);

        self::assertSame(
            self::apply(new RenumberedId('author'), '4242', self::context($generator, column: 'id_author', key: '4242')),
            self::apply(new RenumberedId('author'), '4242', self::context($generator, column: 'author', key: '7', reference: null)),
        );
    }

    public function testTheEmptyStatesPassThrough(): void
    {
        self::assertNull(self::apply(new RenumberedId('author'), value: null));
        self::assertSame('', self::apply(new RenumberedId('author'), ''));
        self::assertSame(0, self::apply(new RenumberedId('author'), 0));
        self::assertSame('0', self::apply(new RenumberedId('author'), '0'));
        self::assertSame('-1', self::apply(new RenumberedId('author'), '-1'));
        self::assertSame(-1, self::apply(new RenumberedId('author'), -1));
        self::assertSame('-99999999999999999999', self::apply(new RenumberedId('author'), '-99999999999999999999'));
    }

    public function testWithoutRenumberingTheIdStays(): void
    {
        self::assertSame('4242', self::apply(new RenumberedId('author'), '4242', self::context(new Generator(self::SALT, renumbering: false))));
    }

    public function testTheLargestIdIsPermuted(): void
    {
        $max = self::apply(new RenumberedId('author'), Permutation::MAX);

        self::assertIsInt($max);
        self::assertLessThanOrEqual(Permutation::MAX, $max);
        self::assertSame(self::apply(new RenumberedId('author'), (string) Permutation::MAX), (string) $max);
    }

    public function testAnIdWrittenWithLeadingZerosKeepsItsWidth(): void
    {
        $generator = new Generator(self::SALT);
        $author = (string) $generator->permutationFor('author')->apply(42);

        self::assertSame(str_pad($author, 12, '0', STR_PAD_LEFT), self::apply(new RenumberedId('author'), '000000000042', self::context($generator)));
        self::assertSame($author, self::apply(new RenumberedId('author'), '042', self::context($generator)), 'a longer permuted id keeps its digits');
        self::assertSame('000', self::apply(new RenumberedId('author'), '000'));
    }

    public function testAnIdBeyondTheDomainIsRefused(): void
    {
        foreach ([Permutation::MAX + 1, (string) (Permutation::MAX + 1), '99999999999999999999', '000002147483648'] as $id) {
            try {
                self::apply(new RenumberedId('author'), $id);
                self::fail('refused: ' . $id);
            } catch (InvalidValueException $exception) {
                self::assertSame('id beyond ' . Permutation::MAX, $exception->reason);
            }
        }
    }

    public function testAValueThatIsNotAnIdIsRefused(): void
    {
        foreach (['twelve', '12a', '1.5', 1.5, true, ['12']] as $value) {
            try {
                self::apply(new RenumberedId('author'), $value);
                self::fail('refused: ' . get_debug_type($value));
            } catch (InvalidValueException $exception) {
                self::assertSame('integer id expected, ' . get_debug_type($value) . ' given', $exception->reason);
            }
        }
    }

    /**
     * A column that sometimes carries something else than an id hands those values to a fallback.
     */
    public function testAFallbackTakesWhatIsNotAnId(): void
    {
        $author = new Fallback(new RenumberedId('author'), new Clear());

        self::assertSame('', self::apply($author, '192.0.2.1'));
        self::assertSame(self::apply(new RenumberedId('author'), '12'), self::apply($author, '12'));
    }
}
