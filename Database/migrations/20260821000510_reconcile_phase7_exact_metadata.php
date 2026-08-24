<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Forward-reconcile Phase 7 exact column metadata';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['internship_posts', 'internship_applications', 'application_profile_snapshots'] as $table) {
            $context->assertTableExists($table);
        }

        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Phase 7 metadata reconciliation requires MySQL session time zone +00:00.');
        }

        $tooLong = (int) $context->pdo()->query(
            'SELECT COUNT(*) FROM internship_posts WHERE CHAR_LENGTH(educationLevel) > 100'
        )?->fetchColumn();
        if ($tooLong !== 0) {
            throw new RuntimeException('Cannot narrow internship_posts.educationLevel while values exceed 100 characters.');
        }

        if ($this->hasColumn($context, 'internship_applications', 'cvUrl')) {
            $nonNullCv = (int) $context->pdo()->query(
                "SELECT COUNT(*) FROM internship_applications WHERE cvUrl IS NOT NULL AND TRIM(cvUrl) <> ''"
            )?->fetchColumn();
            if ($nonNullCv !== 0) {
                throw new RuntimeException('Cannot remove obsolete internship_applications.cvUrl while non-null values exist.');
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $pdo->exec("ALTER TABLE internship_posts ALTER COLUMN workType SET DEFAULT 'full_time'");
        $pdo->exec('ALTER TABLE internship_posts MODIFY COLUMN educationLevel VARCHAR(100) NOT NULL');
        if ($this->hasColumn($context, 'internship_applications', 'cvUrl')) {
            $pdo->exec('ALTER TABLE internship_applications DROP COLUMN cvUrl');
        }
        $pdo->exec("ALTER TABLE application_profile_snapshots ALTER COLUMN schemaVersion SET DEFAULT '1.0.0'");
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only reconciliation: exact metadata must not be rolled back.
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
        SQL);
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }
};
