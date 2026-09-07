<?php
declare(strict_types=1);
require __DIR__.'/learner_ai_grounded_output_integration_test.php';
use TalentHub\Learner\Ai\Snapshot\MatchingInputSnapshot;
use TalentHub\Learner\Ai\Domain\{RecommendationInput,RecommendationContext};
use TalentHub\Learner\Ai\Persistence\{JobMatchRepository,OpportunityMatchRepository};
use TalentHub\Learner\Ai\Service\{JobMatchingService,OpportunityMatchService};
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Matching\StructuredOpportunityScorer;

$errors=[];$check=static function(bool $ok,string $message)use(&$errors):void{if(!$ok)$errors[]=$message;};
$jobInput=MatchingInputSnapshot::build($input,[$job],[$role]);
$projectInput=MatchingInputSnapshot::build($input,[$project]);
$check($jobInput->contentHash()!==MatchingInputSnapshot::build($input,[$job,$other],[$role])->contentHash(),'New unselected jobs invalidate cached ranking.');
$check($jobInput->contentHash()!==MatchingInputSnapshot::build($input,[],[$role])->contentHash(),'Removed or expired offers invalidate matching.');
$check($jobInput->contentHash()!==MatchingInputSnapshot::build($input,[$job],[])->contentHash(),'Changed career benchmarks invalidate matching.');
$check(MatchingInputSnapshot::build($input,[$job,$other],[$role])->contentHash()===MatchingInputSnapshot::build($input,[$other,$job],[$role])->contentHash(),'Catalog query order must not invalidate identical data.');
$repo=new class implements JobMatchRepository {
    public array $cache=[];public int $created=0;
    public function latestValid(string $id,array $ids):?array{return $this->cache;}
    public function createPendingRun(string $id,RecommendationInput $i,RecommendationContext $c):array{$this->created++;throw new RuntimeException('No new run expected');}
    public function completeRun(string $s,string $r,array $records,array $a=[]):array{return [];}
    public function failRun(string $s,string $r,string $code):void{}
};
$repo->cache=['status'=>'completed','generationCurrent'=>true,'inputHash'=>$jobInput->contentHash(),'state'=>'no_matching_jobs','items'=>[['catalogId'=>'job-1','matchScore'=>$match->score()->totalScore(),'analysis'=>['analysis'=>['analysis'=>$item['analysis']]]]]];
$jobEvidence=['source_type'=>'opportunity','source_id'=>'job-1','safe_value'=>$job->providerPayload()+['item_type'=>'internship']];
$decision=static fn()=>new ConsentDecision([],gmdate('c'),ConsentDecision::REQUIRED_SCOPES);
$service=new JobMatchingService($repo,$decision,static fn()=>$input,static fn()=>[$jobEvidence],static fn()=>['status'=>'ok','roles'=>[$role]],null,static fn()=>[]);
$charge=static fn()=>throw new RuntimeException('Cache hit must not consume generation rate limit');
$result=$service->generate('student','req','new-key',$charge);
$check(($result['reuse_reason']??'')==='inputs_unchanged'&&($result['data_changed']??null)===false,'Unchanged job data must reuse despite new request key, even if provider is offline.');
$check(($result['near_match']['analysis']??'')===$item['analysis'],'Reuse must preserve the exact saved prose.');
$check($repo->created===0,'Cache hit must not create a run.');
$repo->cache['generationCurrent']=false;
$check(!isset($service->generate('student','req','next-key')['reuse_reason']),'Old model/prompt must not be called unchanged.');

$pr=new class implements OpportunityMatchRepository {
    public array $cache=[];public int $created=0;
    public function latestValid(string $id,array $ids):?array{return $this->cache;}
    public function createPendingRun(string $id,RecommendationInput $i,RecommendationContext $c):array{$this->created++;throw new RuntimeException('No new run expected');}
    public function completeRun(string $s,string $r,array $m,array $a=[],string $state='ready_model'):array{return [];}
    public function failRun(string $s,string $r,string $code):void{}
};
$pr->cache=['status'=>'completed','generationCurrent'=>true,'inputHash'=>$projectInput->contentHash(),'state'=>'no_fit_model','items'=>[],'analysis'=>['headline'=>'Kết quả cũ','explanation'=>$item['analysis']]];
$projectEvidence=['source_type'=>'opportunity','source_id'=>'project-1','safe_value'=>$project->providerPayload()+['item_type'=>'project']];
$source=[$projectEvidence];$learner=$input;
$ps=new OpportunityMatchService($pr,$decision,static function()use(&$learner){return $learner;},static function()use(&$source){return $source;},static fn($p,$c)=>(new StructuredOpportunityScorer())->score($p,$c),null);
$result=$ps->generate('student','req','new-project-key',$charge);
$check(($result['reuse_reason']??'')==='inputs_unchanged'&&($result['analysis']['explanation']??'')===$item['analysis'],'Unchanged no-fit project summary must reuse exactly.');
$check($pr->created===0,'Unchanged project must not create a run.');
$source[0]['safe_value']['required_skills'][0]['minimum_score']=90;
$check(!isset($ps->generate('student','req','changed-target')['reuse_reason']),'Changed requirement must invalidate project cache.');
$source=[$projectEvidence];$source[0]['safe_value']['provider_name']='Đơn vị đã đổi tên';
$check(!isset($ps->generate('student','req','changed-provider')['reuse_reason']),'Provider-name-only change must invalidate prose, even though snapshot privacy filtering strips raw names.');
$source=[$projectEvidence,['source_type'=>'opportunity','source_id'=>'project-2','safe_value'=>array_replace($projectEvidence['safe_value'],['catalog_id'=>'project-2'])]];
$check(!isset($ps->generate('student','req','new-project')['reuse_reason']),'A newly eligible project must invalidate matching even when the saved project remains active.');
$source=[$projectEvidence];$learner=new RecommendationInput(['skills'=>[['code'=>'sql','score'=>55]]],[],[],[]);
$check(!isset($ps->generate('student','req','changed-student')['reuse_reason']),'Changed student must invalidate project cache.');
if($errors){fwrite(STDERR,"FAIL\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "learner_ai_unchanged_input_test: OK\n";
