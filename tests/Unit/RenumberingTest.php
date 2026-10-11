<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Generator;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Reference\RenumberedPolymorphicId;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedText;
use Shroud\Tests\Unit\Platform\TestPlatform;

/**
 * Declaring RenumberedId somewhere renumbers the type everywhere: keys, links, blobs and embedded references.
 */
final class RenumberingTest extends TestCase
{
    public function testTheIdsStayConsistentAcrossTablesBlobsAndTexts(): void
    {
        $generator = new Generator(Fixture::SALT, platform: new TestPlatform());
        $person = $generator->permutationFor('person')->apply(4242);
        $follower = $generator->permutationFor('person')->apply(4343);

        $result = new Disguiser($this->declaration(), $generator)->disguiseTables([
            'people' => [[
                'id_person' => '4242',
                'name' => 'Zoé Kowalczyk',
                'bio' => 'Follows #person:4343 and #article:12',
                'settings' => serialize([
                    'id_follower' => 4343,
                    'colour' => 9,
                ]),
            ]],
            'contacts' => [[
                'id_contact' => '777',
                'id_person' => '4242',
            ]],
            'links' => [
                [
                    'id_tag' => '387',
                    'object_id' => '4242',
                    'object_type' => 'person',
                ],
                [
                    'id_tag' => '387',
                    'object_id' => '276',
                    'object_type' => 'article',
                ],
            ],
        ]);

        self::assertSame((string) $person, $result['people'][0]['id_person']);
        self::assertSame((string) $person, $result['contacts'][0]['id_person']);
        self::assertSame((string) $person, $result['links'][0]['object_id'], 'the polymorphic link follows the person');
        self::assertSame('276', $result['links'][1]['object_id'], 'an article is not renumbered');
        self::assertNotSame('777', $result['contacts'][0]['id_contact']);

        self::assertIsString($result['people'][0]['settings']);
        $settings = unserialize($result['people'][0]['settings']);
        self::assertIsArray($settings);
        self::assertSame($follower, $settings['id_follower'], 'the id inside the blob follows');
        self::assertSame(9, $settings['colour']);

        self::assertIsString($result['people'][0]['bio']);
        self::assertStringContainsString("#person:{$follower}", $result['people'][0]['bio'], 'the reference embedded in the text follows');
        self::assertStringContainsString('#article:12', $result['people'][0]['bio']);
    }

    /**
     * A pass on the fly over a live base keeps its ids: what the site reads back by id must land on the same object.
     */
    public function testWithoutRenumberingTheIdsStayWhileTheNamesChange(): void
    {
        $generator = new Generator(Fixture::SALT, renumbering: false, platform: new TestPlatform());

        $result = new Disguiser($this->declaration(), $generator)->disguiseTables([
            'people' => [[
                'id_person' => '4242',
                'name' => 'Zoé Kowalczyk',
                'bio' => 'Follows #person:4343',
                'settings' => serialize([
                    'id_follower' => 4343,
                ]),
            ]],
            'contacts' => [[
                'id_contact' => '777',
                'id_person' => '4242',
            ]],
            'links' => [[
                'id_tag' => '387',
                'object_id' => '4242',
                'object_type' => 'person',
            ]],
        ]);

        self::assertSame('4242', $result['people'][0]['id_person']);
        self::assertSame('4242', $result['contacts'][0]['id_person']);
        self::assertSame('777', $result['contacts'][0]['id_contact']);
        self::assertSame('4242', $result['links'][0]['object_id']);
        self::assertIsString($result['people'][0]['settings']);
        self::assertSame([
            'id_follower' => 4343,
        ], unserialize($result['people'][0]['settings']));
        self::assertIsString($result['people'][0]['bio']);
        self::assertStringContainsString('#person:4343', $result['people'][0]['bio'], 'the text changes, the reference keeps its number');
        self::assertNotSame('Zoé Kowalczyk', $result['people'][0]['name']);
        self::assertSame(12, $generator->permutationFor('person')->invert(12));
    }

    private function declaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new RenumberedId('person'),
                'name' => new FullName(),
                'bio' => new ShapedText(),
                'settings' => new Serialized(new PerKey([
                    'id_follower' => new RenumberedId('person'),
                ], new Keep())),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            new Table('contacts', 'id_contact', [
                'id_contact' => new RenumberedId('contact'),
                'id_person' => new RenumberedId('person'),
            ]),
            new Table('links', ['id_tag', 'object_id', 'object_type'], [
                'id_tag' => new Keep(),
                'object_id' => new RenumberedPolymorphicId('object_type'),
                'object_type' => new Keep(),
            ]),
        ]);
    }
}
