<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Create reports table for school report generator'; }
    public function preflight(MigrationContext $c): void { $c->assertTableAbsent('reports'); }
    public function up(MigrationContext $c): void {
        $c->execute("CREATE TABLE reports (
            id CHAR(36) NOT NULL,
            schoolId CHAR(36) NOT NULL,
            generatedByUserId CHAR(36) NOT NULL,
            reportType VARCHAR(50) NOT NULL,
            fileUrl VARCHAR(500) NOT NULL,
            periodStart DATE NOT NULL,
            periodEnd DATE NOT NULL,
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            PRIMARY KEY(id),
            KEY idx_reports_school_created(schoolId, createdAt),
            KEY idx_reports_type(reportType),
            CONSTRAINT fk_reports_school FOREIGN KEY(schoolId) REFERENCES schools(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_reports_user FOREIGN KEY(generatedByUserId) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT chk_reports_period CHECK(periodEnd >= periodStart)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(MigrationContext $c): void { $c->execute('DROP TABLE reports'); }
};