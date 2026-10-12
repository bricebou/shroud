<?php

declare(strict_types=1);

namespace Shroud\Cli;

use Shroud\Adapter\Pdo\ConnectionSettings;
use Shroud\Platform\PlatformInterface;

/**
 * Reads the connection options shared by the commands, and opens the PDO connection they need.
 *
 * In increasing order of precedence: the connection file of the platform, the named options,
 * then a complete DSN. Without password, it is asked on the terminal, without echo.
 */
final readonly class Database
{
    /**
     * The long options, in the format of `getopt()`.
     *
     * @var list<string>
     */
    public const array OPTIONS = ['connect::', 'host::', 'port::', 'database::', 'user::', 'password::', 'dsn::'];

    public const string USAGE = <<<'TXT'
    Connection, in increasing order of precedence:
      --connect   reads the connection file of the platform, the one of the current directory without value
      --host, --port, --database, --user, --password
                  each option takes precedence over what the connection file gives
      --dsn       a complete PDO DSN, which takes precedence over the host, the port and the base
      Without password, it is asked on the terminal, without echo.
    TXT;

    private function __construct(
        private string $dsn,
        private string $user,
        private ?string $password,
    ) {
    }

    /**
     * Reads the connection options, or returns null when they are not enough to connect.
     *
     * @param array<string, mixed> $options the result of `getopt()`
     */
    public static function fromOptions(array $options, PlatformInterface $platform, string $directory): ?self
    {
        $option = static fn (string $name): ?string => is_string($options[$name] ?? null) ? $options[$name] : null;

        $settings = null;
        if (array_key_exists('connect', $options)) {
            $settings = $platform->readConnection($option('connect') ?? $directory . '/' . $platform->connectionFile());
        }
        $host = $option('host') ?? $settings?->host;
        $database = $option('database') ?? $settings?->database;
        $user = $option('user') ?? $settings?->user;
        $dsn = $option('dsn');
        if ($dsn === null && $host !== null && $database !== null) {
            $port = $option('port') ?? ($settings instanceof ConnectionSettings ? $settings->port : '');
            $dsn = new ConnectionSettings($host, $database, $user ?? '', port: $port)->dsn();
        }
        if ($dsn === null || $user === null) {
            return null;
        }

        return new self($dsn, $user, $option('password') ?? $settings?->password);
    }

    /**
     * Opens the connection, asking the password on the terminal when it is missing and a terminal is there.
     */
    public function connect(): \PDO
    {
        $password = $this->password ?? $this->promptPassword();

        return new \PDO($this->dsn, $this->user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY => false,
        ]);
    }

    /**
     * Returns the DSN the connection will open.
     */
    public function dsn(): string
    {
        return $this->dsn;
    }

    /**
     * Asks the password without showing it; empty without a terminal.
     */
    private function promptPassword(): string
    {
        if (! stream_isatty(STDIN)) {
            return '';
        }
        fwrite(STDERR, "Password of {$this->user}: ");
        shell_exec('stty -echo');
        $password = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo');
        fwrite(STDERR, "\n");

        return $password;
    }
}
