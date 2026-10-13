<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Schema\IdWidths;
use Shroud\Schema\Schema;
use Shroud\Schema\TableSchema;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Reference\RenumberedPolymorphicId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Wrapper\Fallback;

final class IdWidthsTest extends TestCase
{
    public function testAColumnTooNarrowForItsIdsIsReportedWithTheWidthToDeclare(): void
    {
        $problems = IdWidths::check(
            new Schema([
                new TableSchema('people', ['id'], [
                    'id' => 'mediumint(8) unsigned',
                    'id_parent' => 'bigint(20)',
                    'code' => 'varchar(6)',
                ])]),
            new Declaration([new Table('people', 'id', [
                'id' => new RenumberedId('person'),
                'id_parent' => new Fallback(new RenumberedId('person'), new Clear()),
                'code' => new RenumberedId('person'),
            ])]),
        );

        self::assertSame([
            "the ids of person are renumbered on 31 bits, up to 2147483647, but people.id is mediumint(8) unsigned, up to 16777215: declare RenumberedId('person', bits: 24) or less",
            "the ids of person are renumbered on 31 bits, up to 2147483647, but people.code is varchar(6), up to 999999: declare RenumberedId('person', bits: 19) or less",
        ], $problems);
    }

    public function testAColumnWideEnoughForTheDeclaredWidthPasses(): void
    {
        $schema = new Schema([
            new TableSchema('people', ['id'], [
                'id' => 'mediumint(8) unsigned',
                'data' => 'varchar(10)',
                'id_objet' => 'smallint(6)',
                'objet' => 'varchar(25)',
            ])]);

        self::assertSame([], IdWidths::check($schema, new Declaration([new Table('people', 'id', [
            'id' => new RenumberedId('person', bits: 24),
            'data' => new Serialized(new PerKey([], new Keep())),
            'id_objet' => new Keep(),
            'objet' => new Keep(),
        ])])));
    }

    public function testAPolymorphicLinkMustHoldTheIdsOfEveryRenumberedType(): void
    {
        $problems = IdWidths::check(
            new Schema([
                new TableSchema('people', ['id'], [
                    'id' => 'int(11)',
                ]),
                new TableSchema('links', ['id_objet'], [
                    'id_objet' => 'smallint(5) unsigned',
                    'objet' => 'varchar(25)',
                ]),
            ]),
            new Declaration([
                new Table('people', 'id', [
                    'id' => new RenumberedId('person', bits: 20),
                ]),
                new Table('links', 'id_objet', [
                    'id_objet' => new RenumberedPolymorphicId('objet'),
                    'objet' => new Keep(),
                ]),
            ]),
        );

        self::assertCount(1, $problems);
        self::assertStringContainsString('links.id_objet is smallint(5) unsigned, up to 65535', $problems[0]);
    }
}
