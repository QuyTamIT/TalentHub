<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Support\Uuid;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create the school activity approval boundary and grant review permission';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activities', 'users', 'schools'] as $table) {
            $context->assertTableExists($table);
        }

        if ($this->hasColumn($context->pdo(), 'activities', 'approvalStatus')) {
            throw new RuntimeException('activities.approvalStatus already exists; manual reconciliation is required.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $pdo->exec(<<<'SQL'
ALTER TABLE activities
  ADD COLUMN approvalStatus VARCHAR(32) NOT NULL DEFAULT 'draft' AFTER visibility,
  ADD COLUMN approvalRequestedAt DATETIME(6) NULL AFTER approvalStatus,
  ADD COLUMN approvedAt DATETIME(6) NULL AFTER approvalRequestedAt,
  ADD COLUMN approvedBy CHAR(36) NULL AFTER approvedAt,
  ADD COLUMN approvalReason VARCHAR(1000) NULL AFTER approvedBy
SQL);

        // Preserve all activities that were already visible before this security gate existed.
        $pdo->exec(<<<'SQL'
UPDATE activities
SET approvalStatus = 'approved',
    approvedAt = COALESCE(updatedAt, createdAt, UTC_TIMESTAMP(6))
WHERE status IN ('published', 'ongoing', 'completed', 'archived')
SQL);

        $pdo->exec(<<<'SQL'
ALTER TABLE activities
  ADD KEY idx_activities_school_approval_status (schoolId, approvalStatus, status),
  ADD CONSTRAINT fk_activities_approved_by FOREIGN KEY (approvedBy) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT chk_activities_approval_status CHECK (approvalStatus IN ('draft','pending_school_review','changes_requested','approved','rejected'))
SQL);

        if ($this->tableExists($pdo, 'roles') && $this->tableExists($pdo, 'permissions') && $this->tableExists($pdo, 'role_permissions')) {
            foreach ([
                'activity.review_school' => 'Review activities owned by the authenticated school',
                'school_credential.manage_own' => 'Manage credentials issued by the authenticated school',
            ] as $code => $description) {
                $insert = $pdo->prepare('INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)');
                $insert->execute(['id' => Uuid::v4(), 'code' => $code, 'description' => $description]);
                $grant = $pdo->prepare("INSERT IGNORE INTO role_permissions (roleId, permissionId) SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.code=:code WHERE r.code IN ('school','platform_admin')");
                $grant->execute(['code' => $code]);
            }
        }
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Activity approval is a forward-only security boundary.');
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column LIMIT 1');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (bool) $statement->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table LIMIT 1');
        $statement->execute(['table' => $table]);
        return (bool) $statement->fetchColumn();
    }
};
