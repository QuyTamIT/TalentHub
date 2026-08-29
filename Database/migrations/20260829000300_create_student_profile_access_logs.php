<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Support\Uuid;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create enterprise profile access audit trail visible to the owning school';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['enterprises', 'student_profiles', 'users', 'roles', 'permissions', 'role_permissions'] as $table) {
            $context->assertTableExists($table);
        }
        $context->assertTableAbsent('student_profile_access_logs');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            CREATE TABLE student_profile_access_logs (
                id CHAR(36) NOT NULL,
                enterpriseId CHAR(36) NOT NULL,
                studentId CHAR(36) NOT NULL,
                accessedByUserId CHAR(36) NULL,
                accessType VARCHAR(50) NOT NULL DEFAULT 'talent_detail',
                requestId VARCHAR(64) NULL,
                ipAddress VARCHAR(45) NULL,
                metadata JSON NULL,
                accessedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_profile_access_student_time (studentId, accessedAt),
                KEY idx_profile_access_enterprise_time (enterpriseId, accessedAt),
                KEY idx_profile_access_actor_time (accessedByUserId, accessedAt),
                CONSTRAINT fk_profile_access_enterprise
                    FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_profile_access_student
                    FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_profile_access_actor
                    FOREIGN KEY (accessedByUserId) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT chk_profile_access_type
                    CHECK (accessType IN ('talent_detail','application_cv','shared_profile')),
                CONSTRAINT chk_profile_access_metadata
                    CHECK (metadata IS NULL OR JSON_VALID(metadata))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $permissionCode = 'school_profile_access_log.read_own_school';
        $permission = $context->pdo()->prepare(
            'INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)'
        );
        $permission->execute([
            'id' => Uuid::v4(),
            'code' => $permissionCode,
            'description' => 'Read enterprise profile access logs for students in own school',
        ]);

        $grant = $context->pdo()->prepare(<<<'SQL'
            INSERT IGNORE INTO role_permissions (roleId, permissionId)
            SELECT r.id, p.id
            FROM roles r
            INNER JOIN permissions p ON p.code = :code
            WHERE r.code = 'school'
        SQL);
        $grant->execute(['code' => $permissionCode]);
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Student profile access audit history must be retained.');
    }
};
