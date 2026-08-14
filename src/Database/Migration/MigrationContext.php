<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

use PDO;
use RuntimeException;

final class MigrationContext
{
    public function __construct(private readonly PDO $pdo) {}
    public function pdo(): PDO { return $this->pdo; }
    public function execute(string $sql): void { $this->pdo->exec($sql); }
    public function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }
    public function assertTableAbsent(string $table): void
    {
        if ($this->tableExists($table)) { throw new RuntimeException("Table {$table} already exists."); }
    }
}
