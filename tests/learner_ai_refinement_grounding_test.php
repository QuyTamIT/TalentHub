<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapEditorDraft;
use TalentHub\Learner\Ai\Model\ModelRoadmapRefinementEngine;
use TalentHub\Learner\Ai\Model\RoadmapRefinementPromptRegistry;
use TalentHub\Learner\Ai\Model\RoadmapRefinementUnavailable;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\RoadmapProviderResponse;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Sources\ConsentSource;

$failures=[]; $assert=static function(bool $ok,string $m)use(&$failures):void{if(!$ok)$failures[]=$m;};
$draftArray=['phases'=>[]];
foreach ([[1,0,30],[2,31,60],[3,61,90]] as $i=>$range) {
    $n=$i+1; $draftArray['phases'][]=['phase_id'=>sprintf('10000000-0000-4000-8000-%012d',$n),'position'=>$n,'start_day'=>$range[1],'end_day'=>$range[2],'code'=>'phase_'.$n,'title'=>'Chặng '.$n,'goal'=>'Luyện tập Python','skill_focus'=>'python','deliverable'=>'Bài thực hành','effort_label'=>'30 phút','metric_label'=>'Hoàn thành một bài','tasks'=>[['task_id'=>sprintf('20000000-0000-4000-8000-%012d',$n),'position'=>1,'title'=>'Bài '.$n,'description'=>'Viết một bài Python nhỏ.','milestone_day'=>$range[1]===0?1:$range[1],'estimated_minutes'=>30]]];
}
$draft=RoadmapEditorDraft::fromArray($draftArray);
$input=new RecommendationInput(['skills'=>[['code'=>'python','score'=>60]]],[],[],[['source_type'=>'skill','source_id'=>'s1','observed_at'=>null,'safe_value'=>['code'=>'python','score'=>60]]]);
$source=new class implements ConsentSource { public int $calls=0; public function forStudent(string $id):array{$this->calls++;$out=[];foreach(ConsentDecision::REQUIRED_SCOPES as $s)$out[]=['scope'=>$s,'action'=>'granted','policy_version'=>'p1','occurred_at'=>'2026-09-05T00:00:00Z','request_id'=>'r-'.$s];return $out;} };
$policy=new ConsentPolicy($source,static fn()=>'2026-09-05T00:00:01Z'); $decision=$policy->decision('student-1');
$context=new RecommendationContext($decision->allowedScopes(),'req','idem','student-1',$decision->decisionHash(),$decision->policyVersion());
$config=RecommendationConfig::fromEnvironment(['TALENTHUB_AI_ENABLED'=>'true','TALENTHUB_AI_PROVIDER'=>'fake','TALENTHUB_AI_MODEL'=>'fake','TALENTHUB_AI_API_URL'=>'http://127.0.0.1:20128','TALENTHUB_AI_API_KEY'=>'x','TALENTHUB_AI_ALLOWED_HOSTS'=>'127.0.0.1','TALENTHUB_AI_MAX_ATTEMPTS'=>'2','APP_ENV'=>'test']);
$makeEngine=static function(RoadmapProvider $provider)use($policy,$config):ModelRoadmapRefinementEngine{return new ModelRoadmapRefinementEngine($provider,new RoadmapRefinementPromptRegistry(),new RecommendationRateLimiter(20,20,60,static fn()=>1),$config,new ProviderConsentGate($policy));};
$invalid=$draftArray; $invalid['phases'][0]['goal']='Bạn đã từng đoạt giải quốc gia và thành thạo Python.'; $invalid['phases'][0]['invented_biography']='Giải quốc gia';
$provider=new class([$invalid,$draftArray]) implements RoadmapProvider {public int $calls=0;public array $requests=[];public function __construct(private array $payloads){}public function generate(ProviderRequest $r,$a):RoadmapProviderResponse{$this->calls++;$a->beforeAttempt($this->calls);$this->requests[]=$r;return RoadmapProviderResponse::success(array_shift($this->payloads),'p'.$this->calls,str_repeat((string)$this->calls,64));}};
$result=$makeEngine($provider)->refine($draft,$input,$context);
$assert($provider->calls===2 && $result['draft']->toArray()===$draftArray,'Invalid grounded prose must retry once and accept a valid same-structure draft.');
$assert(isset($provider->requests[1]->payload()['validation_retry_instruction']),'Retry request must contain a safe correction instruction.');
$assert(in_array($provider->requests[1]->payload()['validation_retry_instruction'] ?? '', $provider->requests[1]->payload()['instructions'] ?? [], true),'Trusted correction must be delivered as a system instruction, outside untrusted evidence.');
$assert(!str_contains((string)$provider->requests[1]->payload()['validation_retry_instruction'],'giải quốc gia'),'Retry instruction must not echo invalid provider output.');
$assert($provider->requests[0]->evidenceReferenceIds()===$provider->requests[1]->evidenceReferenceIds(),'Retry must retain evidence authorization metadata.');
$evidenceId=$provider->requests[0]->evidenceReferenceIds()[0];
$assert($provider->requests[0]->evidence($evidenceId)===$provider->requests[1]->evidence($evidenceId),'Retry must retain exact evidence metadata objects.');
$assert($source->calls===3,'Consent must be read initially and freshly before each of two provider calls.');

$always=new class($invalid) implements RoadmapProvider {public int $calls=0;public function __construct(private array $p){}public function generate(ProviderRequest $r,$a):RoadmapProviderResponse{$this->calls++;$a->beforeAttempt($this->calls);return RoadmapProviderResponse::success($this->p,null,str_repeat('a',64));}};
try{$makeEngine($always)->refine($draft,$input,$context);$failures[]='Persistent invalid output must fail.';}catch(RoadmapRefinementUnavailable $e){$assert($e->reason()==='invalid_refinement_contract'&&$always->calls===2,'Persistent invalid output must stop after two attempts.');}
$failed=new class implements RoadmapProvider {public int $calls=0;public function generate(ProviderRequest $r,$a):RoadmapProviderResponse{$this->calls++;$a->beforeAttempt(1);return RoadmapProviderResponse::failure('provider_unavailable');}};
try{$makeEngine($failed)->refine($draft,$input,$context);$failures[]='Provider failure must fail.';}catch(RoadmapRefinementUnavailable $e){$assert($e->reason()==='provider_unavailable'&&$failed->calls===1,'Provider failure must not trigger validation retry.');}
if($failures){fwrite(STDERR,"FAIL\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "OK refinement grounding retry\n";
