<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
require dirname(__DIR__).'/app/learner/data/bootstrap.php';
require dirname(__DIR__).'/app/learner/data/Database/DatabasePassportCvRepository.php';
require dirname(__DIR__).'/app/learner/data/ReadModel/PassportCvViewModel.php';
use TalentHub\Learner\Data\Database\DatabasePassportCvRepository;
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
 'CREATE TABLE users(id TEXT,fullName TEXT,email TEXT,status TEXT)',
 'CREATE TABLE schools(id TEXT,name TEXT,status TEXT)',
 'CREATE TABLE classes(id TEXT,schoolId TEXT,name TEXT,gradeLevel INT,academicYear TEXT)',
 'CREATE TABLE student_profiles(id TEXT,userId TEXT,classId TEXT,dateOfBirth TEXT,phone TEXT,studyStatus TEXT)',
 'CREATE TABLE student_skills(id TEXT,studentId TEXT,skillId TEXT,sourceType TEXT,verificationStatus TEXT,verifiedAt TEXT)',
 'CREATE TABLE learner_skill_evidence(id TEXT,studentSkillId TEXT,verificationStatus TEXT,observedAt TEXT,revokedAt TEXT,expiresAt TEXT)',
 'CREATE TABLE skills(id TEXT,name TEXT,status TEXT)',
 'CREATE TABLE projects(id TEXT,title TEXT,status TEXT,endAt TEXT,updatedAt TEXT)',
 'CREATE TABLE project_members(projectId TEXT,studentId TEXT,status TEXT,role TEXT,contribution TEXT)',
 'CREATE TABLE enterprises(id TEXT,name TEXT)',
 'CREATE TABLE internship_posts(id TEXT,enterpriseId TEXT,title TEXT)',
 'CREATE TABLE internship_applications(id TEXT,studentId TEXT,postId TEXT,status TEXT,updatedAt TEXT)',
 'CREATE TABLE teacher_profiles(id TEXT,userId TEXT)',
 'CREATE TABLE assessments(id TEXT,teacherId TEXT,studentId TEXT,status TEXT,comment TEXT,publishedAt TEXT,activityId TEXT)',
 'CREATE TABLE learner_evaluations(id TEXT,seriesId TEXT,revision INT,studentId TEXT,teacherId TEXT,status TEXT,comment TEXT,publishedAt TEXT,contextType TEXT,legacyAssessmentId TEXT)',
 'CREATE TABLE experience_logs(id TEXT,studentId TEXT,activityId TEXT,status TEXT,confirmedAt TEXT)',
 'CREATE TABLE activities(id TEXT,title TEXT)',
] as $sql) $pdo->exec($sql);
$student='10000000-0000-4000-8000-000000000001';
$user='10000000-0000-4000-8000-000000000002';
$school='10000000-0000-4000-8000-000000000003';
$class='10000000-0000-4000-8000-000000000004';
$pdo->exec("INSERT INTO users VALUES ('$user','Fixture Student','fixture@example.test','active');
INSERT INTO schools VALUES ('$school','Fixture University','active');
INSERT INTO classes VALUES ('$class','$school','Software Engineering',3,'2026');
INSERT INTO student_profiles VALUES ('$student','$user','$class',NULL,'','active');
INSERT INTO teacher_profiles VALUES ('teacher','$user');
INSERT INTO skills VALUES ('s','SQL','active');
INSERT INTO student_skills VALUES ('ss','$student','s','teacher','verified','2026-09-01');
INSERT INTO projects VALUES ('p','Fixture project','active',NULL,'2026-09-01'),('other','Private other project','completed',NULL,'2026-09-01');
INSERT INTO project_members VALUES ('p','$student','active','member','Tests'),('other','other-student','active','lead','Secret');
INSERT INTO enterprises VALUES ('e','Fixture Company');
INSERT INTO internship_posts VALUES ('post','e','Intern');
INSERT INTO internship_applications VALUES ('app','$student','post','accepted','2026-09-01');
INSERT INTO learner_evaluations VALUES ('ev1','series',1,'$student','teacher','published','Published comment','2026-09-01','project',NULL);
INSERT INTO activities VALUES ('a','Club meeting');
INSERT INTO experience_logs VALUES ('log','$student','a','confirmed','2026-09-01');");
$repo=new DatabasePassportCvRepository($pdo);
$one=$repo->forStudent($student);
if (count($one['projects'])!==1 || $one['projects'][0]['id']!=='p') throw new RuntimeException('Cross-student data leak');
echo "[PASS] Only authenticated student membership selected\n";
$pdo->exec("INSERT INTO learner_skill_evidence VALUES ('e1','ss','verified','2026-09-01','2026-09-02',NULL)");
if ($repo->forStudent($student)['skills']!==[]) throw new RuntimeException('Revoked underlying evidence should not remain on CV');
echo "[PASS] Revoked underlying evidence overrides stale verified skill row\n";
$pdo->exec("UPDATE projects SET status='completed' WHERE id='p'; UPDATE users SET fullName='Updated Name' WHERE id='$user'; UPDATE student_skills SET verificationStatus='rejected',verifiedAt=NULL;
UPDATE internship_applications SET status='withdrawn'; INSERT INTO learner_evaluations VALUES ('ev2','series',2,'$student','teacher','revoked','Withdrawn',NULL,'project',NULL);");
$two=$repo->forStudent($student);
$cv=PassportCvViewModel::build($two,'now');
if ($cv['name']!=='Updated Name' || $cv['projects'][0]['status_label']!=='Dự án đã hoàn thành') throw new RuntimeException('Fresh snapshot not used');
if ($cv['skills']!==[] || $cv['internships']!==[] || $cv['evaluations']!==[]) throw new RuntimeException('Revoked data retained');
echo "[PASS] Next snapshot reflects name/project changes and excludes withdrawn internship, skill and evaluation\n";
if (count($cv['activities'])!==1) throw new RuntimeException('Confirmed activity missing');
echo "[PASS] Confirmed activity remains separate from competence\n";
