<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Declaration;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Generator;
use Shroud\Reference;

final class ReferenceColumnsTest extends TestCase
{
    public function testAFixedTypeReadsTheIdFromItsColumn(): void
    {
        $columns = ReferenceColumns::fromFixedType('person', 'id_person');

        self::assertEquals(new Reference('person', '12'), $columns->resolveIn([
            'id_person' => '12',
            'name' => 'x',
        ]));
        self::assertEquals(new Reference('person', 12), $columns->resolveIn([
            'id_person' => 12,
        ]));
    }

    public function testATypeColumnReadsBothTheTypeAndTheId(): void
    {
        $columns = ReferenceColumns::fromTypeColumn('object_type', 'object_id');

        self::assertEquals(new Reference('contact', '5'), $columns->resolveIn([
            'object_type' => 'contact',
            'object_id' => '5',
        ]));
        self::assertNull($columns->resolveIn([
            'object_type' => '',
            'object_id' => '5',
        ]), 'an empty type is no object');
        self::assertNull($columns->resolveIn([
            'object_type' => 12,
            'object_id' => '5',
        ]), 'a type must be a string');
        self::assertNull($columns->resolveIn([
            'object_id' => '5',
        ]));
    }

    public function testAMissingEmptyOrZeroIdIsNoObject(): void
    {
        $columns = ReferenceColumns::fromFixedType('person', 'id_person');

        self::assertNull($columns->resolveIn([]));
        self::assertNull($columns->resolveIn([
            'id_person' => null,
        ]));
        self::assertNull($columns->resolveIn([
            'id_person' => '',
        ]));
        self::assertNull($columns->resolveIn([
            'id_person' => '0',
        ]));
        self::assertNull($columns->resolveIn([
            'id_person' => 0,
        ]));
        self::assertNull($columns->resolveIn([
            'id_person' => ['12'],
        ]));
    }

    public function testTheFallbackTakesOverWhenTheReferenceDoesNotResolve(): void
    {
        $columns = ReferenceColumns::fromFixedType('person', 'id_person')
            ->withFallback(ReferenceColumns::fromFixedType('contact', 'id_contact'));

        self::assertEquals(new Reference('person', '12'), $columns->resolveIn([
            'id_person' => '12',
            'id_contact' => '5',
        ]));
        self::assertEquals(new Reference('contact', '5'), $columns->resolveIn([
            'id_person' => '0',
            'id_contact' => '5',
        ]));
        self::assertNull($columns->resolveIn([
            'id_person' => '0',
            'id_contact' => '0',
        ]));
    }

    /**
     * An email belongs to a contact, who belongs to a person: the email takes the reference of the person.
     */
    public function testFollowingTheOwnerClimbsTheObservedLinks(): void
    {
        $generator = new Generator('test salt');
        $generator->remember(new Reference('email', '7'), ReferenceColumns::OWNER, new Reference('contact', '5'));
        $generator->remember(new Reference('contact', '5'), ReferenceColumns::OWNER, new Reference('person', '12'));
        $columns = ReferenceColumns::fromFixedType('email', 'id_email')->followingOwner();

        self::assertEquals(new Reference('person', '12'), $columns->resolveIn([
            'id_email' => '7',
        ], $generator));
        self::assertEquals(new Reference('email', '8'), $columns->resolveIn([
            'id_email' => '8',
        ], $generator), 'without owner, its own reference');
        self::assertEquals(new Reference('email', '7'), $columns->resolveIn([
            'id_email' => '7',
        ]), 'without generator, nothing to climb');
        self::assertEquals(new Reference('email', '7'), ReferenceColumns::fromFixedType('email', 'id_email')->resolveIn([
            'id_email' => '7',
        ], $generator), 'not asked to follow');
    }

    public function testClimbingStopsOnACycle(): void
    {
        $generator = new Generator('test salt');
        $generator->remember(new Reference('a', '1'), ReferenceColumns::OWNER, new Reference('b', '1'));
        $generator->remember(new Reference('b', '1'), ReferenceColumns::OWNER, new Reference('a', '1'));

        $reference = ReferenceColumns::fromFixedType('a', 'id')->followingOwner()->resolveIn([
            'id' => '1',
        ], $generator);

        self::assertInstanceOf(Reference::class, $reference);
        self::assertContains($reference->type, ['a', 'b']);
    }
}
