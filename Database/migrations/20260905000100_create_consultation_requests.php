<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Support\Uuid;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create public consultation requests and grant scoped Admin permissions';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['users', 'roles', 'permissions', 'role_permissions', 'auth_rate_limits'] as $table) {
            $context->assertTableExists($table);
        }
        $context->assertTableAbsent('consultation_requests');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE consultation_requests (
  id CHAR(36) NOT NULL,
  fullName VARCHAR(120) NOT NULL,
  audience VARCHAR(24) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(30) NULL,
  message TEXT NOT NULL,
  contactConsent TINYINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  idempotencyKey CHAR(64) NOT NULL,
  handledByUserId CHAR(36) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  completedAt DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_consultation_requests_idempotency (idempotencyKey),
  KEY idx_consultation_requests_queue (status, createdAt),
  CONSTRAINT fk_consultation_requests_handler FOREIGN KEY (handledByUserId) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_consultation_requests_audience CHECK (audience IN ('student','teacher','school','enterprise','other')),
  CONSTRAINT chk_consultation_requests_consent CHECK (contactConsent = 1),
  CONSTRAINT chk_consultation_requests_status CHECK (status IN ('new','in_progress','completed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        foreach (['admin.consultation.read', 'admin.consultation.update'] as $code) {
            $permission = $context->pdo()->prepare(
                'INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)'
            );
            $permission->execute([
                'id' => Uuid::v4(),
                'code' => $code,
                'description' => 'TalentHub consultation permission: ' . $code,
            ]);

            $grant = $context->pdo()->prepare(
                "INSERT IGNORE INTO role_permissions (roleId, permissionId)
                 SELECT roles.id, permissions.id
                 FROM roles
                 INNER JOIN permissions ON permissions.code = :code
                 WHERE roles.code = 'platform_admin'"
            );
            $grant->execute(['code' => $code]);
        }
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Consultation requests are retained records; this migration is forward-only.');
    }
};
