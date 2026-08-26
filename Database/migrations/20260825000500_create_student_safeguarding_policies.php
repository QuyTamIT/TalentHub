<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Create school safeguarding policy, guardian consent and enterprise approval records'; }

    public function preflight(MigrationContext $context): void
    {
        foreach (['schools', 'users', 'student_profiles', 'enterprises'] as $table) { $context->assertTableExists($table); }
        foreach (['student_safeguarding_policies', 'student_guardian_consents', 'student_enterprise_school_approvals'] as $table) { $context->assertTableAbsent($table); }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE student_safeguarding_policies (
  schoolId CHAR(36) NOT NULL,
  minimumDirectContactAge TINYINT UNSIGNED NOT NULL DEFAULT 18,
  guardianConsentRequired TINYINT UNSIGNED NOT NULL DEFAULT 1,
  schoolApprovalRequired TINYINT UNSIGNED NOT NULL DEFAULT 1,
  updatedByUserId CHAR(36) NOT NULL,
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (schoolId),
  CONSTRAINT fk_safeguarding_policy_school FOREIGN KEY (schoolId) REFERENCES schools(id),
  CONSTRAINT fk_safeguarding_policy_actor FOREIGN KEY (updatedByUserId) REFERENCES users(id),
  CONSTRAINT chk_safeguarding_direct_contact_age CHECK (minimumDirectContactAge BETWEEN 13 AND 25),
  CONSTRAINT chk_safeguarding_guardian_required CHECK (guardianConsentRequired IN (0, 1)),
  CONSTRAINT chk_safeguarding_school_required CHECK (schoolApprovalRequired IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $context->execute(<<<'SQL'
CREATE TABLE student_guardian_consents (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  grantedByUserId CHAR(36) NOT NULL,
  verificationMethod VARCHAR(50) NOT NULL,
  grantedAt DATETIME(6) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  PRIMARY KEY (id),
  KEY idx_guardian_consent_scope (studentId, enterpriseId, revokedAt, expiresAt),
  CONSTRAINT fk_guardian_consent_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
  CONSTRAINT fk_guardian_consent_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_guardian_consent_actor FOREIGN KEY (grantedByUserId) REFERENCES users(id),
  CONSTRAINT chk_guardian_consent_expiry CHECK (expiresAt > grantedAt),
  CONSTRAINT chk_guardian_consent_verification CHECK (verificationMethod IN ('verified_guardian_account','school_verified_document'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $context->execute(<<<'SQL'
CREATE TABLE student_enterprise_school_approvals (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  approvedByUserId CHAR(36) NOT NULL,
  approvedAt DATETIME(6) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  PRIMARY KEY (id),
  KEY idx_school_approval_scope (studentId, enterpriseId, revokedAt, expiresAt),
  CONSTRAINT fk_school_approval_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
  CONSTRAINT fk_school_approval_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_school_approval_actor FOREIGN KEY (approvedByUserId) REFERENCES users(id),
  CONSTRAINT chk_school_approval_expiry CHECK (expiresAt > approvedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        foreach (['safeguarding.read_own_school', 'safeguarding.update_own_school', 'safeguarding.approve_own_student'] as $code) {
            $permission = $context->pdo()->prepare('INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)');
            $permission->execute(['id' => \TalentHub\Support\Uuid::v4(), 'code' => $code, 'description' => 'School safeguarding permission: ' . $code]);
            $grant = $context->pdo()->prepare("INSERT IGNORE INTO role_permissions (roleId, permissionId) SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.code = :code WHERE r.code = 'school'");
            $grant->execute(['code' => $code]);
        }
    }

    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void { throw new RuntimeException('Safeguarding audit records must be retained.'); }
};
