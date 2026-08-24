<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function description(): string
    {
        return 'Create notifications and learner notification preferences schema';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['users', 'student_profiles', 'roles', 'permissions', 'role_permissions'] as $table) {
            $context->assertTableExists($table);
        }

        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 8 notifications migration requires MySQL session time zone +00:00.');
        }

        if ($context->tableExists('notifications')) {
            $this->assertExistingNotificationsContract($context);
        }

        if ($context->tableExists('learner_notification_preferences')) {
            $this->assertExistingPreferencesContract($context);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$context->tableExists('notifications')) {
            $context->execute(<<<'SQL'
                CREATE TABLE notifications (
                    id CHAR(36) NOT NULL,
                    userId CHAR(36) NOT NULL,
                    eventKey VARCHAR(191) NULL,
                    notificationType VARCHAR(100) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    deepLink VARCHAR(500) NULL,
                    readAt DATETIME(6) NULL,
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_notifications_user_event (userId, eventKey),
                    KEY idx_notifications_user_timeline (userId, createdAt, id),
                    KEY idx_notifications_user_unread (userId, readAt, createdAt),
                    CONSTRAINT fk_notifications_user FOREIGN KEY (userId)
                        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$context->tableExists('learner_notification_preferences')) {
            $context->execute(<<<'SQL'
                CREATE TABLE learner_notification_preferences (
                    studentId CHAR(36) NOT NULL,
                    notificationType VARCHAR(100) NOT NULL,
                    inAppEnabled TINYINT(1) NOT NULL DEFAULT 1,
                    emailEnabled TINYINT(1) NOT NULL DEFAULT 0,
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (studentId, notificationType),
                    CONSTRAINT fk_learner_notification_preferences_student FOREIGN KEY (studentId)
                        REFERENCES student_profiles (id) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        // Add permission notification.manage_preferences_own if not present
        $permCode = 'notification.manage_preferences_own';
        $permDesc = 'TalentHub permission: notification / manage preferences own';
        $permId = self::stableId("permission:{$permCode}");

        $checkPerm = $context->pdo()->prepare('SELECT id FROM permissions WHERE code = :code');
        $checkPerm->execute(['code' => $permCode]);
        $existingPermId = $checkPerm->fetchColumn();

        if ($existingPermId === false) {
            $insertPerm = $context->pdo()->prepare(
                'INSERT INTO permissions (id, code, description) VALUES (:id, :code, :description)'
            );
            $insertPerm->execute([
                'id' => $permId,
                'code' => $permCode,
                'description' => $permDesc,
            ]);
            $effectivePermId = $permId;
        } else {
            $effectivePermId = (string) $existingPermId;
        }

        // Map to all 4 system roles
        $rolesStmt = $context->pdo()->query("SELECT id FROM roles WHERE code IN ('student', 'teacher', 'school', 'enterprise')");
        $roleIds = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

        $mapInsert = $context->pdo()->prepare(
            'INSERT IGNORE INTO role_permissions (roleId, permissionId) VALUES (:roleId, :permissionId)'
        );
        foreach ($roleIds as $roleId) {
            $mapInsert->execute([
                'roleId' => (string) $roleId,
                'permissionId' => $effectivePermId,
            ]);
        }
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only additive schema: notifications and preferences must be preserved.
    }

    private static function stableId(string $name): string
    {
        $namespace = hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        if ($namespace === false) {
            throw new RuntimeException('Invalid UUID namespace.');
        }
        $hash = sha1($namespace . $name);
        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    private function assertExistingNotificationsContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name, data_type, is_nullable, column_default, datetime_precision,
                   character_maximum_length, column_key
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'notifications'
        SQL);
        $statement->execute();
        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $columns[(string) $row['column_name']] = $row;
        }

        $expected = ['id', 'userId', 'eventKey', 'notificationType', 'title', 'message', 'deepLink', 'readAt', 'createdAt'];
        foreach ($expected as $col) {
            if (!isset($columns[$col])) {
                throw new RuntimeException("Existing notifications table missing column {$col}.");
            }
        }
    }

    private function assertExistingPreferencesContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name, data_type, is_nullable, column_default, datetime_precision,
                   character_maximum_length, column_key
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'learner_notification_preferences'
        SQL);
        $statement->execute();
        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $columns[(string) $row['column_name']] = $row;
        }

        $expected = ['studentId', 'notificationType', 'inAppEnabled', 'emailEnabled', 'updatedAt'];
        foreach ($expected as $col) {
            if (!isset($columns[$col])) {
                throw new RuntimeException("Existing learner_notification_preferences table missing column {$col}.");
            }
        }
    }
};
