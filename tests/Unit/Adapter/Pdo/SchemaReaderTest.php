<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Adapter\Pdo;

use PHPUnit\Framework\TestCase;
use Shroud\Adapter\Pdo\SchemaReader;
use Shroud\Exception\InvalidSchemaException;

final class SchemaReaderTest extends TestCase
{
    public function testTheColumnRowsOfInformationSchemaBuildTheSchema(): void
    {
        $schema = SchemaReader::fromColumnRows([
            [
                'TABLE_NAME' => 'people',
                'COLUMN_NAME' => 'id_person',
                'COLUMN_TYPE' => 'bigint(21)',
                'COLUMN_KEY' => 'PRI',
            ],
            [
                'TABLE_NAME' => 'people',
                'COLUMN_NAME' => 'name',
                'COLUMN_TYPE' => 'text',
                'COLUMN_KEY' => '',
            ],
            [
                'TABLE_NAME' => 'links',
                'COLUMN_NAME' => 'id_person',
                'COLUMN_TYPE' => 'bigint(21)',
                'COLUMN_KEY' => 'PRI',
            ],
            [
                'TABLE_NAME' => 'links',
                'COLUMN_NAME' => 'id_tag',
                'COLUMN_TYPE' => 'bigint(21)',
                'COLUMN_KEY' => 'PRI',
            ],
            [
                'TABLE_NAME' => 'links',
                'COLUMN_NAME' => 'seen',
                'COLUMN_TYPE' => 'varchar(6)',
                'COLUMN_KEY' => 'MUL',
            ],
        ]);

        self::assertSame(['id_person'], $schema->getTable('people')->primaryKey);
        self::assertSame([
            'id_person' => 'bigint(21)',
            'name' => 'text',
        ], $schema->getTable('people')->columns);
        self::assertSame(['id_person', 'id_tag'], $schema->getTable('links')->primaryKey);
        self::assertSame([], SchemaReader::fromColumnRows([])->tables());
    }

    public function testARowWithoutATableNameIsRefused(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('information_schema row without TABLE_NAME');

        SchemaReader::fromColumnRows([[
            'COLUMN_NAME' => 'x',
            'COLUMN_TYPE' => 'text',
        ]]);
    }
}
