<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const MANAGED_PERMISSION_ID = '88761865-f38a-5427-b727-6a1acc983a49';

    public function description(): string
    {
        return 'Extend activity registration cancellation, waitlist, policy, and Teacher transition contracts';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activity_registrations', 'activities', 'permissions', 'roles', 'role_permissions'] as $table) {
            $this->assertTableExists($context, $table);
        }

        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 4 registration migration requires MySQL session time zone +00:00.');
        }

        $this->assertColumn($context, 'activity_registrations', 'id', 'char', 36, false, null, null);
        $this->assertColumn($context, 'activity_registrations', 'activityId', 'char', 36, false, null, null);
        $this->assertColumn($context, 'activity_registrations', 'studentId', 'char', 36, false, null, null);
        $this->assertColumn($context, 'activity_registrations', 'status', 'varchar', 50, false, 'pending', null);
        $this->assertColumn($context, 'activity_registrations', 'registeredAt', 'datetime', null, false, 'CURRENT_TIMESTAMP(6)', 6);
        $this->assertColumn($context, 'activity_registrations', 'updatedAt', 'datetime', null, false, 'CURRENT_TIMESTAMP(6)', 6);

        if ($this->hasColumn($context, 'activity_registrations', 'cancelledAt')) {
            $this->assertColumn($context, 'activity_registrations', 'cancelledAt', 'datetime', null, true, null, 6);
        }
        if ($this->hasColumn($context, 'activity_registrations', 'cancellationReason')) {
            $this->assertColumn($context, 'activity_registrations', 'cancellationReason', 'varchar', 500, true, null, null);
        }
        if ($this->hasColumn($context, 'activity_registrations', 'cancelledAt')
            xor $this->hasColumn($context, 'activity_registrations', 'cancellationReason')) {
            throw new RuntimeException('Phase 4 cancellation columns are only partially present.');
        }

        $this->assertIndex($context, 'activity_registrations', 'uq_activity_registrations_activity_student', true, ['activityId', 'studentId']);
        $this->assertForeignKey($context, 'activity_registrations', 'fk_activity_registrations_activity', 'activityId', 'activities', 'id', 'NO ACTION', 'CASCADE');
        $this->assertForeignKey($context, 'activity_registrations', 'fk_activity_registrations_student', 'studentId', 'student_profiles', 'id', 'NO ACTION', 'CASCADE');

        $statusCheck = $this->checkClause($context, 'activity_registrations', 'chk_activity_registrations_status');
        $oldStatusCheck = $this->normalizeCheck("status IN('pending','approved','rejected','cancelled','attended')");
        $newStatusCheck = $this->normalizeCheck("status IN('pending','approved','rejected','cancelled','attended','waitlisted')");
        if (!in_array($statusCheck, [$oldStatusCheck, $newStatusCheck], true)) {
            throw new RuntimeException('activity_registrations has an incompatible canonical status CHECK.');
        }

        $unexpectedStatus = $context->pdo()->query(<<<'SQL'
            SELECT COUNT(*) FROM activity_registrations
            WHERE status NOT IN ('pending','approved','rejected','cancelled','attended','waitlisted')
        SQL
        )?->fetchColumn();
        if ((int) $unexpectedStatus !== 0) {
            throw new RuntimeException('activity_registrations contains unsupported status values.');
        }

        $orphans = $context->pdo()->query(<<<'SQL'
            SELECT COUNT(*)
            FROM activity_registrations registration
            LEFT JOIN activities activity ON activity.id = registration.activityId
            LEFT JOIN student_profiles student ON student.id = registration.studentId
            WHERE activity.id IS NULL OR student.id IS NULL
        SQL
        )?->fetchColumn();
        if ((int) $orphans !== 0) {
            throw new RuntimeException('activity_registrations contains orphan rows.');
        }

        if ($this->hasColumn($context, 'activity_registrations', 'cancelledAt')) {
            $invalidCancellation = $context->pdo()->query(<<<'SQL'
                SELECT COUNT(*) FROM activity_registrations
                WHERE (status <> 'cancelled' AND (cancelledAt IS NOT NULL OR cancellationReason IS NOT NULL))
                   OR (status = 'cancelled' AND cancelledAt IS NULL AND cancellationReason IS NOT NULL)
            SQL
            )?->fetchColumn();
            if ((int) $invalidCancellation !== 0) {
                throw new RuntimeException('activity_registrations contains incompatible cancellation metadata.');
            }
        }

        if ($context->tableExists('activity_registration_policies')) {
            $this->assertPolicyContract($context);
        }

        $permissionIdCollision = $context->pdo()->prepare(
            'SELECT COUNT(*) FROM permissions WHERE id = :id AND code <> :code'
        );
        $permissionIdCollision->execute([
            'id' => self::MANAGED_PERMISSION_ID,
            'code' => 'activity_registration.update_managed',
        ]);
        if ((int) $permissionIdCollision->fetchColumn() !== 0) {
            throw new RuntimeException('Managed registration permission ID is already used by another permission.');
        }

        $invalidPermissionMapping = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM role_permissions mapping
            INNER JOIN roles role ON role.id = mapping.roleId
            INNER JOIN permissions permission ON permission.id = mapping.permissionId
            WHERE permission.code = :permission
              AND role.code <> 'teacher'
        SQL
        );
        $invalidPermissionMapping->execute(['permission' => 'activity_registration.update_managed']);
        if ((int) $invalidPermissionMapping->fetchColumn() !== 0) {
            throw new RuntimeException('Managed registration transition permission is mapped outside Teacher.');
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->hasColumn($context, 'activity_registrations', 'cancelledAt')) {
            $context->execute('ALTER TABLE activity_registrations ADD COLUMN cancelledAt DATETIME(6) NULL AFTER updatedAt');
        }
        if (!$this->hasColumn($context, 'activity_registrations', 'cancellationReason')) {
            $context->execute('ALTER TABLE activity_registrations ADD COLUMN cancellationReason VARCHAR(500) NULL AFTER cancelledAt');
        }

        $context->execute(<<<'SQL'
            UPDATE activity_registrations
            SET cancelledAt = COALESCE(cancelledAt, updatedAt),
                cancellationReason = 'legacy_migration'
            WHERE status = 'cancelled'
              AND (cancelledAt IS NULL OR cancellationReason IS NULL)
        SQL
        );

        $statusCheck = $this->checkClause($context, 'activity_registrations', 'chk_activity_registrations_status');
        $newStatusCheck = $this->normalizeCheck("status IN('pending','approved','rejected','cancelled','attended','waitlisted')");
        if (!hash_equals($newStatusCheck, $statusCheck)) {
            $context->execute('ALTER TABLE activity_registrations DROP CHECK chk_activity_registrations_status');
            $context->execute("ALTER TABLE activity_registrations ADD CONSTRAINT chk_activity_registrations_status CHECK(status IN('pending','approved','rejected','cancelled','attended','waitlisted'))");
        }

        if (!$this->hasConstraint($context, 'activity_registrations', 'chk_activity_registrations_cancellation')) {
            $context->execute(<<<'SQL'
                ALTER TABLE activity_registrations
                ADD CONSTRAINT chk_activity_registrations_cancellation CHECK(
                    (status = 'cancelled' AND cancelledAt IS NOT NULL)
                    OR
                    (status <> 'cancelled' AND cancelledAt IS NULL AND cancellationReason IS NULL)
                )
            SQL
            );
        }

        if (!$context->tableExists('activity_registration_policies')) {
            $context->execute(<<<'SQL'
                CREATE TABLE activity_registration_policies (
                    activityId CHAR(36) NOT NULL,
                    registrationOpensAt DATETIME(6) NOT NULL,
                    registrationClosesAt DATETIME(6) NOT NULL,
                    cancellationClosesAt DATETIME(6) NOT NULL,
                    approvalMode VARCHAR(32) NOT NULL DEFAULT 'automatic',
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (activityId),
                    KEY idx_activity_registration_policies_close (registrationClosesAt),
                    CONSTRAINT fk_activity_registration_policies_activity
                        FOREIGN KEY (activityId) REFERENCES activities(id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT chk_activity_registration_policies_approval
                        CHECK (approvalMode IN ('automatic','teacher_review')),
                    CONSTRAINT chk_activity_registration_policies_window
                        CHECK (registrationOpensAt <= registrationClosesAt)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
            );
        }

        $permission = $context->pdo()->prepare(<<<'SQL'
            INSERT INTO permissions (id, code, description, createdAt, updatedAt)
            VALUES (:id, :code, :description, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE description = VALUES(description), updatedAt = updatedAt
        SQL
        );
        $permission->execute([
            'id' => self::MANAGED_PERMISSION_ID,
            'code' => 'activity_registration.update_managed',
            'description' => 'TalentHub permission: activity registration / update managed',
        ]);

        $context->execute(<<<'SQL'
            INSERT IGNORE INTO role_permissions (roleId, permissionId, createdAt)
            SELECT role.id, permission.id, CURRENT_TIMESTAMP(6)
            FROM roles role
            INNER JOIN permissions permission ON permission.code = 'activity_registration.update_managed'
            WHERE role.code = 'teacher'
        SQL
        );
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: registration identity, cancellation history, and waitlist state must be preserved.
    }

    private function assertTableExists(MigrationContext $context, string $table): void
    {
        $context->assertTableExists($table);
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function assertColumn(
        MigrationContext $context,
        string $table,
        string $column,
        string $type,
        ?int $length,
        bool $nullable,
        ?string $default,
        ?int $precision,
    ): void {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT data_type, character_maximum_length, is_nullable, column_default, datetime_precision
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException("{$table} is missing canonical column {$column}.");
        }
        $row = array_change_key_case($row, CASE_LOWER);
        $actualDefault = $row['column_default'] === null ? null : strtoupper((string) $row['column_default']);
        $expectedDefault = $default === null ? null : strtoupper($default);
        if (strtolower((string) $row['data_type']) !== $type
            || ($row['character_maximum_length'] === null ? null : (int) $row['character_maximum_length']) !== $length
            || ((string) $row['is_nullable'] === 'YES') !== $nullable
            || $actualDefault !== $expectedDefault
            || ($row['datetime_precision'] === null ? null : (int) $row['datetime_precision']) !== $precision) {
            throw new RuntimeException("{$table}.{$column} has incompatible canonical metadata.");
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
        SQL
        );
        $statement->execute(['table' => $table, 'index_name' => $index]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException("{$table} is missing canonical index {$index}.");
        }
        $row = array_change_key_case($row, CASE_LOWER);
        if (((int) $row['non_unique'] === 0) !== $unique || (string) $row['columns_list'] !== implode(',', $columns)) {
            throw new RuntimeException("{$table} has incompatible canonical index {$index}.");
        }
    }

    private function assertForeignKey(
        MigrationContext $context,
        string $table,
        string $constraint,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $deleteRule,
        string $updateRule,
    ): void {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT key_usage.column_name, key_usage.referenced_table_name, key_usage.referenced_column_name,
                   relation.delete_rule, relation.update_rule
            FROM information_schema.key_column_usage key_usage
            INNER JOIN information_schema.referential_constraints relation
              ON relation.constraint_schema = key_usage.constraint_schema
             AND relation.table_name = key_usage.table_name
             AND relation.constraint_name = key_usage.constraint_name
            WHERE key_usage.constraint_schema = DATABASE()
              AND key_usage.table_name = :table
              AND key_usage.constraint_name = :constraint_name
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException("{$table} is missing canonical foreign key {$constraint}.");
        }
        $row = array_change_key_case($row, CASE_LOWER);
        if ((string) $row['column_name'] !== $column
            || (string) $row['referenced_table_name'] !== $referencedTable
            || (string) $row['referenced_column_name'] !== $referencedColumn
            || strtoupper((string) $row['delete_rule']) !== $deleteRule
            || strtoupper((string) $row['update_rule']) !== $updateRule) {
            throw new RuntimeException("{$table} has incompatible canonical foreign key {$constraint}.");
        }
    }

    private function checkClause(MigrationContext $context, string $table, string $constraint): string
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT checks.check_clause
            FROM information_schema.table_constraints constraints
            INNER JOIN information_schema.check_constraints checks
              ON checks.constraint_schema = constraints.constraint_schema
             AND checks.constraint_name = constraints.constraint_name
            WHERE constraints.table_schema = DATABASE()
              AND constraints.table_name = :table
              AND constraints.constraint_name = :constraint_name
              AND constraints.constraint_type = 'CHECK'
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        $clause = $statement->fetchColumn();
        if ($clause === false) {
            throw new RuntimeException("{$table} is missing canonical CHECK {$constraint}.");
        }
        return $this->normalizeCheck((string) $clause);
    }

    private function normalizeCheck(string $clause): string
    {
        $normalized = strtolower($clause);
        $normalized = str_replace(['`', '_utf8mb4', '(', ')', '\\'], '', $normalized);
        return preg_replace('/\s+/', '', $normalized) ?? '';
    }

    private function hasConstraint(MigrationContext $context, string $table, string $constraint): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :constraint_name
        SQL
        );
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function assertPolicyContract(MigrationContext $context): void
    {
        foreach ([
            ['activityId', 'char', 36, false, null, null],
            ['registrationOpensAt', 'datetime', null, false, null, 6],
            ['registrationClosesAt', 'datetime', null, false, null, 6],
            ['cancellationClosesAt', 'datetime', null, false, null, 6],
            ['approvalMode', 'varchar', 32, false, 'automatic', null],
            ['createdAt', 'datetime', null, false, 'CURRENT_TIMESTAMP(6)', 6],
            ['updatedAt', 'datetime', null, false, 'CURRENT_TIMESTAMP(6)', 6],
        ] as [$column, $type, $length, $nullable, $default, $precision]) {
            $this->assertColumn($context, 'activity_registration_policies', $column, $type, $length, $nullable, $default, $precision);
        }
        $this->assertIndex($context, 'activity_registration_policies', 'PRIMARY', true, ['activityId']);
        $this->assertForeignKey($context, 'activity_registration_policies', 'fk_activity_registration_policies_activity', 'activityId', 'activities', 'id', 'CASCADE', 'CASCADE');
    }
};
