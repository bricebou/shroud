<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Reference;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Exception\InvalidValueException;
use Shroud\Generator;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Reference\RenumberedPolymorphicId;
use Shroud\Strategy\Value\Keep;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class RenumberedPolymorphicIdTest extends StrategyTestCase
{
    public function testTheIdFollowsTheRenumberingOfItsType(): void
    {
        $generator = new Generator(self::SALT);
        $link = new RenumberedPolymorphicId('object_type');

        self::assertSame(
            (string) $generator->permutationFor('person')->apply(12),
            self::apply($link, '12', self::context($generator, row: [
                'object_type' => 'person',
                'object_id' => '12',
            ], declaration: $this->declaration())),
        );
        self::assertSame('12', self::apply($link, '12', self::context($generator, row: [
            'object_type' => 'article',
        ], declaration: $this->declaration())), 'articles are not renumbered');
        self::assertSame('12', self::apply($link, '12', self::context($generator, row: [
            'object_type' => 'person',
        ])), 'nothing is renumbered in an empty declaration');
    }

    public function testATypeThatIsNotAStringLeavesTheIdAlone(): void
    {
        $link = new RenumberedPolymorphicId('object_type');

        self::assertSame('12', self::apply($link, '12', self::context(row: [], declaration: $this->declaration())));
        self::assertSame('12', self::apply($link, '12', self::context(row: [
            'object_type' => null,
        ], declaration: $this->declaration())));
        self::assertSame('12', self::apply($link, '12', self::context(row: [
            'object_type' => 7,
        ], declaration: $this->declaration())));
    }

    public function testAValueThatIsNotAnIdIsRefusedOnlyForARenumberedType(): void
    {
        $link = new RenumberedPolymorphicId('object_type');

        self::assertSame('x', self::apply($link, 'x', self::context(row: [
            'object_type' => 'article',
        ], declaration: $this->declaration())));

        $this->expectException(InvalidValueException::class);
        self::apply($link, 'x', self::context(row: [
            'object_type' => 'person',
        ], declaration: $this->declaration()));
    }

    private function declaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new RenumberedId('person'),
            ]),
            new Table('articles', 'id_article', [
                'id_article' => new Keep(),
            ]),
        ]);
    }
}
