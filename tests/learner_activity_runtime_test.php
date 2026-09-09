<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\ModelActivityMatchEngine;
use TalentHub\Learner\Ai\Service\ActivityMatchService;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo '[PASS] '.$message.PHP_EOL; }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
    'CREATE TABLE schools(id TEXT,name TEXT)', 'CREATE TABLE classes(id TEXT,schoolId TEXT)',
    'CREATE TABLE student_profiles(id TEXT,classId TEXT)', 'CREATE TABLE skills(code TEXT,status TEXT)',
    'CREATE TABLE activities(id TEXT,schoolId TEXT,createdByTeacherId TEXT,title TEXT,category TEXT,startAt TEXT,endAt TEXT,createdAt TEXT,capacity INT,status TEXT,visibility TEXT)',
    'CREATE TABLE activity_details(activityId TEXT,skillTags TEXT)',
    'CREATE TABLE activity_registrations(id TEXT,studentId TEXT,activityId TEXT,status TEXT)',
] as $sql) $pdo->exec($sql);
$migration = require dirname(__DIR__).'/Database/migrations/learner/020_create_activity_match_runs.php';
foreach ($migration->migration->statements('sqlite') as $sql) $pdo->exec($sql);
$student = '00000000-0000-4000-8000-000000000001';
$school = '00000000-0000-4000-8000-000000000002';
$activity = '00000000-0000-4000-8000-000000000003';
$other = '00000000-0000-4000-8000-000000000004';
$pdo->exec("INSERT INTO schools VALUES ('$school','Fixture'),('$other','Other'); INSERT INTO classes VALUES ('class','$school'); INSERT INTO student_profiles VALUES ('$student','class'); INSERT INTO skills VALUES ('teamwork','active')");
$insert = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?,?,?,?,?,?)');
foreach ([$activity=>$school, $other=>$other] as $id=>$schoolId) {
    $insert->execute([$id,$schoolId,null,'Fixture workshop','workshop',gmdate('Y-m-d H:i:s',time()+86400),gmdate('Y-m-d H:i:s',time()+90000),gmdate('Y-m-d H:i:s',time()-86400),10,'published','public']);
    $pdo->prepare('INSERT INTO activity_details VALUES (?,?)')->execute([$id,'["teamwork"]']);
}
$score = 40;
$snapshot = static function ($id) use (&$score): RecommendationInput { return new RecommendationInput(['skills'=>[['code'=>'teamwork','score'=>$score]]], [], [], []); };
$scopes = ConsentDecision::REQUIRED_SCOPES;
$provider = new class implements RecommendationProvider {
    public int $calls = 0;
    public ?Closure $duringCall = null;
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse {
        $authorizer->beforeAttempt(1); $this->calls++;
        $rows = [];
        foreach ($request->payload()['input']['candidates'] as $c) $rows[] = ['activity_id'=>$c['activity_id'],'analysis'=>'Hoạt động này có thể giúp bạn rèn luyện teamwork theo mục tiêu nhỏ. Hãy trao đổi nhiệm vụ với nhóm và xin góp ý để theo dõi sự tiến bộ.','evidence_ref_ids'=>['activity:'.$c['activity_id'],'skill:teamwork']];
        if ($this->duringCall) ($this->duringCall)();
        return ProviderResponse::success($rows);
    }
};
$auth = new class implements ProviderAttemptAuthorizer { public function beforeAttempt(int $attemptNumber): ConsentDecision { return new ConsentDecision([], 'now', ConsentDecision::REQUIRED_SCOPES); } };
$service = new ActivityMatchService($pdo, $snapshot, static function ($id) use (&$scopes) { return $scopes; }, new ModelActivityMatchEngine($provider,$auth));
$result = $service->generate($student);
check($result['state']==='completed' && count($result['items'])===1 && $result['items'][0]['activity_id']===$activity, 'real SQL eligibility excludes cross-school public activity');
check($service->latest($student)['state']==='completed', 'persisted result reloads');
$score = 60;
check($service->latest($student)['state']==='stale_model', 'new learner skill score makes saved analysis stale');
$result2 = $service->generate($student);
check($result2['items'][0]['score']!==$result['items'][0]['score'] && $provider->calls===2, 'fresh learner data changes score and calls model');
check($service->latest($student)['state']==='completed', 'rapid successive generations load newest run');
$pdo->exec("UPDATE activities SET title='Changed title' WHERE id='$activity'");
check($service->latest($student)['state']==='stale_model', 'activity change invalidates snapshot');
$provider->duringCall = static function () use (&$score) { $score = 70; };
$count = (int)$pdo->query('SELECT COUNT(*) FROM learner_activity_match_runs')->fetchColumn();
check($service->generate($student)['state']==='stale_model' && (int)$pdo->query('SELECT COUNT(*) FROM learner_activity_match_runs')->fetchColumn()===$count, 'data changed during provider call is not persisted');
$scopes=[];
check($service->latest($student)['state']==='consent_required', 'revoked scope hides saved analyses');
$scopes=ConsentDecision::REQUIRED_SCOPES;
$pdo->exec("UPDATE activities SET capacity=0 WHERE id='$activity'");
check($service->latest($student)['state']==='no_matches', 'full activity disappears from latest results');
echo "[OK] Isolated SQLite fixtures only; no real learner data modified or transmitted\n";
