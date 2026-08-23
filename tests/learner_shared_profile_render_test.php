<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Service\ProfileSharingService;
use TalentHub\Support\Uuid;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, name TEXT NOT NULL, gradeLevel INTEGER NOT NULL, academicYear TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT NOT NULL, fullName TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL, dateOfBirth TEXT NOT NULL, phone TEXT NOT NULL, studyStatus TEXT NOT NULL);
CREATE TABLE student_profile_details (studentId TEXT PRIMARY KEY, location TEXT NULL, bio TEXT NULL, avatarUrl TEXT NULL, headline TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL);
CREATE TABLE student_profile_shares (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, consentId TEXT NULL, tokenHash TEXT NOT NULL UNIQUE, sharedFieldsJson TEXT NOT NULL, expiresAt TEXT NOT NULL, revokedAt TEXT NULL, createdAt TEXT NOT NULL);
CREATE TABLE privacy_consents (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, scope TEXT NOT NULL, isGranted INTEGER NOT NULL DEFAULT 1, policyVersion TEXT NOT NULL, grantedAt TEXT NULL, revokedAt TEXT NULL, createdAt TEXT NOT NULL);
CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, category TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE student_skills (studentId TEXT NOT NULL, skillId TEXT NOT NULL, levelScore REAL NOT NULL, sourceType TEXT NOT NULL, verificationStatus TEXT NOT NULL, verifiedAt TEXT NULL);
CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, title TEXT NOT NULL, issuingOrganization TEXT NOT NULL, issueDate TEXT NOT NULL, expiryDate TEXT NULL, credentialId TEXT NULL, credentialUrl TEXT NULL, verificationStatus TEXT NOT NULL, verifiedBy TEXT NULL, verifiedAt TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL);
CREATE INDEX idx_certificates_student_status ON certificates(studentId, verificationStatus);
CREATE TABLE projects (id TEXT PRIMARY KEY, schoolId TEXT NULL, mentorTeacherId TEXT NULL, title TEXT NOT NULL, description TEXT NULL, projectUrl TEXT NULL, startAt TEXT NULL, endAt TEXT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL);
CREATE TABLE project_members (id TEXT PRIMARY KEY, projectId TEXT NOT NULL, studentId TEXT NOT NULL, role TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT NOT NULL);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, qrSessionId TEXT NULL, status TEXT NOT NULL, checkedInAt TEXT NULL, confirmedAt TEXT NULL);
CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL);
CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, startedAt TEXT NOT NULL, submittedAt TEXT NOT NULL);
CREATE TABLE test_results (attemptId TEXT NOT NULL PRIMARY KEY, resultCode TEXT NOT NULL, summary TEXT NOT NULL, dimensionScoresJson TEXT NOT NULL, scoringVersion TEXT NOT NULL, createdAt TEXT NOT NULL);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL);
CREATE TABLE assessments (id TEXT PRIMARY KEY, teacherId TEXT NOT NULL, studentId TEXT NOT NULL, activityId TEXT NOT NULL, overallScore REAL NOT NULL, comment TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT NOT NULL, version INTEGER NOT NULL);
CREATE TABLE assessment_scores (assessmentId TEXT NOT NULL, criteriaId TEXT NOT NULL, score REAL NOT NULL);
CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, minScore REAL NOT NULL, maxScore REAL NOT NULL, displayOrder INTEGER NOT NULL, status TEXT NOT NULL);
SQL
);

$schoolId = '0191316b-1000-7000-8000-000000000001';
$classId = '0191316b-2000-7000-8000-000000000001';
$userId = '0191316b-3000-7000-8000-000000000001';
$studentId = '0191316b-4000-7000-8000-000000000001';

$pdo->prepare('INSERT INTO schools (id, name, status) VALUES (?, ?, ?)')->execute([$schoolId, 'THPT Chu Văn An', 'active']);
$pdo->prepare('INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$classId, $schoolId, '12A1', 12, '2025-2026', 'active']);
$pdo->prepare('INSERT INTO users (id, email, fullName, status) VALUES (?, ?, ?, ?)')->execute([$userId, 'student.secret@example.test', '<script>alert(1)</script>Nguyễn Văn A', 'active']);
$pdo->prepare('INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (?, ?, ?, ?, ?, ?)')->execute([$studentId, $userId, $classId, '2008-01-01', '0901234567', 'studying']);
$pdo->prepare('INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, datetime("now"), datetime("now"))')->execute([$studentId, 'Hà Nội', 'Bio text', 'data:image/svg+xml,<svg onload=alert(1)>', 'Software Enthusiast']);
$pdo->prepare('INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute(['activity-1', $schoolId, 'teacher-1', 'STEM Experience', 'stem', '2026-01-01', '2026-01-02', 'completed']);
$pdo->prepare('INSERT INTO experience_logs (id, studentId, activityId, checkinId, hours, status, confirmedAt) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute(['experience-1', $studentId, 'activity-1', 'checkin-1', 2.5, 'confirmed', '2026-01-02']);

$service = new ProfileSharingService($pdo);

// Create share without email and phone
$shareWithoutContact = $service->createShare($studentId, ['fullName', 'headline', 'bio', 'location', 'skills', 'experience', 'certificates'], 7);

$certificateId = Uuid::v4();
$pdo->prepare(<<<'SQL'
INSERT INTO certificates (
  id, studentId, title, issuingOrganization, issueDate, expiryDate,
  credentialId, credentialUrl, verificationStatus, verifiedBy, verifiedAt, createdAt, updatedAt
) VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, 'unverified', NULL, NULL, datetime('now'), datetime('now'))
SQL
)->execute([
    $certificateId,
    $studentId,
    'Hostile URL Certificate',
    'Example Academy',
    '2026-01-01',
    'javascript://x/%0Aalert(document.domain)',
]);

$resolved = $service->resolveShare($shareWithoutContact['rawToken']);
$assert($resolved !== null, 'Valid token resolves share data.');
$assert($resolved['student']['fullName'] === '<script>alert(1)</script>Nguyễn Văn A', 'Full name resolved.');
$assert($resolved['student']['headline'] === 'Software Enthusiast', 'Selected headline resolves from profile details.');
$assert($resolved['student']['bio'] === 'Bio text', 'Selected bio resolves from profile details.');
$assert($resolved['student']['location'] === 'Hà Nội', 'Selected location resolves from profile details.');
$assert(!isset($resolved['student']['avatarUrl']), 'Avatar is not part of the public Phase 3 sharing contract.');
$assert(!isset($resolved['student']['dateOfBirth']), 'Date of birth is not part of the public Phase 3 sharing contract.');
$assert(isset($resolved['sharedAt'], $resolved['expiresAt']), 'Resolved share exposes creation and expiry timestamps separately.');
$assert($resolved['sharedAt'] !== $resolved['expiresAt'], 'sharedAt is not mislabeled expiry time.');
$assert(!isset($resolved['student']['email']), 'Email is excluded because it was not in sharedFields.');
$assert(!isset($resolved['student']['phone']), 'Phone is excluded because it was not in sharedFields.');

$contactShare = $service->createShare($studentId, ['fullName', 'email', 'phone'], 7);
$contactResolved = $service->resolveShare($contactShare['rawToken']);
$assert($contactResolved['student']['email'] === 'student.secret@example.test', 'Explicitly selected email resolves.');
$assert($contactResolved['student']['phone'] === '0901234567', 'Explicitly selected phone resolves.');
$contactConsent = $pdo->prepare('UPDATE privacy_consents SET isGranted = 0, grantedAt = NULL, revokedAt = datetime("now") WHERE id = (SELECT consentId FROM student_profile_shares WHERE id = ?)');
$contactConsent->execute([$contactShare['id']]);
$assert($service->resolveShare($contactShare['rawToken']) === null, 'A separately revoked linked consent immediately disables its share token.');

// Render the real public template with the same PDO fixture.
$GLOBALS['__TALENTHUB_TEST_PDO__'] = $pdo;
$_GET['token'] = $shareWithoutContact['rawToken'];
ob_start();
require dirname(__DIR__) . '/app/learner/shared-profile.php';
$rendered = (string) ob_get_clean();
unset($GLOBALS['__TALENTHUB_TEST_PDO__']);
$assert(str_contains($rendered, '&lt;script&gt;alert(1)&lt;/script&gt;Nguyễn Văn A'), 'Actual shared view escapes hostile profile text.');
$assert(!str_contains($rendered, 'href="javascript:'), 'Actual shared view never renders an unsafe certificate href.');
$assert(!str_contains($rendered, 'src="data:'), 'Actual shared view never renders an unsafe avatar source.');
$assert(!str_contains($rendered, $shareWithoutContact['rawToken']), 'Actual shared view does not echo the raw token.');
$assert(
    str_contains($rendered, 'STEM Experience'),
    'Actual shared view renders explicitly selected experience evidence: ' . json_encode($resolved['experience'] ?? null, JSON_UNESCAPED_SLASHES),
);
$assert(str_contains($rendered, '2.5'), 'Actual shared view renders confirmed experience hours.');
$sharedProfileSource = file_get_contents(dirname(__DIR__) . '/app/learner/shared-profile.php');
$assert(is_string($sharedProfileSource) && str_contains($sharedProfileSource, "header('Referrer-Policy: no-referrer')"), 'Public view sends no-referrer policy.');

// Expired token resolution returns null
$expiredToken = bin2hex(random_bytes(32));
$pdo->prepare(<<<'SQL'
INSERT INTO student_profile_shares (id, studentId, tokenHash, sharedFieldsJson, expiresAt, revokedAt, createdAt)
VALUES (?, ?, ?, ?, datetime('now', '-1 day'), NULL, datetime('now', '-2 days'))
SQL
)->execute([Uuid::v4(), $studentId, hash('sha256', $expiredToken), json_encode(['fullName'])]);

$assert($service->resolveShare($expiredToken) === null, 'Expired token resolves to null.');

// Revoked token resolution returns null
$revokedToken = bin2hex(random_bytes(32));
$pdo->prepare(<<<'SQL'
INSERT INTO student_profile_shares (id, studentId, tokenHash, sharedFieldsJson, expiresAt, revokedAt, createdAt)
VALUES (?, ?, ?, ?, datetime('now', '+7 days'), datetime('now', '-1 hour'), datetime('now', '-2 days'))
SQL
)->execute([Uuid::v4(), $studentId, hash('sha256', $revokedToken), json_encode(['fullName'])]);

$assert($service->resolveShare($revokedToken) === null, 'Revoked token resolves to null.');

// Legacy shares without an explicit consent link fail closed.
$legacyToken = bin2hex(random_bytes(32));
$pdo->prepare(<<<'SQL'
INSERT INTO student_profile_shares (id, studentId, consentId, tokenHash, sharedFieldsJson, expiresAt, revokedAt, createdAt)
VALUES (?, ?, NULL, ?, ?, datetime('now', '+7 days'), NULL, datetime('now'))
SQL
)->execute([Uuid::v4(), $studentId, hash('sha256', $legacyToken), json_encode(['fullName'])]);
$assert($service->resolveShare($legacyToken) === null, 'Legacy share without linked consent fails closed.');

foreach (['avatarUrl', 'dateOfBirth', 'assessmentResults', 'teacherEvaluations'] as $unsupportedPublicField) {
    $assert(!in_array($unsupportedPublicField, ProfileSharingService::ALLOWED_FIELDS, true), "Unsupported public field {$unsupportedPublicField} is not selectable.");
}
$sharingSource = file_get_contents(dirname(__DIR__) . '/app/learner/data/Service/ProfileSharingService.php');
$assert(is_string($sharingSource) && !str_contains($sharingSource, 'aggregateForStudent('), 'Public resolver does not load unrelated assessment/evaluation aggregate data.');
$assert(!str_contains($sharingSource, 'sp.dateOfBirth'), 'Public resolver does not query unselectable date of birth.');
$assert(!str_contains($sharingSource, 'spd.avatarUrl'), 'Public resolver does not query unselectable avatar URL.');

echo "learner_shared_profile_render_test: OK\n";
