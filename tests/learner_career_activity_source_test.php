<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function activity_source_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function activity_source_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, name TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL, studyStatus TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');

    // Schools & Classes
    $pdo->exec("INSERT INTO schools (id, name, status) VALUES ('school-1', 'Trường THPT A', 'active'), ('school-2', 'Trường THPT B', 'active')");
    $pdo->exec("INSERT INTO classes (id, schoolId, name, status) VALUES ('class-1', 'school-1', '10A', 'active'), ('class-2', 'school-2', '10B', 'active')");
    $pdo->exec("INSERT INTO student_profiles (id, classId, studyStatus) VALUES ('student-1', 'class-1', 'active'), ('student-2', 'class-2', 'active')");
    $pdo->exec("INSERT INTO teacher_profiles (id, schoolId) VALUES ('teacher-1', 'school-1'), ('teacher-2', 'school-2')");

    // Activities in School 1:
    // 1. Valid published technical club (future date, open capacity)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-tech-open', 'school-1', 'teacher-1', 'CLB Sáng tạo Robot & IoT', 'career_technical', '2026-09-01 08:00:00', '2026-12-31 17:00:00', 30, 'published')");
    // 2. Valid ongoing business workshop (future end date)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-biz-open', 'school-1', 'teacher-1', 'Workshop Khởi nghiệp', 'career_business', '2026-08-01 08:00:00', '2026-10-31 17:00:00', 20, 'ongoing')");
    // 3. Draft activity (should be excluded)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-draft', 'school-1', 'teacher-1', 'Dự thảo CLB', 'career_arts', '2026-09-01 08:00:00', '2026-12-31 17:00:00', 20, 'draft')");
    // 4. Completed/archived activity (should be excluded)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-completed', 'school-1', 'teacher-1', 'CLB Đã kết thúc', 'career_arts', '2026-01-01 08:00:00', '2026-06-01 17:00:00', 20, 'completed')");
    // 5. Past activity (endAt in past, should be excluded)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-past', 'school-1', 'teacher-1', 'CLB Quá khứ', 'career_arts', '2025-01-01 08:00:00', '2025-06-01 17:00:00', 20, 'published')");
    // 6. Full activity (capacity=1, registered=1 -> should be excluded)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-full', 'school-1', 'teacher-1', 'CLB Đã đầy', 'career_sports_academic', '2026-09-01 08:00:00', '2026-12-31 17:00:00', 1, 'published')");
    $pdo->exec("INSERT INTO activity_registrations (id, activityId, studentId, status) VALUES ('reg-full-other', 'act-full', 'other-student', 'approved')");
    // 7. Activity student-1 already registered for (should be excluded for student-1, visible for others)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-registered', 'school-1', 'teacher-1', 'CLB Đã đăng ký', 'career_arts', '2026-09-01 08:00:00', '2026-12-31 17:00:00', 20, 'published')");
    $pdo->exec("INSERT INTO activity_registrations (id, activityId, studentId, status) VALUES ('reg-student-1', 'act-registered', 'student-1', 'pending')");
    // 8. Activity in School 2 (should be excluded for student-1, visible for student-2)
    $pdo->exec("INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES ('act-school-2', 'school-2', 'teacher-2', 'CLB Trường B', 'career_arts', '2026-09-01 08:00:00', '2026-12-31 17:00:00', 20, 'published')");

    return $pdo;
}

$pdo = activity_source_fixture();
$source = new DatabaseOpportunitySource($pdo);

// Query for student-1 (School 1)
$activitiesStudent1 = $source->forStudent('student-1');
$ids1 = array_map(static fn (array $a): string => $a['opportunity_id'], $activitiesStudent1);

activity_source_assert(in_array('act-tech-open', $ids1, true), 'open published technical activity is included');
activity_source_assert(in_array('act-biz-open', $ids1, true), 'open ongoing business activity is included');
activity_source_assert(!in_array('act-draft', $ids1, true), 'draft activity is excluded');
activity_source_assert(!in_array('act-completed', $ids1, true), 'completed activity is excluded');
activity_source_assert(!in_array('act-past', $ids1, true), 'past activity is excluded');
activity_source_assert(!in_array('act-full', $ids1, true), 'full capacity activity is excluded');
activity_source_assert(!in_array('act-registered', $ids1, true), 'already registered activity is excluded for student-1');
activity_source_assert(!in_array('act-school-2', $ids1, true), 'school 2 activity is excluded for student-1');

// Query for student-2 (School 2)
$activitiesStudent2 = $source->forStudent('student-2');
$ids2 = array_map(static fn (array $a): string => $a['opportunity_id'], $activitiesStudent2);
activity_source_assert(in_array('act-school-2', $ids2, true), 'school 2 activity is included for student-2');
activity_source_assert(!in_array('act-tech-open', $ids2, true), 'school 1 activity is excluded for student-2');

echo "learner_career_activity_source_test: OK\n";
