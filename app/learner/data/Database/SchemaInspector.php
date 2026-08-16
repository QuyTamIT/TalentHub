<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use InvalidArgumentException;
use PDO;

final class SchemaInspector
{
    public function __construct(private readonly PDO $pdo, private readonly string $schema)
    {
        $this->identifier($schema);
    }

    public function hasTable(string $table): bool
    {
        $this->identifier($table);
        if ($this->driver() === 'sqlite') {
            $query = $this->pdo->prepare("SELECT 1 FROM {$this->quote($this->schema)}.sqlite_master WHERE type = 'table' AND name = :name");
            $query->execute(['name' => $table]);
            return $query->fetchColumn() !== false;
        }

        $query = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table');
        $query->execute(['schema' => $this->schema, 'table' => $table]);
        return $query->fetchColumn() !== false;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $this->identifier($table);
        $this->identifier($column);
        if ($this->driver() === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA ' . $this->quote($this->schema) . '.table_info(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $column) {
                    return true;
                }
            }
            return false;
        }

        $query = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column');
        $query->execute(['schema' => $this->schema, 'table' => $table, 'column' => $column]);
        return $query->fetchColumn() !== false;
    }

    public function hasIndex(string $table, string $index): bool
    {
        $this->identifier($table);
        $this->identifier($index);
        if ($this->driver() === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA ' . $this->quote($this->schema) . '.index_list(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $index) {
                    return true;
                }
            }
            return false;
        }

        $query = $this->pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = :schema AND table_name = :table AND index_name = :index');
        $query->execute(['schema' => $this->schema, 'table' => $table, 'index' => $index]);
        return $query->fetchColumn() !== false;
    }

    public function hasForeignKey(string $table, string $referencedTable, string $fromColumn, string $toColumn): bool
    {
        $this->identifier($table);
        $this->identifier($referencedTable);
        $this->identifier($fromColumn);
        $this->identifier($toColumn);
        if ($this->driver() === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA ' . $this->quote($this->schema) . '.foreign_key_list(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (($row['table'] ?? null) === $referencedTable && ($row['from'] ?? null) === $fromColumn && ($row['to'] ?? null) === $toColumn) {
                    return true;
                }
            }
            return false;
        }

        $query = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = :schema AND table_name = :table AND column_name = :column AND referenced_table_name = :referencedTable AND referenced_column_name = :referencedColumn'
        );
        $query->execute([
            'schema' => $this->schema,
            'table' => $table,
            'column' => $fromColumn,
            'referencedTable' => $referencedTable,
            'referencedColumn' => $toColumn,
        ]);
        return $query->fetchColumn() !== false;
    }

    public function columnType(string $table, string $column): ?string
    {
        $this->identifier($table);
        $this->identifier($column);
        if ($this->driver() === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA ' . $this->quote($this->schema) . '.table_info(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $column) {
                    return strtoupper((string) ($row['type'] ?? ''));
                }
            }
            return null;
        }

        $query = $this->pdo->prepare('SELECT data_type, character_maximum_length FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column');
        $query->execute(['schema' => $this->schema, 'table' => $table, 'column' => $column]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $type = strtoupper((string) $row['data_type']);
        $length = $row['character_maximum_length'];
        return $length === null ? $type : $type . '(' . $length . ')';
    }

    private function driver(): string
    {
        return strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    private function identifier(string $value): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid schema identifier: ' . $value);
        }
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
