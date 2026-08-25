<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Validate complete Phase 3 contracts before project and consent reconciliation';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('certificates');
        $context->assertTableExists('projects');
        $context->assertTableExists('project_members');
        $context->assertTableExists('student_profile_details');
        $context->assertTableExists('student_profile_shares');
        $context->assertTableExists('privacy_consents');
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('schools');
        $context->assertTableExists('teacher_profiles');
        $context->assertTableExists('users');

        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 3 precursor requires MySQL session time zone +00:00.');
        }

        foreach (['certificates', 'projects', 'project_members', 'student_profile_details', 'student_profile_shares', 'privacy_consents'] as $table) {
            $this->assertTableOptions($context, $table);
        }

        $this->assertColumnsExist($context, 'certificates', [
            'id', 'studentId', 'title', 'issuingOrganization', 'issueDate', 'expiryDate',
            'credentialId', 'credentialUrl', 'verificationStatus', 'verifiedBy', 'verifiedAt', 'createdAt', 'updatedAt',
        ]);
        $this->assertColumnsExist($context, 'projects', [
            'id', 'schoolId', 'mentorTeacherId', 'title', 'description', 'projectUrl',
            'startAt', 'endAt', 'status', 'createdAt', 'updatedAt',
        ]);
        $this->assertColumnsExist($context, 'project_members', [
            'id', 'projectId', 'studentId', 'role', 'status', 'joinedAt', 'leftAt', 'createdAt', 'updatedAt',
        ]);
        $this->assertColumnsExist($context, 'student_profile_details', [
            'studentId', 'location', 'bio', 'avatarUrl', 'headline', 'createdAt', 'updatedAt',
        ]);
        $this->assertColumnsExist($context, 'student_profile_shares', [
            'id', 'studentId', 'tokenHash', 'sharedFieldsJson', 'expiresAt', 'revokedAt', 'createdAt',
        ]);
        $this->assertColumnsExist($context, 'privacy_consents', [
            'id', 'studentId', 'scope', 'isGranted', 'policyVersion', 'grantedAt', 'revokedAt', 'createdAt',
        ]);

        $this->assertColumn($context, 'certificates', 'id', ['char'], false, 36);
        $this->assertColumn($context, 'certificates', 'studentId', ['char'], false, 36);
        $this->assertColumn($context, 'certificates', 'credentialUrl', ['varchar'], true, 500);
        $this->assertColumn($context, 'certificates', 'verificationStatus', ['varchar'], false, 32);
        $this->assertColumn($context, 'projects', 'title', ['varchar'], false, 255);
        $this->assertColumn($context, 'projects', 'startAt', ['date', 'datetime'], true);
        $this->assertColumn($context, 'projects', 'endAt', ['date', 'datetime'], true);
        $this->assertColumn($context, 'project_members', 'role', ['varchar'], false, 100);
        $this->assertColumn($context, 'student_profile_details', 'avatarUrl', ['varchar'], true, 500);
        $this->assertColumn($context, 'student_profile_shares', 'tokenHash', ['char'], false, 64);
        $this->assertColumn($context, 'student_profile_shares', 'sharedFieldsJson', ['json'], false);
        $this->assertColumn($context, 'privacy_consents', 'scope', ['varchar'], false, 50);

        if ($this->hasColumn($context, 'projects', 'category')) {
            $this->assertColumn($context, 'projects', 'category', ['varchar'], null, 100);
            $maxCategoryLength = $context->pdo()->query('SELECT COALESCE(MAX(CHAR_LENGTH(category)), 0) FROM projects')?->fetchColumn();
            if ((int) $maxCategoryLength > 100) {
                throw new RuntimeException('projects.category contains a value longer than 100 characters.');
            }
        }
        if ($this->hasColumn($context, 'project_members', 'contribution')) {
            $this->assertColumn($context, 'project_members', 'contribution', ['text'], true);
        }
        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $this->assertColumn($context, 'student_profile_shares', 'consentId', ['char'], true, 36);
        }

        $this->assertIndex($context, 'certificates', 'idx_certificates_student_status', ['studentId', 'verificationStatus'], false);
        $this->assertIndex($context, 'projects', 'idx_projects_school_status', ['schoolId', 'status'], false);
        $this->assertIndex($context, 'projects', 'idx_projects_mentor', ['mentorTeacherId'], false);
        $this->assertIndex($context, 'project_members', 'uq_project_members_student', ['projectId', 'studentId'], true);
        $this->assertIndex($context, 'project_members', 'idx_project_members_student_status', ['studentId', 'status'], false);
        $this->assertIndex($context, 'student_profile_shares', 'uq_student_profile_shares_token_hash', ['tokenHash'], true);
        $this->assertIndex($context, 'student_profile_shares', 'idx_student_profile_shares_student_active', ['studentId', 'revokedAt', 'expiresAt'], false);
        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $this->assertIndex($context, 'student_profile_shares', 'idx_student_profile_shares_consent', ['consentId'], false);
        }

        $this->assertForeignKey($context, 'certificates', 'fk_certificates_student', 'studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE');
        $this->assertForeignKey($context, 'certificates', 'fk_certificates_verified_by', 'verifiedBy', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->assertForeignKey($context, 'projects', 'fk_projects_school', 'schoolId', 'schools', 'id', 'SET NULL', 'CASCADE');
        $this->assertForeignKey($context, 'projects', 'fk_projects_mentor', 'mentorTeacherId', 'teacher_profiles', 'id', 'SET NULL', 'CASCADE');
        $this->assertForeignKey($context, 'project_members', 'fk_project_members_project', 'projectId', 'projects', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKey($context, 'project_members', 'fk_project_members_student', 'studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE');
        $this->assertForeignKey($context, 'student_profile_details', 'fk_student_profile_details_student', 'studentId', 'student_profiles', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKey($context, 'student_profile_shares', 'fk_student_profile_shares_student', 'studentId', 'student_profiles', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKey($context, 'privacy_consents', 'fk_privacy_consents_student', 'studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE');
        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $this->assertForeignKey($context, 'student_profile_shares', 'fk_student_profile_shares_consent', 'consentId', 'privacy_consents', 'id', 'SET NULL', 'CASCADE');
        }

        $this->assertCheckContains($context, 'certificates', 'chk_certificates_verification_status', ['verificationStatus', 'unverified', 'verified', 'rejected']);
        $this->assertCheckContains($context, 'certificates', 'chk_certificates_expiry', ['expiryDate', 'issueDate']);
        $this->assertCheckContains($context, 'projects', 'chk_projects_status', ['status', 'draft', 'in_progress', 'completed', 'archived']);
        $this->assertCheckContains($context, 'projects', 'chk_projects_dates', ['endAt', 'startAt']);
        $this->assertCheckContains($context, 'project_members', 'chk_project_members_status', ['status', 'active', 'left', 'removed']);
        $this->assertCheckContains($context, 'student_profile_shares', 'chk_student_profile_shares_json', ['json_valid', 'sharedFieldsJson']);
        $this->assertCheckContains($context, 'student_profile_shares', 'chk_student_profile_shares_expiry', ['expiresAt', 'createdAt']);
        $this->assertCheckContains($context, 'privacy_consents', 'chk_privacy_consents_scope', ['scope', 'profile_share', 'application_profile_share']);

        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $orphans = $context->pdo()->query(<<<'SQL'
                SELECT COUNT(*)
                FROM student_profile_shares s
                LEFT JOIN privacy_consents c ON c.id = s.consentId
                WHERE s.consentId IS NOT NULL AND c.id IS NULL
            SQL
            )?->fetchColumn();
            if ((int) $orphans !== 0) {
                throw new RuntimeException('student_profile_shares contains orphan consentId values.');
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        // Validation-only precursor. It intentionally performs no data or schema mutation.
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only validation evidence remains in the migration registry.
    }

    private function assertTableOptions(MigrationContext $context, string $table): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT engine, table_collation
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :table
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row)
            || strcasecmp((string) $row['engine'], 'InnoDB') !== 0
            || strcasecmp((string) $row['table_collation'], 'utf8mb4_unicode_ci') !== 0) {
            throw new RuntimeException("{$table} must use InnoDB and utf8mb4_unicode_ci.");
        }
    }

    /** @param list<string> $columns */
    private function assertColumnsExist(MigrationContext $context, string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (!$this->hasColumn($context, $table, $column)) {
                throw new RuntimeException("{$table} table missing column: {$column}");
            }
        }
    }

    /** @param list<string> $types */
    private function assertColumn(
        MigrationContext $context,
        string $table,
        string $column,
        array $types,
        ?bool $nullable,
        ?int $length = null,
    ): void {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT data_type, character_maximum_length, is_nullable, column_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        $actualType = strtolower((string) ($row['data_type'] ?? ''));
        $actualNullable = (($row['is_nullable'] ?? null) === 'YES');
        $actualLength = isset($row['character_maximum_length']) ? (int) $row['character_maximum_length'] : null;
        if (!is_array($row)
            || !in_array($actualType, $types, true)
            || ($nullable !== null && $actualNullable !== $nullable)
            || ($length !== null && $actualLength !== $length)) {
            $columnType = is_array($row) ? (string) ($row['column_type'] ?? $actualType) : 'missing';
            throw new RuntimeException("{$table}.{$column} has incompatible definition: {$columnType}");
        }
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    /** @param list<string> $columns */
    private function assertIndex(MigrationContext $context, string $table, string $index, array $columns, bool $unique): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') AS columns_list
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name
            GROUP BY non_unique
        SQL
        );
        $statement->execute(['table' => $table, 'index_name' => $index]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row)
            || ((int) $row['non_unique'] === 0) !== $unique
            || (string) $row['columns_list'] !== implode(',', $columns)) {
            throw new RuntimeException("{$table} has incompatible index: {$index}");
        }
    }

    private function assertForeignKey(
        MigrationContext $context,
        string $table,
        string $constraint,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $deleteRule,
        string $updateRule,
    ): void {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT kcu.column_name, kcu.referenced_table_name, kcu.referenced_column_name,
                   rc.delete_rule, rc.update_rule
            FROM information_schema.key_column_usage kcu
            INNER JOIN information_schema.referential_constraints rc
              ON rc.constraint_schema = kcu.constraint_schema
             AND rc.table_name = kcu.table_name
             AND rc.constraint_name = kcu.constraint_name
            WHERE kcu.constraint_schema = DATABASE()
              AND kcu.table_name = :table
              AND kcu.constraint_name = :constraint_name
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row)
            || (string) $row['column_name'] !== $column
            || (string) $row['referenced_table_name'] !== $referencedTable
            || (string) $row['referenced_column_name'] !== $referencedColumn
            || strtoupper((string) $row['delete_rule']) !== $deleteRule
            || strtoupper((string) $row['update_rule']) !== $updateRule) {
            throw new RuntimeException("{$table} has incompatible foreign key: {$constraint}");
        }
    }

    /** @param list<string> $fragments */
    private function assertCheckContains(MigrationContext $context, string $table, string $constraint, array $fragments): void
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
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        $checkClause = strtolower((string) $statement->fetchColumn());
        foreach ($fragments as $fragment) {
            if (!str_contains($checkClause, strtolower($fragment))) {
                throw new RuntimeException("{$table} has incompatible check constraint: {$constraint}");
            }
        }
    }
};
