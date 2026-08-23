<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseCertificateCommandRepository;
use TalentHub\Learner\Data\Service\CertificateCommandService;
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
CREATE TABLE users (
  id TEXT PRIMARY KEY,
  fullName TEXT NOT NULL
);
CREATE TABLE student_profiles (
  id TEXT PRIMARY KEY,
  userId TEXT NOT NULL
);
CREATE TABLE certificates (
  id TEXT PRIMARY KEY,
  studentId TEXT NOT NULL,
  title TEXT NOT NULL,
  issuingOrganization TEXT NOT NULL,
  issueDate TEXT NOT NULL,
  expiryDate TEXT NULL,
  credentialId TEXT NULL,
  credentialUrl TEXT NULL,
  verificationStatus TEXT NOT NULL DEFAULT 'unverified',
  verifiedBy TEXT NULL,
  verifiedAt TEXT NULL,
  createdAt TEXT NOT NULL,
  updatedAt TEXT NOT NULL
);
SQL
);

$studentA = '0191316b-4000-7000-8000-000000000001';
$studentB = '0191316b-4000-7000-8000-000000000002';
$adminUser = '0191316b-1000-7000-8000-000000000001';

$pdo->prepare('INSERT INTO student_profiles (id, userId) VALUES (?, ?)')->execute([$studentA, 'user-a']);
$pdo->prepare('INSERT INTO student_profiles (id, userId) VALUES (?, ?)')->execute([$studentB, 'user-b']);
$pdo->prepare('INSERT INTO users (id, fullName) VALUES (?, ?)')->execute([$adminUser, 'Admin Verifier']);

$repository = new DatabaseCertificateCommandRepository($pdo);
$service = new CertificateCommandService($repository);

// 1. Create unverified certificate for Student A
$certA = $service->create($studentA, [
    'title' => 'AWS Certified Cloud Practitioner',
    'issuingOrganization' => 'Amazon Web Services',
    'issueDate' => '2026-01-15',
    'expiryDate' => '2029-01-15',
    'credentialId' => 'AWS-123456',
    'credentialUrl' => 'https://aws.amazon.com/verify/123456',
]);

$assert($certA['title'] === 'AWS Certified Cloud Practitioner', 'Certificate created with title.');
$assert($certA['studentId'] === $studentA, 'Certificate studentId matches owner.');
$assert($certA['verificationStatus'] === 'unverified', 'New certificate starts as unverified.');
$assert($certA['verifiedBy'] === null, 'verifiedBy is null.');
$assert($certA['verifiedAt'] === null, 'verifiedAt is null.');

// Reject URL schemes and embedded credentials that are unsafe in public links.
foreach ([
    'javascript://x/%0Aalert(document.domain)',
    'http://example.test/certificate',
    'https://user:password@example.test/certificate',
] as $unsafeUrl) {
    try {
        $service->create($studentA, [
            'title' => 'Unsafe URL Certificate',
            'issuingOrganization' => 'Example Org',
            'issueDate' => '2026-01-01',
            'credentialUrl' => $unsafeUrl,
        ]);
        fwrite(STDERR, "Unsafe credential URL should be rejected: {$unsafeUrl}\n");
        exit(1);
    } catch (ApiException $e) {
        $assert($e->status === 422, 'Unsafe credential URL returns 422.');
    }
}

try {
    $service->update($studentA, $certA['id'], []);
    fwrite(STDERR, "Empty certificate update should be rejected\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Empty certificate update returns 422.');
}

try {
    $service->update($studentA, $certA['id'], ['expiryDate' => '2025-01-01']);
    fwrite(STDERR, "Expiry before persisted issue date should be rejected\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Expiry before persisted issue date returns 422.');
}

$pastExpiryCertificate = $service->create($studentA, [
    'title' => 'Past Training Certificate',
    'issuingOrganization' => 'Example Academy',
    'issueDate' => '2024-01-01',
    'expiryDate' => '2025-01-01',
]);
try {
    $service->update($studentA, $pastExpiryCertificate['id'], ['issueDate' => '2026-01-01']);
    fwrite(STDERR, "Issue date after persisted expiry should be rejected\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Issue date after persisted expiry returns 422.');
}

// 2. Reject client setting verificationStatus or verifiedBy
try {
    $service->create($studentA, [
        'title' => 'Fake Verified Cert',
        'issuingOrganization' => 'Fake Org',
        'issueDate' => '2026-01-01',
        'verificationStatus' => 'verified',
    ]);
    fwrite(STDERR, "Should not allow client to set verificationStatus\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Reject setting verificationStatus with 422.');
}

// 3. Update own unverified certificate
$updatedCertA = $service->update($studentA, $certA['id'], [
    'title' => 'AWS Certified Cloud Practitioner (Updated)',
    'issuingOrganization' => 'Amazon Web Services',
    'issueDate' => '2026-01-15',
    'expiryDate' => '2029-01-15',
]);
$assert($updatedCertA['title'] === 'AWS Certified Cloud Practitioner (Updated)', 'Title updated.');

try {
    $repository->update($studentA, $certA['id'], ['expiryDate' => '2025-01-01']);
    fwrite(STDERR, "Repository must reject an invalid merged date state under its own lock\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Repository invalid merged date state returns 422.');
}

// 4. Cross-student isolation: Student B cannot update or delete Student A certificate
try {
    $service->update($studentB, $certA['id'], [
        'title' => 'Hacked by Student B',
        'issuingOrganization' => 'Hacker Org',
        'issueDate' => '2026-01-15',
    ]);
    fwrite(STDERR, "Should not allow cross-student update\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 404, 'Cross-student update returns 404.');
}

try {
    $service->delete($studentB, $certA['id']);
    fwrite(STDERR, "Should not allow cross-student delete\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 404, 'Cross-student delete returns 404.');
}

// 5. Verified certificate cannot be modified or deleted by student
$verifiedCertId = Uuid::v4();
$pdo->prepare(<<<'SQL'
INSERT INTO certificates (id, studentId, title, issuingOrganization, issueDate, verificationStatus, verifiedBy, verifiedAt, createdAt, updatedAt)
VALUES (?, ?, ?, ?, ?, 'verified', ?, datetime('now'), datetime('now'), datetime('now'))
SQL
)->execute([$verifiedCertId, $studentA, 'Verified IELTS 8.0', 'British Council', '2025-10-01', $adminUser]);

try {
    $service->update($studentA, $verifiedCertId, [
        'title' => 'Modified IELTS',
        'issuingOrganization' => 'British Council',
        'issueDate' => '2025-10-01',
    ]);
    fwrite(STDERR, "Should not allow modifying verified certificate\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Modifying verified cert returns 422.');
}

try {
    $service->delete($studentA, $verifiedCertId);
    fwrite(STDERR, "Should not allow deleting verified certificate\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 422, 'Deleting verified cert returns 422.');
}

// 6. Delete own unverified certificate succeeds
$service->delete($studentA, $certA['id']);
$listA = $service->list($studentA);
$assert(count($listA) === 2, 'Only the verified and date-validation fixture certificates remain for Student A.');
$assert(in_array($verifiedCertId, array_column($listA, 'id'), true), 'Verified certificate remains present.');

$repositorySource = file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseCertificateCommandRepository.php');
$assert(is_string($repositorySource), 'Certificate repository source is readable.');
$assert(substr_count($repositorySource, 'beginTransaction()') >= 3, 'Every certificate mutation owns a transaction.');
$assert(
    substr_count($repositorySource, "verificationStatus = 'unverified'") >= 2,
    'Update and delete enforce unverified state in their final mutation predicates.'
);

echo "learner_certificate_api_test: OK\n";
