<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Create school internship mentor assignments and school oversight permissions'; }

    public function preflight(MigrationContext $context): void
    {
        foreach (['internship_applications', 'teacher_profiles', 'users', 'roles', 'permissions', 'role_permissions'] as $table) {
            $context->assertTableExists($table);
        }
        $context->assertTableAbsent('internship_mentor_assignments');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE internship_mentor_assignments (
  id CHAR(36) NOT NULL,
  applicationId CHAR(36) NOT NULL,
  mentorTeacherId CHAR(36) NOT NULL,
  assignedByUserId CHAR(36) NOT NULL,
  assignedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_internship_mentor_application (applicationId),
  KEY idx_internship_mentor_teacher (mentorTeacherId, assignedAt),
  CONSTRAINT fk_internship_mentor_application FOREIGN KEY (applicationId) REFERENCES internship_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_internship_mentor_teacher FOREIGN KEY (mentorTeacherId) REFERENCES teacher_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_internship_mentor_assigner FOREIGN KEY (assignedByUserId) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $permissions = [
            'internship_application.read_own_school' => 'Read internship application oversight projection for own school',
            'internship_mentor.assign_own_school' => 'Assign same-school internship mentor',
        ];
        foreach ($permissions as $code => $description) {
            $statement = $context->pdo()->prepare('INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)');
            $statement->execute(['id' => \TalentHub\Support\Uuid::v4(), 'code' => $code, 'description' => $description]);
            $grant = $context->pdo()->prepare("INSERT IGNORE INTO role_permissions (roleId, permissionId) SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.code = :code WHERE r.code = 'school'");
            $grant->execute(['code' => $code]);
        }
    }

    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void { throw new RuntimeException('Internship mentor assignment history must be retained.'); }
};
