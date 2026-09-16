<?php
declare(strict_types=1);
require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/learner_evidence_backed_scores_test.php';
require_once __DIR__ . '/../app/learner/ai/bootstrap.php';

use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;

$db = scoreFixture();
$db->exec(<<<'SQL'
CREATE TABLE assessments(id TEXT PRIMARY KEY,studentId TEXT,activityId TEXT,status TEXT,publishedAt TEXT,updatedAt TEXT,overallScore REAL,comment TEXT);
CREATE TABLE skill_groups(code TEXT PRIMARY KEY,name TEXT,status TEXT,displayOrder INTEGER);
CREATE TABLE assessment_skill_group_scores(assessmentId TEXT,groupCode TEXT,score REAL);
INSERT INTO assessments VALUES ('a1','st1',NULL,'published','2026-09-01','2026-09-01',95,'feedback'),('private','st2',NULL,'published','2026-09-02','2026-09-02',100,'private');
UPDATE learner_evaluations SET legacyAssessmentId='a1' WHERE id='ev1';
INSERT INTO skill_groups VALUES ('backend','Lập trình Backend','active',1),('frontend','Lập trình Frontend','active',2);
INSERT INTO assessment_skill_group_scores VALUES ('a1','backend',85.25),('a1','frontend',0),('private','backend',100);
SQL);
$service = new EvidenceBackedScoreService($db);
$read = fn() => $service->forStudent('st1', new ScoreViewer('student', 'u1'));
$groups = $read()['skill_groups'] ?? [];
scoreCheck(count($groups) === 2, 'Published teacher group scores must reach the learner official profile');
scoreCheck($groups[0]['score'] === 85.25 && $groups[1]['score'] === 0.0, 'Keep precise and zero scores');
scoreCheck(count($read()['skills']) === 4, 'Do not fabricate child skills from a group');
$source = new DatabasePublishedEvaluationSource($db);
$before = $source->forStudent('st1');
scoreCheck(count($before[0]['skill_group_scores'] ?? []) === 2, 'AI reads both graded groups');
$registry = \TalentHub\Learner\Ai\Sources\AiSourceRegistry::fromLegacySources([$source]);
$input = $registry->buildInput('st1', ['skills','evaluation']);
$profile = \TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile::fromInput($input);
scoreCheck($profile->skillScore('group_backend') === 85, 'Matching sees the graded group as a distinct competency');
scoreCheck($profile->skillScore('mysql') === null, 'Group score never invents MySQL proficiency');
$context = new \TalentHub\Learner\Ai\Domain\RecommendationContext(['skills','evaluation'], 'test-request', 'test-key');
$roadmap = (new \TalentHub\Learner\Ai\Model\RoadmapPromptRegistry())->create($input, $context)->payload();
scoreCheck(str_contains(json_encode($roadmap), 'private context'), 'Roadmap provider receives the published teacher feedback');
scoreCheck(str_contains(json_encode($roadmap), 'skill_group_scores'), 'Roadmap provider receives exact group evidence');
$candidate = \TalentHub\Learner\Ai\Matching\OpportunityCandidate::fromEvidence([
    'source_type'=>'opportunity', 'source_id'=>'job-1', 'safe_value'=>[
        'catalog_id'=>'job-1', 'item_type'=>'internship', 'enterprise_id'=>'company',
        'title'=>'Backend internship', 'status'=>'active', 'url'=>'/app/learner/opportunity.php?type=internship&id=job-1',
        'required_skills'=>[['code'=>'python','minimum_score'=>70,'label'=>'Python']],
    ],
]);
$role = new \TalentHub\Learner\Ai\Matching\CareerRoleBenchmark('backend', 'Backend', 'technical', [
    ['code'=>'python','label'=>'Python','minimum_score'=>70,'weight'=>100.0,'required'=>true],
], []);
$match = (new \TalentHub\Learner\Ai\Matching\JobMatchScorer())->score($profile, $candidate, $role);
$gap = (new \TalentHub\Learner\Ai\Matching\SkillGapResolver())->resolve($match);
$job = \TalentHub\Learner\Ai\Model\JobMatchPromptRegistry::create($profile, [$candidate], ['job-1'=>$match], ['job-1'=>$gap], $context)->payload();
scoreCheck(str_contains(json_encode($job), 'private context'), 'Internship matching provider receives teacher feedback');
$project = \TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry::create($profile, [], [], $context)->payload();
scoreCheck(str_contains(json_encode($project), 'private context'), 'Project matching provider receives teacher feedback');
$db->exec("UPDATE assessment_skill_group_scores SET score=40 WHERE assessmentId='a1' AND groupCode='backend'");
scoreCheck($read()['skill_groups'][0]['score'] === 40.0, 'Regrading lower is immediately authoritative');
scoreCheck(json_encode($source->forStudent('st1')) !== json_encode($before), 'Group-only edit changes AI freshness input');
scoreCheck($registry->buildInput('st1', ['skills','evaluation'])->contentHash() !== $input->contentHash(), 'Group edit invalidates AI cached input');
$db->exec("DELETE FROM assessment_skill_group_scores WHERE assessmentId='a1' AND groupCode='frontend'");
scoreCheck(count($read()['skill_groups']) === 1, 'Removed group disappears');
$db->exec("UPDATE assessments SET status='draft' WHERE id='a1'");
scoreCheck($read()['skill_groups'] === [], 'Draft cannot contribute published group scores');
$db->exec("UPDATE assessments SET status='published' WHERE id='a1'; UPDATE learner_evaluations SET status='revoked' WHERE id='ev1'");
scoreCheck($read()['skill_groups'] === [], 'Revoked evaluation cannot resurrect legacy group scores');
scoreCheck($source->forStudent('st1') === [], 'AI excludes revoked evaluations');
$db->exec("UPDATE learner_evaluations SET status='published',revokedAt='2026-09-16' WHERE id='ev1'");
scoreCheck($source->forStudent('st1') === [], 'AI honors an explicit revocation timestamp');
$db->exec("UPDATE learner_evaluations SET revokedAt=NULL WHERE id='ev1';
INSERT INTO learner_evaluations(id,seriesId,revision,studentId,teacherId,contextType,contextId,status,actorUserId,legacyAssessmentId)
VALUES ('pending','series',2,'st1','t1','class','c1','draft','ut','a1')");
scoreCheck(count($source->forStudent('st1')) === 1, 'AI keeps the effective publication while a later draft is pending');
echo "teacher_evaluation_group_sync_test: OK\n";
