<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Generator;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Pseudonym\Street;
use Shroud\Strategy\Reference\OwnerLink;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Reference\RenumberedPolymorphicId;
use Shroud\Strategy\Value\Keep;

/**
 * A coordinate linked to a person through a chain of tables carries the pseudonym of that person.
 */
final class OwnershipTest extends TestCase
{
    public function testACoordinateFollowsItsOwnerUpToThePerson(): void
    {
        $result = $this->disguiser()->disguiseTables([
            'people' => [[
                'id_person' => '12',
                'name' => 'Zoé Kowalczyk',
                'email' => 'zoe@example-real.test',
            ]],
            'contacts' => [[
                'id_contact' => '5',
                'id_person' => '12',
                'name' => 'Zoé Kowalczyk',
            ]],
            // The first email belongs to the contact, hence to the person; the second to nobody.
            'email_links' => [[
                'id_email' => '7',
                'object_id' => '5',
                'object_type' => 'contact',
            ]],
            'emails' => [
                [
                    'id_email' => '7',
                    'email' => 'zoe.pro@example-real.test',
                ],
                [
                    'id_email' => '8',
                    'email' => 'orphan@example-real.test',
                ],
            ],
            'addresses' => [[
                'id_address' => '3',
                'street' => '1 rue de la Paix',
            ]],
        ]);

        self::assertSame($result['people'][0]['email'], $result['emails'][0]['email'], 'the linked email carries the pseudonym of the person');
        self::assertNotSame($result['people'][0]['email'], $result['emails'][1]['email'], 'an email without link keeps its own');
        self::assertSame($result['people'][0]['name'], $result['contacts'][0]['name']);
        self::assertNotSame('1 rue de la Paix', $result['addresses'][0]['street'], 'without observed owner, the reference stays its own');
    }

    public function testTheOwnerIsKnownWhateverTheOrderOfTheTables(): void
    {
        $result = $this->disguiser()->disguiseTables([
            'emails' => [[
                'id_email' => '7',
                'email' => 'zoe.pro@example-real.test',
            ]],
            'email_links' => [[
                'id_email' => '7',
                'object_id' => '5',
                'object_type' => 'contact',
            ]],
            'contacts' => [[
                'id_contact' => '5',
                'id_person' => '12',
                'name' => 'Zoé Kowalczyk',
            ]],
            'people' => [[
                'id_person' => '12',
                'name' => 'Zoé Kowalczyk',
                'email' => 'zoe@example-real.test',
            ]],
        ]);

        self::assertSame($result['people'][0]['email'], $result['emails'][0]['email']);
    }

    public function testTheLinkedIdsFollowTheRenumbering(): void
    {
        $generator = new Generator(Fixture::SALT);
        $result = new Disguiser($this->declaration(), $generator)->disguiseTables([
            'people' => [[
                'id_person' => '12',
                'name' => 'Zoé Kowalczyk',
                'email' => '',
            ]],
            'contacts' => [[
                'id_contact' => '5',
                'id_person' => '12',
                'name' => 'Zoé Kowalczyk',
            ]],
            'email_links' => [
                [
                    'id_email' => '7',
                    'object_id' => '5',
                    'object_type' => 'contact',
                ],
                [
                    'id_email' => '7',
                    'object_id' => '9',
                    'object_type' => 'article',
                ],
            ],
        ]);

        $person = (string) $generator->permutationFor('person')->apply(12);
        self::assertSame($person, $result['people'][0]['id_person']);
        self::assertSame($person, $result['contacts'][0]['id_person'], 'the foreign key follows');
        self::assertSame((string) $generator->permutationFor('contact')->apply(5), $result['email_links'][0]['object_id'], 'the polymorphic link follows the contact');
        self::assertSame('9', $result['email_links'][1]['object_id'], 'an article is not renumbered');
    }

    private function disguiser(): Disguiser
    {
        return new Disguiser($this->declaration(), new Generator(Fixture::SALT));
    }

    private function declaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new RenumberedId('person'),
                'name' => new FullName(),
                'email' => new Email(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            // A contact belongs to its person.
            new Table('contacts', 'id_contact', [
                'id_contact' => new RenumberedId('contact'),
                'id_person' => new OwnerLink(
                    ReferenceColumns::fromFixedType('contact', 'id_contact'),
                    ReferenceColumns::fromFixedType('person', 'id_person'),
                    new RenumberedId('person'),
                ),
                'name' => new FullName(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')->withFallback(ReferenceColumns::fromFixedType('contact', 'id_contact'))),
            // An email belongs to the object that the link table gives it.
            new Table('email_links', ['id_email', 'object_id', 'object_type'], [
                'id_email' => new Keep(),
                'object_id' => new RenumberedPolymorphicId('object_type'),
                'object_type' => new OwnerLink(
                    ReferenceColumns::fromFixedType('email', 'id_email'),
                    ReferenceColumns::fromTypeColumn('object_type', 'object_id'),
                ),
            ]),
            new Table('emails', 'id_email', [
                'id_email' => new Keep(),
                'email' => new Email(),
            ], ReferenceColumns::fromFixedType('email', 'id_email')->followingOwner()),
            new Table('addresses', 'id_address', [
                'id_address' => new Keep(),
                'street' => new Street(),
            ], ReferenceColumns::fromFixedType('address', 'id_address')->followingOwner()),
        ]);
    }
}
