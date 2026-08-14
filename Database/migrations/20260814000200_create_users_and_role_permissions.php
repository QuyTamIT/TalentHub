<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Create users and role permission mappings'; }
    public function preflight(MigrationContext $c): void { foreach(['users','role_permissions'] as $t){$c->assertTableAbsent($t);} }
    public function up(MigrationContext $c): void {
        $c->execute("CREATE TABLE users (id CHAR(36) NOT NULL, roleId CHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, passwordHash VARCHAR(255) NOT NULL, fullName VARCHAR(150) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', lastLoginAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY uq_users_email(email), KEY idx_users_role_status(roleId,status), CONSTRAINT fk_users_role FOREIGN KEY(roleId) REFERENCES roles(id), CONSTRAINT chk_users_status CHECK(status IN('pending','active','suspended','disabled'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $c->execute("CREATE TABLE role_permissions (roleId CHAR(36) NOT NULL, permissionId CHAR(36) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(roleId,permissionId), KEY idx_role_permissions_permission(permissionId), CONSTRAINT fk_role_permissions_role FOREIGN KEY(roleId) REFERENCES roles(id) ON DELETE CASCADE, CONSTRAINT fk_role_permissions_permission FOREIGN KEY(permissionId) REFERENCES permissions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(MigrationContext $c): void { $c->execute('DROP TABLE role_permissions');$c->execute('DROP TABLE users'); }
};
