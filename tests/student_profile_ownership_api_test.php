<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Http\ApiException;

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

// Create SQLite schema
$pdo->exec(<<<'SQL'
CREATE TABLE schools (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  status TEXT NOT NULL
);
CREATE TABLE classes (
  id TEXT PRIMARY KEY,
  schoolId TEXT NOT NULL,
  name TEXT NOT NULL,
  gradeLevel INTEGER NOT NULL,
  academicYear TEXT NOT NULL,
  status TEXT NOT NULL
);
CREATE TABLE users (
  id TEXT PRIMARY KEY,
  email TEXT NOT NULL,
  passwordHash TEXT NOT NULL,
  fullName TEXT NOT NULL,
  roles TEXT NOT NULL,
  status TEXT NOT NULL,
  createdAt TEXT NOT NULL
);
CREATE TABLE student_profiles (
  id TEXT PRIMARY KEY,
  userId TEXT NOT NULL UNIQUE,
  classId TEXT NOT NULL,
  dateOfBirth TEXT NOT NULL,
  phone TEXT NOT NULL,
  studyStatus TEXT NOT NULL,
  createdAt TEXT NOT NULL,
  updatedAt TEXT NOT NULL
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
SQL
);

$schoolId = '0191316b-1000-7000-8000-000000000001';
$classId = '0191316b-2000-7000-8000-000000000001';
$userId = '0191316b-3000-7000-8000-000000000001';
$studentId = '0191316b-4000-7000-8000-000000000001';

$pdo->prepare('INSERT INTO schools (id, name, status) VALUES (?, ?, ?)')->execute([$schoolId, 'THPT Demo', 'active']);
$pdo->prepare('INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$classId, $schoolId, '12A1', 12, '2025-2026', 'active']);
$pdo->prepare('INSERT INTO users (id, email, passwordHash, fullName, roles, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$userId, 'student@example.test', 'hash', 'Nguyễn Văn A', 'student', 'active', '2026-08-01 00:00:00']);
$pdo->prepare('INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$studentId, $userId, $classId, '2008-05-15', '0901234567', 'studying', '2026-08-01 00:00:00', '2026-08-01 00:00:00']);

$repository = new StudentRepository($pdo);
$service = new StudentProfileService($repository);

// 1. Test initial get includes detail fields
$profile = $service->get($userId);
$assert($profile['fullName'] === 'Nguyễn Văn A', 'Profile has fullName.');
$assert(array_key_exists('location', $profile), 'Profile array includes location key.');
$assert(array_key_exists('bio', $profile), 'Profile array includes bio key.');
$assert(array_key_exists('avatarUrl', $profile), 'Profile array includes avatarUrl key.');
$assert(array_key_exists('headline', $profile), 'Profile array includes headline key.');

// 2. Test successful update of allowed fields
$updated = $service->update($userId, [
    'fullName' => 'Nguyễn Văn B',
    'dateOfBirth' => '2008-06-20',
    'phone' => '0909999888',
    'location' => 'Hà Nội, Việt Nam',
    'bio' => 'Học sinh đam mê công nghệ và AI.',
    'avatarUrl' => 'https://example.test/avatar.png',
    'headline' => 'Aspiring Software Engineer',
]);

$assert($updated['fullName'] === 'Nguyễn Văn B', 'Full name updated.');
$assert($updated['dateOfBirth'] === '2008-06-20', 'Date of birth updated.');
$assert($updated['phone'] === '0909999888', 'Phone updated.');
$assert($updated['location'] === 'Hà Nội, Việt Nam', 'Location updated.');
$assert($updated['bio'] === 'Học sinh đam mê công nghệ và AI.', 'Bio updated.');
$assert($updated['avatarUrl'] === 'https://example.test/avatar.png', 'Avatar URL updated.');
$assert($updated['headline'] === 'Aspiring Software Engineer', 'Headline updated.');

foreach ([
    'javascript://x/%0Aalert(document.domain)',
    'data:text/html,<svg onload=alert(1)>',
    '//evil.example/avatar.png',
    'https://user:password@example.test/avatar.png',
] as $unsafeAvatar) {
    try {
        $service->update($userId, ['avatarUrl' => $unsafeAvatar]);
        fwrite(STDERR, "Expected unsafe avatar URL rejection: {$unsafeAvatar}\n");
        exit(1);
    } catch (ApiException $e) {
        $assert($e->status === 422, 'Unsafe avatar URL returns 422.');
    }
}
$relativeAvatar = $service->update($userId, ['avatarUrl' => '/uploads/avatars/student-a.png']);
$assert($relativeAvatar['avatarUrl'] === '/uploads/avatars/student-a.png', 'Application-relative avatar path is accepted.');

// 3. Test rejection of forbidden fields
$forbiddenFields = [
    'email' => 'hacker@example.test',
    'roles' => 'admin',
    'status' => 'inactive',
    'classId' => '0191316b-9999-7000-8000-000000000001',
    'schoolId' => '0191316b-9999-7000-8000-000000000001',
    'studyStatus' => 'graduated',
    'verificationStatus' => 'verified',
    'unknownField' => 'malicious_value',
];

foreach ($forbiddenFields as $field => $val) {
    try {
        $service->update($userId, [$field => $val]);
        fwrite(STDERR, "Expected exception for forbidden field {$field}\n");
        exit(1);
    } catch (ApiException $e) {
        $assert($e->status === 422, "Forbidden field {$field} returns 422.");
    }
}

echo "student_profile_ownership_api_test: OK\n";
