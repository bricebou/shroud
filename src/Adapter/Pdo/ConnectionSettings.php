<?php

declare(strict_types=1);

namespace Shroud\Adapter\Pdo;

/**
 * Holds what it takes to open a MySQL or MariaDB base: host, port, user, password and base name.
 */
final readonly class ConnectionSettings
{
    public function __construct(
        public string $host,
        public string $database,
        public string $user,
        public ?string $password = null,
        public string $port = '',
    ) {
    }

    /**
     * Composes the PDO DSN of the base, in UTF-8.
     */
    public function dsn(): string
    {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->database . ';charset=utf8mb4';

        return $this->port === '' ? $dsn : $dsn . ';port=' . $this->port;
    }
}
