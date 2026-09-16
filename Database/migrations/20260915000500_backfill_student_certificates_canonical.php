<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

/**
 * Backfill the canonical student_certificates table from the legacy
 * student_school_certificates table.
 *
 * Migration 20260913000100 created student_certificates and copied the data that
 * existed at that moment, but the demo seeder and the BTEC activation script kept
 * writing to the legacy table afterwards, so the canonical table drifted behind.
 * SchoolCredentialManagementRepository and DatabaseSchoolCredentialRepository now
 * both use student_certificates as the single source of truth, so the backlog has
 * to be reconciled before learners can see their certificates again.
 *
 * Idempotent: INSERT IGNORE on the (studentId, certificateCatalogId) unique key.
 */
return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Backfill canonical student_certificates from legacy student_school_certificates';
    }

    public function isReversible(): bool
    {
        return false; // Award history must be retained; the legacy table is left untouched.
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_certificates');
        $context->assertTableExists('student_school_certificates');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
INSERT IGNORE INTO student_certificates (
  id, studentId, certificateCatalogId, status, issuedAt, issuedBy,
  issueSource, reason, activityName, evidenceContext, createdAt, updatedAt
)
SELECT
  legacy.id,
  legacy.studentId,
  legacy.certificateCatalogId,
  legacy.status,
  legacy.issuedAt,
  legacy.issuedBy,
  'legacy',
  COALESCE(
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(legacy.evidenceContext, '$.reason')), ''),
    'Dữ liệu chứng chỉ Nhà trường trước khi chuẩn hóa'
  ),
  NULLIF(JSON_UNQUOTE(JSON_EXTRACT(legacy.evidenceContext, '$.activityName')), ''),
  legacy.evidenceContext,
  legacy.createdAt,
  legacy.updatedAt
FROM student_school_certificates legacy
SQL);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: never delete credential history.
    }
};
