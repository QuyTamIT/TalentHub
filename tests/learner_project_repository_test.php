<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\DatabaseProjectRepository;
use TalentHub\Learner\Data\ReadModel\ProjectReadModel;

$root = dirname(__DIR__);
require_once $root . '/app/learner/data/bootstrap.php';

function learner_project_repository_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL, studyStatus TEXT NOT NULL)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL)');
$pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE projects (
    id TEXT PRIMARY KEY, schoolId TEXT, mentorTeacherId TEXT, title TEXT NOT NULL,
    category TEXT NOT NULL, description TEXT, fundingGoal NUMERIC, projectUrl TEXT,
    startAt TEXT, endAt TEXT, status TEXT NOT NULL, createdAt TEXT, updatedAt TEXT
)');
$pdo->exec('CREATE TABLE project_members (id TEXT PRIMARY KEY, projectId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE project_sponsorships (
    id TEXT PRIMARY KEY, enterpriseId TEXT NOT NULL, projectId TEXT NOT NULL,
    amount NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL,
    note TEXT, createdAt TEXT
)');

$studentId = '11111111-1111-4111-8111-111111111111';
$sameSchoolId = '22222222-2222-4222-8222-222222222222';
$otherSchoolId = '33333333-3333-4333-8333-333333333333';
$sameSchoolActiveId = '44444444-4444-4444-8444-444444444444';
$sameSchoolDraftId = '55555555-5555-4555-8555-555555555555';
$sameSchoolCompletedId = '66666666-6666-4666-8666-666666666666';
$crossSchoolProjectId = '77777777-7777-4777-8777-777777777777';
$mentorId = '88888888-8888-4888-8888-888888888888';
$mentorUserId = '99999999-9999-4999-8999-999999999999';
$enterpriseId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

$pdo->exec("INSERT INTO schools VALUES ('{$sameSchoolId}', 'FPT Polytechnic', 'active'), ('{$otherSchoolId}', 'Trường khác', 'active')");
$pdo->exec("INSERT INTO classes VALUES ('class-1', '{$sameSchoolId}')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$studentId}', 'class-1', 'active')");
$pdo->exec("INSERT INTO users VALUES ('{$mentorUserId}', 'Nguyễn Minh Anh')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$mentorId}', '{$mentorUserId}', '{$sameSchoolId}')");
$pdo->exec("INSERT INTO enterprises VALUES ('{$enterpriseId}', 'FPT Software', 'active')");

$insertProject = $pdo->prepare('INSERT INTO projects VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insertProject->execute([$sameSchoolActiveId, $sameSchoolId, $mentorId, 'EcoSmart AI', 'career_technical', 'Phân loại rác bằng AI.', 25000000, 'https://github.com/talenthub-demo/ecosmart-ai', '2026-09-01', '2026-12-30', 'in_progress', '2026-08-01', '2026-08-30']);
$insertProject->execute([$sameSchoolDraftId, $sameSchoolId, $mentorId, 'Bản nháp', 'career_technical', 'Không được hiển thị.', null, null, null, null, 'draft', '2026-08-01', '2026-08-29']);
$insertProject->execute([$sameSchoolCompletedId, $sameSchoolId, $mentorId, 'Đã hoàn thành', 'career_arts', 'Không được hiển thị.', null, null, null, null, 'completed', '2026-08-01', '2026-08-28']);
$insertProject->execute([$crossSchoolProjectId, $otherSchoolId, null, 'Dự án trường khác', 'career_business', 'Không được hiển thị.', null, null, null, null, 'in_progress', '2026-08-01', '2026-08-27']);

$pdo->exec("INSERT INTO project_members VALUES
    ('member-1', '{$sameSchoolActiveId}', '{$studentId}', 'active'),
    ('member-2', '{$sameSchoolActiveId}', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'active'),
    ('member-3', '{$sameSchoolActiveId}', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'left')");
$pdo->exec("INSERT INTO project_sponsorships VALUES
    ('sponsor-1', '{$enterpriseId}', '{$sameSchoolActiveId}', 25000000, 'VND', 'paid', 'Tài trợ thiết bị và cố vấn kỹ thuật.', '2026-08-20'),
    ('sponsor-2', '{$enterpriseId}', '{$sameSchoolActiveId}', 5000000, 'VND', 'pledged', 'Chưa thanh toán.', '2026-08-21')");

$repository = new DatabaseProjectRepository($pdo);
$projects = ProjectReadModel::projects($repository->listVisibleForStudent($studentId));

learner_project_repository_assert(array_column($projects, 'id') === [$sameSchoolActiveId], 'only same-school in-progress project is listed');
learner_project_repository_assert(($projects[0]['members_count'] ?? null) === 2, 'active members are counted once');
learner_project_repository_assert(($projects[0]['category_label'] ?? '') === 'Kỹ thuật', 'category is presented in Vietnamese');
learner_project_repository_assert(!array_key_exists('project_url', $projects[0]), 'repository URL is not exposed by the learner read model');

$detail = ProjectReadModel::project($repository->findVisibleForStudent($studentId, $sameSchoolActiveId) ?? []);
learner_project_repository_assert(($detail['school_name'] ?? '') === 'FPT Polytechnic', 'school name is exposed');
learner_project_repository_assert(($detail['mentor_name'] ?? '') === 'Nguyễn Minh Anh', 'mentor user name is exposed');
learner_project_repository_assert(($detail['status_label'] ?? '') === 'Đang triển khai', 'project status is presented in Vietnamese');
learner_project_repository_assert(count($detail['sponsorships'] ?? []) === 1, 'only paid sponsorship is public');
learner_project_repository_assert(($detail['sponsorships'][0]['enterprise_name'] ?? '') === 'FPT Software', 'active sponsor is resolved');
learner_project_repository_assert(($detail['sponsorships'][0]['note'] ?? '') === 'Tài trợ thiết bị và cố vấn kỹ thuật.', 'sponsorship note is exposed');
learner_project_repository_assert(($detail['raised_amount'] ?? null) === 25000000.0, 'paid sponsorship amount is aggregated');
learner_project_repository_assert($repository->findVisibleForStudent($studentId, $crossSchoolProjectId) === null, 'cross-school detail is hidden');

$pdo->exec('DROP TABLE project_sponsorships');
$withoutSponsors = ProjectReadModel::project($repository->findVisibleForStudent($studentId, $sameSchoolActiveId) ?? []);
learner_project_repository_assert(($withoutSponsors['sponsorships'] ?? null) === [], 'sponsor failure does not hide the project');

echo "learner_project_repository_test: OK\n";
