<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\StudentSafeguardingService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
foreach ([
    'CREATE TABLE school_members (id TEXT PRIMARY KEY, schoolId TEXT, userId TEXT, memberRole TEXT)',
    'CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT, isSchoolAdmin INTEGER)',
    'CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT)',
    'CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, gradeLevel INTEGER)',
    'CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, dateOfBirth TEXT)',
    'CREATE TABLE enterprises (id TEXT PRIMARY KEY)',
    'CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, educationLevel TEXT, status TEXT)',
    'CREATE TABLE school_enterprise_partnerships (schoolId TEXT, enterpriseId TEXT, status TEXT)',
    'CREATE TABLE student_safeguarding_policies (schoolId TEXT PRIMARY KEY, minimumDirectContactAge INTEGER, guardianConsentRequired INTEGER, schoolApprovalRequired INTEGER, updatedByUserId TEXT)',
    'CREATE TABLE student_guardian_consents (id TEXT PRIMARY KEY, studentId TEXT, enterpriseId TEXT, grantedByUserId TEXT, grantedAt TEXT, expiresAt TEXT, revokedAt TEXT)',
    'CREATE TABLE student_enterprise_school_approvals (id TEXT PRIMARY KEY, studentId TEXT, enterpriseId TEXT, approvedByUserId TEXT, approvedAt TEXT, expiresAt TEXT, revokedAt TEXT)',
    'CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, metadata TEXT)',
] as $sql) {
    $pdo->exec($sql);
}

$actorA = '00000000-0000-4000-8000-000000000001';
$actorB = '00000000-0000-4000-8000-000000000002';
$schoolA = '10000000-0000-4000-8000-000000000001';
$schoolB = '10000000-0000-4000-8000-000000000002';
$minor = '20000000-0000-4000-8000-000000000001';
$adult = '20000000-0000-4000-8000-000000000002';
$enterprise = '30000000-0000-4000-8000-000000000001';
$schoolPost = '40000000-0000-4000-8000-000000000001';
$universityPost = '40000000-0000-4000-8000-000000000002';

$pdo->exec("INSERT INTO schools VALUES ('{$schoolA}','School A'),('{$schoolB}','School B')");
$pdo->exec("INSERT INTO school_members VALUES ('m-a','{$schoolA}','{$actorA}','admin'),('m-b','{$schoolB}','{$actorB}','admin')");
$pdo->exec("INSERT INTO classes VALUES ('class-a','{$schoolA}',10),('class-b','{$schoolA}',12)");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$minor}','class-a','2010-01-01'),('{$adult}','class-b','2000-01-01')");
$pdo->exec("INSERT INTO enterprises VALUES ('{$enterprise}')");
$pdo->exec("INSERT INTO internship_posts VALUES ('{$schoolPost}','{$enterprise}','Trung học phổ thông','active'),('{$universityPost}','{$enterprise}','Đại học','active')");
$pdo->exec("INSERT INTO school_enterprise_partnerships VALUES ('{$schoolA}','{$enterprise}','approved')");

$service = new StudentSafeguardingService($pdo, new SchoolAuthorization($pdo));
$service->updatePolicy($actorA, [
    'minimumDirectContactAge' => 18,
    'guardianConsentRequired' => true,
    'schoolApprovalRequired' => true,
]);

$eligibility = $service->eligibility($minor, $enterprise, $schoolPost);
$assert($eligibility['eligible'] === false, 'Minor without guardian consent must be blocked.');
$assert($eligibility['blockedReason'] === 'GUARDIAN_CONSENT_REQUIRED', 'Minor block reason must be machine-readable.');
$assert(!in_array('phone', $eligibility['allowedSnapshotFields'], true), 'Minor snapshot must not contain phone.');
$assert(!in_array('email', $eligibility['allowedSnapshotFields'], true), 'Minor snapshot must not contain email.');
$assert(!in_array('dateOfBirth', $eligibility['allowedSnapshotFields'], true), 'Minor snapshot must not contain full date of birth.');

$expiresAt = gmdate(DATE_ATOM, time() + 86400);
$consentId = $service->grantGuardianConsent($actorA, $minor, $enterprise, $expiresAt);
$eligibility = $service->eligibility($minor, $enterprise, $schoolPost);
$assert($eligibility['blockedReason'] === 'SCHOOL_APPROVAL_REQUIRED', 'School approval must be independently required.');
$service->approveEnterpriseAccess($actorA, $minor, $enterprise, $expiresAt);
$eligibility = $service->eligibility($minor, $enterprise, $schoolPost);
$assert($eligibility['eligible'] === true, 'Minor with both scoped decisions and partnership should be eligible.');
$assert($eligibility['contactMode'] === 'school_mediated', 'Minor contact must remain school-mediated.');

$service->revokeGuardianConsent($actorA, $consentId);
$eligibility = $service->eligibility($minor, $enterprise, $schoolPost);
$assert($eligibility['blockedReason'] === 'GUARDIAN_CONSENT_REQUIRED', 'Revoked guardian consent must stop access immediately.');

$education = $service->eligibility($minor, $enterprise, $universityPost);
$assert($education['blockedReason'] === 'EDUCATION_LEVEL_NOT_ELIGIBLE', 'A minor must not pass a higher-education post gate.');
$adultEligibility = $service->eligibility($adult, $enterprise, $universityPost);
$assert($adultEligibility['eligible'] === true, 'An adult student with an approved partnership should not require guardian consent.');

try {
    $service->grantGuardianConsent($actorB, $minor, $enterprise, $expiresAt);
    throw new RuntimeException('Cross-school safeguarding write must be rejected.');
} catch (ApiException $exception) {
    $assert($exception->status === 403, 'Cross-school safeguarding rejection must be forbidden.');
}

echo "student_minor_enterprise_safeguarding_test: OK\n";
