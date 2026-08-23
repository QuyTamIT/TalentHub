<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/app/learner/data/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseApplicationCommandRepository;
use TalentHub\Learner\Data\Service\ApplicationCommandService;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;
use TalentHub\Support\Uuid;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};
$expectApi = static function (callable $callback, string $code) use ($assert): void {
    try {
        $callback();
        $assert(false, "Expected ApiException {$code}");
    } catch (ApiException $exception) {
        $assert($exception->errorCode === $code, "Expected {$code}, got {$exception->errorCode}");
    }
};

$config = require dirname(__DIR__, 2) . '/config/database.php';
$schema = (string) ($config['database'] ?? '');
$assert(Environment::boolean('TALENTHUB_DISPOSABLE_TEST_DB', false), 'explicit disposable database gate is required');
$assert(preg_match('/\Atalenthub_phase7_rehearsal_\d{14}\z/', $schema) === 1, 'database must use the exact Phase 7 rehearsal prefix');
$pdo = (new Connection($config))->connect();
$pdo->exec("SET time_zone = '+00:00'");

foreach (['internship_posts', 'internship_applications', 'application_status_history', 'application_profile_snapshots'] as $table) {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'")->fetchColumn();
    $assert($count === 1, "{$table} exists in disposable schema");
}

$students = $pdo->query('SELECT sp.id AS studentId, sp.userId FROM student_profiles sp ORDER BY sp.id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$assert(count($students) === 2, 'two learner fixtures exist');
$studentA = $students[0];
$studentB = $students[1];
$enterpriseMember = $pdo->query('SELECT userId, enterpriseId FROM enterprise_members ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$assert(is_array($enterpriseMember), 'enterprise fixture exists');

$learner = new ApplicationCommandService(new DatabaseApplicationCommandRepository($pdo));
$enterprise = new InternshipService(new InternshipRepository($pdo));
$postInput = [
    'title' => 'Phase 7 Backend Internship',
    'field' => 'Software Engineering',
    'location' => 'Hà Nội',
    'workType' => 'hybrid',
    'duration' => '3 months',
    'educationLevel' => 'university',
    'description' => 'Production-backed Phase 7 test opportunity.',
    'benefits' => 'Mentoring',
    'skills' => ['PHP', 'SQL'],
    'requirements' => ['Student'],
    'slots' => 2,
    'deadline' => (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
];

$expectApi(fn () => $enterprise->createPost((string) $enterpriseMember['userId'], array_merge($postInput, ['enterpriseId' => Uuid::v4()])), 'VALIDATION_FAILED');
$expiredInput = array_replace($postInput, ['title' => 'Expired Phase 7 Opportunity', 'deadline' => (new DateTimeImmutable('-1 day', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')]);
$expiredDraft = $enterprise->createPost((string) $enterpriseMember['userId'], $expiredInput);
$expectApi(fn () => $enterprise->publish((string) $enterpriseMember['userId'], (string) $expiredDraft['id'], 'draft'), 'VALIDATION_FAILED');
$pdo->prepare("UPDATE internship_posts SET status = 'active' WHERE id = :id")->execute(['id' => $expiredDraft['id']]);
$expectApi(fn () => $learner->submit((string) $studentB['studentId'], (string) $studentB['userId'], 'expired-post', (string) $expiredDraft['id'], ''), 'OPPORTUNITY_NOT_AVAILABLE');

$draft = $enterprise->createPost((string) $enterpriseMember['userId'], $postInput);
$assert($draft['status'] === 'draft', 'enterprise creates an owned draft');
$postId = (string) $draft['id'];
$active = $enterprise->publish((string) $enterpriseMember['userId'], $postId, 'draft');
$assert($active['status'] === 'active', 'enterprise publishes own draft');

$expectApi(
    fn () => $learner->submit((string) $studentA['studentId'], (string) $studentA['userId'], 'missing-consent', $postId, ''),
    'CONSENT_REQUIRED',
);
$assert((int) $pdo->query('SELECT COUNT(*) FROM internship_applications')->fetchColumn() === 0, 'missing consent inserts no application');
$consent = $learner->grantConsent((string) $studentA['studentId'], (string) $studentA['userId'], 'grant-consent', true);
$assert($consent['scope'] === 'application_profile_share', 'explicit consent uses canonical scope');
$pdo->prepare("UPDATE student_profile_details SET avatarUrl = 'javascript:alert(1)' WHERE studentId = :studentId")->execute(['studentId' => $studentA['studentId']]);

$application = $learner->submit((string) $studentA['studentId'], (string) $studentA['userId'], 'submit', $postId, 'Xin ứng tuyển');
$applicationId = (string) $application['id'];
$assert($application['status'] === 'submitted', 'learner submits application');
$assert(count($application['history']) === 1 && $application['history'][0]['toStatus'] === 'submitted', 'initial history is atomic');
$snapshotBefore = json_encode($application['snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$assert(!str_contains($snapshotBefore, 'passwordHash') && !str_contains($snapshotBefore, 'reviewerNote'), 'snapshot excludes private/internal fields');
$assert(!str_contains($snapshotBefore, 'levelScore') && !str_contains($snapshotBefore, 'verificationStatus'), 'snapshot skills expose only the approved shape');
$assert(($application['snapshot']['student']['avatarUrl'] ?? null) === null, 'unsafe snapshot URL is removed');
foreach ($application['snapshot']['skills'] ?? [] as $skill) {
    $keys = array_keys($skill);
    sort($keys);
    $assert($keys === ['category', 'level', 'skillName'], 'snapshot skill uses the exact allow-list');
}
$assert(($application['snapshot']['consentId'] ?? null) === $consent['id'], 'snapshot references active consent');

$expectApi(
    fn () => $learner->submit((string) $studentA['studentId'], (string) $studentA['userId'], 'duplicate', $postId, ''),
    'DUPLICATE_APPLICATION',
);
$expectApi(
    fn () => $learner->detail((string) $studentB['studentId'], $applicationId),
    'RESOURCE_NOT_FOUND',
);

$pdo->prepare("UPDATE student_profile_details SET headline = 'Changed after submission' WHERE studentId = :studentId")->execute(['studentId' => $studentA['studentId']]);
$snapshotAfter = json_encode($learner->detail((string) $studentA['studentId'], $applicationId)['snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$assert($snapshotAfter === $snapshotBefore, 'snapshot stays immutable after profile edit');

$enterpriseRoleId = (string) $pdo->query("SELECT id FROM roles WHERE code = 'enterprise' LIMIT 1")->fetchColumn();
$otherUser = Uuid::v4();
$otherEnterprise = Uuid::v4();
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$pdo->prepare('INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt) VALUES (:id, :roleId, :email, :passwordHash, :fullName, \'active\', :createdAt, :updatedAt)')->execute(['id' => $otherUser, 'roleId' => $enterpriseRoleId, 'email' => $otherUser . '@phase7.test', 'passwordHash' => password_hash('Phase7Test!123', PASSWORD_DEFAULT), 'fullName' => 'Other Enterprise Reviewer', 'createdAt' => $now, 'updatedAt' => $now]);
$pdo->prepare("INSERT INTO enterprises (id, name, status, verificationStatus, createdAt, updatedAt) VALUES (:id, 'Other Enterprise', 'active', 'verified', :createdAt, :updatedAt)")->execute(['id' => $otherEnterprise, 'createdAt' => $now, 'updatedAt' => $now]);
$pdo->prepare("INSERT INTO enterprise_members (id, enterpriseId, userId, memberRole, createdAt, updatedAt) VALUES (:id, :enterpriseId, :userId, 'owner', :createdAt, :updatedAt)")->execute(['id' => Uuid::v4(), 'enterpriseId' => $otherEnterprise, 'userId' => $otherUser, 'createdAt' => $now, 'updatedAt' => $now]);
$expectApi(fn () => $enterprise->application($otherUser, $applicationId), 'RESOURCE_NOT_FOUND');

$reviewing = $enterprise->review((string) $enterpriseMember['userId'], $applicationId, ['expectedCurrentStatus' => 'submitted', 'targetStatus' => 'reviewing', 'reviewerNote' => 'Đang xem xét']);
$assert($reviewing['status'] === 'reviewing', 'owner enterprise reviews application');
$expectApi(fn () => $enterprise->review((string) $enterpriseMember['userId'], $applicationId, ['expectedCurrentStatus' => 'submitted', 'targetStatus' => 'declined']), 'CONCURRENT_MODIFICATION');

$withdrawn = $learner->withdraw((string) $studentA['studentId'], (string) $studentA['userId'], 'withdraw', $applicationId, 'Đổi kế hoạch');
$assert($withdrawn['status'] === 'withdrawn', 'learner can withdraw from reviewing');
$assert(count($withdrawn['history']) === 3, 'withdraw appends ordered history');
$expectApi(fn () => $enterprise->review((string) $enterpriseMember['userId'], $applicationId, ['expectedCurrentStatus' => 'withdrawn', 'targetStatus' => 'accepted']), 'ILLEGAL_STATUS_TRANSITION');

// Force history failure after application update and prove the transaction rolls the update back.
$secondDraft = $enterprise->createPost((string) $enterpriseMember['userId'], $postInput + ['title' => 'Rollback Probe']);
$secondPost = (string) $secondDraft['id'];
$enterprise->publish((string) $enterpriseMember['userId'], $secondPost, 'draft');
$pdo->prepare('UPDATE privacy_consents SET isGranted = 0, grantedAt = NULL, revokedAt = :revokedAt WHERE id = :id')->execute(['revokedAt' => $now, 'id' => $consent['id']]);
$expectApi(fn () => $learner->submit((string) $studentA['studentId'], (string) $studentA['userId'], 'revoked-consent', $secondPost, ''), 'CONSENT_REQUIRED');
$renewedConsent = $learner->grantConsent((string) $studentA['studentId'], (string) $studentA['userId'], 'renew-consent', true);
$assert($renewedConsent['id'] !== $consent['id'], 'revoked consent is renewed by a separate explicit command');
$secondApplication = $learner->submit((string) $studentA['studentId'], (string) $studentA['userId'], 'submit-2', $secondPost, 'Rollback probe');
$secondApplicationId = (string) $secondApplication['id'];
$pdo->exec("ALTER TABLE application_status_history ADD CONSTRAINT chk_phase7_history_failure CHECK (note <> 'rollback-probe')");
try {
    $enterprise->review((string) $enterpriseMember['userId'], $secondApplicationId, ['expectedCurrentStatus' => 'submitted', 'targetStatus' => 'reviewing', 'reviewerNote' => 'rollback-probe']);
    $assert(false, 'forced history failure must escape');
} catch (Throwable) {
    $status = $pdo->query("SELECT status FROM internship_applications WHERE id = " . $pdo->quote($secondApplicationId))->fetchColumn();
    $assert($status === 'submitted', 'application update rolls back when history insert fails');
}
$pdo->exec('ALTER TABLE application_status_history DROP CHECK chk_phase7_history_failure');

echo "EnterpriseApplicationLifecycleTest: OK ({$assertions} assertions)\n";
