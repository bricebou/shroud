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
        'phone' => new Phone(),
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

    /**
     * A WordPress-like schema: keys of key/value tables, prefixes that repeat the table, numbers named like texts.
     */
    public function testTheShapesOfAWordPressSchemaAreRecognised(): void
    {
        $users = new TableSchema('wp_users', ['ID'], [
            'ID' => 'bigint(20) unsigned',
            'user_login' => 'varchar(60)',
            'user_pass' => 'varchar(255)',
            'user_nicename' => 'varchar(50)',
            'user_email' => 'varchar(100)',
            'user_url' => 'varchar(100)',
            'user_activation_key' => 'varchar(255)',
            'user_status' => 'int(11)',
            'display_name' => 'varchar(250)',
        ]);
        $meta = new TableSchema('wp_usermeta', ['umeta_id'], [
            'umeta_id' => 'bigint(20) unsigned',
            'meta_key' => 'varchar(255)',
            'meta_value' => 'longtext',
        ]);
        $links = new TableSchema('wp_links', ['link_id'], [
            'link_id' => 'bigint(20) unsigned',
            'link_url' => 'varchar(255)',
            'link_name' => 'varchar(255)',
            'link_owner' => 'bigint(20) unsigned',
            'link_rating' => 'int(11)',
            'link_updated' => 'datetime',
            'link_visible' => 'varchar(20)',
        ]);
        $orders = new TableSchema('wp_wc_orders', ['id'], [
            'id' => 'bigint(20) unsigned',
            'post_status' => 'varchar(20)',
            'billing_email' => 'varchar(320)',
            'ip_address' => 'varchar(100)',
            'user_agent' => 'text',
            'transaction_id' => 'varchar(100)',
            'new_order_email_sent' => 'tinyint(1)',
        ]);
        $draft = new Draft(new Generic());
        $declaration = new Declaration([]);

        $code = $draft->table($users, $declaration);
        self::assertStringContainsString("'user_login' => new Login(),", $code);
        self::assertStringContainsString("'user_pass' => new Clear(),", $code);
        self::assertStringContainsString("'user_nicename' => new Login(),", $code);
        self::assertStringContainsString("'user_email' => new Email(),\n", $code, 'sure: the table says whose email it is');
        self::assertStringContainsString("'user_url' => new Url(keepHost: false), // @todo review", $code);
        self::assertStringContainsString("'user_activation_key' => new Clear(),", $code);
        self::assertStringContainsString("'user_status' => new Keep(),", $code);
        self::assertStringContainsString("'display_name' => new FullName(), // @todo review", $code);
        self::assertStringEndsWith("], ReferenceColumns::fromFixedType('user', 'ID')),", $code);

        $code = $draft->table($meta, $declaration);
        self::assertStringContainsString("'meta_key' => new Keep(),", $code);
        self::assertStringContainsString("'meta_value' => new ByColumn('meta_key', [], new Fallback(new Serialized(new PerKey([], new Keep())), new Keep())), // @todo review", $code);

        $code = $draft->table($links, $declaration);
        self::assertStringContainsString("'link_url' => new Url(keepHost: false), // @todo review", $code);
        self::assertStringContainsString("'link_owner' => new Keep(),", $code);
        self::assertStringContainsString("'link_rating' => new Keep(),", $code);
        self::assertStringContainsString("'link_updated' => new Keep(),", $code);
        self::assertStringNotContainsString("'link_visible' => new Url", $code);
        self::assertStringNotContainsString("'link_name' => new FullName", $code, 'the name of a link is not that of a person');

        $code = $draft->table($orders, $declaration);
        self::assertStringContainsString("'post_status' => new Keep(),", $code);
        self::assertStringContainsString("'billing_email' => new Email(), // @todo review", $code);
        self::assertStringContainsString("'ip_address' => new WithoutIp(),", $code);
        self::assertStringContainsString("'user_agent' => new Clear(),", $code);
        self::assertStringContainsString("'transaction_id' => new Keep(),", $code, 'an id shape wins, the glossary does not see it');
        self::assertStringContainsString("'new_order_email_sent' => new Keep(),", $code, 'a number takes no text strategy');
        self::assertStringEndsWith("], ReferenceColumns::fromFixedType('order', 'id')),", $code);
    }
}
