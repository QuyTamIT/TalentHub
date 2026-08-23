<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create activity experience policies for automatic confirmed check-ins';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activities', 'activity_qr_sessions', 'activity_registrations', 'checkins', 'experience_logs', 'audit_logs'] as $table) {
            $context->assertTableExists($table);
        }
        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 5 experience policy migration requires MySQL session time zone +00:00.');
        }
        if ($context->tableExists('activity_experience_policies')) {
            $this->assertExistingPolicyContract($context);
            return;
        }
        if ($this->semanticEquivalentExists($context)) {
            throw new RuntimeException('A semantic equivalent activity experience policy table already exists.');
        }
        $this->assertIndex($context, 'checkins', 'uq_checkins_registration', true, ['registrationId']);
        $this->assertIndex($context, 'experience_logs', 'uq_experience_logs_checkin', true, ['checkinId']);
    }

    public function up(MigrationContext $context): void
    {
        if (!$context->tableExists('activity_experience_policies')) {
            $context->execute(<<<'SQL'
                CREATE TABLE activity_experience_policies (
                    activityId CHAR(36) NOT NULL,
                    confirmedHours DECIMAL(7,2) NOT NULL,
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (activityId),
                    CONSTRAINT fk_activity_experience_policies_activity
                        FOREIGN KEY (activityId) REFERENCES activities(id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT chk_activity_experience_policies_hours
                        CHECK (confirmedHours >= 0 AND confirmedHours <= 24)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only additive schema: check-in history must keep its policy anchor.
    }

    private function semanticEquivalentExists(MigrationContext $context): bool
    {
        if ($context->tableExists('activity_experience_policies')) {
            return false;
        }
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT table_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND column_name IN ('activityId', 'confirmedHours')
            GROUP BY table_name
            HAVING COUNT(DISTINCT column_name) = 2
        SQL);
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (str_contains((string) $table, 'experience') && str_contains((string) $table, 'polic')) {
                return true;
            }
        }
        return false;
    }

    private function assertExistingPolicyContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name, data_type, is_nullable, column_default, datetime_precision,
                   character_maximum_length, numeric_precision, numeric_scale, column_key, extra
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'activity_experience_policies'
        SQL);
        $statement->execute();
        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $columns[(string) $row['column_name']] = $row;
        }
        if (count($columns) !== 4) {
            throw new RuntimeException('Existing activity_experience_policies table must contain exactly four canonical columns.');
        }
        foreach (['activityId', 'confirmedHours', 'createdAt', 'updatedAt'] as $column) {
            if (!isset($columns[$column])) {
                throw new RuntimeException('Existing activity_experience_policies table is missing canonical column ' . $column . '.');
            }
        }
        if (strtolower((string) $columns['activityId']['data_type']) !== 'char'
            || (int) ($columns['activityId']['character_maximum_length'] ?? 36) !== 36
            || (string) $columns['activityId']['is_nullable'] !== 'NO') {
            throw new RuntimeException('Existing activity_experience_policies.activityId has incompatible exact metadata.');
        }
        if (strtolower((string) $columns['confirmedHours']['data_type']) !== 'decimal'
            || (int) ($columns['confirmedHours']['numeric_precision'] ?? 7) !== 7
            || (int) ($columns['confirmedHours']['numeric_scale'] ?? 2) !== 2
            || (string) $columns['confirmedHours']['is_nullable'] !== 'NO') {
            throw new RuntimeException('Existing activity_experience_policies.confirmedHours has incompatible exact metadata.');
        }
        if ((string) $columns['activityId']['column_key'] !== 'PRI') {
            throw new RuntimeException('Existing activity_experience_policies.activityId must remain the primary key.');
        }

        foreach (['createdAt', 'updatedAt'] as $timestamp) {
            if (strtolower((string) $columns[$timestamp]['data_type']) !== 'datetime'
                || (int) ($columns[$timestamp]['datetime_precision'] ?? -1) !== 6
                || (string) $columns[$timestamp]['is_nullable'] !== 'NO') {
                throw new RuntimeException("Existing activity_experience_policies.{$timestamp} has incompatible exact metadata.");
            }
        }

        $table = $context->pdo()->query("SELECT engine, table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='activity_experience_policies'")?->fetch(PDO::FETCH_ASSOC);
        if (is_array($table)) {
            $table = array_change_key_case($table, CASE_LOWER);
        }
        if (!is_array($table) || strtoupper((string) $table['engine']) !== 'INNODB' || (string) $table['table_collation'] !== 'utf8mb4_unicode_ci') {
            throw new RuntimeException('Existing activity_experience_policies table engine or collation is incompatible.');
        }

        $foreignKey = $context->pdo()->query(<<<'SQL'
            SELECT rc.constraint_name, rc.update_rule, rc.delete_rule,
                   kcu.referenced_table_name, kcu.referenced_column_name
            FROM information_schema.referential_constraints rc
            INNER JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_schema = rc.constraint_schema
             AND kcu.constraint_name = rc.constraint_name
             AND kcu.table_name = rc.table_name
            WHERE rc.constraint_schema = DATABASE()
              AND rc.table_name = 'activity_experience_policies'
              AND kcu.column_name = 'activityId'
        SQL)?->fetch(PDO::FETCH_ASSOC);
        if (is_array($foreignKey)) {
            $foreignKey = array_change_key_case($foreignKey, CASE_LOWER);
        }
        if (!is_array($foreignKey)
            || (string) $foreignKey['constraint_name'] !== 'fk_activity_experience_policies_activity'
            || (string) $foreignKey['update_rule'] !== 'CASCADE'
            || (string) $foreignKey['delete_rule'] !== 'CASCADE'
            || (string) $foreignKey['referenced_table_name'] !== 'activities'
            || (string) $foreignKey['referenced_column_name'] !== 'id') {
            throw new RuntimeException('Existing activity_experience_policies foreign key is incompatible.');
        }

        $check = $context->pdo()->query(<<<'SQL'
            SELECT cc.check_clause
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.check_constraints cc
              ON cc.constraint_schema = tc.constraint_schema
             AND cc.constraint_name = tc.constraint_name
            WHERE tc.constraint_schema = DATABASE()
              AND tc.table_name = 'activity_experience_policies'
              AND tc.constraint_name = 'chk_activity_experience_policies_hours'
              AND tc.constraint_type = 'CHECK'
        SQL)?->fetchColumn();
        $normalizedCheck = strtolower(preg_replace('/[\s`()]+/', '', (string) $check) ?? '');
        if (!str_contains($normalizedCheck, 'confirmedhours>=0') || !str_contains($normalizedCheck, 'confirmedhours<=24')) {
            throw new RuntimeException('Existing activity_experience_policies hours CHECK is incompatible.');
        }
    }

    /** @param list<string> $columns */
    private function assertIndex(MigrationContext $context, string $table, string $index, bool $unique, array $columns): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_list
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name
            GROUP BY non_unique
        SQL);
        $statement->execute(['table' => $table, 'index_name' => $index]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException("{$table} is missing required replay barrier {$index}.");
        }
        $row = array_change_key_case($row, CASE_LOWER);
        if (((int) $row['non_unique'] === 0) !== $unique || (string) $row['columns_list'] !== implode(',', $columns)) {
            throw new RuntimeException("{$table}.{$index} replay barrier metadata is incompatible.");
        }
    }
};
