<?php

declare(strict_types=1);

namespace Shroud\Cli;

use Shroud\Configuration;
use Shroud\Exception\ShroudException;

/**
 * Shares between the commands what they all do: read an option, load the project, open the base, fail.
 */
final class Console
{
    /**
     * The default name of the configuration file of a project, in its root directory.
     */
    public const string CONFIGURATION_FILE = 'shroud.php';

    /**
     * Returns a string option, or null when it is absent or has no value.
     *
     * @param array<string, mixed> $options the result of `getopt()`
     */
    public static function option(array $options, string $name): ?string
    {
        return is_string($options[$name] ?? null) ? $options[$name] : null;
    }

    /**
     * Loads the configuration of the project: the `--config` option, else `shroud.php` of the current directory.
     *
     * @param array<string, mixed> $options
     */
    public static function configuration(array $options, string $directory): Configuration
    {
        return Configuration::fromFile(self::configurationFile($options, $directory));
    }

    /**
     * Returns the path of the configuration file the command reads.
     *
     * @param array<string, mixed> $options
     */
    public static function configurationFile(array $options, string $directory): string
    {
        return self::option($options, 'config') ?? $directory . '/' . self::CONFIGURATION_FILE;
    }

    /**
     * Opens the base from the connection options, or fails with the usage when they are not enough.
     *
     * @param array<string, mixed> $options
     */
    public static function connect(array $options, Configuration $configuration, string $directory, string $usage): \PDO
    {
        $database = Database::fromOptions($options, $configuration->platform, $directory);
        if (! $database instanceof Database) {
            self::fail("Incomplete connection: --connect, or --host and --database and --user, or --dsn and --user\n\n" . $usage);
        }
        try {
            return $database->connect();
        } catch (\PDOException $exception) {
            self::fail('Connection refused: ' . $exception->getMessage());
        }
    }

    /**
     * Runs the body of a command, turning a refusal of the library or of the base into an error message and the exit code 2.
     *
     * @param callable(): int $body returns the exit code
     */
    public static function run(callable $body): never
    {
        try {
            exit($body());
        } catch (ShroudException $exception) {
            self::fail($exception->getMessage());
        } catch (\PDOException $exception) {
            self::fail('Database error: ' . $exception->getMessage());
        }
    }

    /**
     * Writes a message on the error output and exits with the code of a usage or execution error.
     */
    public static function fail(string $message, int $code = 2): never
    {
        fwrite(STDERR, rtrim($message) . "\n");
        exit($code);
    }

    /**
     * Returns the current directory, or fails when it cannot be read.
     */
    public static function directory(): string
    {
        $directory = getcwd();
        if ($directory === false) {
            self::fail('The current directory cannot be read');
        }

        return $directory;
    }
}
