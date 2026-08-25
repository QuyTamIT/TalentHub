<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\School\Repository\SchoolRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectFailure = static function (callable $operation, string $message): void {
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
foreach ([
    'CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, status TEXT)',
    'CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT)',
    'CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT)',
    'CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT)',
    'CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT)',
    'CREATE TABLE internship_applications (id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT, appliedAt TEXT, reviewedAt TEXT)',
    'CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT)',
    'CREATE TABLE internship_mentor_assignments (id TEXT PRIMARY KEY, applicationId TEXT UNIQUE, mentorTeacherId TEXT, assignedByUserId TEXT, status TEXT, assignedAt TEXT DEFAULT CURRENT_TIMESTAMP, endedAt TEXT)',
    'CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT)',
] as $sql) {
    $pdo->exec($sql);
}

$schoolA = '10000000-0000-4000-8000-000000000001';
$schoolB = '10000000-0000-4000-8000-000000000002';
$applicationA = '20000000-0000-4000-8000-000000000001';
$applicationB = '20000000-0000-4000-8000-000000000002';
$teacherA = '30000000-0000-4000-8000-000000000001';
$teacherB = '30000000-0000-4000-8000-000000000002';
$actorA = '40000000-0000-4000-8000-000000000001';

$pdo->exec("INSERT INTO users VALUES ('student-user-a','Student A','active'),('student-user-b','Student B','active'),('teacher-user-a','Teacher A','active'),('teacher-user-b','Teacher B','active')");
$pdo->exec("INSERT INTO classes VALUES ('class-a','{$schoolA}'),('class-b','{$schoolB}')");
$pdo->exec("INSERT INTO student_profiles VALUES ('student-a','student-user-a','class-a'),('student-b','student-user-b','class-b')");
$pdo->exec("INSERT INTO enterprises VALUES ('enterprise-a','Enterprise A')");
$pdo->exec("INSERT INTO internship_posts VALUES ('post-a','enterprise-a','Intern A')");
$pdo->exec("INSERT INTO internship_applications VALUES ('{$applicationA}','post-a','student-a','accepted',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),('{$applicationB}','post-a','student-b','accepted',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$teacherA}','teacher-user-a','{$schoolA}'),('{$teacherB}','teacher-user-b','{$schoolB}')");

$repository = new SchoolRepository($pdo);
$applications = $repository->listInternshipApplications($schoolA);
$assert(count($applications) === 1 && $applications[0]['id'] === $applicationA, 'School oversight must only return its own students.');
$expectFailure(fn () => $repository->assignInternshipMentor($schoolA, $applicationB, $teacherA, $actorA), 'School A must not assign a mentor to School B application.');
$expectFailure(fn () => $repository->assignInternshipMentor($schoolA, $applicationA, $teacherB, $actorA), 'School A must not assign a School B teacher.');
$assigned = $repository->assignInternshipMentor($schoolA, $applicationA, $teacherA, $actorA);
$assert($assigned['mentorTeacherId'] === $teacherA, 'Same-school mentor assignment must persist.');

echo "school_teacher_internship_oversight_scope_test: OK\n";
