<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Nested;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Exception\InvalidValueException;
use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Strategy\Nested\ByColumn;
use Shroud\Strategy\Nested\Delimited;
use Shroud\Strategy\Nested\Json;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Constant;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\WithoutEmails;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class NestedStrategiesTest extends StrategyTestCase
{
    public function testPerKeyAppliesTheStrategyOfEachKeyAndTheOthersToTheRest(): void
    {
        $strategy = new PerKey([
            'secret' => new Clear(),
            0 => new Constant('first'),
        ], new Keep());

        self::assertSame(
            [
                'secret' => '',
                'colour' => 9,
                0 => 'first',
                1 => 'second',
            ],
            self::apply($strategy, [
                'secret' => 'x',
                'colour' => 9,
                0 => 'one',
                1 => 'second',
            ]),
        );
        self::assertNull(self::apply($strategy, value: null));
    }

    public function testPerKeyRefusesAValueThatIsNotAnArray(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('people.value: array expected, string given');

        self::apply(new PerKey([], new Keep()), 'text');
    }

    public function testSerializedOnlyTouchesTheDeclaredKeys(): void
    {
        $preferences = serialize([
            'colour' => 9,
            'digest' => [
                'zoe@example-real.test' => 'zoe@example-real.test:realm:abcd',
            ],
        ]);

        $result = self::applyToString(new Serialized(new PerKey([
            'digest' => new Clear(),
        ], new Keep())), $preferences);

        self::assertSame(serialize([
            'colour' => 9,
            'digest' => [],
        ]), $result);
    }

    public function testSerializedReadsTheSerializedFalse(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('array expected, bool given');

        self::apply(new Serialized(new PerKey([], new Keep())), serialize(value: false));
    }

    public function testSerializedRefusesAnInvalidSerialization(): void
    {
        foreach (['a:1:{truncated', 'not a blob'] as $value) {
            try {
                self::apply(new Serialized(new PerKey([], new Keep())), $value);
                self::fail('refused: ' . $value);
            } catch (InvalidValueException $exception) {
                self::assertSame('invalid PHP serialization', $exception->reason);
            }
        }
    }

    public function testSerializedRestoresNoObject(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('array expected, __PHP_Incomplete_Class given');

        self::apply(new Serialized(new PerKey([], new Keep())), serialize(new \ArrayObject()));
    }

    /**
     * A column the application may or may not have serialized: every kind of value finds its way.
     */
    public function testSerializedWithAPlainStrategyTakesWhatIsNotASerializedArray(): void
    {
        $maybe = new Serialized(new PerKey([
            'email' => new Constant('hidden'),
        ], new Keep()), new Constant('plain'));

        self::assertSame(serialize([
            'email' => 'hidden',
            'id' => 7,
        ]), self::apply($maybe, serialize([
            'email' => 'zoe@example.test',
            'id' => 7,
        ])), 'an array, key by key');
        self::assertSame(serialize('plain'), self::apply($maybe, serialize('zoe@example.test')), 'a string, serialized again');
        self::assertSame('plain', self::apply($maybe, 'zoe@example.test'), 'a value that is not serialized');
        self::assertSame('plain', self::apply($maybe, 'a:2:{s:5:"email";'), 'a corrupted blob, never kept');
        foreach ([serialize(42), serialize(value: true), serialize(value: false), serialize(value: null), serialize(new \ArrayObject())] as $kept) {
            self::assertSame($kept, self::apply($maybe, $kept), 'a number, a boolean, a null, an object: kept');
        }
        self::assertNull(self::apply($maybe, value: null));
    }

    public function testSerializedRelaysTheObservationToThePlainStrategy(): void
    {
        $generator = new Generator(self::SALT);
        $context = self::context($generator);
        $maybe = new Serialized(new PerKey([], new Keep()), new FirstName());

        $maybe->observe(serialize('Jean Pierre'), $context);

        self::assertSame(2, $generator->recall(new Reference('person', 42), FirstName::WORD_COUNT));
    }

    public function testJsonAppliesTheStrategiesPerKey(): void
    {
        $result = json_decode(self::applyToString(new Json(new PerKey([
            'email' => new Email(),
        ], new Keep())), '{"email":"x@example-real.test","lang":"fr"}'), associative: true);

        self::assertIsArray($result);
        self::assertSame('fr', $result['lang']);
        self::assertIsString($result['email']);
        self::assertMatchesRegularExpression('/@example\.(org|com|net)$/', $result['email']);
    }

    public function testJsonKeepsItsShape(): void
    {
        $keep = new Json(new PerKey([], new Keep()));

        self::assertSame('{}', self::apply($keep, '{}'), 'an empty object stays an object');
        self::assertSame('[]', self::apply($keep, '[]'));
        self::assertSame('{"a":{},"b":[],"c":[{}]}', self::apply($keep, '{"a":{},"b":[],"c":[{}]}'), 'at any depth');
        self::assertSame('{"0":"x","1":{"0":"y"}}', self::apply($keep, '{"0":"x","1":{"0":"y"}}'), 'an object keyed 0, 1, 2… is no list');
        self::assertSame('{"":1}', self::apply($keep, '{"":1}'));
        self::assertSame('{"ratio":1.0,"path":"a/b","name":"Zoé"}', self::apply($keep, '{"ratio":1.0,"path":"a\/b","name":"Zoé"}'));
    }

    public function testJsonRefusesAnInvalidDocument(): void
    {
        try {
            self::apply(new Json(new PerKey([], new Keep())), 'a:1:{s:1:"a";i:1;}');
            self::fail('refused');
        } catch (InvalidValueException $exception) {
            self::assertSame('invalid JSON: Syntax error', $exception->reason);
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    /**
     * An old base may carry something else than a blob in the column: the fallback takes the value
     * as it is, and a readable value still follows the normal path.
     */
    public function testAnInvalidBlobFallsBack(): void
    {
        $serialized = new Fallback(new Serialized(new PerKey([], new Keep())), new Clear());
        self::assertSame('', self::apply($serialized, 'not a blob'));
        self::assertSame(serialize([
            'a' => 1,
        ]), self::apply($serialized, serialize([
            'a' => 1,
        ])));

        $json = new Fallback(new Json(new PerKey([], new Keep())), new Constant('{}'));
        self::assertSame('{}', self::apply($json, 'a:1:{s:1:"a";i:1;}'));
        self::assertSame('{"a":1}', self::apply($json, '{"a":1}'));
    }

    /**
     * A refusal inside the blob refuses the whole blob: the fallback goes on the key to keep the rest.
     */
    public function testARefusalInsideTheBlobIsHandledOnItsKey(): void
    {
        $value = '{"phone":"call me","lang":"fr"}';

        self::assertSame('{}', self::apply(new Fallback(new Json(new PerKey([
            'phone' => new ShapedDigits(),
        ], new Keep())), new Constant('{}')), $value));
        self::assertSame(
            '{"phone":"","lang":"fr"}',
            self::apply(new Json(new PerKey([
                'phone' => new Fallback(new ShapedDigits(), new Clear()),
            ], new Keep())), $value),
        );
    }

    public function testByColumnPicksTheStrategyFromAnotherColumnOfTheRow(): void
    {
        $value = new ByColumn('name', [
            'admin_email' => new Email(),
            '18' => new Clear(),
        ], new Keep());

        self::assertMatchesRegularExpression('/@example\.(org|com|net)$/', self::applyToString($value, 'x@example-real.test', self::context(row: [
            'name' => 'admin_email',
        ])));
        self::assertSame('Lyon', self::apply($value, 'Lyon', self::context(row: [
            'name' => 'city',
        ])));
        self::assertSame('', self::apply($value, 'secret', self::context(row: [
            'name' => 18,
        ])), 'an integer finds its case');
        self::assertSame('Lyon', self::apply($value, 'Lyon', self::context(row: [])), 'a missing column takes the default');
        self::assertSame('Lyon', self::apply($value, 'Lyon', self::context(row: [
            'name' => null,
        ])));
    }

    public function testPerKeyCanWalkNestedArrays(): void
    {
        $value = [
            'email' => 'a@example-real.test',
            'contacts' => [
                'main' => [
                    'email' => 'b@example-real.test',
                    'note' => 'Write to c@example-real.test',
                ],
            ],
        ];

        $shallow = self::apply(new PerKey([
            'email' => new Clear(),
        ], new WithoutEmails()), $value);
        $deep = self::apply(new PerKey([
            'email' => new Clear(),
        ], new WithoutEmails(), deep: true), $value);

        self::assertIsArray($shallow);
        self::assertSame($value['contacts'], $shallow['contacts'], 'shallow: the nested array passes through the common strategy untouched');
        self::assertIsArray($deep);
        self::assertSame('', $deep['email']);
        self::assertIsArray($deep['contacts']);
        self::assertIsArray($deep['contacts']['main']);
        self::assertSame('', $deep['contacts']['main']['email'], 'deep: the declared key applies at any depth');
        self::assertIsString($deep['contacts']['main']['note']);
        self::assertStringNotContainsString('c@example-real.test', $deep['contacts']['main']['note']);
    }

    public function testDelimitedAppliesTheStrategyToEachItemAndKeepsTheShape(): void
    {
        $generator = new Generator(self::SALT);
        $declaration = new Declaration([
            new Table('posts', 'id', [
                'id' => new RenumberedId('post'),
            ])]);
        $context = self::context($generator, declaration: $declaration);
        $permute = static fn (int $id): int => $generator->permutationFor('post')->apply($id);

        self::assertSame(sprintf('%d,%d,%d', $permute(12), $permute(13), $permute(14)), self::apply(new Delimited(',', new RenumberedId('post')), '12,13,14', $context));
        self::assertSame(sprintf('%d, %d,', $permute(12), $permute(13)), self::apply(new Delimited(',', new RenumberedId('post')), '12, 13,', $context), 'blanks and empty items stay');
        self::assertSame((string) $permute(25), self::apply(new Delimited(',', new RenumberedId('post')), '25', $context), 'a single id is a list of one');
        self::assertSame('', self::apply(new Delimited(',', new RenumberedId('post')), '', $context));
    }

    public function testDelimitedRefusesAnItemItsStrategyRefuses(): void
    {
        $this->expectException(InvalidValueException::class);

        self::apply(new Delimited(',', new RenumberedId('post')), '12,abc');
    }

    public function testDelimitedNeedsASeparator(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new Delimited('', new Keep());
    }
}
