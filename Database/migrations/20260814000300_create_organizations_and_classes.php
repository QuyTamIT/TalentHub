<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Create enterprises and classes'; }
    public function preflight(MigrationContext $c): void { foreach(['enterprises','classes'] as $t){$c->assertTableAbsent($t);} }
    public function up(MigrationContext $c): void {
        $c->execute("CREATE TABLE enterprises (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', logoUrl VARCHAR(500) NULL, industry VARCHAR(150) NULL, description TEXT NULL, email VARCHAR(255) NULL, phone VARCHAR(30) NULL, website VARCHAR(500) NULL, address VARCHAR(500) NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending', verificationNote TEXT NULL, verifiedAt DATETIME(6) NULL, verifiedBy CHAR(36) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), KEY idx_enterprises_status(status), KEY idx_enterprises_verified_by(verifiedBy), CONSTRAINT fk_enterprises_verified_by FOREIGN KEY(verifiedBy) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT chk_enterprises_status CHECK(status IN('active','inactive','suspended')), CONSTRAINT chk_enterprises_verification CHECK(verificationStatus IN('pending','verified','rejected'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $c->execute("CREATE TABLE classes (id CHAR(36) NOT NULL, schoolId CHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, gradeLevel TINYINT UNSIGNED NOT NULL, academicYear VARCHAR(20) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), KEY idx_classes_school_status(schoolId,status), CONSTRAINT fk_classes_school FOREIGN KEY(schoolId) REFERENCES schools(id), CONSTRAINT chk_classes_grade CHECK(gradeLevel BETWEEN 1 AND 12), CONSTRAINT chk_classes_status CHECK(status IN('active','archived'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(MigrationContext $c): void { $c->execute('DROP TABLE classes');$c->execute('DROP TABLE enterprises'); }
};
