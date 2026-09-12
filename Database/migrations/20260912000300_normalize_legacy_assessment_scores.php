<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Normalize leftover demo assessment overallScore values from 0-10 to canonical 0-100';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('assessments');
    }

    public function up(MigrationContext $context): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            UPDATE assessments
            SET overallScore = overallScore * 10,
                updatedAt = CURRENT_TIMESTAMP(6)
            WHERE (id LIKE '21000000-%' OR id LIKE '22000000-%')
              AND overallScore BETWEEN 0 AND 10
            SQL);
        $statement->execute();
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Assessment score normalization is forward-only.');
    }
};
