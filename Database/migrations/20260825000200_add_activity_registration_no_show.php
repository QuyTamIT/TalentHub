<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add no_show attendance resolution metadata to activity registrations';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activity_registrations', 'activities', 'student_profiles'] as $table) {
            $context->assertTableExists($table);
        }
        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('No-show migration requires MySQL session time zone +00:00.');
        }
        $resolvedAt = $this->hasColumn($context, 'attendanceResolvedAt');
        $reason = $this->hasColumn($context, 'attendanceResolutionReason');
        if ($resolvedAt xor $reason) {
            throw new RuntimeException('No-show attendance resolution columns are only partially present.');
        }
        if ($resolvedAt) {
            $this->assertColumn($context, 'attendanceResolvedAt', 'datetime', true, null, 6, null);
            $this->assertColumn($context, 'attendanceResolutionReason', 'varchar', true, 120, null, null);
        }
        $this->assertIndex($context, 'uq_activity_registrations_activity_student', true, ['activityId', 'studentId']);
        $this->assertForeignKey($context, 'fk_activity_registrations_activity', 'activityId', 'activities', 'id', 'NO ACTION', 'CASCADE');
        $this->assertForeignKey($context, 'fk_activity_registrations_student', 'studentId', 'student_profiles', 'id', 'NO ACTION', 'CASCADE');
        $this->assertCheck($context, 'chk_activity_registrations_cancellation', "((status = 'cancelled' AND cancelledAt IS NOT NULL) OR (status <> 'cancelled' AND cancelledAt IS NULL AND cancellationReason IS NULL))");
        $check = $this->checkClause($context, 'chk_activity_registrations_status');
        $old = $this->normalizeCheck("status IN('pending','approved','rejected','cancelled','attended','waitlisted')");
        $final = $this->normalizeCheck("status IN('pending','approved','rejected','cancelled','attended','waitlisted','no_show')");
        if (!in_array($check, [$old, $final], true)) {
            throw new RuntimeException('activity_registrations has an incompatible canonical status CHECK.');
        }
        $unsupported = $context->pdo()->query(<<<'SQL'
            SELECT COUNT(*) FROM activity_registrations
            WHERE status NOT IN ('pending','approved','rejected','cancelled','attended','waitlisted','no_show')
        SQL
        )?->fetchColumn();
        if ((int) $unsupported !== 0) {
            throw new RuntimeException('activity_registrations contains unsupported status values.');
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->hasColumn($context, 'attendanceResolvedAt')) {
            $context->execute(<<<'SQL'
                ALTER TABLE activity_registrations
                    ADD COLUMN attendanceResolvedAt DATETIME(6) NULL AFTER cancellationReason,
                    ADD COLUMN attendanceResolutionReason VARCHAR(120) NULL AFTER attendanceResolvedAt
            SQL
            );
        }
        $final = $this->normalizeCheck("status IN('pending','approved','rejected','cancelled','attended','waitlisted','no_show')");
        if (!hash_equals($final, $this->checkClause($context, 'chk_activity_registrations_status'))) {
            $context->execute(<<<'SQL'
                ALTER TABLE activity_registrations
                    DROP CHECK chk_activity_registrations_status,
                    ADD CONSTRAINT chk_activity_registrations_status
                    CHECK (status IN ('pending','approved','rejected','cancelled','attended','waitlisted','no_show'))
            SQL
            );
        }
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: resolved attendance states must remain auditable.
    }

    private function hasColumn(MigrationContext $context, string $column): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'activity_registrations\' AND column_name=:column');
        $statement->execute(['column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function assertColumn(MigrationContext $context, string $column, string $type, bool $nullable, ?int $length, ?int $precision, ?string $default): void
    {
        $statement = $context->pdo()->prepare('SELECT data_type,is_nullable,character_maximum_length,datetime_precision,column_default FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'activity_registrations\' AND column_name=:column');
        $statement->execute(['column' => $column]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row) || strtolower((string) $row['data_type']) !== $type || ((string) $row['is_nullable'] === 'YES') !== $nullable
            || ($row['character_maximum_length'] === null ? null : (int) $row['character_maximum_length']) !== $length
            || ($row['datetime_precision'] === null ? null : (int) $row['datetime_precision']) !== $precision
            || ($row['column_default'] === null ? null : strtoupper((string) $row['column_default'])) !== $default) {
            throw new RuntimeException("activity_registrations.{$column} has incompatible exact metadata.");
        }
    }

    /** @param list<string> $columns */
    private function assertIndex(MigrationContext $context, string $name, bool $unique, array $columns): void
    {
        $statement = $context->pdo()->prepare("SELECT non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_list FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='activity_registrations' AND index_name=:name GROUP BY non_unique");
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row) || (((int) $row['non_unique'] === 0) !== $unique) || (string) $row['columns_list'] !== implode(',', $columns)) {
            throw new RuntimeException("activity_registrations index {$name} is incompatible.");
        }
    }

    private function assertForeignKey(MigrationContext $context, string $name, string $column, string $table, string $referencedColumn, string $delete, string $update): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT kcu.column_name,kcu.referenced_table_name,kcu.referenced_column_name,rc.delete_rule,rc.update_rule
            FROM information_schema.key_column_usage kcu INNER JOIN information_schema.referential_constraints rc
            ON rc.constraint_schema=kcu.constraint_schema AND rc.constraint_name=kcu.constraint_name AND rc.table_name=kcu.table_name
            WHERE kcu.constraint_schema=DATABASE() AND kcu.table_name='activity_registrations' AND kcu.constraint_name=:name
        SQL
        );
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row) || (string) $row['column_name'] !== $column || (string) $row['referenced_table_name'] !== $table
            || (string) $row['referenced_column_name'] !== $referencedColumn || strtoupper((string) $row['delete_rule']) !== $delete
            || strtoupper((string) $row['update_rule']) !== $update) {
            throw new RuntimeException("activity_registrations foreign key {$name} is incompatible.");
        }
    }

    private function assertCheck(MigrationContext $context, string $name, string $expected): void
    {
        if (!hash_equals($this->normalizeCheck($expected), $this->checkClause($context, $name))) {
            throw new RuntimeException("activity_registrations CHECK {$name} is incompatible.");
        }
    }

    private function checkClause(MigrationContext $context, string $name): string
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT cc.check_clause FROM information_schema.table_constraints tc
            INNER JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name
            WHERE tc.table_schema=DATABASE() AND tc.table_name='activity_registrations' AND tc.constraint_name=:name AND tc.constraint_type='CHECK'
        SQL
        );
        $statement->execute(['name' => $name]);
        $clause = $statement->fetchColumn();
        if ($clause === false) {
            throw new RuntimeException("activity_registrations is missing canonical CHECK {$name}.");
        }
        return $this->normalizeCheck((string) $clause);
    }

    private function normalizeCheck(string $value): string
    {
        // MySQL INFORMATION_SCHEMA escapes quoted CHECK literals as \' in CHECK_CLAUSE.
        $value = str_replace(["_utf8mb4", "\\'"], ['', "'"], $value);
        return preg_replace('/[\s`()]+/', '', strtolower($value)) ?? '';
    }
};
