<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Create audit logs'; }
    public function preflight(MigrationContext $c): void { $c->assertTableAbsent('audit_logs'); }
    public function up(MigrationContext $c): void { $c->execute("CREATE TABLE audit_logs (id CHAR(36) NOT NULL, userId CHAR(36) NULL, action VARCHAR(150) NOT NULL, entityType VARCHAR(100) NULL, entityId CHAR(36) NULL, requestId CHAR(26) NULL, ipAddress VARCHAR(45) NULL, metadata JSON NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id), KEY idx_audit_logs_user_created(userId,createdAt), KEY idx_audit_logs_entity_created(entityType,entityId,createdAt), KEY idx_audit_logs_request(requestId), CONSTRAINT fk_audit_logs_user FOREIGN KEY(userId) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); }
    public function down(MigrationContext $c): void { $c->execute('DROP TABLE audit_logs'); }
};
