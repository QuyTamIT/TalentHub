<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Extend enterprises with company size, founded year, and tax code'; }

    public function preflight(MigrationContext $c): void
    {
        $c->assertTableExists('enterprises');
    }

    /**
     * @return array<string,bool> map of column name => exists
     */
    private function existingColumns(MigrationContext $c): array
    {
        $stmt = $c->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => 'enterprises']);
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
            'companySize' => 'ALTER TABLE enterprises ADD COLUMN companySize VARCHAR(100) NULL AFTER industry',
            'foundedYear' => 'ALTER TABLE enterprises ADD COLUMN foundedYear SMALLINT UNSIGNED NULL AFTER companySize',
            'taxCode'     => 'ALTER TABLE enterprises ADD COLUMN taxCode VARCHAR(50) NULL AFTER website',
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
        $columns  = ['taxCode', 'foundedYear', 'companySize'];
        $toDrop   = array_values(array_intersect($columns, array_keys($existing)));
        if ($toDrop === []) {
            return;
        }
        $sql = 'ALTER TABLE enterprises DROP COLUMN ' . implode(', DROP COLUMN ', $toDrop);
        $c->execute($sql);
    }
};
