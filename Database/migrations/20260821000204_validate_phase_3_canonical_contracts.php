<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\Migration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Validate every canonical Phase 3 column, CHECK, and consent-link contract';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $structuralPreflight = require __DIR__ . '/20260821000205_preflight_phase_3_reconciliation.php';
        if (!$structuralPreflight instanceof Migration) {
            throw new RuntimeException('Phase 3 structural precursor is unavailable.');
        }
        $structuralPreflight->preflight($context);

        $this->assertCanonicalColumns($context, 'certificates', [
            'id' => $this->column(['char'], false, 36),
            'studentId' => $this->column(['char'], false, 36),
            'title' => $this->column(['varchar'], false, 255),
            'issuingOrganization' => $this->column(['varchar'], false, 255),
            'issueDate' => $this->column(['date'], false),
            'expiryDate' => $this->column(['date'], true),
            'credentialId' => $this->column(['varchar'], true, 255),
            'credentialUrl' => $this->column(['varchar'], true, 500),
            'verificationStatus' => $this->column(['varchar'], false, 32, ['unverified']),
            'verifiedBy' => $this->column(['char'], true, 36),
            'verifiedAt' => $this->column(['datetime'], true, null, [null], [6]),
            'createdAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
            'updatedAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6], 'on update CURRENT_TIMESTAMP(6)'),
        ]);
        $this->assertCanonicalColumns($context, 'projects', [
            'id' => $this->column(['char'], false, 36),
            'schoolId' => $this->column(['char'], true, 36),
            'mentorTeacherId' => $this->column(['char'], true, 36),
            'title' => $this->column(['varchar'], false, 255),
            'description' => $this->column(['text'], true, 65535),
            'projectUrl' => $this->column(['varchar'], true, 500),
            'startAt' => $this->column(['date', 'datetime'], true, null, [null], [null, 6]),
            'endAt' => $this->column(['date', 'datetime'], true, null, [null], [null, 6]),
            'status' => $this->column(['varchar'], false, 32, ['draft']),
            'createdAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
            'updatedAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6], 'on update CURRENT_TIMESTAMP(6)'),
        ]);
        $this->assertOptionalCanonicalColumn(
            $context,
            'projects',
            'category',
            $this->column(['varchar'], [true, false], 100),
        );
        $this->assertCanonicalColumns($context, 'project_members', [
            'id' => $this->column(['char'], false, 36),
            'projectId' => $this->column(['char'], false, 36),
            'studentId' => $this->column(['char'], false, 36),
            'role' => $this->column(['varchar'], false, 100, ['member']),
            'status' => $this->column(['varchar'], false, 32, ['active']),
            'joinedAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
            'leftAt' => $this->column(['datetime'], true, null, [null], [6]),
            'createdAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
            'updatedAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6], 'on update CURRENT_TIMESTAMP(6)'),
        ]);
        $this->assertOptionalCanonicalColumn(
            $context,
            'project_members',
            'contribution',
            $this->column(['text'], true, 65535),
        );
        $this->assertCanonicalColumns($context, 'student_profile_details', [
            'studentId' => $this->column(['char'], false, 36),
            'location' => $this->column(['varchar'], true, 255),
            'bio' => $this->column(['text'], true, 65535),
            'avatarUrl' => $this->column(['varchar'], true, 500),
            'headline' => $this->column(['varchar'], true, 255),
            'createdAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
            'updatedAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6], 'on update CURRENT_TIMESTAMP(6)'),
        ]);
        $this->assertCanonicalColumns($context, 'student_profile_shares', [
            'id' => $this->column(['char'], false, 36),
            'studentId' => $this->column(['char'], false, 36),
            'tokenHash' => $this->column(['char'], false, 64),
            'sharedFieldsJson' => $this->column(['json'], false),
            'expiresAt' => $this->column(['datetime'], false, null, [null], [6]),
            'revokedAt' => $this->column(['datetime'], true, null, [null], [6]),
            'createdAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
        ]);
        $this->assertOptionalCanonicalColumn(
            $context,
            'student_profile_shares',
            'consentId',
            $this->column(['char'], true, 36),
        );
        $this->assertCanonicalColumns($context, 'privacy_consents', [
            'id' => $this->column(['char'], false, 36),
            'studentId' => $this->column(['char'], false, 36),
            'scope' => $this->column(['varchar'], false, 50),
            'isGranted' => $this->column(['tinyint'], false, null, ['0'], [null], '', 'tinyint unsigned'),
            'policyVersion' => $this->column(['varchar'], false, 100),
            'grantedAt' => $this->column(['datetime'], true, null, [null], [6]),
            'revokedAt' => $this->column(['datetime'], true, null, [null], [6]),
            'createdAt' => $this->column(['datetime'], false, null, ['CURRENT_TIMESTAMP(6)'], [6]),
        ]);

        $this->assertForeignKeyEquals($context, 'certificates', 'fk_certificates_student', 'studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'certificates', 'fk_certificates_verified_by', 'verifiedBy', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'projects', 'fk_projects_school', 'schoolId', 'schools', 'id', 'SET NULL', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'projects', 'fk_projects_mentor', 'mentorTeacherId', 'teacher_profiles', 'id', 'SET NULL', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'project_members', 'fk_project_members_project', 'projectId', 'projects', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'project_members', 'fk_project_members_student', 'studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'student_profile_details', 'fk_student_profile_details_student', 'studentId', 'student_profiles', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'student_profile_shares', 'fk_student_profile_shares_student', 'studentId', 'student_profiles', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKeyEquals($context, 'privacy_consents', 'fk_privacy_consents_student', 'studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE');
        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $this->assertForeignKeyEquals($context, 'student_profile_shares', 'fk_student_profile_shares_consent', 'consentId', 'privacy_consents', 'id', 'SET NULL', 'CASCADE');
        }

        $this->assertCheckEquals($context, 'certificates', 'chk_certificates_expiry', 'expirydateisnullorexpirydate>=issuedate');
        $this->assertCheckEquals($context, 'certificates', 'chk_certificates_verification_status', "verificationstatusin('unverified','verified','rejected')");
        $this->assertCheckEquals($context, 'projects', 'chk_projects_dates', 'endatisnullorstartatisnullorendat>=startat');
        $this->assertCheckEquals($context, 'projects', 'chk_projects_status', "statusin('draft','in_progress','completed','archived')");
        $this->assertCheckEquals($context, 'project_members', 'chk_project_members_status', "statusin('active','left','removed')");
        $this->assertCheckEquals($context, 'student_profile_shares', 'chk_student_profile_shares_json', 'json_valid(sharedfieldsjson)');
        $this->assertCheckEquals($context, 'student_profile_shares', 'chk_student_profile_shares_expiry', 'expiresat>createdat');
        $this->assertCheckEquals($context, 'privacy_consents', 'chk_privacy_consents_granted', 'isgrantedin(0,1)');
        $this->assertCheckEquals(
            $context,
            'privacy_consents',
            'chk_privacy_consents_dates',
            'isgranted=1andgrantedatisnotnullandrevokedatisnullorisgranted=0andgrantedatisnullandrevokedatisnotnull',
        );
        $this->assertCheckEquals(
            $context,
            'privacy_consents',
            'chk_privacy_consents_scope',
            "scopein('assessment','skills','activity','evaluation','profile_share','application_profile_share')",
        );

        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $invalidConsentLinks = $context->pdo()->query(<<<'SQL'
                SELECT COUNT(*)
                FROM student_profile_shares s
                LEFT JOIN privacy_consents c ON c.id = s.consentId
                WHERE s.consentId IS NOT NULL
                  AND (c.id IS NULL OR c.studentId <> s.studentId OR c.scope <> 'profile_share')
            SQL
            )?->fetchColumn();
            if ((int) $invalidConsentLinks !== 0) {
                throw new RuntimeException('student_profile_shares contains a consent link with the wrong Student or scope.');
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        // Validation-only precursor. It intentionally performs no DDL or application DML.
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only validation evidence remains recorded.
    }

    /**
     * @param list<string> $types
     * @param bool|list<bool> $nullable
     * @param list<?string> $defaults
     * @param list<?int> $dateTimePrecision
     * @return array{types:list<string>,nullable:list<bool>,length:?int,defaults:list<?string>,datetime_precision:list<?int>,extra:string,column_type:string}
     */
    private function column(
        array $types,
        bool|array $nullable,
        ?int $length = null,
        array $defaults = [null],
        array $dateTimePrecision = [null],
        string $extra = '',
        string $columnType = '',
    ): array {
        return [
            'types' => $types,
            'nullable' => is_array($nullable) ? $nullable : [$nullable],
            'length' => $length,
            'defaults' => $defaults,
            'datetime_precision' => $dateTimePrecision,
            'extra' => $extra,
            'column_type' => $columnType,
        ];
    }

    /** @param array<string,array{types:list<string>,nullable:list<bool>,length:?int,defaults:list<?string>,datetime_precision:list<?int>,extra:string,column_type:string}> $definitions */
    private function assertCanonicalColumns(MigrationContext $context, string $table, array $definitions): void
    {
        foreach ($definitions as $column => $definition) {
            $this->assertCanonicalColumn($context, $table, $column, $definition);
        }
    }

    /** @param array{types:list<string>,nullable:list<bool>,length:?int,defaults:list<?string>,datetime_precision:list<?int>,extra:string,column_type:string} $definition */
    private function assertOptionalCanonicalColumn(MigrationContext $context, string $table, string $column, array $definition): void
    {
        if ($this->hasColumn($context, $table, $column)) {
            $this->assertCanonicalColumn($context, $table, $column, $definition);
        }
    }

    /** @param array{types:list<string>,nullable:list<bool>,length:?int,defaults:list<?string>,datetime_precision:list<?int>,extra:string,column_type:string} $definition */
    private function assertCanonicalColumn(MigrationContext $context, string $table, string $column, array $definition): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT data_type, character_maximum_length, is_nullable, column_default,
                   datetime_precision, extra, column_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException("{$table} table missing canonical column: {$column}");
        }
        $row = array_change_key_case($row, CASE_LOWER);
        $actualType = strtolower((string) $row['data_type']);
        $actualNullable = (string) $row['is_nullable'] === 'YES';
        $actualLength = $row['character_maximum_length'] === null ? null : (int) $row['character_maximum_length'];
        $actualDefault = $row['column_default'] === null ? null : strtoupper((string) $row['column_default']);
        $expectedDefaults = array_map(
            static fn (?string $value): ?string => $value === null ? null : strtoupper($value),
            $definition['defaults'],
        );
        $actualPrecision = $row['datetime_precision'] === null ? null : (int) $row['datetime_precision'];
        $actualExtra = strtolower((string) $row['extra']);
        $actualColumnType = strtolower((string) $row['column_type']);

        $valid = in_array($actualType, $definition['types'], true)
            && in_array($actualNullable, $definition['nullable'], true)
            && $actualLength === $definition['length']
            && in_array($actualDefault, $expectedDefaults, true)
            && in_array($actualPrecision, $definition['datetime_precision'], true)
            && ($definition['extra'] === '' || str_contains($actualExtra, strtolower($definition['extra'])))
            && ($definition['column_type'] === '' || $actualColumnType === strtolower($definition['column_type']));
        if (!$valid) {
            throw new RuntimeException("{$table}.{$column} has incompatible canonical metadata.");
        }
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function assertCheckEquals(MigrationContext $context, string $table, string $constraint, string $expected): void
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
        $actual = $this->normalizedCheckClause((string) $statement->fetchColumn());
        $normalizedExpected = $this->normalizedCheckClause($expected);
        if (!hash_equals($normalizedExpected, $actual)) {
            throw new RuntimeException("{$table} has incompatible canonical CHECK: {$constraint} ({$actual})");
        }
    }

    private function assertForeignKeyEquals(
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
        if (!is_array($row)) {
            throw new RuntimeException("{$table} is missing canonical foreign key: {$constraint}");
        }
        $row = array_change_key_case($row, CASE_LOWER);
        if ((string) $row['column_name'] !== $column
            || (string) $row['referenced_table_name'] !== $referencedTable
            || (string) $row['referenced_column_name'] !== $referencedColumn
            || strtoupper((string) $row['delete_rule']) !== $deleteRule
            || strtoupper((string) $row['update_rule']) !== $updateRule) {
            throw new RuntimeException("{$table} has incompatible canonical foreign key: {$constraint}");
        }
    }

    private function normalizedCheckClause(string $clause): string
    {
        $normalized = strtolower($clause);
        $normalized = str_replace(['`', '_utf8mb4', '(', ')', '\\'], '', $normalized);
        return preg_replace('/\s+/', '', $normalized) ?? '';
    }
};
