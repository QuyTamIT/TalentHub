<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
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
CREATE TABLE users (
  id TEXT PRIMARY KEY,
  fullName TEXT NOT NULL,
  email TEXT NOT NULL
);
CREATE TABLE student_profiles (
  id TEXT PRIMARY KEY,
  userId TEXT NOT NULL,
  phone TEXT NOT NULL,
  classId TEXT NOT NULL
);
CREATE TABLE student_profile_details (
  studentId TEXT PRIMARY KEY,
  location TEXT NULL,
  bio TEXT NULL,
  avatarUrl TEXT NULL,
  headline TEXT NULL,
  createdAt TEXT NOT NULL,
  updatedAt TEXT NOT NULL
);
CREATE TABLE student_profile_shares (
  id TEXT PRIMARY KEY,
  studentId TEXT NOT NULL,
  consentId TEXT NULL,
  tokenHash TEXT NOT NULL UNIQUE,
  sharedFieldsJson TEXT NOT NULL,
  expiresAt TEXT NOT NULL,
  revokedAt TEXT NULL,
  createdAt TEXT NOT NULL
);
CREATE TABLE privacy_consents (
  id TEXT PRIMARY KEY,
  studentId TEXT NOT NULL,
  scope TEXT NOT NULL,
  isGranted INTEGER NOT NULL DEFAULT 1,
  policyVersion TEXT NOT NULL,
  grantedAt TEXT NULL,
  revokedAt TEXT NULL,
  createdAt TEXT NOT NULL
);
SQL
);

$studentA = '0191316b-4000-7000-8000-000000000001';
$studentB = '0191316b-4000-7000-8000-000000000002';
$pdo->prepare('INSERT INTO student_profiles (id, userId, phone, classId) VALUES (?, ?, ?, ?)')->execute([$studentA, 'user-a', '0901111111', 'class-1']);
$pdo->prepare('INSERT INTO student_profiles (id, userId, phone, classId) VALUES (?, ?, ?, ?)')->execute([$studentB, 'user-b', '0902222222', 'class-1']);

$service = new ProfileSharingService($pdo);

// 1. Create profile share for Student A
$share = $service->createShare($studentA, ['fullName', 'skills', 'certificates'], 7);
$assert(is_string($share['rawToken']) && strlen($share['rawToken']) === 64, 'Raw token is 64 hex chars (32 bytes).');
$assert(str_contains($share['shareUrl'], $share['rawToken']), 'Share URL contains raw token.');
$assert(is_string($share['id']) && Uuid::isValid($share['id']), 'Share has valid UUID id.');

// 2. Assert raw token is NOT in database
$dbShare = $pdo->query("SELECT * FROM student_profile_shares WHERE id = '{$share['id']}'")->fetch(PDO::FETCH_ASSOC);
$assert($dbShare !== false, 'Share row exists in DB.');
$assert($dbShare['tokenHash'] === hash('sha256', $share['rawToken']), 'DB stores SHA-256 hash.');
$assert(!str_contains(json_encode($dbShare), $share['rawToken']), 'Raw token is NOT present in database row.');

// 3. Assert consent record is created
$consent = $pdo->query("SELECT * FROM privacy_consents WHERE studentId = '{$studentA}' AND scope = 'profile_share'")->fetch(PDO::FETCH_ASSOC);
$assert($consent !== false, 'privacy_consents record created for profile_share.');
$assert($dbShare['consentId'] === $consent['id'], 'Profile share links to its explicit consent record.');

// 4. List shares
$list = $service->listShares($studentA);
$assert(count($list) === 1, 'Student A has 1 share.');
$assert(!isset($list[0]['rawToken']) && !isset($list[0]['tokenHash']), 'List does not leak raw token or hash.');
$assert($list[0]['isRevoked'] === false, 'Share is not revoked.');

// 5. Cross-student revoke denial
try {
    $service->revokeShare($studentB, $share['id']);
    fwrite(STDERR, "Student B should not be able to revoke Student A share\n");
    exit(1);
} catch (ApiException $e) {
    $assert($e->status === 404, 'Cross-student revoke returns 404.');
}

// 6. Owner revokes share
$service->revokeShare($studentA, $share['id']);
$listAfterRevoke = $service->listShares($studentA);
$assert($listAfterRevoke[0]['isRevoked'] === true, 'Share is marked revoked.');
$revokedConsent = $pdo->query("SELECT * FROM privacy_consents WHERE id = '{$consent['id']}'")->fetch(PDO::FETCH_ASSOC);
$assert((int) $revokedConsent['isGranted'] === 0, 'Linked profile-share consent is revoked.');
$assert($revokedConsent['grantedAt'] === null, 'Revoked consent no longer has grantedAt.');
$assert(is_string($revokedConsent['revokedAt']) && $revokedConsent['revokedAt'] !== '', 'Revoked consent records revokedAt.');

// 7. Consent and share insert roll back together.
$pdo->exec(<<<'SQL'
CREATE TRIGGER abort_profile_share_insert
BEFORE INSERT ON student_profile_shares
BEGIN
  SELECT RAISE(ABORT, 'injected share insert failure');
END;
SQL
);
$consentCountBeforeFailure = (int) $pdo->query('SELECT COUNT(*) FROM privacy_consents')->fetchColumn();
try {
    $service->createShare($studentA, ['fullName'], 7);
    fwrite(STDERR, "Injected share failure should throw\n");
    exit(1);
} catch (Throwable) {
    // Expected injected storage failure.
}
$consentCountAfterFailure = (int) $pdo->query('SELECT COUNT(*) FROM privacy_consents')->fetchColumn();
$assert($consentCountAfterFailure === $consentCountBeforeFailure, 'Failed share creation rolls back consent insert.');

echo "learner_profile_privacy_api_test: OK\n";
