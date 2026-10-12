<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Reference;

use Shroud\Declaration\ReferenceColumns;
use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Strategy\Reference\OwnerLink;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Keep;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class OwnerLinkTest extends StrategyTestCase
{
    public function testTheObservationNotesTheOwnerOfTheObjectOfTheRow(): void
    {
        $generator = new Generator(self::SALT);
        $link = new OwnerLink(ReferenceColumns::fromFixedType('contact', 'id_contact'), ReferenceColumns::fromFixedType('person', 'id_person'));

        $link->observe('12', self::context($generator, column: 'id_person', key: '5', row: [
            'id_contact' => '5',
            'id_person' => '12',
        ]));

        self::assertEquals(new Reference('person', '12'), $generator->recall(new Reference('contact', '5'), ReferenceColumns::OWNER));
        self::assertEquals(
            new Reference('person', '12'),
            ReferenceColumns::fromFixedType('contact', 'id_contact')->followingOwner()->resolveIn([
                'id_contact' => '5',
            ], $generator),
            'a reference following the owner climbs to the person',
        );
    }

    public function testTheOwnerCanBeNamedByAPolymorphicLink(): void
    {
        $generator = new Generator(self::SALT);
        $link = new OwnerLink(ReferenceColumns::fromFixedType('email', 'id_email'), ReferenceColumns::fromTypeColumn('object_type', 'object_id'));

        $link->observe('contact', self::context($generator, column: 'object_type', row: [
            'id_email' => '7',
            'object_type' => 'contact',
            'object_id' => '5',
        ]));

        self::assertEquals(new Reference('contact', '5'), $generator->recall(new Reference('email', '7'), ReferenceColumns::OWNER));
    }

    public function testNothingIsNotedWithoutBothObjectsOrWhenTheyAreTheSame(): void
    {
        $generator = new Generator(self::SALT);
        $link = new OwnerLink(ReferenceColumns::fromFixedType('contact', 'id_contact'), ReferenceColumns::fromFixedType('person', 'id_person'));

        $link->observe('0', self::context($generator, row: [
            'id_contact' => '5',
            'id_person' => '0',
        ]));
        $link->observe('5', self::context($generator, row: [
            'id_contact' => '5',
        ]));
        $self = new OwnerLink(ReferenceColumns::fromFixedType('contact', 'id_contact'), ReferenceColumns::fromTypeColumn('object_type', 'object_id'));
        $self->observe('contact', self::context($generator, row: [
            'id_contact' => '5',
            'object_type' => 'contact',
            'object_id' => '5',
        ]));

        self::assertNull($generator->recall(new Reference('contact', '5'), ReferenceColumns::OWNER));
    }

    public function testTheWrappedStrategyTransformsTheValue(): void
    {
        $generator = new Generator(self::SALT);
        $link = new OwnerLink(ReferenceColumns::fromFixedType('contact', 'id_contact'), ReferenceColumns::fromFixedType('person', 'id_person'), new RenumberedId('person'));

        self::assertSame((string) $generator->permutationFor('person')->apply(12), self::apply($link, '12', self::context($generator)));
        self::assertSame('12', self::apply(new OwnerLink(ReferenceColumns::fromFixedType('contact', 'id_contact'), ReferenceColumns::fromFixedType('person', 'id_person')), '12'), 'kept by default');
        self::assertEquals([new Keep()], [...new OwnerLink(ReferenceColumns::fromFixedType('a', 'a'), ReferenceColumns::fromFixedType('b', 'b'))->innerStrategies()]);
    }
}
