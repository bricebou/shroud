<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shroud\Adapter\Pdo\ConnectionSettings;
use Shroud\Cli\Database;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Platform\Generic;
use Shroud\Tests\Unit\Platform\TestPlatform;

final class DatabaseTest extends TestCase
{
    private const string PROJECT = __DIR__ . '/../../fixtures/project';

    public function testTheSettingsComposeTheDsn(): void
    {
        self::assertSame('mysql:host=db;dbname=site;charset=utf8mb4', new ConnectionSettings('db', 'site', 'site')->dsn());
        self::assertSame('mysql:host=db;dbname=site;charset=utf8mb4;port=3307', new ConnectionSettings('db', 'site', 'site', port: '3307')->dsn());
    }

    public function testTheNamedOptionsBuildTheDsn(): void
    {
        $database = Database::fromOptions([
            'host' => 'db',
            'database' => 'site',
            'user' => 'site',
            'port' => '3307',
        ], new Generic(), self::PROJECT);

        self::assertInstanceOf(Database::class, $database);
        self::assertSame('mysql:host=db;dbname=site;charset=utf8mb4;port=3307', $database->dsn());
    }

    public function testADsnTakesPrecedenceOverTheHost(): void
    {
        $database = Database::fromOptions([
            'dsn' => 'mysql:host=other;dbname=x',
            'host' => 'db',
            'database' => 'site',
            'user' => 'site',
        ], new Generic(), self::PROJECT);

        self::assertInstanceOf(Database::class, $database);
        self::assertSame('mysql:host=other;dbname=x', $database->dsn());
    }

    public function testIncompleteOptionsGiveNothing(): void
    {
        self::assertNull(Database::fromOptions([], new Generic(), self::PROJECT));
        self::assertNull(Database::fromOptions([
            'host' => 'db',
            'database' => 'site',
        ], new Generic(), self::PROJECT), 'no user');
        self::assertNull(Database::fromOptions([
            'host' => 'db',
            'user' => 'site',
        ], new Generic(), self::PROJECT), 'no database');
        self::assertNull(Database::fromOptions([
            'dsn' => 'mysql:host=db;dbname=site',
        ], new Generic(), self::PROJECT), 'no user');
    }

    public function testTheConnectionFileOfThePlatformIsReadAndTheOptionsTakePrecedence(): void
    {
        $database = Database::fromOptions([
            'connect' => false,
        ], new TestPlatform(), self::PROJECT);
        self::assertInstanceOf(Database::class, $database);
        self::assertSame('mysql:host=db;dbname=site;charset=utf8mb4;port=3307', $database->dsn());

        $overridden = Database::fromOptions([
            'connect' => false,
            'host' => '127.0.0.1',
            'port' => '',
        ], new TestPlatform(), self::PROJECT);
        self::assertInstanceOf(Database::class, $overridden);
        self::assertSame('mysql:host=127.0.0.1;dbname=site;charset=utf8mb4', $overridden->dsn());

        $explicit = Database::fromOptions([
            'connect' => self::PROJECT . '/config/database.json',
        ], new TestPlatform(), '/elsewhere');
        self::assertInstanceOf(Database::class, $explicit);
    }

    public function testAMissingConnectionFileIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('connection file not found');

        Database::fromOptions([
            'connect' => false,
        ], new TestPlatform(), '/elsewhere');
    }

    public function testTheGenericPlatformHasNoConnectionFile(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        Database::fromOptions([
            'connect' => false,
        ], new Generic(), self::PROJECT);
    }
}
