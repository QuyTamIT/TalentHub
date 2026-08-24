<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Forward-reconcile Phase 7 exact index contracts';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['internship_posts', 'internship_applications', 'application_status_history'] as $table) {
            $context->assertTableExists($table);
        }
        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Phase 7 index reconciliation requires MySQL session time zone +00:00.');
        }
        $this->assertIndex($context, 'internship_posts', 'idx_internship_posts_enterprise_status_deadline', ['enterpriseId', 'status', 'deadline']);
        $this->assertIndex($context, 'internship_applications', 'idx_internship_applications_student_status', ['studentId', 'status', 'appliedAt']);
        $this->assertIndex($context, 'internship_applications', 'idx_internship_applications_post_status', ['postId', 'status', 'appliedAt']);
        $this->assertIndex($context, 'application_status_history', 'fk_application_status_history_user', ['changedByUserId']);
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            ALTER TABLE internship_posts
                DROP KEY idx_internship_posts_enterprise_status_deadline,
                ADD KEY idx_internship_posts_enterprise (enterpriseId),
                ADD KEY idx_internship_posts_status_deadline (status, deadline)
        SQL);
        $context->execute(<<<'SQL'
            ALTER TABLE internship_applications
                DROP KEY idx_internship_applications_student_status,
                DROP KEY idx_internship_applications_post_status,
                ADD KEY idx_internship_applications_student (studentId),
                ADD KEY idx_internship_applications_post_status (postId, status)
        SQL);
        $context->execute(<<<'SQL'
            ALTER TABLE application_status_history
                ADD KEY idx_application_status_history_changed_by (changedByUserId),
                DROP KEY fk_application_status_history_user
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only reconciliation: exact query indexes must remain stable.
    }

    /** @param list<string> $expected */
    private function assertIndex(MigrationContext $context, string $table, string $index, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name
            ORDER BY seq_in_index
        SQL);
        $statement->execute(['table' => $table, 'index_name' => $index]);
        $actual = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if ($actual !== $expected) {
            throw new RuntimeException("{$table}.{$index} has an unexpected column contract.");
        }
    }
};
