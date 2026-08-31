<?php

declare(strict_types=1);

use TalentHub\Modules\School\Repository\SchoolRepository;

require_once dirname(__DIR__) . '/bin/bootstrap.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(<<<'SQL'
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
    fullName TEXT NOT NULL,
    status TEXT NOT NULL
);
CREATE TABLE student_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    classId TEXT,
    dateOfBirth TEXT,
    phone TEXT,
    studyStatus TEXT NOT NULL
);
SQL);

$pdo->exec(<<<'SQL'
INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status) VALUES
    ('class-a1', 'school-a', 'BTEC-AI-2026A', 1, '2025-2026', 'active'),
    ('class-a2', 'school-a', 'BTEC-SE-2026A', 1, '2025-2026', 'active'),
    ('class-a3', 'school-a', 'BTEC-OLD-2025A', 2, '2024-2025', 'archived'),
    ('class-b1', 'school-b', 'FOREIGN-CLASS', 1, '2025-2026', 'active');

INSERT INTO users (id, email, fullName, status) VALUES
    ('user-1', 'an@example.test', 'Nguyễn Hoàng An', 'active'),
    ('user-2', 'binh@example.test', 'Trần Gia Bình', 'suspended'),
    ('user-3', 'chi@example.test', 'Lê Minh Chi', 'disabled'),
    ('user-4', 'dung@example.test', 'Phạm Anh Dũng', 'active'),
    ('user-5', 'foreign@example.test', 'Foreign Student', 'active');

INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES
    ('student-1', 'user-1', 'class-a1', '2005-01-01', '0901000001', 'active'),
    ('student-2', 'user-2', 'class-a1', '2005-02-02', NULL, 'graduated'),
    ('student-3', 'user-3', 'class-a1', NULL, NULL, 'inactive'),
    ('student-4', 'user-4', 'class-a3', '2004-04-04', '0901000004', 'transferred'),
    ('student-5', 'user-5', 'class-b1', '2005-05-05', '0901000005', 'active');
SQL);

$repository = new SchoolRepository($pdo);
$activeClasses = $repository->listClasses('school-a');
$activeById = [];
foreach ($activeClasses as $class) {
    $activeById[(string) $class['id']] = $class;
}

$assert(count($activeClasses) === 2, 'Only active classes from the requested school are listed by default.');
$assert((int) ($activeById['class-a1']['studentCount'] ?? -1) === 3, 'Class size counts every student_profile assigned to class-a1.');
$assert((int) ($activeById['class-a2']['studentCount'] ?? -1) === 0, 'A class without student_profiles has size zero.');
$assert(!isset($activeById['class-b1']), 'A class from another school is not exposed.');

$withArchived = $repository->listClasses('school-a', true);
$archivedById = [];
foreach ($withArchived as $class) {
    $archivedById[(string) $class['id']] = $class;
}
$assert((int) ($archivedById['class-a3']['studentCount'] ?? -1) === 1, 'Archived class size is still derived from its student_profiles.');

$classStudents = $repository->listStudents('school-a', 25, 0, 'class-a1');
$assert(count($classStudents) === 3, 'Class detail returns the same number of students shown on the class card.');
$assert($repository->countStudents('school-a', 'class-a1') === 3, 'Filtered student count matches the class roster.');
$assert(
    array_column($classStudents, 'fullName') === ['Lê Minh Chi', 'Nguyễn Hoàng An', 'Trần Gia Bình'],
    'Class roster resolves and sorts real users.fullName values through userId.',
);
$assert(
    !in_array('student-1', array_column($classStudents, 'fullName'), true),
    'Student profile UUIDs are never used as display names.',
);
$assert($repository->listStudents('school-a', 25, 0, 'class-b1') === [], 'A valid class ID from another school cannot leak students.');
$assert($repository->countStudents('school-a', 'class-b1') === 0, 'Cross-school class count is zero.');

$repositorySource = (string) file_get_contents(dirname(__DIR__) . '/src/Modules/School/Repository/SchoolRepository.php');
$classesPage = (string) file_get_contents(dirname(__DIR__) . '/app/school/classes.php');
$studentsPage = (string) file_get_contents(dirname(__DIR__) . '/app/school/students.php');
$classListPartial = (string) file_get_contents(dirname(__DIR__) . '/app/school/includes/class-list.php');

$assert(str_contains($repositorySource, 'GROUP BY sp.classId'), 'Class-size query groups student_profiles by classId.');
$assert(str_contains($repositorySource, 'INNER JOIN users u ON u.id = sp.userId'), 'Student roster explicitly INNER JOINs users through student_profiles.userId.');
$assert(str_contains($repositorySource, 'u.fullName'), 'Student roster selects users.fullName as the display name.');
$assert(!str_contains($studentsPage, '$service->students($userId, 1000)'), 'Class detail no longer loads an arbitrary 1000-student school roster.');
$assert(!str_contains($studentsPage, '$allStudents = $service->students'), 'Class detail no longer loads the whole school roster before filtering.');
$assert(!str_contains($studentsPage, 'static fn(array $s) => $s[\'classId\']'), 'Class filtering is performed by SQL rather than PHP.');
$assert(str_contains($classesPage, 'class="school-grade-grid"'), 'Existing school grade grid layout is preserved.');
$assert(str_contains($classesPage, 'class="school-class-grid"'), 'Existing school class card grid layout is preserved.');
$assert(str_contains($studentsPage, 'class="school-section-box"'), 'Existing student section/card wrapper is preserved.');
$assert(str_contains($studentsPage, 'class="school-class-table"'), 'Existing student table layout is preserved.');
$assert(!str_contains($classListPartial, "'students' => 42"), 'Legacy class-list partial no longer contains static class sizes.');
$assert(!str_contains($classListPartial, '>12 lớp trong trường<'), 'Legacy class-list partial derives its class total dynamically.');

if ($failures !== []) {
    fwrite(STDERR, "school_dynamic_classes_test: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "school_dynamic_classes_test: OK\n");
