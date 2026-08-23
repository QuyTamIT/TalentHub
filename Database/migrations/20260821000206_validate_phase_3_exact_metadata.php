<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\Migration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Validate exact Phase 3 CHECK grouping and column EXTRA metadata';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $strictValidation = require __DIR__ . '/20260821000204_validate_phase_3_canonical_contracts.php';
        if (!$strictValidation instanceof Migration) {
            throw new RuntimeException('Phase 3 strict canonical validator is unavailable.');
        }
        $strictValidation->preflight($context);

        $this->assertExactChecks($context, [
            'certificates' => [
                'chk_certificates_expiry' => '((expiryDate IS NULL) OR (expiryDate >= issueDate))',
                'chk_certificates_verification_status' => "(verificationStatus IN ('unverified','verified','rejected'))",
            ],
            'projects' => [
                'chk_projects_dates' => '((endAt IS NULL) OR (startAt IS NULL) OR (endAt >= startAt))',
                'chk_projects_status' => "(status IN ('draft','in_progress','completed','archived'))",
            ],
            'project_members' => [
                'chk_project_members_status' => "(status IN ('active','left','removed'))",
            ],
            'student_profile_shares' => [
                'chk_student_profile_shares_json' => 'JSON_VALID(sharedFieldsJson)',
                'chk_student_profile_shares_expiry' => '(expiresAt > createdAt)',
            ],
            'privacy_consents' => [
                'chk_privacy_consents_granted' => '(isGranted IN (0,1))',
                'chk_privacy_consents_dates' => '(((isGranted = 1) AND (grantedAt IS NOT NULL) AND (revokedAt IS NULL)) OR ((isGranted = 0) AND (grantedAt IS NULL) AND (revokedAt IS NOT NULL)))',
                'chk_privacy_consents_scope' => "(scope IN ('assessment','skills','activity','evaluation','profile_share','application_profile_share'))",
            ],
        ]);

        $this->assertExactColumnExtras($context, [
            'certificates' => [
                'id', 'studentId', 'title', 'issuingOrganization', 'issueDate', 'expiryDate',
                'credentialId', 'credentialUrl', 'verificationStatus', 'verifiedBy', 'verifiedAt',
                'createdAt', 'updatedAt',
            ],
            'projects' => [
                'id', 'schoolId', 'mentorTeacherId', 'title', 'description', 'projectUrl',
                'startAt', 'endAt', 'status', 'createdAt', 'updatedAt', 'category',
            ],
            'project_members' => [
                'id', 'projectId', 'studentId', 'role', 'status', 'joinedAt', 'leftAt',
                'createdAt', 'updatedAt', 'contribution',
            ],
            'student_profile_details' => [
                'studentId', 'location', 'bio', 'avatarUrl', 'headline', 'createdAt', 'updatedAt',
            ],
            'student_profile_shares' => [
                'id', 'studentId', 'tokenHash', 'sharedFieldsJson', 'expiresAt', 'revokedAt',
                'createdAt', 'consentId',
            ],
            'privacy_consents' => [
                'id', 'studentId', 'scope', 'isGranted', 'policyVersion', 'grantedAt',
                'revokedAt', 'createdAt',
            ],
        ]);
    }

    public function up(MigrationContext $context): void
    {
        // Validation-only. It records evidence after preflight and performs no DDL or application DML.
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only validation evidence remains recorded.
    }

    /** @param array<string,array<string,string>> $definitions */
    private function assertExactChecks(MigrationContext $context, array $definitions): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT cc.check_clause
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.check_constraints cc
              ON cc.constraint_schema = tc.constraint_schema
             AND cc.constraint_name = tc.constraint_name
            WHERE tc.table_schema = DATABASE()
              AND tc.table_name = :table
              AND tc.constraint_type = 'CHECK'
              AND tc.constraint_name = :constraint_name
            LIMIT 1
        SQL
        );

        foreach ($definitions as $table => $checks) {
            foreach ($checks as $constraint => $expected) {
                $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
                $actual = $this->normalizeExactCheck((string) $statement->fetchColumn());
                $canonical = $this->normalizeExactCheck($expected);
                if (!hash_equals($canonical, $actual)) {
                    throw new RuntimeException("{$table} has incompatible exact CHECK grouping: {$constraint} ({$actual})");
                }
            }
        }
    }

    /** @param array<string,list<string>> $definitions */
    private function assertExactColumnExtras(MigrationContext $context, array $definitions): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT extra
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
            LIMIT 1
        SQL
        );

        foreach ($definitions as $table => $columns) {
            foreach ($columns as $column) {
                $statement->execute(['table' => $table, 'column' => $column]);
                $actual = $statement->fetchColumn();
                if ($actual === false) {
                    // Optional reconciliation columns do not exist before 00210 on a fresh database.
                    if (in_array($column, ['category', 'contribution', 'consentId'], true)) {
                        continue;
                    }
                    throw new RuntimeException("{$table} table missing canonical column: {$column}");
                }

                $expected = match ($column) {
                    'updatedAt' => 'default_generated on update current_timestamp(6)',
                    'createdAt', 'joinedAt' => 'default_generated',
                    default => '',
                };
                $normalizedActual = $this->normalizeExtra((string) $actual);
                if (!hash_equals($expected, $normalizedActual)) {
                    throw new RuntimeException("{$table}.{$column} has incompatible exact EXTRA metadata ({$normalizedActual}).");
                }
            }
        }
    }

    private function normalizeExactCheck(string $clause): string
    {
        $normalized = strtolower($clause);
        $normalized = str_replace(['`', '_utf8mb4', '\\'], '', $normalized);
        return preg_replace('/\s+/', '', $normalized) ?? '';
    }

    private function normalizeExtra(string $extra): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($extra)) ?? '');
    }
};
