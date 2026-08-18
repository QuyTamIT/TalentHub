<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\Student\Repository\StudentRepository;

function student_repository_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function student_repository_database(bool $profileTimestamps): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, fullName TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT)');
    $pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER, academicYear TEXT)');
    $timestamps = $profileTimestamps ? ', createdAt TEXT, updatedAt TEXT' : '';
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, dateOfBirth TEXT, phone TEXT, studyStatus TEXT' . $timestamps . ')');
    $pdo->exec("INSERT INTO users VALUES ('user-1','student@example.test','Test Student','2026-01-01 00:00:00')");
    $pdo->exec("INSERT INTO schools VALUES ('school-1','Test School')");
    $pdo->exec("INSERT INTO classes VALUES ('class-1','school-1','10A',10,'2026-2027')");
    $columns = $profileTimestamps ? ',createdAt,updatedAt' : '';
    $values = $profileTimestamps ? ",'2026-02-01 00:00:00','2026-02-02 00:00:00'" : '';
    $pdo->exec("INSERT INTO student_profiles (id,userId,classId,dateOfBirth,phone,studyStatus{$columns}) VALUES ('student-1','user-1','class-1','2010-01-01','0900000000','active'{$values})");
    return $pdo;
}

$legacy = (new StudentRepository(student_repository_database(false)))->findByUserId('user-1');
student_repository_assert($legacy !== null, 'Legacy profile must be readable.');
student_repository_assert($legacy['createdAt'] === '2026-01-01 00:00:00', 'Legacy profile uses the user creation timestamp.');
student_repository_assert($legacy['updatedAt'] === '2026-01-01 00:00:00', 'Legacy profile exposes a stable update fallback.');

$modern = (new StudentRepository(student_repository_database(true)))->findByUserId('user-1');
student_repository_assert($modern !== null, 'Modern profile must be readable.');
student_repository_assert($modern['createdAt'] === '2026-02-01 00:00:00', 'Modern profile keeps its creation timestamp.');
student_repository_assert($modern['updatedAt'] === '2026-02-02 00:00:00', 'Modern profile keeps its update timestamp.');

echo "student_repository_schema_compatibility_test: OK\n";
