<?php

declare(strict_types=1);

namespace TalentHub\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use TalentHub\Database\Exception\DatabaseConnectionException;

final class Connection
{
    private ?PDO $pdo = null;

    /**
     * @param array{
     *     driver: string,
     *     host: string,
     *     port: int,
     *     database: string,
     *     username: string,
     *     password: string,
     *     charset: string,
     *     connectTimeout: int,
     *     persistent: bool,
     *     options?: array<int, mixed>
     * } $config
     */
    public function __construct(private readonly array $config)
    {
        $this->validateConfig();
    }

    public function connect(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $this->pdo = new PDO(
                $this->dsn(),
                $this->config['username'],
                $this->config['password'],
                $this->pdoOptions(),
            );
        } catch (PDOException $exception) {
            throw new DatabaseConnectionException($this->extractSqlState($exception));
        }

        return $this->pdo;
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    private function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'],
        );
    }

    /** @return array<int, mixed> */
    private function pdoOptions(): array
    {
        $configured = $this->config['options'] ?? [];

        return array_replace($configured, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => $this->config['persistent'],
            PDO::ATTR_TIMEOUT => $this->config['connectTimeout'],
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION time_zone = '+00:00'",
        ]);
    }

    private function validateConfig(): void
    {
        $expectedTypes = [
            'driver' => 'string', 'host' => 'string', 'port' => 'integer', 'database' => 'string',
            'username' => 'string', 'password' => 'string', 'charset' => 'string',
            'connectTimeout' => 'integer', 'persistent' => 'boolean',
        ];
        foreach ($expectedTypes as $key => $type) {
            if (!array_key_exists($key, $this->config) || gettype($this->config[$key]) !== $type) {
                throw new InvalidArgumentException("Database config key {$key} must be {$type}.");
            }
        }
        if (isset($this->config['options']) && !is_array($this->config['options'])) {
            throw new InvalidArgumentException('Database config key options must be an array.');
        }
        if ($this->config['driver'] !== 'mysql') {
            throw new InvalidArgumentException('Only the mysql database driver is supported.');
        }
        if ($this->config['charset'] !== 'utf8mb4') {
            throw new InvalidArgumentException('Database charset must be utf8mb4.');
        }
        if ($this->config['host'] === '' || preg_match('/[;\x00-\x20]/', $this->config['host']) === 1) {
            throw new InvalidArgumentException('DB_HOST contains unsupported characters.');
        }
        if (preg_match('/\A[A-Za-z0-9_$-]+\z/', $this->config['database']) !== 1) {
            throw new InvalidArgumentException('DB_DATABASE contains unsupported characters.');
        }
        if ($this->config['username'] === '') {
            throw new InvalidArgumentException('DB_USERNAME must not be empty.');
        }
        if ($this->config['port'] < 1 || $this->config['port'] > 65535) {
            throw new InvalidArgumentException('DB_PORT must be between 1 and 65535.');
        }
        if ($this->config['connectTimeout'] < 1 || $this->config['connectTimeout'] > 60) {
            throw new InvalidArgumentException('DB_CONNECT_TIMEOUT must be between 1 and 60 seconds.');
        }
    }

    private function extractSqlState(PDOException $exception): ?string
    {
        if (is_string($exception->errorInfo[0] ?? null)) {
            return $exception->errorInfo[0];
        }

        return is_string($exception->getCode()) && preg_match('/\A[A-Z0-9]{5}\z/', $exception->getCode()) === 1
            ? $exception->getCode()
            : null;
    }
}
