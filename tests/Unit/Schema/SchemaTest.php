<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Shroud\Exception\InvalidSchemaException;
use Shroud\Schema\Schema;
use Shroud\Schema\TableSchema;

final class SchemaTest extends TestCase
{
    public function testTheJsonFormIsReadBackIdentically(): void
    {
        $schema = new Schema([
            new TableSchema('contacts', ['id_contact'], [
                'id_contact' => 'bigint(21)',
                'name' => 'text',
            ]),
            new TableSchema('contact_links', ['id_contact', 'object_id', 'object_type'], [
                'id_contact' => 'bigint(21)',
                'object_id' => 'bigint(21)',
                'object_type' => 'varchar(25)',
            ]),
        ]);

        $data = json_decode($schema->toJson(), associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $read = Schema::fromArray($data);

        self::assertSame($schema->toArray(), $read->toArray());
        self::assertSame(['contact_links', 'contacts'], array_map(static fn (TableSchema $table): string => $table->name, $read->tables()), 'the tables come out sorted');
        self::assertSame(['id_contact', 'object_id', 'object_type'], $read->getTable('contact_links')->primaryKey);
        self::assertTrue($read->has('contacts'));
        self::assertFalse($read->has('people'));
        self::assertTrue($read->getTable('contacts')->hasColumn('name'));
        self::assertSame(['id_contact', 'name'], $read->getTable('contacts')->columnNames());
    }

    public function testAFrozenSchemaIsReadFromItsFile(): void
    {
        $schema = Schema::fromJsonFile(__DIR__ . '/../../fixtures/schema/test-2.0.0.json');

        self::assertSame(['contacts', 'people'], array_map(static fn (TableSchema $table): string => $table->name, $schema->tables()));
        self::assertSame('bigint(21)', $schema->getTable('people')->columns['id_person']);
    }

    public function testAMalformedTableIsRefused(): void
    {
        foreach ([[
            'x' => [
                'columns' => 'not an array',
            ],
        ], [
            0 => [
                'columns' => [],
            ],
        ], [
            'x' => [
                'columns' => [
                    'a' => 1,
                ],
            ],
        ], [
            'x' => [
                'columns' => [],
                'primaryKey' => 'id',
            ],
        ]] as $data) {
            try {
                Schema::fromArray($data);
                self::fail('refused');
            } catch (InvalidSchemaException $exception) {
                self::assertStringStartsWith('malformed', $exception->reason);
            }
        }
    }

    public function testAMissingOrInvalidFileIsRefused(): void
    {
        try {
            Schema::fromJsonFile(__DIR__ . '/../../fixtures/schema/test-0.0.0.json');
            self::fail('refused');
        } catch (InvalidSchemaException $exception) {
            self::assertStringStartsWith('schema file not found', $exception->reason);
        }

        try {
            Schema::fromJsonFile(__FILE__);
            self::fail('refused');
        } catch (InvalidSchemaException $exception) {
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function testAnUnknownTableIsRefused(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('table absent from the schema: people');

        new Schema([])->getTable('people');
    }
}
