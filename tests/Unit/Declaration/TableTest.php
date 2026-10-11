<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Declaration;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Exception\UndeclaredColumnException;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedText;

final class TableTest extends TestCase
{
    public function testThePrimaryKeyIsAListOfColumns(): void
    {
        self::assertSame(['id'], new Table('people', 'id', [])->primaryKey);
        self::assertSame(['id_person', 'id_tag'], new Table('links', ['id_person', 'id_tag'], [])->primaryKey);
    }

    public function testTheKeyOfARowJoinsItsPrimaryKeyValues(): void
    {
        $links = new Table('links', ['id_person', 'id_tag'], []);

        self::assertSame('12|7', $links->computeKey([
            'id_person' => '12',
            'id_tag' => 7,
            'other' => 'x',
        ]));
        self::assertSame('12|', $links->computeKey([
            'id_person' => 12,
        ]), 'a missing column is empty');
        self::assertSame('|', $links->computeKey([
            'id_person' => [
                'not' => 'scalar',
            ],
            'id_tag' => null,
        ]));
    }

    public function testTheColumnsAreExtendedAndOverriddenColumnByColumn(): void
    {
        $people = new Table('people', 'id', [
            'id' => new Keep(),
            'name' => new ShapedText(),
            'email' => new Email(),
        ], ReferenceColumns::fromFixedType('person', 'id'));

        $extended = $people->withColumns([
            'institution' => new ShapedText(),
            'name' => new Keep(),
        ]);

        self::assertInstanceOf(ShapedText::class, $extended->getStrategyFor('institution'));
        self::assertInstanceOf(Keep::class, $extended->getStrategyFor('name'), 'the existing column is replaced');
        self::assertInstanceOf(Email::class, $extended->getStrategyFor('email'), 'the other columns remain');
        self::assertSame($people->reference, $extended->reference);
        self::assertSame(['id'], $extended->primaryKey);
        self::assertInstanceOf(ShapedText::class, $people->getStrategyFor('name'), 'the original is untouched');
    }

    public function testTheReferenceCanBeChanged(): void
    {
        $people = new Table('people', 'id', [
            'id' => new Keep(),
        ]);
        $reference = ReferenceColumns::fromFixedType('person', 'id');

        self::assertNull($people->reference);
        self::assertSame($reference, $people->withReference($reference)->reference);
        self::assertNull($people->withReference($reference)->withReference(reference: null)->reference);
    }

    public function testAnUndeclaredColumnIsRefused(): void
    {
        $this->expectException(UndeclaredColumnException::class);
        $this->expectExceptionMessage('people.phone');

        new Table('people', 'id', [
            'id' => new Keep(),
        ])->getStrategyFor('phone');
    }

    public function testATruncatedTableHasNoColumn(): void
    {
        $visits = Table::truncated('visits');

        self::assertTrue($visits->truncated);
        self::assertSame([], $visits->columns);
        self::assertSame([], $visits->primaryKey);
        self::assertFalse(new Table('people', 'id', [
            'id' => new Keep(),
        ])->truncated);
    }

    public function testATableKnowsWhetherAColumnObserves(): void
    {
        self::assertTrue(new Table('people', 'id', [
            'id' => new Keep(),
            'first_name' => new FirstName(),
        ])->hasObservers());
        self::assertFalse(new Table('people', 'id', [
            'id' => new Keep(),
            'bio' => new Clear(),
        ])->hasObservers());
    }
}
