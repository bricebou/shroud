<?php

/**
 * The configuration file of a project, to copy at its root under the name `shroud.php` and to version.
 *
 * The commands read it in the current directory by default; see docs/commands.md. This example
 * runs on the generic platform, with a declaration written by hand; a project on a platform
 * package starts from `$platform->bareDeclaration()` instead.
 */

declare(strict_types=1);

use Shroud\Configuration;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Language\French;
use Shroud\Platform\Generic;
use Shroud\Schema\Glossary\English;
use Shroud\Schema\Glossary\French as FrenchGlossary;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Pseudonym\LastName;
use Shroud\Strategy\Reference\OwnerLink;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Value\ShapedText;
use Shroud\Strategy\Value\ShiftedDate;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\WithoutEmails;

$platform = new Generic();

// The tables of the site: persons, their contact cards, and tables of mass data kept empty.
$declaration = new Declaration([
    new Table('people', 'id_person', [
        'id_person' => new RenumberedId('person'),
        'name' => new FullName(),
        'email' => new Email(),
        'password' => new Clear(),
        'bio' => new WithoutEmails(new ShapedText()),
        'created_at' => new ShiftedDate(),
        'status' => new Keep(),
    ], ReferenceColumns::fromFixedType('person', 'id_person')),
    // A contact card belongs to its person: its names follow the pseudonym of the person.
    new Table('contacts', 'id_contact', [
        'id_contact' => new RenumberedId('contact'),
        'id_person' => new OwnerLink(
            ReferenceColumns::fromFixedType('contact', 'id_contact'),
            ReferenceColumns::fromFixedType('person', 'id_person'),
            new RenumberedId('person'),
        ),
        'first_name' => new FirstName(),
        'last_name' => new LastName(),
        // An old base carries letters in some phone numbers: those are cleared.
        'phone' => new Fallback(new ShapedDigits(keepDiallingCode: true), new Clear()),
    ], ReferenceColumns::fromFixedType('person', 'id_person')->withFallback(ReferenceColumns::fromFixedType('contact', 'id_contact'))),
    Table::truncated('visits'),
    Table::truncated('search_log'),
]);

// A local file, not versioned, wins table by table: to keep a table of statistics on one machine, for instance.
if (is_file(__DIR__ . '/shroud.local.php')) {
    $local = require __DIR__ . '/shroud.local.php';
    if ($local instanceof Declaration) {
        $declaration = $declaration->merge($local);
    }
}

return new Configuration(
    declaration: $declaration,
    salt: 'a salt of the project, chosen once and for all',
    locale: 'fr_FR',
    language: new French(),
    platform: $platform,
    glossaries: [new FrenchGlossary(), new English()],
);
