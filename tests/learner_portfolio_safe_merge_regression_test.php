<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Modules\Student\Repository\PortfolioRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE roles(id TEXT PRIMARY KEY,code TEXT);
CREATE TABLE users(id TEXT PRIMARY KEY,roleId TEXT,status TEXT,fullName TEXT);
CREATE TABLE schools(id TEXT PRIMARY KEY,name TEXT);
CREATE TABLE classes(id TEXT PRIMARY KEY,schoolId TEXT);
CREATE TABLE student_profiles(id TEXT PRIMARY KEY,userId TEXT,classId TEXT,studyStatus TEXT);
CREATE TABLE teacher_profiles(id TEXT PRIMARY KEY,userId TEXT,schoolId TEXT);
CREATE TABLE projects(id TEXT PRIMARY KEY,schoolId TEXT,mentorTeacherId TEXT,title TEXT,status TEXT);
CREATE TABLE project_members(id TEXT PRIMARY KEY,projectId TEXT,studentId TEXT,status TEXT);
CREATE TABLE project_skill_tags(id TEXT PRIMARY KEY,projectId TEXT,skillId TEXT,verifiedAt TEXT,createdAt TEXT);
CREATE TABLE skills(id TEXT PRIMARY KEY,code TEXT,name TEXT,category TEXT,status TEXT);
CREATE TABLE project_submissions(id TEXT PRIMARY KEY,studentId TEXT,projectId TEXT,version INTEGER,revision INTEGER,status TEXT,notes TEXT,repositoryUrl TEXT,demoUrl TEXT,startDate TEXT,endDate TEXT,hours REAL,stage TEXT,submittedAt TEXT,reviewedAt TEXT,reviewedByUserId TEXT,feedback TEXT,updatedAt TEXT);
CREATE TABLE learner_internship_reports(id TEXT PRIMARY KEY,studentId TEXT,applicationId TEXT,version INTEGER,revision INTEGER,status TEXT,notes TEXT,repositoryUrl TEXT,demoUrl TEXT,startDate TEXT,endDate TEXT,hours REAL,stage TEXT,submittedAt TEXT,reviewedAt TEXT,reviewedByUserId TEXT,feedback TEXT,updatedAt TEXT);
CREATE TABLE enterprises(id TEXT PRIMARY KEY,name TEXT);
CREATE TABLE internship_posts(id TEXT PRIMARY KEY,enterpriseId TEXT,title TEXT,status TEXT);
CREATE TABLE internship_applications(id TEXT PRIMARY KEY,postId TEXT,studentId TEXT,status TEXT);
CREATE TABLE internship_mentor_assignments(id TEXT PRIMARY KEY,applicationId TEXT,mentorTeacherId TEXT);
CREATE TABLE learner_portfolio_skills(kind TEXT,reportId TEXT,skillId TEXT);
CREATE TABLE learner_portfolio_history(id TEXT PRIMARY KEY,kind TEXT,reportId TEXT,version INTEGER,status TEXT,actorUserId TEXT,snapshotJson TEXT,createdAt TEXT);
SQL);
$pdo->exec("INSERT INTO roles VALUES ('student-role','student'),('teacher-role','teacher');");
$pdo->exec("INSERT INTO users VALUES ('student-user','student-role','active','Student'),('teacher-user','teacher-role','active','Teacher');");
$pdo->exec("INSERT INTO schools VALUES ('school-1','School'); INSERT INTO classes VALUES ('class-1','school-1'); INSERT INTO student_profiles VALUES ('student-1','student-user','class-1','active'); INSERT INTO teacher_profiles VALUES ('teacher-1','teacher-user','school-1');");
$pdo->exec("INSERT INTO projects VALUES ('project-1','school-1','teacher-1','Project','completed'); INSERT INTO project_members VALUES ('member-1','project-1','student-1','active'); INSERT INTO skills VALUES ('skill-1','php','PHP','technical','active'); INSERT INTO project_skill_tags VALUES ('tag-1','project-1','skill-1','2026-01-01','2026-01-01');");
$pdo->exec("INSERT INTO project_submissions VALUES ('report-1','student-1','project-1',1,1,'verified','evidence',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-01-01','teacher-user','feedback','2026-01-01');");
$pdo->exec("INSERT INTO learner_portfolio_skills VALUES ('project','report-1','skill-1');");

$portfolio = new PortfolioRepository($pdo, static function (): void {});
$verified = $portfolio->verifiedForStudent('student-1');
$assert(count($verified['skills']) === 1, 'Legacy portfolio skill remains readable before migration 022');
$assert(array_key_exists('score', $verified['skills'][0]) && $verified['skills'][0]['score'] === null, 'Legacy portfolio skill has an explicit null score fallback');
$assert(($verified['skills'][0]['evidenceStatus'] ?? null) === 'verified', 'Legacy portfolio skill keeps verified evidence fallback');
$assert(($verified['skills'][0]['sourceType'] ?? null) === 'project_submission', 'Legacy portfolio skill gets a source-aware fallback');

$pdo->exec("UPDATE project_submissions SET status='submitted', reviewedAt=NULL, reviewedByUserId=NULL WHERE id='report-1';");
try {
    $portfolio->review('teacher-user', 'project', 'report-1', 1, 'verified', '', ['skill-1'], ['skill-1' => 80]);
    throw new RuntimeException('Expected scored review to fail when migration 022 metadata is unavailable');
} catch (ApiException $exception) {
    $assert($exception->status === 503, 'Scored legacy review reports schema unavailability');
    $assert($exception->errorCode === 'SCHEMA_UNAVAILABLE', 'Scored legacy review has a clear schema error code');
}

$pdo->exec("UPDATE project_submissions SET status='verified', reviewedAt='2026-01-01', reviewedByUserId='teacher-user' WHERE id='report-1';");
$passport = new DatabaseTalentPassportRepository($pdo);
$method = new ReflectionMethod($passport, 'portfolioVerifiedSkills');
$method->setAccessible(true);
$skills = $method->invoke($passport, 'student-1');
$assert(count($skills) === 1, 'Passport reads legacy portfolio evidence');
$assert(($skills[0]['level_score'] ?? null) === null, 'Passport uses null score fallback before migration 022');
$assert(($skills[0]['verification_status'] ?? null) === 'verified', 'Passport preserves verified evidence status before migration 022');
$assert(($skills[0]['source_type'] ?? null) === 'project_submission', 'Passport supplies a source-aware legacy fallback');

echo "learner_portfolio_safe_merge_regression_test: OK\n";
