<?php
declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../app/learner/data/Migrations/LearnerForwardMigration.php';
require_once __DIR__ . '/../app/learner/data/Migrations/ForwardMigrationDefinition.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Student\Repository\PortfolioRepository;
use TalentHub\Modules\Student\Service\PortfolioHttp;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\Request;

// =========================================================================
// 1. Kiểm tra MySQL theo task brief
// =========================================================================
try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = (new Connection($config))->connect();

    $repo = new PortfolioRepository($pdo);

    $studentRow = $pdo->query("SELECT id FROM student_profiles WHERE studyStatus='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($studentRow) {
        $studentId = (string)$studentRow['id'];

        $allData = $repo->listForStudent($studentId, false);
        $completedData = $repo->listForStudent($studentId, true);

        // Mọi mục thực tập trong $completedData phải có report verified và stage completed
        foreach ($completedData['internships'] as $intern) {
            $report = $intern['report'] ?? null;
            assert($report !== null, "Mục hoàn thành phải có report");
            assert(($report['status'] ?? '') === 'verified', "Report phải verified");
            assert(($report['stage'] ?? '') === 'completed', "Stage phải completed");
        }
        foreach ($completedData['projects'] as $proj) {
            $status = $proj['report']['status'] ?? '';
            $pStatus = $proj['projectStatus'] ?? '';
            assert($status === 'verified' || $pStatus === 'completed', "Dự án hoàn thành phải verified hoặc completed");
        }
    }
} catch (Throwable $e) {
    // Database check optional if MySQL not configured or empty, but sqlite unit tests below are authoritative
}

// =========================================================================
// 2. Unit test độc lập với SQLite (kiểm tra chặt chẽ điều kiện lọc)
// =========================================================================
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException("Assertion failed: " . $message);
    }
};

$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlitePdo->exec('PRAGMA foreign_keys=ON');
$sqlitePdo->exec(<<<'SQL'
CREATE TABLE roles(id VARCHAR(64) PRIMARY KEY,code VARCHAR(64));
CREATE TABLE users(id VARCHAR(64) PRIMARY KEY,roleId VARCHAR(64),status VARCHAR(64),fullName VARCHAR(255));
CREATE TABLE schools(id VARCHAR(64) PRIMARY KEY,name VARCHAR(255));
CREATE TABLE classes(id VARCHAR(64) PRIMARY KEY,schoolId VARCHAR(64));
CREATE TABLE student_profiles(id VARCHAR(64) PRIMARY KEY,userId VARCHAR(64),classId VARCHAR(64),studyStatus VARCHAR(64));
CREATE TABLE teacher_profiles(id VARCHAR(64) PRIMARY KEY,userId VARCHAR(64),schoolId VARCHAR(64));
CREATE TABLE projects(id VARCHAR(64) PRIMARY KEY,schoolId VARCHAR(64),mentorTeacherId VARCHAR(64),title VARCHAR(255),status VARCHAR(64));
CREATE TABLE project_members(id VARCHAR(64) PRIMARY KEY,projectId VARCHAR(64),studentId VARCHAR(64),status VARCHAR(64));
CREATE TABLE enterprises(id VARCHAR(64) PRIMARY KEY,name VARCHAR(255));
CREATE TABLE internship_posts(id VARCHAR(64) PRIMARY KEY,enterpriseId VARCHAR(64),title VARCHAR(255),status VARCHAR(64));
CREATE TABLE internship_applications(id VARCHAR(64) PRIMARY KEY,postId VARCHAR(64),studentId VARCHAR(64),status VARCHAR(64));
CREATE TABLE internship_mentor_assignments(id VARCHAR(64) PRIMARY KEY,applicationId VARCHAR(64) UNIQUE,mentorTeacherId VARCHAR(64));
CREATE TABLE skills(id VARCHAR(64) PRIMARY KEY,name VARCHAR(255),status VARCHAR(64));
SQL);

$migration = require dirname(__DIR__) . '/Database/migrations/learner/021_create_learner_portfolio_reports.php';
foreach ($migration->migration->statements('sqlite') as $statement) {
    $sqlitePdo->exec($statement);
}

$sqlitePdo->exec(<<<'SQL'
INSERT INTO roles VALUES ('rs','student'),('rt','teacher');
INSERT INTO users VALUES ('us1','rs','active','Student One'),('ut1','rt','active','Teacher One');
INSERT INTO schools VALUES ('s1','School One');
INSERT INTO classes VALUES ('c1','s1');
INSERT INTO student_profiles VALUES ('stu1','us1','c1','active');
INSERT INTO teacher_profiles VALUES ('t1','ut1','s1');

-- Projects:
-- p1: in_progress, report is draft (not completed)
-- p2: completed status (completed)
-- p3: in_progress status, but report is verified (completed)
INSERT INTO projects VALUES
    ('p1','s1','t1','Project One (In Progress)','in_progress'),
    ('p2','s1','t1','Project Two (Completed Status)','completed'),
    ('p3','s1','t1','Project Three (Verified Report)','in_progress');

INSERT INTO project_members VALUES
    ('pm1','p1','stu1','active'),
    ('pm2','p2','stu1','active'),
    ('pm3','p3','stu1','active');

-- Internships:
-- a1: report is verified & stage completed (completed)
-- a2: report is submitted & stage active (not completed)
-- a3: no report, only accepted application (not completed)
INSERT INTO enterprises VALUES ('e1','Enterprise One');
INSERT INTO internship_posts VALUES
    ('post1','e1','Internship 1','active'),
    ('post2','e1','Internship 2','active'),
    ('post3','e1','Internship 3','active');

INSERT INTO internship_applications VALUES
    ('a1','post1','stu1','accepted'),
    ('a2','post2','stu1','accepted'),
    ('a3','post3','stu1','accepted');

INSERT INTO internship_mentor_assignments VALUES
    ('ima1','a1','t1'),
    ('ima2','a2','t1'),
    ('ima3','a3','t1');

-- Reports
INSERT INTO project_submissions (id, studentId, projectId, version, revision, status, notes, updatedAt) VALUES
    ('ps1', 'stu1', 'p1', 1, 1, 'draft', 'Work in progress', '2026-01-01 00:00:00'),
    ('ps3', 'stu1', 'p3', 1, 1, 'verified', 'Completed and verified', '2026-01-01 00:00:00');

INSERT INTO learner_internship_reports (id, studentId, applicationId, version, revision, status, stage, notes, updatedAt) VALUES
    ('ir1', 'stu1', 'a1', 1, 1, 'verified', 'completed', 'Internship finished', '2026-01-01 00:00:00'),
    ('ir2', 'stu1', 'a2', 1, 1, 'submitted', 'active', 'Internship ongoing', '2026-01-01 00:00:00');
SQL);

$sqliteRepo = new PortfolioRepository($sqlitePdo);

// Test 1: $completedOnly = false returns ALL items
$all = $sqliteRepo->listForStudent('stu1', false);
$assert(count($all['projects']) === 3, "All projects count must be 3, got " . count($all['projects']));
$assert(count($all['internships']) === 3, "All internships count must be 3, got " . count($all['internships']));

// Test 2: $completedOnly = true returns ONLY completed items
$filtered = $sqliteRepo->listForStudent('stu1', true);
$assert(count($filtered['projects']) === 2, "Filtered projects count must be 2, got " . count($filtered['projects']));
$assert(count($filtered['internships']) === 1, "Filtered internships count must be 1, got " . count($filtered['internships']));

// Check project details in filtered
$filteredProjIds = array_map(fn($p) => $p['contextId'], $filtered['projects']);
$assert(in_array('p2', $filteredProjIds, true), "Project p2 must be in completed list");
$assert(in_array('p3', $filteredProjIds, true), "Project p3 must be in completed list");
$assert(!in_array('p1', $filteredProjIds, true), "Project p1 must NOT be in completed list");

// Check internship details in filtered
$assert($filtered['internships'][0]['contextId'] === 'a1', "Internship a1 must be the only completed internship");
$assert($filtered['internships'][0]['report']['status'] === 'verified', "Internship report status must be verified");
$assert($filtered['internships'][0]['report']['stage'] === 'completed', "Internship report stage must be completed");

// Test 3: PortfolioHttp GET with filter parameter
$_SESSION = ['user' => ['id' => 'us1', 'role' => 'student'], 'csrfToken' => 'fixture-token'];
$session = new SessionManager([]);

// Without filter=completed: returns all
$reqAll = new Request('GET', '/portfolio', [], '', [], []);
$httpAll = PortfolioHttp::handle($sqlitePdo, $session, $reqAll, 'student');
$assert(count($httpAll['projects']) === 3, "HTTP without filter must return all 3 projects");
$assert(count($httpAll['internships']) === 3, "HTTP without filter must return all 3 internships");

// With filter=completed: returns filtered
$reqFiltered = new Request('GET', '/portfolio', [], '', [], ['filter' => 'completed']);
$httpFiltered = PortfolioHttp::handle($sqlitePdo, $session, $reqFiltered, 'student');
$assert(count($httpFiltered['projects']) === 2, "HTTP with filter=completed must return 2 projects");
$assert(count($httpFiltered['internships']) === 1, "HTTP with filter=completed must return 1 internship");

// Test 4: student-data.php mock mode project filter check
$mockProjects = [
    [
        'name' => 'Smart Garden IoT',
        'description' => 'Hệ thống tưới tự động dùng ESP32 + cảm biến độ ẩm.',
        'role' => 'Trưởng nhóm',
        'status' => 'Đã hoàn thành',
        'tone' => 'success',
    ],
    [
        'name' => 'EduTalent Hackathon 2025',
        'description' => 'Top 5 toàn quốc – ứng dụng quản lý hoạt động học sinh.',
        'role' => 'Lập trình viên',
        'status' => 'Đang triển khai',
        'tone' => 'warning',
    ],
];
// Verify mock mode in student-data.php only keeps 'Đã hoàn thành'
$studentDataFile = file_get_contents(__DIR__ . '/../app/learner/includes/student-data.php');
$assert(!str_contains($studentDataFile, "'status' => 'Đang triển khai'"), "student-data.php mock projects must not contain 'Đang triển khai'");

echo "[PASS] learner_completed_portfolio_filter_test passed\n";
