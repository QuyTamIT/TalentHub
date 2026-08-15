<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Create roles, permissions and schools'; }
    public function preflight(MigrationContext $c): void { foreach(['roles','permissions','schools'] as $t){$c->assertTableAbsent($t);} }
    public function up(MigrationContext $c): void {
        $c->execute("CREATE TABLE roles (id CHAR(36) NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(500) NULL, isSystem TINYINT UNSIGNED NOT NULL DEFAULT 1, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY uq_roles_code(code), CONSTRAINT chk_roles_is_system CHECK(isSystem IN(0,1))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $c->execute("CREATE TABLE permissions (id CHAR(36) NOT NULL, code VARCHAR(150) NOT NULL, description VARCHAR(500) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY uq_permissions_code(code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $c->execute("CREATE TABLE schools (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), KEY idx_schools_status(status), CONSTRAINT chk_schools_status CHECK(status IN('active','inactive','suspended'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(MigrationContext $c): void { $c->execute('DROP TABLE schools');$c->execute('DROP TABLE permissions');$c->execute('DROP TABLE roles'); }
};
