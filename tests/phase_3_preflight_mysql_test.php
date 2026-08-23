<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$mode = (string) (getenv('PHASE3_PREFLIGHT_MODE') ?: 'canonical');
$allowedModes = [
    'canonical', 'bad_category', 'bad_index', 'bad_fk', 'bad_check', 'bad_nullability',
    'bad_required_column', 'bad_vacuous_check', 'bad_consent_scope',
    'bad_check_grouping', 'bad_unexpected_on_update',
];
$assert(in_array($mode, $allowedModes, true), 'Preflight test mode is explicitly allowed.');

$config = require dirname(__DIR__) . '/config/database.php';
$databaseName = (string) ($config['database'] ?? '');
$assert(
    preg_match('/\Atalenthub_phase3_preflight_[a-z_]+_[0-9]{14}\z/', $databaseName) === 1,
    'Phase 3 preflight test runs only on a validated disposable schema.',
);

$pdo = (new Connection($config))->connect();
$runner = new MigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations');

$hasColumn = static function (string $table, string $column) use ($pdo): bool {
    $statement = $pdo->prepare(<<<'SQL'
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
    SQL
    );
    $statement->execute(['table' => $table, 'column' => $column]);
    return (int) $statement->fetchColumn() === 1;
};

if ($mode === 'canonical') {
    $schoolId = (string) $pdo->query('SELECT id FROM schools ORDER BY id LIMIT 1')->fetchColumn();
    $teacherId = (string) $pdo->query('SELECT id FROM teacher_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $studentId = (string) $pdo->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $assert($schoolId !== '' && $teacherId !== '' && $studentId !== '', 'Canonical clone contains project parents.');

    $projectId = '0191316b-4000-7000-8000-000000009001';
    $memberId = '0191316b-4000-7000-8000-000000009002';
    $certificateId = '0191316b-4000-7000-8000-000000009003';
    $shareId = '0191316b-4000-7000-8000-000000009004';
    $project = $pdo->prepare(<<<'SQL'
        INSERT INTO projects (id, schoolId, mentorTeacherId, title, description, startAt, endAt, status)
        VALUES (:id, :schoolId, :teacherId, 'Preflight Populated Project', 'Preserve me', '2026-01-02', '2026-02-03', 'in_progress')
    SQL
    );
    $project->execute(['id' => $projectId, 'schoolId' => $schoolId, 'teacherId' => $teacherId]);
    $member = $pdo->prepare(<<<'SQL'
        INSERT INTO project_members (id, projectId, studentId, role, status)
        VALUES (:id, :projectId, :studentId, 'developer', 'active')
    SQL
    );
    $member->execute(['id' => $memberId, 'projectId' => $projectId, 'studentId' => $studentId]);
    $certificate = $pdo->prepare(<<<'SQL'
        INSERT INTO certificates (id, studentId, title, issuingOrganization, issueDate, verificationStatus)
        VALUES (:id, :studentId, 'Preflight Certificate', 'TalentHub', '2026-01-01', 'unverified')
    SQL
    );
    $certificate->execute(['id' => $certificateId, 'studentId' => $studentId]);
    $share = $pdo->prepare(<<<'SQL'
        INSERT INTO student_profile_shares (id, studentId, tokenHash, sharedFieldsJson, expiresAt)
        VALUES (:id, :studentId, :tokenHash, JSON_ARRAY('fullName'), '2099-01-01 00:00:00.000000')
    SQL
    );
    $share->execute(['id' => $shareId, 'studentId' => $studentId, 'tokenHash' => str_repeat('a', 64)]);

    $before = [
        'projects' => (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
        'project_members' => (int) $pdo->query('SELECT COUNT(*) FROM project_members')->fetchColumn(),
        'certificates' => (int) $pdo->query('SELECT COUNT(*) FROM certificates')->fetchColumn(),
        'student_profile_shares' => (int) $pdo->query('SELECT COUNT(*) FROM student_profile_shares')->fetchColumn(),
    ];

    $strictValidation = $runner->migrate(1);
    $assert($strictValidation === ['20260821000204'], 'Strict canonical validation applies first.');
    $applied = $runner->migrate(1);
    $assert($applied === ['20260821000205'], 'Structural validation applies after strict validation.');
    $assert(!$hasColumn('projects', 'category'), 'Validation precursors perform no project DDL.');
    $assert(!$hasColumn('project_members', 'contribution'), 'Validation precursors perform no project-member DDL.');
    $assert(!$hasColumn('student_profile_shares', 'consentId'), 'Validation precursors perform no share DDL.');

    $exactValidation = $runner->migrate(1);
    $assert($exactValidation === ['20260821000206'], 'Exact CHECK grouping and column-extra validation applies before reconciliation.');

    $repair = $runner->migrate(1);
    $assert($repair === ['20260821000210'], 'Reconciliation follows the validated precursor.');
    $assert($hasColumn('projects', 'category'), 'Repair adds project category.');
    $assert($hasColumn('project_members', 'contribution'), 'Repair adds member contribution.');
    $assert($hasColumn('student_profile_shares', 'consentId'), 'Repair adds linked consent column.');
    $row = $pdo->query("SELECT title, category, startAt, endAt FROM projects WHERE id = '{$projectId}'")->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($row) && $row['title'] === 'Preflight Populated Project', 'Populated project survives repair.');
    $assert($row['category'] === 'general', 'Populated project receives deterministic category backfill.');
    $assert(str_starts_with((string) $row['startAt'], '2026-01-02'), 'Project start date survives DATETIME widening.');
    $assert(str_starts_with((string) $row['endAt'], '2026-02-03'), 'Project end date survives DATETIME widening.');

    foreach ($before as $table => $count) {
        $after = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $assert($after === $count, "{$table} row count is preserved across precursor and repair.");
    }
} else {
    $expectedError = match ($mode) {
        'bad_category' => 'projects.category has incompatible definition',
        'bad_index' => 'project_members has incompatible index: uq_project_members_student',
        'bad_fk' => 'projects has incompatible foreign key: fk_projects_school',
        'bad_check' => 'project_members has incompatible check constraint: chk_project_members_status',
        'bad_nullability' => 'certificates.credentialUrl has incompatible definition',
        'bad_required_column' => 'certificates.title has incompatible canonical metadata',
        'bad_vacuous_check' => 'project_members has incompatible canonical CHECK: chk_project_members_status',
        'bad_consent_scope' => 'student_profile_shares contains a consent link with the wrong Student or scope',
        'bad_check_grouping' => 'privacy_consents has incompatible exact CHECK grouping: chk_privacy_consents_dates',
        'bad_unexpected_on_update' => 'certificates.createdAt has incompatible exact EXTRA metadata',
        default => '',
    };
    if ($mode === 'bad_category') {
        $pdo->exec('ALTER TABLE projects ADD COLUMN category VARCHAR(255) NULL AFTER title');
    } elseif ($mode === 'bad_index') {
        $pdo->exec('ALTER TABLE project_members ADD KEY idx_preflight_project (projectId)');
        $pdo->exec('ALTER TABLE project_members DROP INDEX uq_project_members_student');
        $pdo->exec('ALTER TABLE project_members ADD UNIQUE KEY uq_project_members_student (id)');
    } elseif ($mode === 'bad_fk') {
        $pdo->exec('ALTER TABLE projects DROP FOREIGN KEY fk_projects_school');
        $pdo->exec(<<<'SQL'
            ALTER TABLE projects ADD CONSTRAINT fk_projects_school
            FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL
        );
    } elseif ($mode === 'bad_check') {
        $pdo->exec('ALTER TABLE project_members DROP CHECK chk_project_members_status');
        $pdo->exec("ALTER TABLE project_members ADD CONSTRAINT chk_project_members_status CHECK (status IN ('active'))");
    } elseif ($mode === 'bad_nullability') {
        $pdo->exec("ALTER TABLE certificates MODIFY COLUMN credentialUrl VARCHAR(500) NOT NULL DEFAULT ''");
    } elseif ($mode === 'bad_required_column') {
        $pdo->exec('ALTER TABLE certificates MODIFY COLUMN title VARCHAR(64) NOT NULL');
    } elseif ($mode === 'bad_vacuous_check') {
        $pdo->exec('ALTER TABLE project_members DROP CHECK chk_project_members_status');
        $pdo->exec("ALTER TABLE project_members ADD CONSTRAINT chk_project_members_status CHECK (status IN ('active','left','removed') OR TRUE)");
    } elseif ($mode === 'bad_consent_scope') {
        $studentA = (string) $pdo->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1')->fetchColumn();
        $studentB = (string) $pdo->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1 OFFSET 1')->fetchColumn();
        $assert($studentA !== '' && $studentB !== '' && $studentA !== $studentB, 'Consent mismatch fixture has two Students.');
        $pdo->exec('ALTER TABLE student_profile_shares ADD COLUMN consentId CHAR(36) NULL AFTER studentId');
        $pdo->exec('ALTER TABLE student_profile_shares ADD KEY idx_student_profile_shares_consent (consentId)');
        $pdo->exec(<<<'SQL'
            ALTER TABLE student_profile_shares ADD CONSTRAINT fk_student_profile_shares_consent
            FOREIGN KEY (consentId) REFERENCES privacy_consents(id) ON DELETE SET NULL ON UPDATE CASCADE
        SQL
        );
        $consent = $pdo->prepare(<<<'SQL'
            INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt)
            VALUES ('0191316b-4000-7000-8000-000000009101', :studentId, 'skills', 1, 'phase3-negative', CURRENT_TIMESTAMP(6), NULL)
        SQL
        );
        $consent->execute(['studentId' => $studentA]);
        $share = $pdo->prepare(<<<'SQL'
            INSERT INTO student_profile_shares (id, studentId, consentId, tokenHash, sharedFieldsJson, expiresAt)
            VALUES ('0191316b-4000-7000-8000-000000009102', :studentId, '0191316b-4000-7000-8000-000000009101', :tokenHash, JSON_ARRAY('fullName'), '2099-01-01 00:00:00.000000')
        SQL
        );
        $share->execute(['studentId' => $studentB, 'tokenHash' => str_repeat('b', 64)]);
    } elseif ($mode === 'bad_check_grouping') {
        $pdo->exec('ALTER TABLE privacy_consents DROP CHECK chk_privacy_consents_dates');
        $pdo->exec(<<<'SQL'
            ALTER TABLE privacy_consents ADD CONSTRAINT chk_privacy_consents_dates CHECK (
                isGranted = 1 AND grantedAt IS NOT NULL AND
                (revokedAt IS NULL OR isGranted = 0) AND
                grantedAt IS NULL AND revokedAt IS NOT NULL
            )
        SQL
        );
    } elseif ($mode === 'bad_unexpected_on_update') {
        $pdo->exec(<<<'SQL'
            ALTER TABLE certificates MODIFY COLUMN createdAt DATETIME(6) NOT NULL
            DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
        SQL
        );
    }

    try {
        if (in_array($mode, ['bad_check_grouping', 'bad_unexpected_on_update'], true)) {
            $assert($runner->migrate(1) === ['20260821000204'], "{$mode} reaches strict token validation.");
            $assert($runner->migrate(1) === ['20260821000205'], "{$mode} reaches structural validation.");
        }
        $runner->migrate(1);
        $assert(false, "{$mode} must fail before reconciliation DDL.");
    } catch (RuntimeException $exception) {
        $assert(
            str_contains($exception->getMessage(), $expectedError),
            "{$mode} returns its targeted preflight error: {$exception->getMessage()}",
        );
    }

    $appliedCount = $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version IN ('20260821000204','20260821000205','20260821000206','20260821000210')")->fetchColumn();
    $expectedAppliedCount = in_array($mode, ['bad_check_grouping', 'bad_unexpected_on_update'], true) ? 2 : 0;
    $assert((int) $appliedCount === $expectedAppliedCount, "{$mode} records only validations completed before the targeted failure.");
    $assert(!$hasColumn('project_members', 'contribution'), "{$mode} stops before project-member DDL.");
    if ($mode !== 'bad_consent_scope') {
        $assert(!$hasColumn('student_profile_shares', 'consentId'), "{$mode} stops before share DDL.");
    }
}

echo "phase_3_preflight_mysql_test ({$mode}): OK\n";
