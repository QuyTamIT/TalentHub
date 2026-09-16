<?php
declare(strict_types=1);
require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../app/learner/data/bootstrap.php';
require_once __DIR__ . '/../app/learner/ai/bootstrap.php';
require_once __DIR__ . '/learner_evidence_backed_scores_test.php';

use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;
use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Support\Uuid;

$db = scoreFixture();
$ids = [];
foreach (['st1','u1','t1','ut','c1','s1','py'] as $old) $ids[$old] = Uuid::v4();
// Convert the shared short-id fixture into public API UUIDs.
foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
    foreach ($db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $column) {
        if (!in_array($column['name'], ['id','studentId','userId','teacherId','actorUserId','classId','schoolId','skillId','contextId','mentorTeacherId'], true)) continue;
        $update = $db->prepare("UPDATE {$table} SET {$column['name']}=? WHERE {$column['name']}=?");
        foreach ($ids as $old=>$new) $update->execute([$new,$old]);
    }
}
$db->exec(<<<'SQL'
CREATE TABLE assessments(id TEXT PRIMARY KEY,teacherId TEXT,studentId TEXT,activityId TEXT,classId TEXT,projectId TEXT,overallScore REAL,comment TEXT,status TEXT,publishedAt TEXT,version INT,createdAt TEXT,updatedAt TEXT,scoreMethod TEXT,formulaVersion TEXT,calculationJson TEXT);
CREATE TABLE assessment_criteria(id TEXT PRIMARY KEY,code TEXT,name TEXT,description TEXT,minScore REAL,maxScore REAL,weight REAL,displayOrder INT,status TEXT);
CREATE TABLE assessment_scores(id TEXT PRIMARY KEY,assessmentId TEXT,criteriaId TEXT,score REAL,UNIQUE(assessmentId,criteriaId));
CREATE TABLE activities(id TEXT PRIMARY KEY,title TEXT,schoolId TEXT,createdByTeacherId TEXT,status TEXT);
CREATE TABLE schools(id TEXT PRIMARY KEY,name TEXT);
CREATE TABLE activity_registrations(id TEXT PRIMARY KEY,activityId TEXT,studentId TEXT,status TEXT);
CREATE TABLE skill_groups(code TEXT PRIMARY KEY,name TEXT,status TEXT,displayOrder INT);
CREATE TABLE assessment_skill_group_scores(assessmentId TEXT,groupCode TEXT,score REAL,PRIMARY KEY(assessmentId,groupCode));
ALTER TABLE learner_evaluations ADD COLUMN createdAt TEXT;
ALTER TABLE learner_evaluations ADD COLUMN eventKey TEXT;
ALTER TABLE learner_evaluation_items ADD COLUMN comment TEXT;
ALTER TABLE learner_evaluation_items ADD COLUMN createdAt TEXT;
CREATE UNIQUE INDEX evaluation_item ON learner_evaluation_items(evaluationId,itemKind,itemCode);
INSERT INTO skill_groups VALUES ('backend','Backend','active',1),('frontend','Frontend','active',2);
SQL);
$activity = Uuid::v4(); $criterion = Uuid::v4();
$db->prepare('INSERT INTO schools VALUES (?,?)')->execute([$ids['s1'],'Test school']);
$db->prepare('INSERT INTO activities VALUES (?,?,?,?,?)')->execute([$activity,'Workflow test',$ids['s1'],$ids['t1'],'published']);
$db->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?)')->execute([Uuid::v4(),$activity,$ids['st1'],'approved']);
$db->prepare('INSERT INTO assessment_criteria VALUES (?,?,?,?,?,?,?,?,?)')->execute([$criterion,'knowledge','Chuyên môn','',0,100,1,1,'active']);
$writer = new TeacherGradingService(new TeacherGradingRepository($db));
$reader = new DatabaseAssessmentRepository($db);
$payload = ['mode'=>'activity','contextId'=>$activity,'studentId'=>$ids['st1'],'expectedVersion'=>'0',
    'assessmentStatus'=>'published','comment'=>'First review','criteria'=>[$criterion=>'75.25'],
    'skillGroups'=>[['groupCode'=>'backend','score'=>'85.25'],['groupCode'=>'frontend','score'=>'0']]];
$writer->save($ids['ut'], $payload);
$first = $reader->publishedEvaluationsForStudent($ids['st1'])[0];
scoreCheck(count($first['skills']) === 2 && (float)$first['scores'][0]['score'] === 75.25, 'Teacher write reaches learner criteria and both groups');
$aiRows = (new \TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource($db))->forStudent($ids['st1']);
scoreCheck(($aiRows[0]['criteria_scores'][0]['score'] ?? null) === 75.25, 'AI receives exact published rubric criteria as context');
$payload['assessmentId'] = $first['id']; $payload['expectedVersion']='1'; $payload['comment']='Regraded review';
$payload['skillGroups']=[['groupCode'=>'backend','score'=>'40']];
$writer->save($ids['ut'], $payload);
$second = $reader->publishedEvaluationsForStudent($ids['st1'])[0];
scoreCheck(count($second['skills']) === 1 && $second['skills'][0]['score'] === 40.0, 'Real regrade removes old group and replaces higher score');
scoreCheck($second['comment'] === 'Regraded review', 'Learner gets updated feedback');
$official = (new EvidenceBackedScoreService($db))->forStudent($ids['st1'], new ScoreViewer('student',$ids['u1']));
scoreCheck($official['skill_groups'][0]['score'] === 40.0, 'Same latest grade reaches official learner profile');
$draftId = Uuid::v4();
$db->prepare("INSERT INTO learner_evaluations(id,seriesId,revision,studentId,teacherId,legacyAssessmentId,contextType,contextId,status,actorUserId)
    SELECT ?,seriesId,99,studentId,teacherId,legacyAssessmentId,contextType,contextId,'draft',actorUserId
    FROM learner_evaluations WHERE legacyAssessmentId=? AND revision=2")->execute([$draftId,$first['id']]);
scoreCheck(count($reader->publishedEvaluationsForStudent($ids['st1'])) === 1, 'An unpublished pending draft does not hide the current publication');
$db->prepare('DELETE FROM learner_evaluations WHERE id=?')->execute([$draftId]);
// Retained individual skills must come only from the current revision.
$payload['expectedVersion']='2'; unset($payload['skillGroups']); $payload['skills']=[['skillId'=>$ids['py'],'score'=>'60']];
$writer->save($ids['ut'], $payload);
$payload['expectedVersion']='3'; $payload['skills'][0]['score']='20';
$writer->save($ids['ut'], $payload);
$skills = $reader->publishedEvaluationsForStudent($ids['st1'])[0]['skills'];
$python = array_values(array_filter($skills, fn($s)=>($s['skillName'] ?? '')==='Python'));
scoreCheck(count($python) === 1 && (float)$python[0]['score'] === 20.0, 'Only latest individual skill revision is exposed');
echo "teacher_evaluation_workflow_sync_test: OK\n";
