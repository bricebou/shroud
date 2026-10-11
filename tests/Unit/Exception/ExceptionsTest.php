<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Exception\InvalidSchemaException;
use Shroud\Exception\InvalidValueException;
use Shroud\Exception\MissingReferenceException;
use Shroud\Exception\ShroudException;
use Shroud\Exception\TruncatedTableException;
use Shroud\Exception\UndeclaredColumnException;
use Shroud\Exception\UndeclaredTableException;

final class ExceptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{ShroudException, string}>
     */
    public static function exceptions(): iterable
    {
        yield 'undeclared table' => [new UndeclaredTableException('users'), 'Undeclared table: users'];
        yield 'truncated table' => [new TruncatedTableException('visits'), 'Truncated table: visits has no row to transform'];
        yield 'undeclared column' => [new UndeclaredColumnException('users', 'email'), 'Undeclared column: users.email'];
        yield 'missing object reference' => [
            new MissingReferenceException('users', 'email'),
            'The strategy of users.email needs an object reference, but the row carries none',
        ];
        yield 'invalid configuration' => [new InvalidConfigurationException('the salt is empty'), 'Invalid configuration: the salt is empty'];
        yield 'invalid schema' => [new InvalidSchemaException('file not found: schema.json'), 'Invalid schema: file not found: schema.json'];
        yield 'invalid value' => [new InvalidValueException('users', 'email', 'invalid UTF-8'), 'Invalid value in users.email: invalid UTF-8'];
    }

    #[DataProvider('exceptions')]
    public function testEveryRefusalIsARuntimeExceptionCaughtByTheCommonInterface(ShroudException $exception, string $message): void
    {
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame($message, $exception->getMessage());
    }

    public function testTheTableAndColumnOfARefusalAreExposed(): void
    {
        $undeclaredTable = new UndeclaredTableException('users');
        $undeclaredColumn = new UndeclaredColumnException('users', 'email');
        $missingReference = new MissingReferenceException('users', 'email');
        $invalidValue = new InvalidValueException('users', 'email', 'invalid UTF-8');

        self::assertSame('users', $undeclaredTable->table);
        self::assertSame('visits', new TruncatedTableException('visits')->table);
        self::assertSame(['users', 'email'], [$undeclaredColumn->table, $undeclaredColumn->column]);
        self::assertSame(['users', 'email'], [$missingReference->table, $missingReference->column]);
        self::assertSame(['users', 'email', 'invalid UTF-8'], [$invalidValue->table, $invalidValue->column, $invalidValue->reason]);
    }

    public function testTheReasonOfAnInvalidInputIsExposed(): void
    {
        self::assertSame('the salt is empty', new InvalidConfigurationException('the salt is empty')->reason);
        self::assertSame('malformed table', new InvalidSchemaException('malformed table')->reason);
    }

    public function testTheCauseOfAnInvalidInputIsKept(): void
    {
        $cause = new \JsonException('Syntax error');

        self::assertSame($cause, new InvalidValueException('users', 'settings', 'invalid JSON', $cause)->getPrevious());
        self::assertSame($cause, new InvalidSchemaException('invalid JSON', $cause)->getPrevious());
        self::assertSame($cause, new InvalidConfigurationException('invalid JSON', $cause)->getPrevious());
    }
}
