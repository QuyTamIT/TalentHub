<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/learner/data/Service/EvidenceBackedScoreService.php';
use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Learner\Data\Service\ScoreViewer;

// SQLite mirrors the real 003/019 evidence contract and P3 nullable projection.
function scoreFixture(): PDO {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec(<<<'SQL'
CREATE TABLE users(id TEXT PRIMARY KEY, fullName TEXT, role TEXT, status TEXT DEFAULT 'active');
CREATE TABLE classes(id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, status TEXT DEFAULT 'active');
CREATE TABLE student_profiles(id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT DEFAULT 'active', talentScore NUMERIC, updatedAt TEXT);
CREATE TABLE teacher_profiles(id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT, isSchoolAdmin INTEGER DEFAULT 0);
CREATE TABLE teacher_class_assignments(id TEXT PRIMARY KEY, teacherId TEXT, classId TEXT, status TEXT);
CREATE TABLE skills(id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT DEFAULT 'active');
CREATE TABLE learner_evaluations(id TEXT PRIMARY KEY, seriesId TEXT, revision INTEGER, studentId TEXT, teacherId TEXT, legacyAssessmentId TEXT, contextType TEXT DEFAULT 'general', contextId TEXT, overallScore NUMERIC, comment TEXT, status TEXT, publishedAt TEXT, actorUserId TEXT, updatedAt TEXT, scoreMethod TEXT, formulaVersion TEXT, calculationJson TEXT, supersededAt TEXT, revokedAt TEXT, UNIQUE(seriesId,revision));
CREATE TABLE learner_evaluation_items(id TEXT PRIMARY KEY, evaluationId TEXT, itemKind TEXT, itemCode TEXT, skillId TEXT, label TEXT, score NUMERIC, maxScore NUMERIC, confirmed INTEGER DEFAULT 0);
CREATE TABLE learner_skill_evidence(id TEXT PRIMARY KEY, studentSkillId TEXT, evidenceType TEXT, evidenceRef TEXT, verificationStatus TEXT, observedAt TEXT, createdAt TEXT, studentId TEXT, skillId TEXT, evidenceKind TEXT DEFAULT 'skill', sourceType TEXT, sourceId TEXT, sourceVersion INTEGER, score NUMERIC, comment TEXT, actorUserId TEXT, expiresAt TEXT, revokedAt TEXT, supersedesId TEXT);
CREATE TABLE student_skills(id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore NUMERIC, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT, createdAt TEXT DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT DEFAULT CURRENT_TIMESTAMP, scoreState TEXT, sourceEvaluationId TEXT, sourceEvidenceId TEXT, formulaVersion TEXT, UNIQUE(studentId,skillId,sourceType));
CREATE TABLE projects(id TEXT PRIMARY KEY, title TEXT, schoolId TEXT, mentorTeacherId TEXT, status TEXT);
CREATE TABLE project_members(id TEXT PRIMARY KEY, projectId TEXT, studentId TEXT, status TEXT, leftAt TEXT);
CREATE TABLE project_submissions(id TEXT PRIMARY KEY, projectId TEXT, studentId TEXT, version INTEGER, status TEXT, reviewedAt TEXT, reviewedByUserId TEXT);
CREATE TABLE learner_portfolio_skills(kind TEXT, reportId TEXT, skillId TEXT);
INSERT INTO users(id,fullName,role) VALUES ('u1','Learner','student'),('u2','Other','student'),('ut','Teacher','teacher'),('ux','Wrong school','teacher');
INSERT INTO classes VALUES ('c1','s1','Class','active'),('c2','s2','Other','active');
INSERT INTO student_profiles(id,userId,classId,talentScore) VALUES ('st1','u1','c1',99),('st2','u2','c2',99);
INSERT INTO teacher_profiles VALUES ('t1','ut','s1',0),('tx','ux','s2',0);
INSERT INTO teacher_class_assignments VALUES ('ta','t1','c1','active');
INSERT INTO skills(id,code,name,category) VALUES ('py','python','Python','technical'),('db','database','Database','technical'),('old','legacy','Legacy','soft'),('self','self','Self','soft');
INSERT INTO learner_evaluations(id,seriesId,revision,studentId,teacherId,contextType,contextId,overallScore,comment,status,publishedAt,actorUserId,scoreMethod,formulaVersion,calculationJson) VALUES ('ev1','series',1,'st1','t1','class','c1',95,'private context','published','2026-09-01','ut','rubric_weighted','rubric-weighted-1.0','{"total":95}');
INSERT INTO learner_evaluation_items VALUES ('it1','ev1','skill','python','py','Python',0,100,1);
INSERT INTO student_skills(id,studentId,skillId,levelScore,sourceType,verificationStatus,verifiedAt) VALUES ('legacy-py','st1','py',95,'teacher','verified','2026-08-01'),('self-py','st1','py',80,'self_declared','self_declared',NULL),('legacy','st1','old',88,'teacher','verified','2026-08-01'),('self','st1','self',70,'self_declared','self_declared',NULL);
INSERT INTO projects VALUES ('p1','Project','s1','t1','completed');
INSERT INTO project_members VALUES ('pm','p1','st1','active',NULL);
INSERT INTO project_submissions VALUES ('r1','p1','st1',2,'verified','2026-09-02','ut');
INSERT INTO learner_portfolio_skills VALUES ('project','r1','db');
INSERT INTO learner_skill_evidence(id,studentId,skillId,evidenceType,evidenceRef,verificationStatus,sourceType,sourceId,sourceVersion,score,observedAt,actorUserId) VALUES ('proof','st1','db','project','r1','verified','project_submission','r1',2,100,'2026-09-02','ut');
SQL);
    return $db;
}
function scoreCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function scoreDenied(callable $fn): bool { try { $fn(); } catch (DomainException $e) { return true; } return false; }
function scoreById(array $result): array { return array_column($result['skills'], null, 'skill_id'); }

if (realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) return;
$db = scoreFixture();
$service = new EvidenceBackedScoreService($db);
$viewer = new ScoreViewer('student', 'u1');
$failures = [];
$cases = [
    'anonymous denied' => function () use ($service) { scoreCheck(scoreDenied(fn() => $service->forStudent('st1', new ScoreViewer())), 'anonymous received sources'); },
    'wrong learner denied' => function () use ($service) { scoreCheck(scoreDenied(fn() => $service->forStudent('st1', new ScoreViewer('student','u2'))), 'wrong learner received scores'); },
    'wrong school denied' => function () use ($service) { scoreCheck(scoreDenied(fn() => $service->forStudent('st1', new ScoreViewer('teacher','ux','s1'))), 'forged school accepted'); },
    'source-only evidence and zero' => function () use ($service,$viewer) {
        $r = $service->forStudent('st1',$viewer); $s=scoreById($r);
        scoreCheck($s['py']['score']===0.0 && $s['py']['state']==='scored','valid zero lost');
        scoreCheck($s['db']['score']===null && $s['db']['state']==='evidence_only','evidence score trusted without evaluation');
        scoreCheck($s['old']['state']==='missing_source' && $s['self']['state']==='unverified','legacy provenance');
        scoreCheck($s['py']['formula_version']===null && $s['py']['calculation']===null,'context rubric fabricated as per-skill formula');
        scoreCheck($r['summary']['score']===0.0 && count($r['summary']['included_skill_ids'])===1,'mean includes non-skill scores');
    },
    'unconfirmed item denied' => function () use ($db,$service,$viewer) { $db->exec('UPDATE learner_evaluation_items SET confirmed=0'); try { scoreCheck($service->forStudent('st1',$viewer)['summary']['score']===null,'unconfirmed score accepted'); } finally { $db->exec('UPDATE learner_evaluation_items SET confirmed=1'); } },
    'teacher authority' => function () use ($db,$service,$viewer) { $db->exec("UPDATE learner_evaluations SET teacherId='tx'"); try { scoreCheck($service->forStudent('st1',$viewer)['summary']['score']===null,'foreign teacher accepted'); } finally { $db->exec("UPDATE learner_evaluations SET teacherId='t1'"); } },
    'projection preserves history and uniqueness' => function () use ($db,$service) {
        $before=$db->query('SELECT * FROM learner_evaluations')->fetchAll(PDO::FETCH_ASSOC);
        $service->projectOfficialScores('st1'); $service->projectOfficialScores('st1');
        scoreCheck((float)$db->query("SELECT levelScore FROM student_skills WHERE id='legacy-py'")->fetchColumn()===95.0,'legacy score overwritten');
        scoreCheck($db->query("SELECT sourceType FROM student_skills WHERE id='self-py'")->fetchColumn()==='self_declared','legacy source rewritten');
        scoreCheck((int)$db->query("SELECT COUNT(*) FROM student_skills WHERE studentId='st1' AND scoreState='scored'")->fetchColumn()===1,'duplicate official skill');
        scoreCheck($before===$db->query('SELECT * FROM learner_evaluations')->fetchAll(PDO::FETCH_ASSOC),'evaluation history changed');
        $db->exec("UPDATE learner_evaluations SET status='revoked'");
        $service->projectOfficialScores('st1');
        scoreCheck($db->query("SELECT talentScore FROM student_profiles WHERE id='st1'")->fetchColumn()===null,'revoked mean stale');
        scoreCheck((int)$db->query("SELECT COUNT(*) FROM student_skills WHERE scoreState='scored'")->fetchColumn()===0,'revoked projection stale');
        $db->exec("UPDATE learner_evaluations SET status='published'");
    },
];
foreach ($cases as $name=>$case) { try { $case(); echo "PASS $name\n"; } catch (Throwable $e) { $failures[]="$name: {$e->getMessage()}"; echo "FAIL ".end($failures)."\n"; } }
if ($failures) exit(1);
echo "learner_evidence_backed_scores_test: OK\n";
