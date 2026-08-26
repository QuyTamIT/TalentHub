<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add school-scoped badge metadata and school certificate catalog';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['schools', 'badges', 'student_profiles', 'classes', 'users'] as $table) {
            $context->assertTableExists($table);
        }

        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('School credential migration requires MySQL session time zone +00:00.');
        }

        $targetTables = ['school_certificate_catalog', 'student_school_certificates'];
        $existing = array_values(array_filter($targetTables, static fn (string $table): bool => $context->tableExists($table)));
        if ($existing !== [] && count($existing) !== count($targetTables)) {
            throw new RuntimeException('School credential migration found a partial target schema: ' . implode(', ', $existing));
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        if (!$this->hasColumn($pdo, 'badges', 'schoolId')) {
            $pdo->exec('ALTER TABLE badges ADD COLUMN schoolId CHAR(36) NULL AFTER id');
        }
        if (!$this->hasColumn($pdo, 'badges', 'recommendationProfile')) {
            $pdo->exec('ALTER TABLE badges ADD COLUMN recommendationProfile JSON NULL AFTER description');
        }
        if (!$this->hasColumn($pdo, 'badges', 'recommendationEnabled')) {
            $pdo->exec("ALTER TABLE badges ADD COLUMN recommendationEnabled TINYINT(1) NOT NULL DEFAULT 0 AFTER recommendationProfile");
        }
        if (!$this->hasIndex($pdo, 'badges', 'idx_badges_school_status')) {
            $pdo->exec('ALTER TABLE badges ADD KEY idx_badges_school_status (schoolId, status)');
        }
        if (!$this->hasForeignKey($pdo, 'badges', 'fk_badges_school')) {
            $pdo->exec("ALTER TABLE badges ADD CONSTRAINT fk_badges_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }

        if (!$context->tableExists('school_certificate_catalog')) {
            $pdo->exec(<<<'SQL'
CREATE TABLE school_certificate_catalog (
  id CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  code VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  issuerName VARCHAR(255) NOT NULL,
  iconKey VARCHAR(50) NOT NULL DEFAULT 'certificate',
  eligibilityCriteria JSON NOT NULL,
  recommendationProfile JSON NOT NULL,
  recommendationEnabled TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_certificate_catalog_code (code),
  KEY idx_school_certificate_catalog_scope (schoolId, status, recommendationEnabled),
  CONSTRAINT fk_school_certificate_catalog_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_school_certificate_catalog_status CHECK (status IN ('active','inactive','deprecated')),
  CONSTRAINT chk_school_certificate_catalog_recommendation CHECK (recommendationEnabled IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if (!$context->tableExists('student_school_certificates')) {
            $pdo->exec(<<<'SQL'
CREATE TABLE student_school_certificates (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  certificateCatalogId CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'issued',
  issuedAt DATETIME(6) NOT NULL,
  issuedBy CHAR(36) NULL,
  evidenceContext JSON NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_school_certificate (studentId, certificateCatalogId),
  KEY idx_student_school_certificates_student_status (studentId, status, issuedAt),
  CONSTRAINT fk_student_school_certificates_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_student_school_certificates_catalog FOREIGN KEY (certificateCatalogId) REFERENCES school_certificate_catalog(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_student_school_certificates_issuer FOREIGN KEY (issuedBy) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_student_school_certificates_status CHECK (status IN ('issued','revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        foreach (['badges', 'school_certificate_catalog', 'student_school_certificates'] as $table) {
            if (!$context->tableExists($table)) {
                throw new RuntimeException("School credential migration failed to create {$table}.");
            }
        }
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: issued school credentials and metadata are protected evidence.
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (bool) $statement->fetchColumn();
    }

    private function hasIndex(PDO $pdo, string $table, string $index): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index LIMIT 1');
        $statement->execute(['table' => $table, 'index' => $index]);
        return (bool) $statement->fetchColumn();
    }

    private function hasForeignKey(PDO $pdo, string $table, string $constraint): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint LIMIT 1');
        $statement->execute(['table' => $table, 'constraint' => $constraint]);
        return (bool) $statement->fetchColumn();
    }
};
