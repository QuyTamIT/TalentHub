<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create school enterprise partnerships, internship post audience and target schools';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('schools');
        $context->assertTableExists('enterprises');
        $context->assertTableExists('users');
        $context->assertTableExists('internship_posts');

        $timeZoneStatement = $context->pdo()->query('SELECT @@session.time_zone');
        $timeZone = $timeZoneStatement === false ? false : $timeZoneStatement->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new \RuntimeException('School enterprise partnerships migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        // 1. Create school_enterprise_partnerships table
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS school_enterprise_partnerships (
  id CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  requestedByUserId CHAR(36) NOT NULL,
  reviewedByUserId CHAR(36) NULL,
  reviewedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_enterprise_partnership (schoolId, enterpriseId),
  KEY idx_school_enterprise_partnership_lookup (enterpriseId, status),
  KEY idx_school_enterprise_partnership_school (schoolId, status),
  CONSTRAINT fk_school_enterprise_partnership_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_school_enterprise_partnership_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_school_enterprise_partnership_requester FOREIGN KEY (requestedByUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_school_enterprise_partnership_reviewer FOREIGN KEY (reviewedByUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_school_enterprise_partnership_status CHECK (status IN ('pending', 'approved', 'rejected', 'suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        // 2. Add audience column to internship_posts if not exists
        if (!$this->hasColumn($context, 'internship_posts', 'audience')) {
            $pdo->exec(<<<'SQL'
ALTER TABLE internship_posts
  ADD COLUMN audience VARCHAR(20) NOT NULL DEFAULT 'public' AFTER status,
  ADD CONSTRAINT chk_internship_post_audience CHECK (audience IN ('public', 'partner_schools')),
  ADD INDEX idx_internship_post_audience_status (audience, status, enterpriseId);
SQL
            );
        }

        // 3. Create internship_post_target_schools table
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS internship_post_target_schools (
  postId CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (postId, schoolId),
  KEY idx_internship_post_target_school (schoolId, postId),
  CONSTRAINT fk_internship_post_target_post FOREIGN KEY (postId) REFERENCES internship_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_internship_post_target_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        // 1. Drop internship_post_target_schools table
        $pdo->exec('DROP TABLE IF EXISTS internship_post_target_schools');

        // 2. Drop audience column and index from internship_posts
        if ($this->hasColumn($context, 'internship_posts', 'audience')) {
            try {
                $pdo->exec('ALTER TABLE internship_posts DROP INDEX idx_internship_post_audience_status');
            } catch (\Throwable) {}

            try {
                $pdo->exec('ALTER TABLE internship_posts DROP CONSTRAINT chk_internship_post_audience');
            } catch (\Throwable) {}

            $pdo->exec('ALTER TABLE internship_posts DROP COLUMN audience');
        }

        // 3. Drop school_enterprise_partnerships table
        $pdo->exec('DROP TABLE IF EXISTS school_enterprise_partnerships');
    }

    private function hasColumn(MigrationContext $context, string $tableName, string $columnName): bool
    {
        $statement = $context->pdo()->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1');
        $statement->execute(['table_name' => $tableName, 'column_name' => $columnName]);
        return (bool) $statement->fetchColumn();
    }
};
