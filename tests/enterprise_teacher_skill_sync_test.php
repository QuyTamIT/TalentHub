<?php
declare(strict_types=1);
require __DIR__ . '/teacher_evaluation_workflow_sync_test.php';

use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseMatchService;

$db->exec(<<<'SQL'
CREATE TABLE enterprises(id TEXT PRIMARY KEY,status TEXT,verificationStatus TEXT);
INSERT INTO enterprises VALUES ('ent','active','verified');
CREATE TABLE enterprise_talent_access_grants(id TEXT,enterpriseId TEXT,studentId TEXT,scope TEXT,consentId TEXT,revokedAt TEXT,expiresAt TEXT);
CREATE TABLE privacy_consents(id TEXT,studentId TEXT,scope TEXT,isGranted INT,revokedAt TEXT);
CREATE TABLE enterprise_ai_match_rankings(enterprise_id TEXT,job_hash TEXT,ranking_json TEXT,updated_at TEXT,PRIMARY KEY(enterprise_id,job_hash));
ALTER TABLE projects ADD COLUMN category TEXT;
ALTER TABLE projects ADD COLUMN description TEXT;
ALTER TABLE projects ADD COLUMN projectUrl TEXT;
ALTER TABLE projects ADD COLUMN createdAt TEXT;
ALTER TABLE projects ADD COLUMN updatedAt TEXT;
ALTER TABLE project_members ADD COLUMN role TEXT;
ALTER TABLE project_members ADD COLUMN contribution TEXT;
ALTER TABLE users ADD COLUMN email TEXT;
ALTER TABLE student_profiles ADD COLUMN phone TEXT;
ALTER TABLE student_profiles ADD COLUMN createdAt TEXT;
ALTER TABLE enterprise_talent_access_grants ADD COLUMN grantedAt TEXT;
CREATE TABLE student_profile_details(studentId TEXT,headline TEXT,bio TEXT,location TEXT,avatarUrl TEXT);
CREATE TABLE enterprise_contact_requests(id TEXT,studentId TEXT,enterpriseId TEXT,status TEXT);
CREATE TABLE activity_experience_policies(activityId TEXT,confirmedHours REAL);
CREATE TABLE checkins(registrationId TEXT,status TEXT,createdAt TEXT);
ALTER TABLE activity_registrations ADD COLUMN registeredAt TEXT;
SQL);
$repo = new EnterpriseTalentRepository($db);
$skills = $repo->skillsWithDetailsForStudent($ids['st1'], 'ent');
scoreCheck(count($skills) === 2, 'Enterprise sees deduplicated current individual and group skills');
$byId = array_column($skills, null, 'skillId');
scoreCheck(($byId['group:backend']['levelScore'] ?? null) === 40.0, 'Enterprise includes latest group grade');
scoreCheck(($byId[$ids['py']]['levelScore'] ?? null) === 20.0, 'Enterprise uses latest individual grade, not historic maximum');
scoreCheck(!isset($byId['group:frontend']), 'Removed group does not reappear');
scoreCheck(!isset($byId[$ids['py']]['evaluator']), 'Enterprise skill projection excludes private assessor data');

// Exercise actual list/detail boundaries with a recipient-scoped discovery grant.
$db->prepare("INSERT INTO privacy_consents VALUES ('consent',?,'enterprise_talent_discovery',1,NULL)")->execute([$ids['st1']]);
$db->prepare("INSERT INTO enterprise_talent_access_grants(id,enterpriseId,studentId,scope,consentId,expiresAt) VALUES ('grant','ent',?,'enterprise_talent_discovery','consent','2099-01-01')")->execute([$ids['st1']]);
$detail = $repo->getTalentDetail('ent',$ids['st1']);
scoreCheck(array_column($detail['skills'],'skillId') === array_column($skills,'skillId'), 'Actual detail uses the same current skill set');
foreach ([['skills'=>'Backend'],['skills'=>['Backend']],['skill_tag'=>'backend'],['search'=>'Backend']] as $filters) {
    $list = $repo->listTalents('ent',$filters);
    scoreCheck(count($list['items']) === 1 && $list['items'][0]['skillCount'] === 2, 'Discovery filters find current grouped skills without duplicates');
}
scoreCheck($repo->listTalents('ent',['skills'=>'Frontend'])['total'] === 0, 'Removed group is not discoverable');
$db->exec("UPDATE projects SET category='unique_project_domain'");
scoreCheck($repo->listTalents('ent',['search'=>'unique_project_domain'])['total'] === 1, 'Project category search remains available');
// A high stale projection must not win current-score ordering before pagination.
$db->exec("INSERT INTO privacy_consents VALUES ('consent2','st2','enterprise_talent_discovery',1,NULL)");
$db->exec("INSERT INTO enterprise_talent_access_grants(id,enterpriseId,studentId,scope,consentId,expiresAt) VALUES ('grant2','ent','st2','enterprise_talent_discovery','consent2','2099-01-01')");
$db->exec("INSERT INTO project_members(id,projectId,studentId,status) VALUES ('pm2','p1','st2','active')");
$db->exec("UPDATE users SET fullName='Mai' WHERE id='u2'");
$aiList = $repo->listTalents('ent',['major_field'=>'AI']);
scoreCheck(!in_array('st2',array_column($aiList['items'],'studentId'),true), 'AI inside a candidate name is not domain evidence');
$paged = $repo->listTalents('ent',['sort'=>'score_desc','limit'=>1]);
scoreCheck($paged['total'] === 2 && $paged['items'][0]['studentId'] === $ids['st1'], 'Sort and limit use current scores, not stale 99-point projection');

$service = new EnterpriseMatchService($repo);
$calls = 0; $lastInput = [];
$provider = function ($job, $candidates) use (&$calls, &$lastInput) {
    $calls++; $lastInput = $candidates;
    return ['model_version'=>'fixture-model','items'=>array_map(fn($c)=>[
        'candidate_ref'=>$c['candidate_ref'], 'match_score'=>70,
        'reason_codes'=>['verified_skill_match','domain_match'],
    ], $candidates)];
};
$job = ['id'=>'job','title'=>'Backend intern','required_skills'=>['Backend']];
$result = $service->match('ent', $job, $provider);
scoreCheck($result['analysis_origin'] === 'model', 'Provider output accepted');
$service->match('ent', $job, $provider);
scoreCheck($calls === 1, 'Unchanged input reuses matching cache');
$payload['expectedVersion']='4'; $payload['skillGroups']=[['groupCode'=>'backend','score'=>'0']];
unset($payload['skills']);
$writer->save($ids['ut'], $payload);
$payload['expectedVersion']='5'; unset($payload['skillGroups']);
$payload['skills']=[['skillId'=>$ids['py'],'score'=>'12.5']];
$writer->save($ids['ut'], $payload);
$service->match('ent', $job, $provider);
scoreCheck($calls === 2, 'Regrading invalidates matching cache');
$inputSkills = array_merge(...array_column($lastInput,'verified_skills'));
$inputByName = array_column($inputSkills,null,'name');
scoreCheck(($inputByName['Backend']['level_score'] ?? null) === 0.0, 'Matching retains legitimate zero group score');
scoreCheck(($inputByName['Python']['level_score'] ?? null) === 12.5, 'Matching receives exact current individual grade');
scoreCheck(($inputByName['Backend']['item_kind'] ?? null) === 'skill_group', 'Provider knows a group is not an individual skill');
$payload['expectedVersion']='6'; unset($payload['skills']); $payload['skillGroups']=[];
$writer->save($ids['ut'], $payload);
$service->match('ent',$job,$provider);
scoreCheck($calls === 3, 'Removing a group invalidates a populated matching cache');
$removedInputs = array_merge(...array_column($lastInput,'verified_skills'));
scoreCheck(!in_array('Backend',array_column($removedInputs,'name'),true), 'Removed group is absent from refreshed provider input');
// Revoke the authoritative publication without refreshing the projection.
$db->prepare("UPDATE learner_evaluations SET revokedAt=CURRENT_TIMESTAMP WHERE legacyAssessmentId=? AND status='published'")->execute([$first['id']]);
$revoked = $repo->skillsWithDetailsForStudent($ids['st1'], 'ent');
scoreCheck(!in_array('group:backend',array_column($revoked,'skillId'),true), 'Revoked group cannot survive via stale projections');
$service->match('ent',$job,$provider);
scoreCheck($calls === 4, 'Revocation invalidates matching cache');
// Recipient consent is rechecked; stale results cannot escape via legacy fallback.
$db->exec("UPDATE privacy_consents SET revokedAt=CURRENT_TIMESTAMP");
scoreCheck($repo->skillsWithDetailsForStudent($ids['st1'],'ent') === [], 'Revoked consent denies enterprise skill reads');
scoreCheck($repo->skillsWithDetailsForStudent($ids['st1'],'other-enterprise') === [], 'Unknown enterprise cannot read scores');
echo "enterprise_teacher_skill_sync_test: OK\n";
