<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;

function portfolio_feedback_check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE roles(id TEXT PRIMARY KEY, code TEXT);
CREATE TABLE users(id TEXT PRIMARY KEY, roleId TEXT, status TEXT, fullName TEXT, email TEXT);
CREATE TABLE schools(id TEXT PRIMARY KEY, name TEXT, status TEXT);
CREATE TABLE classes(id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT);
CREATE TABLE student_profiles(id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT);
CREATE TABLE teacher_profiles(id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT);
CREATE TABLE projects(id TEXT PRIMARY KEY, schoolId TEXT, mentorTeacherId TEXT, title TEXT, status TEXT);
CREATE TABLE project_members(id TEXT PRIMARY KEY, projectId TEXT, studentId TEXT, status TEXT);
CREATE TABLE skills(id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT);
CREATE TABLE internship_posts(id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, status TEXT);
CREATE TABLE internship_applications(id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT);
CREATE TABLE project_submissions(id TEXT PRIMARY KEY, studentId TEXT, projectId TEXT, version INT, revision INT, status TEXT, notes TEXT, repositoryUrl TEXT, demoUrl TEXT, startDate TEXT, endDate TEXT, hours REAL, stage TEXT, submittedAt TEXT, reviewedAt TEXT, reviewedByUserId TEXT, feedback TEXT, updatedAt TEXT);
CREATE TABLE learner_internship_reports(id TEXT PRIMARY KEY, studentId TEXT, applicationId TEXT, version INT, revision INT, status TEXT, notes TEXT, repositoryUrl TEXT, demoUrl TEXT, startDate TEXT, endDate TEXT, hours REAL, stage TEXT, submittedAt TEXT, reviewedAt TEXT, reviewedByUserId TEXT, feedback TEXT, updatedAt TEXT);
CREATE TABLE learner_portfolio_skills(kind TEXT, reportId TEXT, skillId TEXT, PRIMARY KEY(kind, reportId, skillId));
CREATE TABLE student_skills(id TEXT, studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT);
CREATE TABLE assessments(id TEXT, teacherId TEXT, studentId TEXT, classId TEXT, projectId TEXT, activityId TEXT, overallScore REAL, comment TEXT, status TEXT, publishedAt TEXT, version INT);
CREATE TABLE talent_tests(id TEXT, code TEXT, name TEXT, type TEXT);
CREATE TABLE test_attempts(id TEXT, testId TEXT, studentId TEXT, status TEXT, startedAt TEXT, submittedAt TEXT);
CREATE TABLE test_results(id TEXT, attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT);
CREATE TABLE activities(id TEXT, title TEXT, category TEXT, status TEXT, startAt TEXT);
CREATE TABLE activity_registrations(id TEXT, studentId TEXT, activityId TEXT, status TEXT);
CREATE TABLE checkins(id TEXT, registrationId TEXT, status TEXT, confirmedAt TEXT);
CREATE TABLE experience_logs(id TEXT, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT);
SQL);

$student = '00000000-0000-4000-8000-000000000001';
$pdo->exec("INSERT INTO roles VALUES ('rs','student')");
$pdo->exec("INSERT INTO users VALUES ('us1','rs','active','Student One','stu@test.local')");
$pdo->exec("INSERT INTO schools VALUES ('s1','School','active')");
$pdo->exec("INSERT INTO classes VALUES ('c1','s1','12A','12','2026')");
$pdo->exec("INSERT INTO student_profiles VALUES ('$student','us1','c1','active')");
$pdo->exec("INSERT INTO projects VALUES ('p1','s1','t1','Robotics','in_progress')");
$pdo->exec("INSERT INTO project_members VALUES ('m1','p1','$student','active')");
$pdo->exec("INSERT INTO skills VALUES ('sk1','php','PHP','technical','active')");
$pdo->exec("INSERT INTO internship_posts VALUES ('post1','e1','PHP Intern','active')");
$pdo->exec("INSERT INTO internship_applications VALUES ('a1','post1','$student','accepted')");
$pdo->exec("INSERT INTO project_submissions VALUES ('r1','$student','p1',1,1,'verified','notes',NULL,NULL,NULL,NULL,NULL,'completed','2026-09-01','2026-09-02','ut1','Rõ ràng về php nhưng cần luyện thêm test.','2026-09-02')");
$pdo->exec("INSERT INTO project_submissions VALUES ('r-revoked','$student','p1',1,1,'revoked','old',NULL,NULL,NULL,NULL,NULL,'completed','2026-08-01','2026-08-02','ut1','Báo cáo đã thu hồi.','2026-08-02')");
$pdo->exec("INSERT INTO learner_portfolio_skills VALUES ('project','r1','sk1')");

$registry = new AiSourceRegistry();
$registry->registerTalentPassportSources(new DatabaseTalentPassportRepository($pdo));

$evalOnly = $registry->buildInput($student, ['evaluation']);
$skillsOnly = $registry->buildInput($student, ['skills']);
portfolio_feedback_check(($evalOnly->payload()['portfolio_feedback'][0]['feedback'] ?? '') === 'Rõ ràng về php nhưng cần luyện thêm test.', 'Verified portfolio feedback is current evaluation evidence');
portfolio_feedback_check(($evalOnly->payload()['portfolio_feedback'][0]['skill_codes'] ?? []) === ['php'], 'Feedback keeps confirmed skill tags without inventing a score');
portfolio_feedback_check(($evalOnly->payload()['skills'] ?? []) === [], 'Evaluation consent cannot inject portfolio skills');
portfolio_feedback_check(($skillsOnly->payload()['portfolio_feedback'] ?? []) === [], 'Skills consent cannot read lecturer feedback');
$before = $evalOnly->contentHash();

$pdo->exec("UPDATE project_submissions SET status='revoked', feedback='Revoked now', updatedAt='2026-09-03' WHERE id='r1'");
$pdo->exec("DELETE FROM learner_portfolio_skills WHERE reportId='r1'");
$afterRevoke = $registry->buildInput($student, ['evaluation']);
portfolio_feedback_check(($afterRevoke->payload()['portfolio_feedback'] ?? []) === [], 'Revoked reports leave the current snapshot');
portfolio_feedback_check($afterRevoke->contentHash() !== $before, 'Revoking portfolio feedback changes the AI hash');

$pdo->exec("UPDATE project_members SET status='inactive' WHERE id='m1'");
$pdo->exec("UPDATE project_submissions SET status='verified', feedback='Still verified text', updatedAt='2026-09-04' WHERE id='r1'");
$inactive = $registry->buildInput($student, ['evaluation']);
portfolio_feedback_check(($inactive->payload()['portfolio_feedback'] ?? []) === [], 'Inactive project membership is not current evidence');

echo "learner_ai_portfolio_feedback_snapshot_test: OK\n";
