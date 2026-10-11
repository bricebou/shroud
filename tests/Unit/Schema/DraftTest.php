<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Platform\Generic;
use Shroud\Schema\Baseline;
use Shroud\Schema\Coverage;
use Shroud\Schema\Draft;
use Shroud\Schema\DraftRule;
use Shroud\Schema\Glossary\English;
use Shroud\Schema\Glossary\French;
use Shroud\Schema\Schema;
use Shroud\Schema\TableSchema;
use Shroud\Strategy\Value\Keep;
use Shroud\Tests\Unit\Platform\TestPlatform;

final class DraftTest extends TestCase
{
    private const string CONTACTS = <<<'PHP'
    new Table('contacts', 'id_contact', [
        'id_contact' => new Keep(),
        'id_person' => new RenumberedId('person'),
        'section_id' => new Keep(),
        'first_name' => new FirstName(),
        'nom' => new FullName(), // @todo review
        'email' => new Email(),
        'phone' => new ShapedDigits(keepDiallingCode: true),
        'address' => new Street(),
        'postal_code' => new PostalCode(),
        'city' => new City(),
        'country' => new Keep(), // @todo review
        'company' => new Organization(),
        'website' => new Url(keepHost: false), // @todo review
        'password' => new Clear(),
        'remember_token' => new Clear(),
        'birth_date' => new ShiftedDate(),
        'notes' => new ShapedText(), // @todo review
        'active' => new Keep(),
        'status' => new Keep(),
        'updated_at' => new Keep(),
    ], ReferenceColumns::fromFixedType('contact', 'id_contact')),
    PHP;

    public function testATableIsDraftedColumnByColumn(): void
    {
        $table = new TableSchema('contacts', ['id_contact'], [
            'id_contact' => 'bigint(21)',
            'id_person' => 'bigint(21)',
            'section_id' => 'bigint(21)',
            'first_name' => 'text',
            'nom' => 'text',
            'email' => 'text',
            'phone' => 'varchar(30)',
            'address' => 'text',
            'postal_code' => 'varchar(10)',
            'city' => 'text',
            'country' => 'text',
            'company' => 'text',
            'website' => 'text',
            'password' => 'tinytext',
            'remember_token' => 'varchar(100)',
            'birth_date' => 'date',
            'notes' => 'longtext',
            'active' => 'tinyint(1)',
            'status' => 'varchar(10)',
            'updated_at' => 'timestamp',
        ]);

        self::assertSame(self::CONTACTS, new Draft(new TestPlatform(), new English(), new French())->table($table, new TestPlatform()->bareDeclaration()));
        self::assertStringContainsString("'nom' => new ShapedText(), // @todo review", new Draft(new TestPlatform())->table($table, new Declaration([])), 'with the glossaries of the platform, English alone, nom is a free text');
    }

    public function testALinkTableFollowsThePlatformRulesAndGetsNoReference(): void
    {
        $table = new TableSchema('contact_links', ['id_contact', 'object_id', 'object_type'], [
            'id_contact' => 'bigint(21)',
            'object_id' => 'bigint(21)',
            'object_type' => 'varchar(25)',
            'seen' => 'varchar(6)',
        ]);

        $code = new Draft(new TestPlatform())->table($table, new Declaration([]));

        self::assertStringContainsString("new Table('contact_links', ['id_contact', 'object_id', 'object_type'], [", $code);
        self::assertStringContainsString("'object_id' => new RenumberedPolymorphicId('object_type'),", $code);
        self::assertStringContainsString("'object_type' => new Keep(),", $code);
        self::assertStringContainsString("'seen' => new ShapedText(), // @todo review", $code);
        self::assertStringEndsWith(']),', $code);
        self::assertStringNotContainsString('ReferenceColumns', $code);
    }

    public function testThePlatformRuleAppliesOnlyWithItsSibling(): void
    {
        $alone = new TableSchema('objects', ['object_id'], [
            'object_id' => 'bigint(21)',
        ]);

        self::assertStringContainsString("'object_id' => new Keep(),", new Draft(new TestPlatform())->table($alone, new Declaration([])));
        self::assertStringContainsString("'object_id' => new RenumberedId('object'),", new Draft(new Generic())->table($alone, new Declaration([
            new Table('objects', 'object_id', [
                'object_id' => new \Shroud\Strategy\Reference\RenumberedId('object'),
            ])])));
        self::assertTrue(new DraftRule('/^x$/', 'new Keep()', sibling: 'y')->matches('X', new TableSchema('t', [], [
            'x' => 'int',
            'y' => 'int',
        ])));
    }

    public function testColumnsAloneAreDraftedForATableAlreadyDeclared(): void
    {
        $table = new TableSchema('people', ['id_person'], [
            'id_person' => 'bigint(21)',
            'institution' => 'text',
            'member' => 'varchar(3)',
        ]);

        $code = new Draft(new Generic())->columns($table, ['institution', 'member'], new Declaration([]));

        self::assertSame("    'institution' => new Organization(),\n    'member' => new ShapedText(), // @todo review", $code);
    }

    private const string FILE = <<<'PHP'
    <?php

    /**
     * Declaration draft suggested by shroud-diff on 2026-10-12 for test 1.0.0.
     *
     * This file returns a function that receives the declaration of the site and returns it completed.
     * Read it column by column, the todos first, then fold it into shroud.php: either by copying
     * the tables and columns below into it, or by having it apply this file:
     *     $declaration = (require __DIR__ . '/shroud.suggestions.php')($declaration);
     *
     * @todo Remove from the declaration, named without existing in the base:
     *     old_table (whole table)
     *     people: former
     */

    declare(strict_types=1);

    use Shroud\Declaration\Declaration;
    use Shroud\Declaration\ReferenceColumns;
    use Shroud\Declaration\Table;
    use Shroud\Strategy\Pseudonym\Email;
    use Shroud\Strategy\Pseudonym\Organization;
    use Shroud\Strategy\Value\Clear;
    use Shroud\Strategy\Value\Keep;

    return static fn (Declaration $declaration): Declaration => $declaration->with(
        new Table('contacts', 'id_contact', [
            'id_contact' => new Keep(),
            'email' => new Email(),
        ], ReferenceColumns::fromFixedType('contact', 'id_contact')),
        $declaration->getTable('people')->withColumns([
            'institution' => new Organization(),
            'recovery_code' => new Clear(), // test 1.0.0, not declared by the bare declaration
        ]),
    );

    PHP;

    /**
     * The file returns a function that completes the declaration, runs as it is, and its header
     * recalls what the declaration names without the base having it.
     */
    public function testTheDraftFileCompletesTheDeclaration(): void
    {
        $bare = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
                'recovery_code' => 'text',
            ])]);
        $site = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
                'recovery_code' => 'text',
                'institution' => 'text',
            ]),
            new TableSchema('contacts', ['id_contact'], [
                'id_contact' => 'bigint(21)',
                'email' => 'text',
            ]),
        ]);
        $declaration = new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new Keep(),
                'former' => new Keep(),
            ]),
            new Table('old_table', 'id_old', [
                'id_old' => new Keep(),
            ]),
        ]);
        // The bare declaration of the baseline declares nothing: what the site declares in excess is indeed to remove.
        $coverage = Coverage::compare($site, $declaration, new Baseline('test', '1.0.0', $bare, new Declaration([])));

        $file = new Draft(new TestPlatform())->file($coverage, $site, $declaration, '2026-10-12');

        self::assertSame(self::FILE, $file);

        $path = tempnam(sys_get_temp_dir(), 'shroud');
        self::assertNotFalse($path);
        file_put_contents($path, $file);
        $completed = (require $path)($declaration);
        unlink($path);
        self::assertInstanceOf(Declaration::class, $completed);
        self::assertTrue($completed->has('contacts'));
        self::assertSame(['id_person', 'former', 'institution', 'recovery_code'], array_keys($completed->getTable('people')->columns));
    }
}
