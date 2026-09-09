<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigration.php';
require dirname(__DIR__) . '/app/learner/data/Migrations/ForwardMigrationDefinition.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\PortfolioRepository;

$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$error = static function (callable $fn, int $status, ?string $code=null) use ($assert): void {
    try { $fn(); } catch (ApiException $e) { $assert($e->status === $status, "Expected {$status}, got {$e->status}"); if($code!==null)$assert($e->errorCode===$code,"Expected code {$code}, got {$e->errorCode}"); return; }
    throw new RuntimeException("Expected ApiException {$status}");
};

$pdo = $GLOBALS['portfolio_test_pdo'] ?? new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') { $pdo->exec('PRAGMA foreign_keys=ON'); }
$pdo->exec(<<<'SQL'
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
foreach ($migration->migration->statements($driver) as $statement) { $pdo->exec($statement); }

$pdo->exec(<<<'SQL'
INSERT INTO roles VALUES ('rs','student'),('rt','teacher');
INSERT INTO users VALUES ('us1','rs','active','Student One'),('us2','rs','active','Student Two'),('ut1','rt','active','Teacher One'),('ut2','rt','active','Teacher Two'),('ut3','rt','active','Other School');
INSERT INTO schools VALUES ('s1','School One'),('s2','School Two');
INSERT INTO classes VALUES ('c1','s1'),('c2','s2');
INSERT INTO student_profiles VALUES ('stu1','us1','c1','active'),('stu2','us2','c2','active');
INSERT INTO teacher_profiles VALUES ('t1','ut1','s1'),('t2','ut2','s1'),('t3','ut3','s2');
INSERT INTO projects VALUES ('p1','s1','t1','Robotics','in_progress'),('p2','s2','t3','Wrong School','in_progress');
INSERT INTO project_members VALUES ('m1','p1','stu1','active'),('m2','p2','stu1','active');
INSERT INTO enterprises VALUES ('e1','Acme');
INSERT INTO internship_posts VALUES ('post1','e1','PHP Intern','active');
INSERT INTO internship_applications VALUES ('a1','post1','stu1','accepted');
INSERT INTO internship_mentor_assignments VALUES ('ima1','a1','t1');
INSERT INTO skills VALUES ('sk1','PHP','active'),('sk2','Old','inactive');
SQL);

$events = [];
$repo = new PortfolioRepository($pdo, static function (string $type, string $recipient, array $payload) use (&$events): void { $events[] = [$type,$recipient,$payload]; });
$listed = $repo->listForStudent('stu1');
$assert(count($listed['projects']) === 1 && count($listed['internships']) === 1, 'Only eligible same-school contexts are listed');

$draft = $repo->save('stu1','project','p1',0,['notes'=>'Initial','repositoryUrl'=>'https://example.test/repo','submit'=>false]);
$assert($draft['status']==='draft' && $draft['version']===1 && count($draft['history'])===1, 'Draft created with history');
$error(fn()=>$repo->save('stu1','project','p1',0,['notes'=>'stale']),409);
$error(fn()=>$repo->save('stu2','project','p1',1,['notes'=>'steal']),403);
$error(fn()=>$repo->save('stu1','project','p1',1,['notes'=>'x','repositoryUrl'=>'ftp://bad','submit'=>false]),422);
$assert($repo->listForStudent('stu1')['projects'][0]['report']['version']===1,'Invalid update does not overwrite');
$submitted=$repo->save('stu1','project','p1',1,['notes'=>'Submitted evidence','repositoryUrl'=>'https://example.test/repo','submit'=>true]);
$assert($submitted['status']==='submitted' && count($events)===1,'Submission notifies mentor');
$error(fn()=>$repo->save('stu1','project','p1',2,['notes'=>'immutable']),422);
$error(fn()=>$repo->review('ut2','project',$submitted['id'],2,'verified','',[]),403);
$changed=$repo->review('ut1','project',$submitted['id'],2,'changes_requested','Needs detail');
$assert($changed['status']==='changes_requested','Teacher requests changes');
$resub=$repo->save('stu1','project','p1',3,['notes'=>'More detail','submit'=>true]);
$error(fn()=>$repo->review('ut1','project',$resub['id'],4,'verified','', ['sk2']),422);
$verified=$repo->review('ut1','project',$resub['id'],4,'verified','Good',['sk1','sk1']);
$assert($verified['status']==='verified' && count($verified['history'])===5,'Verified with unique skills and full history');
$cv=$repo->verifiedForStudent('stu1');
$assert(count($cv['projects'])===1 && count($cv['skills'])===1,'Only verified evidence and skills appear');
$fakeSkill=$pdo->prepare("INSERT INTO learner_portfolio_skills(kind,reportId,skillId) VALUES ('internship',:id,'sk1')");
$fakeSkill->execute(['id'=>$verified['id']]);
$assert(count($repo->verifiedForStudent('stu1')['skills'])===1,'Skill report identity includes kind, not just id');
$pdo->prepare("DELETE FROM learner_portfolio_skills WHERE kind='internship' AND reportId=?")->execute([$verified['id']]);
$revoked=$repo->review('ut1','project',$verified['id'],5,'revoked','Evidence withdrawn');
$assert($revoked['status']==='revoked' && $repo->verifiedForStudent('stu1')['skills']===[],'Revocation removes current verified evidence');
$revision=$repo->save('stu1','project','p1',6,['notes'=>'Revision two','newRevision'=>true,'submit'=>false]);
$assert($revision['revision']===2 && $revision['status']==='draft','New revision starts draft and preserves row history');

$error(fn()=>$repo->save('stu1','internship','a1',0,['notes'=>'x','stage'=>'active','startDate'=>'2026-02-30','hours'=>10]),422,'VALIDATION_FAILED');
$error(fn()=>$repo->save('stu1','internship','a1',0,['notes'=>'x','stage'=>'active','startDate'=>'2026-01-01','hours'=>-0.25]),422,'VALIDATION_FAILED');
$error(fn()=>$repo->save('stu1','internship','a1',0,['notes'=>'x','stage'=>'completed','startDate'=>'2099-01-01','endDate'=>'2099-02-01','hours'=>0,'submit'=>true]),422,'VALIDATION_FAILED');
$error(fn()=>$repo->save('stu1','internship','a1',0,['notes'=>'x','stage'=>'active','hours'=>'1.234']),422,'VALIDATION_FAILED');
$intern=$repo->save('stu1','internship','a1',0,['notes'=>'Internship','stage'=>'completed','startDate'=>'2026-01-01','endDate'=>'2026-02-01','hours'=>120.25,'submit'=>true]);
$assert($intern['hours']===120.25,'Fractional internship hours retained exactly');
$assert($intern['status']==='submitted','Valid internship submission accepted');
$teacher=$repo->listForTeacher('ut1');
$assert(count($teacher)>=1 && isset($teacher[0]['studentName']),'Teacher list exposes assigned reports with student identity');
$assert(array_reduce($teacher, static fn(bool $ok,array $item):bool=>$ok&&$item['report']['status']!=='draft',true),'Teacher list never exposes draft content');
$pdo->exec("DELETE FROM internship_mentor_assignments WHERE applicationId='a1'");
$assert(count($repo->listForStudent('stu1')['internships'])===1,'Own report history remains visible after assignment removed');
$error(fn()=>$repo->review('ut1','internship',$intern['id'],1,'verified','',[]),403);

$error(fn()=>$repo->listForTeacher('us1'),403);

$submittedForMembership=$repo->save('stu1','project','p1',7,['notes'=>'Check current membership','submit'=>true]);
$pdo->exec("UPDATE project_members SET status='removed' WHERE id='m1'");
$error(fn()=>$repo->review('ut1','project',$revision['id'],8,'verified','',[]),422,'INVALID_CONTEXT');
$pdo->exec("UPDATE project_members SET status='active' WHERE id='m1'");
$repo->review('ut1','project',$revision['id'],8,'changes_requested','Please amend');
$revision=$repo->save('stu1','project','p1',9,['notes'=>'Draft for notification rollback']);
$pdo->exec("UPDATE teacher_profiles SET schoolId='s2' WHERE id='t1'");
$assert($repo->listForTeacher('ut1')===[],'Different-school assignments are not exposed');

$pdo->exec("UPDATE teacher_profiles SET schoolId='s1' WHERE id='t1'");
$failing = new PortfolioRepository($pdo, static function (): void { throw new RuntimeException('notification failed'); });
$before=(int)$revision['version'];
$error(fn()=>$failing->save('stu1','project','p1',$before,['notes'=>'Will roll back','submit'=>true]),500);
$after=$repo->listForStudent('stu1')['projects'][0]['report'];
$assert($after['version']===$before && $after['status']==='draft','Notification failure rolls back mutation and history');

echo "learner_portfolio_repository_test: OK\n";
