<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Extend schools with self-service profile fields'; }

    public function preflight(MigrationContext $c): void
    {
        $c->assertTableExists('schools');
    }

    /**
     * @return array<string,bool> map of column name => exists
     */
    private function existingColumns(MigrationContext $c): array
    {
        $stmt = $c->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => 'schools']);
        $existing = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['COLUMN_NAME']] = true;
        }
        return $existing;
    }

    public function up(MigrationContext $c): void
    {
        $existing = $this->existingColumns($c);
        $additions = [
            'logoUrl'      => 'ALTER TABLE schools ADD COLUMN logoUrl VARCHAR(500) NULL AFTER status',
            'address'      => 'ALTER TABLE schools ADD COLUMN address VARCHAR(500) NULL AFTER logoUrl',
            'phone'        => 'ALTER TABLE schools ADD COLUMN phone VARCHAR(30) NULL AFTER address',
            'email'        => 'ALTER TABLE schools ADD COLUMN email VARCHAR(255) NULL AFTER phone',
            'website'      => 'ALTER TABLE schools ADD COLUMN website VARCHAR(500) NULL AFTER email',
            'level'        => 'ALTER TABLE schools ADD COLUMN level VARCHAR(100) NULL AFTER website',
            'studentCount' => 'ALTER TABLE schools ADD COLUMN studentCount INT UNSIGNED NOT NULL DEFAULT 0 AFTER level',
            'teacherCount' => 'ALTER TABLE schools ADD COLUMN teacherCount INT UNSIGNED NOT NULL DEFAULT 0 AFTER studentCount',
            'academicYear' => 'ALTER TABLE schools ADD COLUMN academicYear VARCHAR(20) NOT NULL DEFAULT \'2025-2026\' AFTER teacherCount',
        ];
        foreach ($additions as $column => $ddl) {
            if (!isset($existing[$column])) {
                $c->execute($ddl);
            }
        }
    }

    public function down(MigrationContext $c): void
    {
        $existing = $this->existingColumns($c);
        $columns  = ['academicYear', 'teacherCount', 'studentCount', 'level', 'website', 'email', 'phone', 'address', 'logoUrl'];
        $toDrop   = array_values(array_intersect($columns, array_keys($existing)));
        if ($toDrop === []) {
            return;
        }
        $sql = 'ALTER TABLE schools DROP COLUMN ' . implode(', DROP COLUMN ', $toDrop);
        $c->execute($sql);
    }
};