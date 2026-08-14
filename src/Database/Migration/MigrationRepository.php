<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

use PDO;

final class MigrationRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function bootstrap(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version CHAR(14) NOT NULL, name VARCHAR(255) NOT NULL, checksum CHAR(64) NOT NULL, batch INT UNSIGNED NOT NULL, executionMs INT UNSIGNED NOT NULL, appliedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (version), UNIQUE KEY uq_schema_migrations_name (name), KEY idx_schema_migrations_batch (batch)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    /** @return array<string,array{name:string,checksum:string,batch:int}> */
    public function applied(): array
    {
        $rows = $this->pdo->query('SELECT version,name,checksum,batch FROM schema_migrations ORDER BY version')->fetchAll();
        $result=[]; foreach ($rows as $row) { $result[$row['version']]=['name'=>$row['name'],'checksum'=>$row['checksum'],'batch'=>(int)$row['batch']]; }
        return $result;
    }
    public function nextBatch(): int { return (int)$this->pdo->query('SELECT COALESCE(MAX(batch),0)+1 FROM schema_migrations')->fetchColumn(); }
    public function record(MigrationDefinition $definition, int $batch, int $ms): void
    {
        $s=$this->pdo->prepare('INSERT INTO schema_migrations(version,name,checksum,batch,executionMs) VALUES(?,?,?,?,?)');
        $s->execute([$definition->version,$definition->name,$definition->checksum,$batch,$ms]);
    }
    public function remove(string $version): void { $s=$this->pdo->prepare('DELETE FROM schema_migrations WHERE version=?'); $s->execute([$version]); }
}
