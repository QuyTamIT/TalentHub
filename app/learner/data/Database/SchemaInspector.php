<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SchemaInspector
{
    public function __construct(private readonly PDO $pdo, private readonly string $databaseName)
    {
        $this->validateIdentifier($databaseName);
    }

    public function hasTable(string $table): bool
    {
        $this->validateIdentifier($table);
        if ($this->isSqlite()) {
            return $this->exists('SELECT 1 FROM sqlite_master WHERE type = :type AND name = :table LIMIT 1', ['type' => 'table', 'table' => $table]);
        }

        return $this->exists('SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table LIMIT 1', ['schema' => $this->databaseName, 'table' => $table]);
    }

    public function hasColumn(string $table, string $column): bool
    {
        $this->validateIdentifier($table);
        $this->validateIdentifier($column);
        if ($this->isSqlite()) {
            return $this->exists('SELECT 1 FROM pragma_table_info(:table) WHERE name = :column LIMIT 1', ['table' => $table, 'column' => $column]);
        }

        return $this->exists('SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column LIMIT 1', ['schema' => $this->databaseName, 'table' => $table, 'column' => $column]);
    }

    public function hasIndex(string $table, string $index): bool
    {
        $this->validateIdentifier($table);
        $this->validateIdentifier($index);
        if ($this->isSqlite()) {
            return $this->exists('SELECT 1 FROM sqlite_master WHERE type = :type AND tbl_name = :table AND name = :index LIMIT 1', ['type' => 'index', 'table' => $table, 'index' => $index]);
        }

        return $this->exists('SELECT 1 FROM information_schema.statistics WHERE table_schema = :schema AND table_name = :table AND index_name = :index LIMIT 1', ['schema' => $this->databaseName, 'table' => $table, 'index' => $index]);
    }

    private function exists(string $sql, array $parameters): bool
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false || !$statement->execute($parameters)) {
            throw new RuntimeException('Schema inspection query failed.');
        }

        return $statement->fetchColumn() !== false;
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function validateIdentifier(string $identifier): void
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier) !== 1) {
            throw new InvalidArgumentException('Schema inspector requires a validated identifier.');
        }
    }
}
