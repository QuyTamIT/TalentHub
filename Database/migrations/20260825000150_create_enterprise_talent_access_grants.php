<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create enterprise talent access grants and contact requests, expand privacy consent scopes';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('users');
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('enterprises');
        $context->assertTableExists('privacy_consents');

        $timeZoneStatement = $context->pdo()->query('SELECT @@session.time_zone');
        $timeZone = $timeZoneStatement === false ? false : $timeZoneStatement->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new \RuntimeException('Enterprise talent access grants migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        // Update privacy_consents check constraint
        if ($this->hasCheckConstraint($context, 'privacy_consents', 'chk_privacy_consents_scope')) {
            $pdo->exec('ALTER TABLE privacy_consents DROP CHECK chk_privacy_consents_scope');
        }
        $pdo->exec(<<<'SQL'
ALTER TABLE privacy_consents ADD CONSTRAINT chk_privacy_consents_scope CHECK (
  scope IN (
    'assessment', 'skills', 'activity', 'evaluation', 'profile_share',
    'application_profile_share', 'enterprise_talent_discovery',
    'enterprise_talent_contact'
  )
);
SQL
        );

        // Create enterprise_talent_access_grants
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS enterprise_talent_access_grants (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  consentId CHAR(36) NOT NULL,
  scope VARCHAR(50) NOT NULL,
  grantedAt DATETIME(6) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_enterprise_talent_grant (studentId, enterpriseId, scope),
  KEY idx_enterprise_talent_grant_lookup (enterpriseId, scope, revokedAt, expiresAt),
  CONSTRAINT fk_enterprise_talent_grant_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enterprise_talent_grant_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enterprise_talent_grant_consent FOREIGN KEY (consentId) REFERENCES privacy_consents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_enterprise_talent_grant_scope CHECK (scope IN ('enterprise_talent_discovery', 'enterprise_talent_contact')),
  CONSTRAINT chk_enterprise_talent_grant_expiry CHECK (expiresAt > grantedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        // Create enterprise_contact_requests
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS enterprise_contact_requests (
  id CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  idempotencyKey CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  message VARCHAR(1000) NULL,
  requestedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_enterprise_contact_idempotency (enterpriseId, idempotencyKey),
  KEY idx_enterprise_contact_student_status (studentId, status),
  CONSTRAINT fk_enterprise_contact_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enterprise_contact_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_enterprise_contact_status CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only migration to protect consented student-enterprise relations and contact requests.
    }

    private function hasCheckConstraint(MigrationContext $context, string $table, string $constraintName): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND constraint_type = 'CHECK'
              AND constraint_name = :constraint_name
            LIMIT 1
        SQL
        );
        $statement->execute([
            'table_name' => $table,
            'constraint_name' => $constraintName,
        ]);
        return (bool) $statement->fetchColumn();
    }
};
