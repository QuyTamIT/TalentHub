<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;
use TalentHub\Modules\School\Repository\SchoolAuditRepository;
use TalentHub\Modules\School\Service\SchoolAuditService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE school_members (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, userId TEXT NOT NULL, memberRole TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, name TEXT NOT NULL);
CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT NOT NULL, fullName TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE enterprise_members (id TEXT PRIMARY KEY, enterpriseId TEXT NOT NULL, userId TEXT NOT NULL, memberRole TEXT NOT NULL);
CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT NOT NULL, title TEXT NOT NULL);
CREATE TABLE internship_applications (
    id TEXT PRIMARY KEY,
    postId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL,
    message TEXT,
    reviewerNote TEXT,
    reviewedAt TEXT,
    appliedAt TEXT NOT NULL,
    createdAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);
CREATE TABLE application_status_history (id TEXT PRIMARY KEY, applicationId TEXT NOT NULL, fromStatus TEXT, toStatus TEXT, changedByRole TEXT, note TEXT, createdAt TEXT NOT NULL);
CREATE TABLE student_profile_access_logs (
    id TEXT PRIMARY KEY,
    enterpriseId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    accessedByUserId TEXT NOT NULL,
    accessType TEXT NOT NULL,
    requestId TEXT,
    ipAddress TEXT,
    metadata TEXT,
    accessedAt TEXT NOT NULL
);
SQL);

$id = static fn (int $n): string => sprintf('%08d-0000-4000-8000-%012d', $n, $n);
$schoolA = $id(1);
$schoolB = $id(2);
$schoolUserA = $id(3);
$schoolUserB = $id(4);
$classA = $id(5);
$classB = $id(6);
$studentA = $id(7);
$studentB = $id(8);
$studentUserA = $id(9);
$studentUserB = $id(10);
$enterprise = $id(11);
$enterpriseUser = $id(12);
$post = $id(13);
$application = $id(14);
$now = '2099-08-29 12:00:00.000000';

$pdo->prepare('INSERT INTO schools VALUES (?,?,?),(?,?,?)')->execute([$schoolA, 'School A', 'active', $schoolB, 'School B', 'active']);
$pdo->prepare('INSERT INTO school_members VALUES (?,?,?,?),(?,?,?,?)')->execute([
    $id(15), $schoolA, $schoolUserA, 'admin',
    $id(16), $schoolB, $schoolUserB, 'admin',
]);
$pdo->prepare('INSERT INTO classes VALUES (?,?,?),(?,?,?)')->execute([$classA, $schoolA, 'A1', $classB, $schoolB, 'B1']);
$insertUser = $pdo->prepare('INSERT INTO users VALUES (?,?,?)');
foreach ([
    [$schoolUserA, 'school-a@example.test', 'School A Admin'],
    [$schoolUserB, 'school-b@example.test', 'School B Admin'],
    [$studentUserA, 'student-a@example.test', 'Student A'],
    [$studentUserB, 'student-b@example.test', 'Student B'],
    [$enterpriseUser, 'enterprise@example.test', 'Enterprise Reviewer'],
] as $row) { $insertUser->execute($row); }
$pdo->prepare('INSERT INTO student_profiles VALUES (?,?,?),(?,?,?)')->execute([
    $studentA, $studentUserA, $classA,
    $studentB, $studentUserB, $classB,
]);
$pdo->prepare('INSERT INTO enterprises VALUES (?,?)')->execute([$enterprise, 'Enterprise']);
$pdo->prepare('INSERT INTO enterprise_members VALUES (?,?,?,?)')->execute([$id(17), $enterprise, $enterpriseUser, 'admin']);
$pdo->prepare('INSERT INTO internship_posts VALUES (?,?,?)')->execute([$post, $enterprise, 'Backend Intern']);
$pdo->prepare('INSERT INTO internship_applications VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
    $application, $post, $studentA, 'submitted', null, null, null, $now, $now, $now,
]);

$internships = new InternshipService(new InternshipRepository($pdo));
$opened = $internships->application($enterpriseUser, $application, 'cv-request-001', '127.0.0.1');
$assert(($opened['studentId'] ?? null) === $studentA, 'enterprise opens its own application CV');
$log = $pdo->query('SELECT accessType, requestId, ipAddress, metadata FROM student_profile_access_logs')->fetch(PDO::FETCH_ASSOC);
$assert(($log['accessType'] ?? null) === 'application_cv', 'CV open is recorded with application_cv type');
$assert(($log['requestId'] ?? null) === 'cv-request-001', 'CV log retains request trace');
$assert(($log['ipAddress'] ?? null) === '127.0.0.1', 'CV log retains source IP');
$assert(str_contains((string) ($log['metadata'] ?? ''), $application), 'CV log identifies the application');

$pdo->prepare('INSERT INTO student_profile_access_logs VALUES (?,?,?,?,?,?,?,?,?)')->execute([
    $id(18), $enterprise, $studentB, $enterpriseUser, 'talent_detail', 'foreign-school-log', null, '{}', $now,
]);
$audit = new SchoolAuditService(new SchoolAuditRepository($pdo));
$schoolAOverview = $audit->profileAccessOverview($schoolUserA);
$assert(($schoolAOverview['page']['total'] ?? null) === 1, 'School A only sees access to its student');
$assert(($schoolAOverview['items'][0]['studentId'] ?? null) === $studentA, 'School A scoped item belongs to School A');
$assert(($schoolAOverview['summary']['uniqueEnterprises'] ?? null) === 1, 'School A summary aggregates scoped enterprise access');
$schoolBOverview = $audit->profileAccessOverview($schoolUserB);
$assert(($schoolBOverview['page']['total'] ?? null) === 1, 'School B only sees its own student access');
$assert(($schoolBOverview['items'][0]['studentId'] ?? null) === $studentB, 'School B cannot see School A student log');

echo "school_profile_access_audit_test: OK\n";
