<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
require dirname(__DIR__).'/app/learner/data/bootstrap.php';
require dirname(__DIR__).'/app/learner/data/Database/DatabasePassportCvRepository.php';
require dirname(__DIR__).'/app/learner/data/ReadModel/PassportCvViewModel.php';
use TalentHub\Learner\Data\Database\DatabasePassportCvRepository;
use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = [
    'CREATE TABLE users(id TEXT PRIMARY KEY, fullName TEXT, email TEXT, role TEXT, status TEXT DEFAULT "active")',
    'CREATE TABLE schools(id TEXT PRIMARY KEY, name TEXT, status TEXT DEFAULT "active")',
    'CREATE TABLE classes(id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INT, academicYear TEXT, status TEXT DEFAULT "active")',
    'CREATE TABLE student_profiles(id TEXT PRIMARY KEY, userId TEXT, classId TEXT, dateOfBirth TEXT, phone TEXT, studyStatus TEXT DEFAULT "active", talentScore NUMERIC, updatedAt TEXT)',
    'CREATE TABLE teacher_profiles(id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT, isSchoolAdmin INT DEFAULT 0)',
    'CREATE TABLE skills(id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT DEFAULT "active")',
    'CREATE TABLE learner_evaluations(id TEXT PRIMARY KEY, seriesId TEXT, revision INT, studentId TEXT, teacherId TEXT, legacyAssessmentId TEXT, contextType TEXT DEFAULT "general", contextId TEXT, overallScore NUMERIC, comment TEXT, status TEXT, publishedAt TEXT, actorUserId TEXT, updatedAt TEXT, scoreMethod TEXT, formulaVersion TEXT, calculationJson TEXT, supersededAt TEXT, revokedAt TEXT)',
    'CREATE TABLE learner_evaluation_items(id TEXT PRIMARY KEY, evaluationId TEXT, itemKind TEXT, itemCode TEXT, skillId TEXT, label TEXT, score NUMERIC, maxScore NUMERIC, confirmed INT DEFAULT 0)',
    'CREATE TABLE learner_skill_evidence(id TEXT PRIMARY KEY, studentSkillId TEXT, evidenceType TEXT, evidenceRef TEXT, verificationStatus TEXT, observedAt TEXT, createdAt TEXT, studentId TEXT, skillId TEXT, evidenceKind TEXT DEFAULT "skill", sourceType TEXT, sourceId TEXT, sourceVersion INT, score NUMERIC, comment TEXT, actorUserId TEXT, expiresAt TEXT, revokedAt TEXT, supersedesId TEXT)',
    'CREATE TABLE student_skills(id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore NUMERIC, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT, createdAt TEXT DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT DEFAULT CURRENT_TIMESTAMP, scoreState TEXT, sourceEvaluationId TEXT, sourceEvidenceId TEXT, formulaVersion TEXT)',
    'CREATE TABLE projects(id TEXT PRIMARY KEY, title TEXT, category TEXT, description TEXT, status TEXT, endAt TEXT, updatedAt TEXT, schoolId TEXT, mentorTeacherId TEXT)',
    'CREATE TABLE project_members(id TEXT PRIMARY KEY, projectId TEXT, studentId TEXT, status TEXT, role TEXT, contribution TEXT, leftAt TEXT)',
    'CREATE TABLE enterprises(id TEXT PRIMARY KEY, name TEXT)',
    'CREATE TABLE internship_posts(id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT)',
    'CREATE TABLE internship_applications(id TEXT PRIMARY KEY, studentId TEXT, postId TEXT, status TEXT, updatedAt TEXT)',
    'CREATE TABLE assessments(id TEXT PRIMARY KEY, teacherId TEXT, studentId TEXT, status TEXT, comment TEXT, publishedAt TEXT, activityId TEXT)',
    'CREATE TABLE experience_logs(id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, status TEXT, confirmedAt TEXT, durationMinutes INT, reflection TEXT)',
    'CREATE TABLE activities(id TEXT PRIMARY KEY, title TEXT, schoolId TEXT, createdByTeacherId TEXT)',
];
foreach ($tables as $sql) $pdo->exec($sql);

$student = '10000000-0000-4000-8000-000000000001';
$user = '10000000-0000-4000-8000-000000000002';
$school = '10000000-0000-4000-8000-000000000003';
$class = '10000000-0000-4000-8000-000000000004';
$teacherUser = '10000000-0000-4000-8000-000000000005';

$pdo->exec("INSERT INTO users VALUES ('$user','Fixture Student','fixture@example.test','student','active');
INSERT INTO users VALUES ('$teacherUser','Teacher User','teacher@example.test','teacher','active');
INSERT INTO schools VALUES ('$school','Fixture University','active');
INSERT INTO classes VALUES ('$class','$school','Software Engineering',3,'2026','active');
INSERT INTO student_profiles VALUES ('$student','$user','$class',NULL,'','active',NULL,NULL);
INSERT INTO teacher_profiles VALUES ('teacher','$teacherUser','$school',0);
INSERT INTO skills VALUES ('s','sql','SQL','technical','active');
INSERT INTO student_skills (id,studentId,skillId,levelScore,sourceType,verificationStatus,verifiedAt) VALUES ('ss','$student','s',NULL,'teacher','verified','2026-09-01');
INSERT INTO projects VALUES ('p','Fixture project','technical','Desc','active',NULL,'2026-09-01','$school','teacher'),('other','Private other project','general','Secret','completed',NULL,'2026-09-01','$school','teacher');
INSERT INTO project_members VALUES ('pm1','p','$student','active','member','Tests',NULL),('pm2','other','other-student','active','lead','Secret',NULL);
INSERT INTO enterprises VALUES ('e','Fixture Company');
INSERT INTO internship_posts VALUES ('post','e','Intern');
INSERT INTO internship_applications VALUES ('app','$student','post','accepted','2026-09-01');
INSERT INTO learner_evaluations (id,seriesId,revision,studentId,teacherId,contextType,contextId,status,comment,publishedAt,actorUserId) VALUES ('ev1','series',1,'$student','teacher','project','p','published','Published comment','2026-09-01','$teacherUser');
INSERT INTO activities VALUES ('a','Club meeting','$school','teacher');
INSERT INTO experience_logs (id,studentId,activityId,status,confirmedAt) VALUES ('log','$student','a','confirmed','2026-09-01');");

$repo = new DatabasePassportCvRepository($pdo, new ScoreViewer(ScoreViewer::ROLE_STUDENT, $user, $school));
$one = $repo->forStudent($student);
if (count($one['projects']) !== 1 || $one['projects'][0]['id'] !== 'p') throw new RuntimeException('Cross-student data leak');
echo "[PASS] Only authenticated student membership selected\n";

$pdo->exec("INSERT INTO learner_skill_evidence (id,studentSkillId,verificationStatus,observedAt,revokedAt,expiresAt,studentId,skillId,evidenceKind,sourceType,sourceId,sourceVersion,score,actorUserId) VALUES ('e1','ss','verified','2026-09-01','2026-09-02',NULL,'$student','s','skill','evaluation','ev1',1,NULL,'$teacherUser')");
if ($repo->forStudent($student)['skills'] !== []) throw new RuntimeException('Revoked underlying evidence should not remain on CV');
echo "[PASS] Revoked underlying evidence overrides stale verified skill row\n";

$pdo->exec("UPDATE projects SET status='completed' WHERE id='p'; UPDATE users SET fullName='Updated Name' WHERE id='$user'; UPDATE student_skills SET verificationStatus='rejected',verifiedAt=NULL;
UPDATE internship_applications SET status='withdrawn'; INSERT INTO learner_evaluations (id,seriesId,revision,studentId,teacherId,contextType,contextId,status,comment,publishedAt,actorUserId) VALUES ('ev2','series',2,'$student','teacher','project','p','revoked','Withdrawn',NULL,'$teacherUser');");
$two = $repo->forStudent($student);
$cv = PassportCvViewModel::build($two, 'now');
if ($cv['name'] !== 'Updated Name' || $cv['projects'][0]['status_label'] !== 'Dự án đã hoàn thành') throw new RuntimeException('Fresh snapshot not used');
if ($cv['skills'] !== [] || $cv['internships'] !== [] || $cv['evaluations'] !== []) throw new RuntimeException('Revoked data retained');
echo "[PASS] Next snapshot reflects name/project changes and excludes withdrawn internship, skill and evaluation\n";
if (count($cv['activities']) !== 1) throw new RuntimeException('Confirmed activity missing');
echo "[PASS] Confirmed activity remains separate from competence\n";
