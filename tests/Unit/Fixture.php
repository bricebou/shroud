<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Generator;
use Shroud\Language\French;
use Shroud\Language\LanguageInterface;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Pseudonym\LastName;
use Shroud\Strategy\Value\Keep;

/**
 * Two tables that speak of the same person: the people, and their contact cards.
 */
final class Fixture
{
    public const string SALT = 'test salt';

    public static function declaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new Keep(),
                'name' => new FullName(),
                'email' => new Email(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            new Table('contacts', 'id_contact', [
                'id_contact' => new Keep(),
                'id_person' => new Keep(),
                'first_name' => new FirstName(),
                'last_name' => new LastName(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            Table::truncated('visits'),
        ]);
    }

    /**
     * A disguiser of the fixture, in French so that the diacritics show.
     */
    public static function disguiser(string $salt = self::SALT, LanguageInterface $language = new French()): Disguiser
    {
        return new Disguiser(self::declaration(), new Generator($salt, language: $language));
    }
}
