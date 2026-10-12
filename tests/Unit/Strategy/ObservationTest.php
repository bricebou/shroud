<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Strategy\Nested\ByColumn;
use Shroud\Strategy\Nested\Json;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Observation;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Reference\OwnerLink;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\Hashed;
use Shroud\Strategy\Wrapper\WithoutEmails;

final class ObservationTest extends StrategyTestCase
{
    public function testAnObserverIsFoundAtAnyDepth(): void
    {
        self::assertTrue(Observation::observes(new FirstName()));
        self::assertTrue(Observation::observes(new ByColumn('key', [
            'first_name' => new FirstName(),
        ], new Keep())));
        self::assertTrue(Observation::observes(new Fallback(new Serialized(new PerKey([
            'name' => new FirstName(),
        ], new Keep())), new Keep())));
        self::assertTrue(Observation::observes(new WithoutEmails(new Json(new PerKey([], new FirstName())))));
        self::assertTrue(Observation::observes(new OwnerLink(ReferenceColumns::fromFixedType('a', 'a'), ReferenceColumns::fromFixedType('b', 'b'))));
    }

    public function testACompositeWithoutObserverDoesNotObserve(): void
    {
        self::assertFalse(Observation::observes(new Keep()));
        self::assertFalse(Observation::observes(new ByColumn('key', [
            'email' => new Email(),
        ], new Keep())));
        self::assertFalse(Observation::observes(new Fallback(new Serialized(new PerKey([], new Keep())), new Clear())));
        self::assertFalse(Observation::observes(new Hashed(new FirstName())), 'a digest says nothing of a first name');
        self::assertTrue(new Table('t', 'id', [
            'id' => new Keep(),
            'v' => new ByColumn('k', [
                'x' => new FirstName(),
            ], new Keep()),
        ])->hasObservers());
        self::assertSame(['v'], new Table('t', 'id', [
            'id' => new Keep(),
            'v' => new ByColumn('k', [
                'x' => new FirstName(),
            ], new Keep()),
        ])->observingColumns());
        self::assertFalse(new Table('t', 'id', [
            'id' => new Keep(),
            'v' => new ByColumn('k', [
                'x' => new Email(),
            ], new Keep()),
        ])->hasObservers());
    }

    /**
     * A key/value table holds the first name of a person under one key: the full name of the person takes as many first names.
     */
    public function testAFirstNameInsideAKeyValueTableIsObserved(): void
    {
        $generator = new Generator(self::SALT);
        $declaration = new Declaration([
            new Table('people', 'id', [
                'id' => new Keep(),
                'name' => new FullName(),
            ], ReferenceColumns::fromFixedType('person', 'id')),
            new Table('person_meta', 'meta_id', [
                'meta_id' => new Keep(),
                'person_id' => new Keep(),
                'meta_key' => new Keep(),
                'meta_value' => new ByColumn('meta_key', [
                    'first_name' => new FirstName(),
                ], new Keep()),
            ], ReferenceColumns::fromFixedType('person', 'person_id')),
        ]);

        $result = new Disguiser($declaration, $generator)->disguiseTables([
            'people' => [[
                'id' => '7',
                'name' => 'Jean Pierre Dupont',
            ]],
            'person_meta' => [
                [
                    'meta_id' => '1',
                    'person_id' => '7',
                    'meta_key' => 'first_name',
                    'meta_value' => 'Jean Pierre',
                ],
                [
                    'meta_id' => '2',
                    'person_id' => '7',
                    'meta_key' => 'nickname',
                    'meta_value' => 'jp jp jp',
                ],
            ],
        ]);

        self::assertSame(2, $generator->recall(new Reference('person', '7'), FirstName::WORD_COUNT), 'the nickname under another key is not observed');
        self::assertIsString($result['people'][0]['name']);
        self::assertIsString($result['person_meta'][0]['meta_value']);
        self::assertStringStartsWith($result['person_meta'][0]['meta_value'] . ' ', $result['people'][0]['name']);
    }

    /**
     * An order meta names its customer: an owner link inside the key/value table ties the order to its person.
     */
    public function testAnOwnerLinkInsideAKeyValueTableIsObserved(): void
    {
        $generator = new Generator(self::SALT);
        $declaration = new Declaration([
            new Table('people', 'id', [
                'id' => new Keep(),
                'email' => new Email(),
            ], ReferenceColumns::fromFixedType('person', 'id')),
            new Table('order_meta', 'meta_id', [
                'meta_id' => new Keep(),
                'order_id' => new Keep(),
                'meta_key' => new Keep(),
                'meta_value' => new ByColumn('meta_key', [
                    'customer' => new OwnerLink(ReferenceColumns::fromFixedType('order', 'order_id'), ReferenceColumns::fromFixedType('person', 'meta_value')),
                    'billing_email' => new Email(),
                ], new Keep()),
            ], ReferenceColumns::fromFixedType('order', 'order_id')->followingOwner()),
        ]);

        $result = new Disguiser($declaration, $generator)->disguiseTables([
            'order_meta' => [
                [
                    'meta_id' => '1',
                    'order_id' => '50',
                    'meta_key' => 'billing_email',
                    'meta_value' => 'zoe@example-real.test',
                ],
                [
                    'meta_id' => '2',
                    'order_id' => '50',
                    'meta_key' => 'customer',
                    'meta_value' => '7',
                ],
            ],
            'people' => [[
                'id' => '7',
                'email' => 'zoe@example-real.test',
            ]],
        ]);

        self::assertSame($result['people'][0]['email'], $result['order_meta'][0]['meta_value']);
        self::assertSame('7', $result['order_meta'][1]['meta_value'], 'the owner link keeps the value by default');
    }

    public function testABlobRelaysTheObservationToItsKeys(): void
    {
        $generator = new Generator(self::SALT);
        $context = self::context($generator);

        new Serialized(new PerKey([
            'first' => new FirstName(),
        ], new Keep()))->observe(serialize([
            'first' => 'Jean Pierre',
            'other' => 'a b c',
        ]), $context);
        self::assertSame(2, $generator->recall(new Reference('person', 42), FirstName::WORD_COUNT));

        new Json(new PerKey([
            'first' => new FirstName(),
        ], new Keep()))->observe('{"first":"Anne"}', $context);
        self::assertSame(1, $generator->recall(new Reference('person', 42), FirstName::WORD_COUNT));

        new Json(new PerKey([
            'first' => new FirstName(),
        ], new Keep()))->observe('not json', $context);
        new Serialized(new PerKey([
            'first' => new FirstName(),
        ], new Keep()))->observe('not serialized', $context);
        self::assertSame(1, $generator->recall(new Reference('person', 42), FirstName::WORD_COUNT), 'an unreadable blob is not observed');
    }
}
