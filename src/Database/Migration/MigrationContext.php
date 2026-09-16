<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

use PDO;
use RuntimeException;

final class MigrationContext
{
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

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
    public function assertTableExists(string $table): void
    {
        if (!$this->tableExists($table)) { throw new RuntimeException("Table {$table} does not exist."); }
    }

    /**
     * Deterministic UUID v5 (SHA-1, namespace = UUID v5 standard).
     * Useful for seed data where re-running the migration must yield the same IDs.
     */
    public function uuidV5(string $name): string
    {
        $namespace = hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        if ($namespace === false) {
            throw new RuntimeException('Invalid UUID namespace.');
        }
        $hash = sha1($namespace . $name);
        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }
}
