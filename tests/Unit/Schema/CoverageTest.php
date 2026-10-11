<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Schema\Baseline;
use Shroud\Schema\Coverage;
use Shroud\Schema\Schema;
use Shroud\Schema\TableSchema;
use Shroud\Strategy\Value\Keep;
use Shroud\Tests\Unit\Fixture;
use Shroud\Tests\Unit\Platform\TestPlatform;

final class CoverageTest extends TestCase
{
    public function testADeclarationThatMatchesTheSchemaIsComplete(): void
    {
        $schema = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
                'name' => 'text',
                'email' => 'text',
            ]),
            new TableSchema('contacts', ['id_contact'], [
                'id_contact' => 'bigint(21)',
                'id_person' => 'bigint(21)',
                'first_name' => 'text',
                'last_name' => 'text',
            ]),
        ]);

        $coverage = Coverage::compare($schema, Fixture::declaration());

        self::assertTrue($coverage->isComplete());
        self::assertFalse($coverage->hasUndeclared());
        self::assertSame(['Complete declaration: every table and every column of the base is declared, and nothing more.'], $coverage->report());
        self::assertSame('Summary: complete declaration.', $coverage->summary());
    }

    public function testEveryGapIsReportedBothWays(): void
    {
        $schema = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
                'name' => 'text',
                'email' => 'text',
                'phone' => 'text',
                'city' => 'text',
            ]),
            new TableSchema('visits', ['date'], [
                'date' => 'date',
                'visits' => 'int(11)',
            ]),
            new TableSchema('groups', ['id_group'], [
                'id_group' => 'bigint(21)',
            ]),
        ]);
        $declaration = Fixture::declaration()->with(
            Table::truncated('referers'),
            new Table('contacts', 'id_contact', [
                'id_contact' => new Keep(),
                'id_person' => new Keep(),
                'first_name' => new Keep(),
                'last_name' => new Keep(),
            ]),
        );

        $coverage = Coverage::compare($schema, $declaration);

        self::assertFalse($coverage->isComplete());
        self::assertTrue($coverage->hasUndeclared());
        self::assertSame(['groups'], $coverage->undeclaredTables);
        self::assertSame([
            'people' => ['phone', 'city'],
        ], $coverage->undeclaredColumns);
        self::assertSame(['contacts'], $coverage->missingTables, 'the truncated table absent from the base does not count');
        self::assertSame([], $coverage->missingColumns);
        self::assertSame([
            '1. Specific to the site: to declare',
            '   The base carries this, which the declaration ignores; without a baseline, nothing tells whether it belongs to the site or to the platform.',
            '   The draft at the end of the output declares it.',
            '',
            '     groups    whole table',
            '     people    phone, city',
            '',
            '2. Declared without existing in the base: to remove from the declaration',
            '   The declaration names this, which the base does not have.',
            '   Remove it, unless the base is incomplete; the draft recalls it as a todo.',
            '',
            '     contacts  whole table',
            '',
            'Summary: 3 to declare, 1 to remove.',
        ], $coverage->report());
    }

    public function testADeclaredColumnTheBaseLostIsReported(): void
    {
        $schema = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
                'name' => 'text',
            ])]);
        $declaration = new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new Keep(),
                'name' => new Keep(),
                'email' => new Keep(),
            ])]);

        $coverage = Coverage::compare($schema, $declaration);

        self::assertSame([
            'people' => ['email'],
        ], $coverage->missingColumns);
        self::assertFalse($coverage->hasUndeclared());
    }

    /**
     * The site runs version 1.0.0, the bare declaration was written for 2.0.0: only what is specific
     * to the site is to declare, the version gap is named apart.
     */
    public function testABaselineSeparatesTheSiteFromTheVersionGapOfThePlatform(): void
    {
        $platform = new TestPlatform();
        // The site: the bare 1.0.0 (people with a legacy column), plus a table and a column of its own.
        $site = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
                'name' => 'text',
                'email' => 'text',
                'legacy' => 'text',
                'institution' => 'text',
            ]),
            new TableSchema('newsletters', ['id_newsletter'], [
                'id_newsletter' => 'bigint(21)',
            ]),
        ]);
        // The bare declaration, written for 2.0.0: no legacy column, a contacts table.
        $declaration = $platform->bareDeclaration()->with(
            new Table('contacts', 'id_contact', [
                'id_contact' => new Keep(),
                'id_person' => new Keep(),
                'first_name' => new Keep(),
                'last_name' => new Keep(),
            ]),
        );
        $baseline = new Baseline('test', '1.0.0', $platform->frozenSchemas()->load('1.0.0'), $declaration);

        $coverage = Coverage::compare($site, $declaration, $baseline);

        self::assertSame(['newsletters'], $coverage->undeclaredTables);
        self::assertSame([
            'people' => ['institution'],
        ], $coverage->undeclaredColumns);
        self::assertSame([], $coverage->platformUndeclaredTables);
        self::assertSame([
            'people' => ['legacy'],
        ], $coverage->platformUndeclaredColumns);
        self::assertSame(['contacts'], $coverage->otherVersionTables);
        self::assertSame([], $coverage->otherVersionColumns);
        self::assertSame([], $coverage->missingTables);
        self::assertSame([], $coverage->missingColumns);
        self::assertFalse($coverage->isComplete());
        self::assertSame([
            '1. Specific to the site: to declare',
            '   The base carries this, which a bare test 1.0.0 does not have: extensions, added columns.',
            '   The draft at the end of the output declares it.',
            '',
            '     newsletters  whole table',
            '     people       institution',
            '',
            '2. Missing from the bare declaration of the platform: to declare, and to report',
            '   A bare test 1.0.0 carries this, which the bare declaration of the platform does not declare.',
            '   The draft at the end of the output declares it too, until the platform package is updated; report it there.',
            '',
            '     people       legacy',
            '',
            '3. No effect: declared by the bare declaration of the platform for another version',
            '   The bare declaration of the platform declares this for another version than 1.0.0, and the base does not have it.',
            '   A declared column that the base does not have hinders neither the dump nor the transformation: nothing to do.',
            '',
            '     contacts     whole table',
            '',
            'Summary: 3 to declare, 1 with no effect.',
        ], $coverage->report());
    }

    public function testTheVersionGapAloneLeavesTheDeclarationComplete(): void
    {
        $bare = new Schema([
            new TableSchema('people', ['id_person'], [
                'id_person' => 'bigint(21)',
            ])]);
        $declaration = new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new Keep(),
                'newer' => new Keep(),
            ])]);

        $coverage = Coverage::compare($bare, $declaration, new Baseline('test', '1.0.0', $bare, $declaration));

        self::assertTrue($coverage->isComplete());
        self::assertSame([
            'people' => ['newer'],
        ], $coverage->otherVersionColumns);
        self::assertSame('Summary: complete declaration, 1 with no effect.', $coverage->summary());
    }

    /**
     * The bare declaration of the test platform covers its latest frozen schema: the check every platform package runs.
     */
    public function testTheBareDeclarationOfThePlatformCoversItsLatestFrozenSchema(): void
    {
        $platform = new TestPlatform();
        $frozen = $platform->frozenSchemas();

        $coverage = Coverage::compare($frozen->load($frozen->latest()), $platform->bareDeclaration());

        self::assertSame(['contacts'], $coverage->undeclaredTables, 'the test platform declares people only');
        self::assertSame([], $coverage->undeclaredColumns);
    }
}
