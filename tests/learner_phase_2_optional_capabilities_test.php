<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Readiness\GitScopeGuard;
use TalentHub\Learner\Data\Readiness\PhaseRequirements;
use TalentHub\Learner\Data\Readiness\ReadinessChecker;
use TalentHub\Learner\Data\Readiness\TalentPassportOptionalSchema;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function phase_2_assert(bool $condition, string $message, ?\TalentHub\Learner\Data\Readiness\ReadinessResult $result = null): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        if ($result !== null) {
            fwrite(STDERR, "Result: " . json_encode($result->toArray(), JSON_PRETTY_PRINT) . "\n");
        }
        exit(1);
    }
}

$requirements = new PhaseRequirements();
$phase2 = $requirements->forPhase(2);

phase_2_assert(class_exists(TalentPassportOptionalSchema::class), 'shared optional capability schema contract exists');
$optionalContracts = TalentPassportOptionalSchema::definitions();
phase_2_assert(($optionalContracts['certificates']['columns']['certificates'] ?? []) === [
    'id', 'studentId', 'title', 'issuingOrganization', 'issueDate', 'expiryDate', 'credentialId',
    'credentialUrl', 'verificationStatus', 'verifiedBy', 'verifiedAt', 'createdAt', 'updatedAt',
], 'certificate contract matches the approved Phase 3 schema');
phase_2_assert(($optionalContracts['certificates']['indexes']['certificates'] ?? []) === [
    'idx_certificates_student_status',
], 'certificate contract requires the approved lookup index');
phase_2_assert(($optionalContracts['projects']['columns']['projects'] ?? []) === [
    'id', 'title', 'category', 'description', 'mentorTeacherId', 'schoolId', 'status',
    'startAt', 'endAt', 'createdAt', 'updatedAt',
], 'project contract matches the approved Phase 3 schema');
phase_2_assert(($optionalContracts['projects']['columns']['project_members'] ?? []) === [
    'id', 'projectId', 'studentId', 'role', 'contribution', 'status', 'joinedAt',
], 'project member contract matches the approved Phase 3 schema');
phase_2_assert(($optionalContracts['projects']['indexes']['project_members'] ?? []) === [
    'uq_project_members_student',
], 'project contract requires the approved membership index');
phase_2_assert(($optionalContracts['badges']['columns']['badges'] ?? []) === [
    'id', 'code', 'name', 'category', 'description', 'iconUrl', 'level', 'status', 'createdAt', 'updatedAt',
], 'badge contract matches the approved Phase 9 schema');
phase_2_assert(($optionalContracts['badges']['columns']['badge_rule_definitions'] ?? []) === [
    'id', 'badgeId', 'ruleType', 'thresholdCriteria', 'version', 'isActive', 'createdAt', 'updatedAt',
], 'badge rule contract matches the approved Phase 9 schema');
phase_2_assert(($optionalContracts['badges']['columns']['student_badges'] ?? []) === [
    'id', 'studentId', 'badgeId', 'ruleDefinitionId', 'awardedAt', 'awardedBy', 'awardContext',
], 'student badge contract matches the approved Phase 9 schema');
phase_2_assert(($optionalContracts['badges']['indexes'] ?? []) === [
    'badges' => ['uq_badges_code'],
    'badge_rule_definitions' => ['uq_badge_rules_badge_version', 'idx_badge_rules_active'],
    'student_badges' => ['uq_student_badges_award'],
], 'badge contract requires the approved indexes');

phase_2_assert($phase2['requires_database'] === true, 'Phase 2 requires a database');
phase_2_assert(!in_array('certificates', $phase2['tables'], true), 'certificates are not a Phase 2 hard dependency');
phase_2_assert(!in_array('projects', $phase2['tables'], true), 'projects are not a Phase 2 hard dependency');
phase_2_assert(!in_array('badges', $phase2['tables'], true), 'badges are not a Phase 2 hard dependency');
phase_2_assert(!in_array('project_members', $phase2['tables'], true), 'project_members are not a Phase 2 hard dependency');
phase_2_assert(!in_array('student_badges', $phase2['tables'], true), 'student_badges are not a Phase 2 hard dependency');

phase_2_assert(($phase2['optional_table_groups'] ?? null) === [
    'certificates' => ['certificates'],
    'projects' => ['projects', 'project_members'],
    'badges' => ['badges', 'badge_rule_definitions', 'student_badges'],
], 'Phase 2 optional groups are explicit');

$expectedRequiredTables = [
    'users', 'student_profiles', 'classes', 'schools',
    'skills', 'student_skills',
    'activities', 'activity_registrations', 'checkins', 'experience_logs',
    'talent_tests', 'test_attempts', 'test_results',
    'assessments', 'assessment_scores', 'assessment_criteria',
];
phase_2_assert(
    count(array_diff($expectedRequiredTables, $phase2['tables'])) === 0
    && count(array_diff($phase2['tables'], $expectedRequiredTables)) === 0,
    'Phase 2 required tables match canonical list'
);

$expectedColumns = [
    'users' => ['id', 'fullName', 'email', 'status'],
    'student_profiles' => ['id', 'userId', 'classId', 'studyStatus'],
    'classes' => ['id', 'schoolId', 'name', 'gradeLevel', 'academicYear', 'status'],
    'schools' => ['id', 'name', 'status'],
    'skills' => ['id', 'code', 'name', 'category', 'status'],
    'student_skills' => ['studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus', 'verifiedAt'],
    'activities' => ['id', 'schoolId', 'createdByTeacherId', 'title', 'category', 'startAt', 'endAt', 'status'],
    'activity_registrations' => ['id', 'activityId', 'studentId', 'status', 'registeredAt'],
    'checkins' => ['id', 'registrationId', 'qrSessionId', 'status', 'checkedInAt', 'confirmedAt'],
    'experience_logs' => ['id', 'studentId', 'activityId', 'checkinId', 'hours', 'status', 'confirmedAt'],
    'talent_tests' => ['id', 'code', 'name', 'type', 'status'],
    'test_attempts' => ['id', 'testId', 'studentId', 'status', 'startedAt', 'submittedAt'],
    'test_results' => ['attemptId', 'resultCode', 'summary', 'dimensionScoresJson', 'scoringVersion', 'createdAt'],
    'assessments' => ['id', 'teacherId', 'studentId', 'activityId', 'overallScore', 'comment', 'status', 'publishedAt', 'version'],
    'assessment_scores' => ['assessmentId', 'criteriaId', 'score'],
    'assessment_criteria' => ['id', 'code', 'name', 'minScore', 'maxScore', 'displayOrder', 'status'],
];
foreach ($expectedColumns as $table => $cols) {
    phase_2_assert(isset($phase2['columns'][$table]), "table {$table} has required column definitions");
    foreach ($cols as $col) {
        phase_2_assert(in_array($col, $phase2['columns'][$table], true), "column {$table}.{$col} is required in Phase 2");
    }
}

$expectedIndexes = [
    'student_profiles' => ['uq_student_profiles_user', 'idx_student_profiles_class_status'],
    'classes' => ['idx_classes_school_status'],
    'skills' => ['uq_skills_code', 'idx_skills_status_category'],
    'student_skills' => ['uq_student_skills_student_skill_source', 'idx_student_skills_student_verification'],
    'activities' => ['idx_activities_teacher_status', 'idx_activities_school_start'],
    'activity_registrations' => ['uq_activity_registrations_activity_student', 'idx_activity_registrations_student_status'],
    'checkins' => ['uq_checkins_registration', 'idx_checkins_qr_session'],
    'experience_logs' => ['uq_experience_logs_checkin', 'idx_experience_logs_student_status'],
    'test_attempts' => ['idx_test_attempts_student_status'],
    'test_results' => ['uq_test_results_attempt'],
    'assessments' => ['uq_assessments_teacher_student_activity', 'idx_assessments_student_status'],
    'assessment_scores' => ['uq_assessment_scores_assessment_criteria'],
    'assessment_criteria' => ['uq_assessment_criteria_code', 'idx_assessment_criteria_status_order'],
];
foreach ($expectedIndexes as $table => $idxs) {
    phase_2_assert(isset($phase2['indexes'][$table]), "table {$table} has required index definitions");
    foreach ($idxs as $idx) {
        phase_2_assert(in_array($idx, $phase2['indexes'][$table], true), "index {$table}.{$idx} is required in Phase 2");
    }
}

// Test readiness checker with in-memory SQLite containing all required schema and NO optional tables
$pdo = new PDO('sqlite::memory:');
$pdo->sqliteCreateFunction('database', static fn (): string => 'main');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, email TEXT, status TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_users_email ON users(email)');
$pdo->exec('CREATE INDEX idx_users_role_status ON users(status)');

$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT, status TEXT)');
$pdo->exec('CREATE INDEX idx_classes_school_status ON classes(schoolId, status)');

$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_student_profiles_user ON student_profiles(userId)');
$pdo->exec('CREATE INDEX idx_student_profiles_class_status ON student_profiles(classId, studyStatus)');

$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_skills_code ON skills(code)');
$pdo->exec('CREATE INDEX idx_skills_status_category ON skills(status, category)');

$pdo->exec('CREATE TABLE student_skills (studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_student_skills_student_skill_source ON student_skills(studentId, skillId, sourceType)');
$pdo->exec('CREATE INDEX idx_student_skills_student_verification ON student_skills(studentId, verificationStatus)');

$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, status TEXT)');
$pdo->exec('CREATE INDEX idx_activities_teacher_status ON activities(createdByTeacherId, status)');
$pdo->exec('CREATE INDEX idx_activities_school_start ON activities(schoolId, startAt)');

$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_activity_registrations_activity_student ON activity_registrations(activityId, studentId)');
$pdo->exec('CREATE INDEX idx_activity_registrations_student_status ON activity_registrations(studentId, status)');

$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_checkins_registration ON checkins(registrationId)');
$pdo->exec('CREATE INDEX idx_checkins_qr_session ON checkins(qrSessionId)');

$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_experience_logs_checkin ON experience_logs(checkinId)');
$pdo->exec('CREATE INDEX idx_experience_logs_student_status ON experience_logs(studentId, status)');

$pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, name TEXT, type TEXT, status TEXT)');
$pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, status TEXT, startedAt TEXT, submittedAt TEXT)');
$pdo->exec('CREATE INDEX idx_test_attempts_student_status ON test_attempts(studentId, status)');

$pdo->exec('CREATE TABLE test_results (attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_test_results_attempt ON test_results(attemptId)');

$pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, teacherId TEXT, studentId TEXT, activityId TEXT, overallScore REAL, comment TEXT, status TEXT, publishedAt TEXT, version INT)');
$pdo->exec('CREATE UNIQUE INDEX uq_assessments_teacher_student_activity ON assessments(teacherId, studentId, activityId)');
$pdo->exec('CREATE INDEX idx_assessments_student_status ON assessments(studentId, status)');

$pdo->exec('CREATE TABLE assessment_scores (assessmentId TEXT, criteriaId TEXT, score REAL)');
$pdo->exec('CREATE UNIQUE INDEX uq_assessment_scores_assessment_criteria ON assessment_scores(assessmentId, criteriaId)');

$pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT, name TEXT, minScore REAL, maxScore REAL, displayOrder INT, status TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_assessment_criteria_code ON assessment_criteria(code)');
$pdo->exec('CREATE INDEX idx_assessment_criteria_status_order ON assessment_criteria(status, displayOrder)');

function phase_2_cleanup_fixture_dir(string $dir): void
{
    $tempDir = realpath(sys_get_temp_dir());
    $realDir = realpath($dir);
    if ($tempDir === false || $realDir === false) {
        return;
    }
    $prefix = $tempDir . DIRECTORY_SEPARATOR . 'talenthub-readiness-';
    if (!str_starts_with($realDir, $prefix) || strlen($realDir) <= strlen($prefix)) {
        return;
    }
    if (!is_dir($realDir)) {
        return;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        exec('cmd /c "attrib -r -s -h ' . escapeshellarg($realDir . '\\*') . ' /s /d" 2>NUL');
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $path = $item->getRealPath();
        if ($item->isDir()) {
            @chmod($path, 0777);
            @rmdir($path);
        } else {
            @chmod($path, 0666);
            @unlink($path);
        }
    }
    @chmod($realDir, 0777);
    @rmdir($realDir);

    if (is_dir($realDir) && DIRECTORY_SEPARATOR === '\\') {
        exec('cmd /c "rmdir /s /q ' . escapeshellarg($realDir) . '" 2>NUL');
    }

    phase_2_assert(!is_dir($realDir), "temporary fixture directory {$realDir} must be deleted after cleanup");
}

$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'talenthub-readiness-' . bin2hex(random_bytes(6));
if (!mkdir($fixtureRoot, 0777, true) && !is_dir($fixtureRoot)) {
    fwrite(STDERR, "Assertion failed: unable to create temporary repo fixture\n");
    exit(1);
}

exec('cmd /c "cd /d ' . $fixtureRoot . ' && git init -q && git config user.email p2@example.com && git config user.name p2 && mkdir app\\learner\\data" 2>&1');
file_put_contents($fixtureRoot . DIRECTORY_SEPARATOR . 'app\\learner\\data\\bootstrap.php', 'ok');

try {
    $guard = new GitScopeGuard();
    $checker = new ReadinessChecker($requirements, $guard);

    // Case 1: Clean absence of all optional tables
    $result = $checker->check(2, $fixtureRoot, static fn (): PDO => $pdo);
    phase_2_assert($result->status() === 'READY', 'Phase 2 is READY when required tables exist and future tables are absent', $result);
    phase_2_assert($result->exitCode() === 0, 'Phase 2 exit code is 0');

    $passes = array_column($result->toArray()['passes'], 'message');
    phase_2_assert(in_array('Optional capability certificates is unavailable (cleanly absent).', $passes, true), 'optional certificates cleanly absent noted');
    phase_2_assert(in_array('Optional capability projects is unavailable (cleanly absent).', $passes, true), 'optional projects cleanly absent noted');
    phase_2_assert(in_array('Optional capability badges is unavailable (cleanly absent).', $passes, true), 'optional badges cleanly absent noted');

    // Case 2: Legacy-looking but incompatible optional capability
    $pdo->exec('CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT, title TEXT, issuer TEXT, issueDate TEXT, verificationStatus TEXT, createdAt TEXT)');
    $resultWithCert = $checker->check(2, $fixtureRoot, static fn (): PDO => $pdo);
    phase_2_assert($resultWithCert->status() === 'READY', 'Phase 2 is READY with incompatible optional capability');
    $passesWithCert = array_column($resultWithCert->toArray()['passes'], 'message');
    phase_2_assert(in_array('Optional capability certificates is unavailable (incompatible schema).', $passesWithCert, true), 'incompatible certificates are not reported as available');

    // Case 3: Full canonical optional capability
    $pdo->exec('DROP TABLE certificates');
    $pdo->exec('CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT, title TEXT, issuingOrganization TEXT, issueDate TEXT, expiryDate TEXT, credentialId TEXT, credentialUrl TEXT, verificationStatus TEXT, verifiedBy TEXT, verifiedAt TEXT, createdAt TEXT, updatedAt TEXT)');
    $pdo->exec('CREATE INDEX idx_certificates_student_status ON certificates(studentId, verificationStatus)');
    $resultWithCanonicalCert = $checker->check(2, $fixtureRoot, static fn (): PDO => $pdo);
    phase_2_assert($resultWithCanonicalCert->status() === 'READY', 'Phase 2 is READY with canonical optional capability');
    $passesWithCanonicalCert = array_column($resultWithCanonicalCert->toArray()['passes'], 'message');
    phase_2_assert(in_array('Optional capability certificates is available.', $passesWithCanonicalCert, true), 'canonical certificates are reported as available');

    // Case 4: Partial optional capability (projects without project_members)
    $pdo->exec('CREATE TABLE projects (id TEXT PRIMARY KEY)');
    $resultPartial = $checker->check(2, $fixtureRoot, static fn (): PDO => $pdo);
    phase_2_assert($resultPartial->status() === 'READY', 'Phase 2 is READY even with partial optional capability');
    $passesPartial = array_column($resultPartial->toArray()['passes'], 'message');
    phase_2_assert(in_array('Optional capability projects is unavailable (partially present).', $passesPartial, true), 'partial capability projects noted as unavailable');

} finally {
    phase_2_cleanup_fixture_dir($fixtureRoot);
}

echo "learner_phase_2_optional_capabilities_test: OK\n";
