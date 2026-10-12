<?php

declare(strict_types=1);

namespace Shroud\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shroud\Adapter\Pdo\MysqlDump;
use Shroud\Adapter\Pdo\SchemaReader;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Exception\UndeclaredTableException;
use Shroud\Generator;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Wrapper\Fallback;

/**
 * Runs the PDO adapters against a real MySQL or MariaDB server.
 *
 * The suite needs a server: `SHROUD_TEST_DSN` (without base name), `SHROUD_TEST_USER` and
 * `SHROUD_TEST_PASSWORD`, an account allowed to create and drop the two bases it uses. Without
 * them, every test is skipped.
 */
final class MysqlTest extends TestCase
{
    private const string SOURCE = 'shroud_test_source';

    private const string TARGET = 'shroud_test_target';

    private \PDO $server;

    protected function setUp(): void
    {
        $dsn = getenv('SHROUD_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('SHROUD_TEST_DSN is not set: no server to run the integration tests against');
        }
        $user = getenv('SHROUD_TEST_USER');
        $password = getenv('SHROUD_TEST_PASSWORD');
        $this->server = new \PDO($dsn, is_string($user) ? $user : 'root', is_string($password) ? $password : '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        foreach ([self::SOURCE, self::TARGET] as $base) {
            $this->server->exec(sprintf('DROP DATABASE IF EXISTS %1$s; CREATE DATABASE %1$s CHARACTER SET utf8mb4', $base));
        }
        $settings = addslashes(serialize([
            'colour' => 9,
            'id_friend' => 13,
        ]));
        $this->server->exec(sprintf(<<<'SQL'
            CREATE TABLE %1$s.people (
                id_person BIGINT NOT NULL PRIMARY KEY,
                name TEXT NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(30) NULL,
                settings TEXT NULL,
                password VARCHAR(255) NOT NULL,
                UNIQUE KEY unique_email (email)
            );
            CREATE TABLE %1$s.links (
                id_person BIGINT NOT NULL,
                id_tag BIGINT NOT NULL,
                PRIMARY KEY (id_person, id_tag)
            );
            CREATE TABLE %1$s.visits (day DATE NOT NULL PRIMARY KEY, total INT NOT NULL);
            INSERT INTO %1$s.people VALUES
                (12, 'Zoé Kowalczyk', 'zoe@example-real.test', '+33 1 23 45 67 89', '%2$s', 'secret'),
                (13, 'Jean Dupont', 'jean@example-real.test', 'call me', NULL, 'secret');
            INSERT INTO %1$s.links VALUES (12, 1), (13, 1), (13, 2);
            INSERT INTO %1$s.visits VALUES ('2026-01-01', 3);
            SQL
            , self::SOURCE, $settings));
    }

    protected function tearDown(): void
    {
        if (isset($this->server)) {
            $this->server->exec(sprintf('DROP DATABASE IF EXISTS %s; DROP DATABASE IF EXISTS %s', self::SOURCE, self::TARGET));
        }
    }

    public function testTheSchemaIsReadFromInformationSchema(): void
    {
        $schema = new SchemaReader($this->connect(self::SOURCE))->read();

        self::assertSame(['links', 'people', 'visits'], array_map(static fn ($table): string => $table->name, $schema->tables()));
        self::assertSame(['id_person'], $schema->getTable('people')->primaryKey);
        self::assertSame(['id_person', 'id_tag'], $schema->getTable('links')->primaryKey);
        self::assertSame('varchar(255)', $schema->getTable('people')->columns['email']);
        self::assertSame(['id_person', 'name', 'email', 'phone', 'settings', 'password'], $schema->getTable('people')->columnNames());
    }

    public function testTheDumpImportsIntoAnEmptyBaseWithoutAnyRealValue(): void
    {
        $generator = new Generator('integration salt');
        $output = fopen('php://memory', 'w+');
        self::assertIsResource($output);

        new MysqlDump($this->connect(self::SOURCE), $this->declaration(), new Disguiser($this->declaration(), $generator))->write($output);
        rewind($output);
        $dump = (string) stream_get_contents($output);
        $this->connect(self::TARGET)->exec($dump);

        $target = $this->connect(self::TARGET, buffered: true);
        $people = $this->query($target, 'SELECT * FROM people ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(2, $people);
        foreach ($people as $person) {
            self::assertIsString($person['email']);
            self::assertStringNotContainsString('example-real', $person['email']);
            self::assertSame('', $person['password']);
        }
        $ids = array_map(static fn (array $person): int => (int) $person['id_person'], $people);
        self::assertNotContains(12, $ids);
        self::assertEqualsCanonicalizing($ids, array_map(intval(...), $this->query($target, 'SELECT DISTINCT id_person FROM links')->fetchAll(\PDO::FETCH_COLUMN)), 'the links follow the renumbering');
        self::assertSame('0', (string) $this->query($target, 'SELECT COUNT(*) FROM visits')->fetchColumn(), 'a truncated table keeps its schema alone');

        $settings = (string) $this->query($target, 'SELECT settings FROM people WHERE settings IS NOT NULL')->fetchColumn();
        self::assertSame([
            'colour' => 9,
            'id_friend' => $generator->permutationFor('person')->apply(13),
        ], unserialize($settings));
    }

    public function testTheDryRunCountsTheRowsTheRefusalsAndTheFallbacks(): void
    {
        $dryRun = new MysqlDump($this->connect(self::SOURCE), $this->declaration(), new Disguiser($this->declaration(), new Generator('integration salt')))->dryRun();

        self::assertTrue($dryRun->isClean());
        self::assertSame(5, $dryRun->rows());
        self::assertSame(0, $dryRun->rows('visits'), 'a truncated table is not read');
        self::assertSame([
            'people' => [
                'phone' => [
                    'letters found' => 1,
                ],
            ],
        ], $dryRun->fallbacks());
    }

    public function testAnUndeclaredTableStopsTheDump(): void
    {
        $this->server->exec(sprintf('CREATE TABLE %s.newsletters (id INT PRIMARY KEY)', self::SOURCE));
        $output = fopen('php://memory', 'w+');
        self::assertIsResource($output);

        $this->expectException(UndeclaredTableException::class);

        new MysqlDump($this->connect(self::SOURCE), $this->declaration(), new Disguiser($this->declaration(), new Generator('integration salt')))->write($output);
    }

    private function declaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new RenumberedId('person'),
                'name' => new FullName(),
                'email' => new Email(),
                'phone' => new Fallback(new ShapedDigits(keepDiallingCode: true), new Clear()),
                'settings' => new Serialized(new PerKey([
                    'id_friend' => new RenumberedId('person'),
                ], new Keep())),
                'password' => new Clear(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            new Table('links', ['id_person', 'id_tag'], [
                'id_person' => new RenumberedId('person'),
                'id_tag' => new Keep(),
            ]),
            Table::truncated('visits'),
        ]);
    }

    /**
     * Opens a base as the commands do: unbuffered, to stream large tables, unless asked otherwise.
     */
    private function connect(string $base, bool $buffered = false): \PDO
    {
        $dsn = (string) getenv('SHROUD_TEST_DSN');
        $user = getenv('SHROUD_TEST_USER');
        $password = getenv('SHROUD_TEST_PASSWORD');

        return new \PDO($dsn . ';dbname=' . $base . ';charset=utf8mb4', is_string($user) ? $user : 'root', is_string($password) ? $password : '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $buffered,
        ]);
    }

    /**
     * Runs a query that must succeed.
     */
    private function query(\PDO $pdo, string $sql): \PDOStatement
    {
        $statement = $pdo->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);

        return $statement;
    }
}
