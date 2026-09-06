<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Track school project funding completion independently from delivery status';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['projects', 'project_sponsorships', 'payment_orders'] as $table) {
            $context->assertTableExists($table);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->columnExists($context, 'projects', 'fundingStatus')) {
            $context->execute(<<<'SQL'
                ALTER TABLE projects
                    ADD COLUMN fundingStatus VARCHAR(20) NOT NULL DEFAULT 'open' AFTER fundingGoal,
                    ADD CONSTRAINT chk_projects_funding_status
                        CHECK (fundingStatus IN ('not_required','open','goal_reached')),
                    ADD KEY idx_projects_school_funding_status (schoolId, fundingStatus)
            SQL);
        }

        if (!$this->columnExists($context, 'projects', 'fundingReachedAt')) {
            $context->execute('ALTER TABLE projects ADD COLUMN fundingReachedAt DATETIME(6) NULL AFTER fundingStatus');
        }

        $context->execute(<<<'SQL'
            UPDATE projects p
            LEFT JOIN (
                SELECT projectId, COALESCE(SUM(amount), 0) AS paidAmount
                FROM project_sponsorships
                WHERE status = 'paid'
                GROUP BY projectId
            ) paid ON paid.projectId = p.id
            SET p.fundingStatus = CASE
                    WHEN p.fundingGoal IS NULL THEN 'not_required'
                    WHEN COALESCE(paid.paidAmount, 0) >= p.fundingGoal THEN 'goal_reached'
                    ELSE 'open'
                END,
                p.fundingReachedAt = CASE
                    WHEN p.fundingGoal IS NOT NULL
                     AND COALESCE(paid.paidAmount, 0) >= p.fundingGoal
                    THEN COALESCE(p.fundingReachedAt, UTC_TIMESTAMP(6))
                    ELSE NULL
                END
        SQL);
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Project funding history must be retained.');
    }

    private function columnExists(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }
};
