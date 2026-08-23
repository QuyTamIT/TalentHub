<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Validate exact Phase 8 notification schema and RBAC contracts';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $this->assertExactContract($context);
    }

    public function up(MigrationContext $context): void
    {
        // Forward-only validation marker. The applied 00600 migration is immutable;
        // this migration records that its live result passed the exact contract.
        $this->assertExactContract($context);
    }

    public function down(MigrationContext $context): void
    {
        // Validation history is intentionally irreversible.
    }

    private function assertExactContract(MigrationContext $context): void
    {
        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Phase 8 exact validation requires MySQL session time zone +00:00.');
        }

        foreach (['users', 'student_profiles', 'roles', 'permissions', 'role_permissions', 'notifications', 'learner_notification_preferences'] as $table) {
            $context->assertTableExists($table);
        }

        $this->assertTableOptions($context, 'notifications');
        $this->assertTableOptions($context, 'learner_notification_preferences');

        $this->assertColumns($context, 'notifications', [
            'id' => ['char(36)', 'NO', null, ''],
            'userId' => ['char(36)', 'NO', null, ''],
            'eventKey' => ['varchar(191)', 'YES', null, ''],
            'notificationType' => ['varchar(100)', 'NO', null, ''],
            'title' => ['varchar(255)', 'NO', null, ''],
            'message' => ['text', 'NO', null, ''],
            'deepLink' => ['varchar(500)', 'YES', null, ''],
            'readAt' => ['datetime(6)', 'YES', null, ''],
            'createdAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED'],
        ]);
        $this->assertColumns($context, 'learner_notification_preferences', [
            'studentId' => ['char(36)', 'NO', null, ''],
            'notificationType' => ['varchar(100)', 'NO', null, ''],
            'inAppEnabled' => ['tinyint(1)', 'NO', '1', ''],
            'emailEnabled' => ['tinyint(1)', 'NO', '0', ''],
            'updatedAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6)'],
        ]);

        $this->assertIndexes($context, 'notifications', [
            'PRIMARY' => [true, ['id']],
            'idx_notifications_user_timeline' => [false, ['userId', 'createdAt', 'id']],
            'idx_notifications_user_unread' => [false, ['userId', 'readAt', 'createdAt']],
            'uq_notifications_user_event' => [true, ['userId', 'eventKey']],
        ]);
        $this->assertIndexes($context, 'learner_notification_preferences', [
            'PRIMARY' => [true, ['studentId', 'notificationType']],
        ]);

        $this->assertForeignKeys($context, 'notifications', [
            'fk_notifications_user' => ['userId', 'users', 'id', 'RESTRICT', 'CASCADE'],
        ]);
        $this->assertForeignKeys($context, 'learner_notification_preferences', [
            'fk_learner_notification_preferences_student' => ['studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE'],
        ]);

        $this->assertPermissionContract($context);
    }

    private function assertTableOptions(MigrationContext $context, string $table): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT engine, table_collation
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :table AND table_type = 'BASE TABLE'
        SQL);
        $statement->execute(['table' => $table]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)
            || strtoupper((string) $row['ENGINE']) !== 'INNODB'
            || (string) $row['TABLE_COLLATION'] !== 'utf8mb4_unicode_ci'
        ) {
            throw new RuntimeException("{$table} has unexpected engine or collation metadata.");
        }
    }

    /** @param array<string, array{0:string,1:string,2:?string,3:string}> $expected */
    private function assertColumns(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name, column_type, is_nullable, column_default, extra
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY ordinal_position
        SQL);
        $statement->execute(['table' => $table]);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actual[(string) $row['COLUMN_NAME']] = [
                strtolower((string) $row['COLUMN_TYPE']),
                (string) $row['IS_NULLABLE'],
                $row['COLUMN_DEFAULT'] === null ? null : (string) $row['COLUMN_DEFAULT'],
                (string) $row['EXTRA'],
            ];
        }
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact column metadata.");
        }
    }

    /** @param array<string, array{0:bool,1:list<string>}> $expected */
    private function assertIndexes(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT index_name, non_unique, column_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY index_name, seq_in_index
        SQL);
        $statement->execute(['table' => $table]);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string) $row['INDEX_NAME'];
            if (!isset($actual[$name])) {
                $actual[$name] = [(int) $row['NON_UNIQUE'] === 0, []];
            }
            $actual[$name][1][] = (string) $row['COLUMN_NAME'];
        }
        ksort($actual);
        ksort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact index metadata.");
        }
    }

    /** @param array<string, array{0:string,1:string,2:string,3:string,4:string}> $expected */
    private function assertForeignKeys(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT rc.constraint_name, kcu.column_name, kcu.referenced_table_name,
                   kcu.referenced_column_name, rc.delete_rule, rc.update_rule
            FROM information_schema.referential_constraints rc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_schema = rc.constraint_schema
               AND kcu.table_name = rc.table_name
               AND kcu.constraint_name = rc.constraint_name
            WHERE rc.constraint_schema = DATABASE() AND rc.table_name = :table
            ORDER BY rc.constraint_name, kcu.ordinal_position
        SQL);
        $statement->execute(['table' => $table]);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actual[(string) $row['CONSTRAINT_NAME']] = [
                (string) $row['COLUMN_NAME'],
                (string) $row['REFERENCED_TABLE_NAME'],
                (string) $row['REFERENCED_COLUMN_NAME'],
                (string) $row['DELETE_RULE'],
                (string) $row['UPDATE_RULE'],
            ];
        }
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact foreign-key metadata.");
        }
    }

    private function assertPermissionContract(MigrationContext $context): void
    {
        $permission = $context->pdo()->prepare(
            'SELECT id, description FROM permissions WHERE code = :code'
        );
        $permission->execute(['code' => 'notification.manage_preferences_own']);
        $rows = $permission->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1
            || (string) $rows[0]['description'] !== 'TalentHub permission: notification / manage preferences own'
        ) {
            throw new RuntimeException('Phase 8 preference permission has unexpected metadata.');
        }

        $roles = $context->pdo()->prepare(<<<'SQL'
            SELECT r.code
            FROM role_permissions rp
            INNER JOIN roles r ON r.id = rp.roleId
            INNER JOIN permissions p ON p.id = rp.permissionId
            WHERE p.code = :code
              AND r.code IN ('student', 'teacher', 'school', 'enterprise')
            ORDER BY r.code
        SQL);
        $roles->execute(['code' => 'notification.manage_preferences_own']);
        $actualRoles = array_map('strval', $roles->fetchAll(PDO::FETCH_COLUMN));
        $expectedRoles = ['enterprise', 'school', 'student', 'teacher'];
        if ($actualRoles !== $expectedRoles) {
            throw new RuntimeException('Phase 8 preference permission is not mapped to all four canonical roles.');
        }
    }
};
