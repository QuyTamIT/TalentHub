<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseApplicationCommandRepository;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\School\Repository\SchoolRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};
$expect = static function (callable $callback, int $status, string $code, string $message) use ($assert): void {
    try {
        $callback();
    } catch (ApiException $exception) {
        $assert($exception->status === $status, "{$message}: HTTP status");
        $assert($exception->errorCode === $code, "{$message}: error code");
        return;
    }
    $assert(false, "{$message}: expected ApiException");
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT NOT NULL, fullName TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL, deadline TEXT NOT NULL);
CREATE TABLE internship_applications (
    id TEXT PRIMARY KEY,
    postId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL,
    message TEXT,
    appliedAt TEXT NOT NULL,
    createdAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL);
CREATE TABLE internship_mentor_assignments (
    id TEXT PRIMARY KEY,
    applicationId TEXT NOT NULL UNIQUE,
    mentorTeacherId TEXT NOT NULL,
    assignedByUserId TEXT NOT NULL,
    assignedAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);
CREATE TABLE internship_application_locks (
    applicationId TEXT PRIMARY KEY,
    lockedByApplicationId TEXT NOT NULL,
    reason TEXT NOT NULL,
    lockedAt TEXT NOT NULL
);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT);
CREATE TABLE application_status_history (id TEXT PRIMARY KEY, applicationId TEXT NOT NULL, fromStatus TEXT, toStatus TEXT NOT NULL, changedByUserId TEXT, changedByRole TEXT NOT NULL, note TEXT, createdAt TEXT NOT NULL);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NOT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT, readAt TEXT, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL, emailEnabled INTEGER NOT NULL, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType));
SQL);

$id = static fn (int $n): string => sprintf('%08d-0000-4000-8000-%012d', $n, $n);
$schoolA = $id(1);
$schoolB = $id(2);
$classA = $id(3);
$classB = $id(4);
$studentA = $id(5);
$studentB = $id(6);
$studentUserA = $id(7);
$studentUserB = $id(8);
$enterprise = $id(9);
$post = $id(10);
$applicationA = $id(11);
$applicationB = $id(12);
$schoolAdmin = $id(13);
$teacherUserA = $id(14);
$teacherUserB = $id(15);
$teacherA = $id(16);
$teacherB = $id(17);
$competingPost = $id(18);
$acceptedElsewhere = $id(19);
$studentC = $id(20);
$studentUserC = $id(21);
$applicationC = $id(22);
$competingApplicationC = $id(23);

$insertUser = $pdo->prepare('INSERT INTO users VALUES (?,?,?,?)');
foreach ([
    [$studentUserA, 'student-a@example.test', 'Student A', 'active'],
    [$studentUserB, 'student-b@example.test', 'Student B', 'active'],
    [$schoolAdmin, 'school@example.test', 'School Admin', 'active'],
    [$teacherUserA, 'teacher-a@example.test', 'Teacher A', 'active'],
    [$teacherUserB, 'teacher-b@example.test', 'Teacher B', 'active'],
    [$studentUserC, 'student-c@example.test', 'Student C', 'active'],
] as $row) { $insertUser->execute($row); }
$pdo->prepare('INSERT INTO classes VALUES (?,?),(?,?)')->execute([$classA, $schoolA, $classB, $schoolB]);
$pdo->prepare('INSERT INTO student_profiles VALUES (?,?,?),(?,?,?)')->execute([
    $studentA, $studentUserA, $classA,
    $studentB, $studentUserB, $classB,
]);
$pdo->prepare('INSERT INTO student_profiles VALUES (?,?,?)')->execute([$studentC, $studentUserC, $classA]);
$pdo->prepare('INSERT INTO enterprises VALUES (?,?)')->execute([$enterprise, 'Enterprise']);
$pdo->prepare('INSERT INTO internship_posts VALUES (?,?,?,?,?),(?,?,?,?,?)')->execute([
    $post, $enterprise, 'Backend Intern', 'active', '2099-12-31 23:59:59',
    $competingPost, $enterprise, 'Data Intern', 'active', '2099-12-31 23:59:59',
]);
$now = '2026-08-29 00:00:00.000000';
$insertApplication = $pdo->prepare('INSERT INTO internship_applications VALUES (?,?,?,?,?,?,?,?)');
$insertApplication->execute([$applicationA, $post, $studentA, 'interview', null, $now, $now, $now]);
$insertApplication->execute([$applicationB, $post, $studentB, 'accepted', null, $now, $now, $now]);
$insertApplication->execute([$acceptedElsewhere, $competingPost, $studentA, 'accepted', null, $now, $now, $now]);
$insertApplication->execute([$applicationC, $post, $studentC, 'reviewing', null, $now, $now, $now]);
$insertApplication->execute([$competingApplicationC, $competingPost, $studentC, 'interview', null, $now, $now, $now]);
$pdo->prepare('INSERT INTO teacher_profiles VALUES (?,?,?),(?,?,?)')->execute([
    $teacherA, $teacherUserA, $schoolA,
    $teacherB, $teacherUserB, $schoolB,
]);

$schoolRepository = new SchoolRepository($pdo);
$expect(
    fn () => $schoolRepository->assignInternshipMentor($schoolA, $applicationB, $teacherA, $schoolAdmin),
    404,
    'APPLICATION_NOT_FOUND',
    'school cannot access another school application'
);
$expect(
    fn () => $schoolRepository->assignInternshipMentor($schoolA, $applicationA, $teacherA, $schoolAdmin),
    422,
    'PLACEMENT_NOT_CONFIRMED',
    'mentor cannot be assigned before acceptance'
);
$pdo->prepare("UPDATE internship_applications SET status = 'accepted' WHERE id = ?")->execute([$applicationA]);
$expect(
    fn () => $schoolRepository->assignInternshipMentor($schoolA, $applicationA, $teacherB, $schoolAdmin),
    422,
    'MENTOR_NOT_FOUND',
    'school cannot assign a teacher from another school'
);
$assigned = $schoolRepository->assignInternshipMentor($schoolA, $applicationA, $teacherA, $schoolAdmin);
$assert(($assigned['mentorTeacherId'] ?? null) === $teacherA, 'accepted placement receives same-school mentor');
$assert(($assigned['mentorUserId'] ?? null) === $teacherUserA, 'assignment exposes mentor notification recipient');
$assert(($assigned['studentUserId'] ?? null) === $studentUserA, 'assignment exposes student notification recipient');

$studentCommands = new DatabaseApplicationCommandRepository($pdo);
$before = (int) $pdo->query('SELECT COUNT(*) FROM internship_applications')->fetchColumn();
$expect(
    fn () => $studentCommands->submit($studentA, $studentUserA, 'placement-lock-test', $competingPost, ''),
    409,
    'INTERNSHIP_PLACEMENT_LOCKED',
    'accepted student cannot submit a competing application'
);
$assert((int) $pdo->query('SELECT COUNT(*) FROM internship_applications')->fetchColumn() === $before, 'placement lock writes no new application');

$enterpriseRepository = new InternshipRepository($pdo);
$accepted = $enterpriseRepository->review($enterprise, $schoolAdmin, $applicationC, 'reviewing', 'accepted', 'Accepted by enterprise');
$assert(($accepted['status'] ?? null) === 'accepted', 'enterprise acceptance confirms the placement');
$lockStatement = $pdo->prepare('SELECT lockedByApplicationId FROM internship_application_locks WHERE applicationId = ?');
$lockStatement->execute([$competingApplicationC]);
$assert($lockStatement->fetchColumn() === $applicationC, 'acceptance locks every competing active application');
$expect(
    fn () => $enterpriseRepository->review($enterprise, $schoolAdmin, $competingApplicationC, 'interview', 'accepted', ''),
    409,
    'INTERNSHIP_PLACEMENT_LOCKED',
    'competing enterprise cannot accept a locked application'
);

echo "school_internship_flow_test: OK\n";
