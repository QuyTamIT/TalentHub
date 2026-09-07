<?php
declare(strict_types=1);
require __DIR__.'/learner_ai_grounded_output_integration_test.php';

use TalentHub\Learner\Ai\Consent\{ConsentDecision,ProviderAttemptAuthorizer};
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\{RecommendationContext,RecommendationInput};
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Model\ModelOpportunityMatchEngine;
use TalentHub\Learner\Ai\Persistence\OpportunityMatchRepository;
use TalentHub\Learner\Ai\Provider\{ProviderRequest,ProviderResponse};
use TalentHub\Learner\Ai\Service\OpportunityMatchService;

$projectItem['gap_reasons']=['SQL hiện đạt 45 điểm, thấp hơn yêu cầu 75 điểm.'];
$invalidItem=$projectItem;$invalidItem['why_fit']=str_replace('45 điểm','95 điểm',$projectItem['why_fit']);
$repo=new class implements OpportunityMatchRepository {
    public bool $reused=true; public ?array $cache=null; public int $completed=0;
    public function latestValid(string $id,array $ids):?array{return $this->cache;}
    public function createPendingRun(string $id,RecommendationInput $input,RecommendationContext $context):array{return ['runId'=>'run-1','status'=>'pending','reused'=>$this->reused];}
    public function completeRun(string $id,string $run,array $matches,array $analysis=[],string $state='ready_model'):array{$this->completed++;throw new RuntimeException('synthetic write failure');}
    public function failRun(string $id,string $run,string $code):void{}
};
$provider=new class([$invalidItem,$projectItem]) implements RecommendationProvider {
    public int $calls=0;public array $requests=[];
    public function __construct(private array $items){}
    public function generate(ProviderRequest $r,ProviderAttemptAuthorizer $a):ProviderResponse{$this->calls++;$a->beforeAttempt(1);$this->requests[]=$r;return ProviderResponse::success([array_shift($this->items)]);}
};
$authorizer=new class implements ProviderAttemptAuthorizer {public function beforeAttempt(int $n):ConsentDecision{return new ConsentDecision([],gmdate('c'),ConsentDecision::REQUIRED_SCOPES);}};
$candidateEvidence=['source_type'=>'opportunity','source_id'=>'project-1','safe_value'=>$project->providerPayload()+['item_type'=>'project']];
$service=new OpportunityMatchService($repo,static fn()=>$authorizer->beforeAttempt(1),static fn()=>$input,static fn()=>[$candidateEvidence],static fn()=>new OpportunityScore(['skill_match'=>35,'assessment_alignment'=>15,'experience_relevance'=>10,'growth_potential'=>0,'feasibility'=>10]),new ModelOpportunityMatchEngine($provider,$authorizer));
$errors=[];
$check=static function(bool $ok,string $message)use(&$errors):void{if(!$ok)$errors[]=$message;};
$check($service->generate('student','request','key')['state']==='pending','Reused running request without cache must stay pending.');
$repo->cache=['status'=>'completed','state'=>'ready_model','items'=>[],'analysis'=>['headline'=>'Saved prior analysis']];
$check($service->generate('student','request','key')['state']==='stale_model','Reused pending request must retain cached analysis.');
$check($provider->calls===0,'Reused requests must not duplicate provider calls.');
$repo->reused=false;
$result=$service->generate('student','request','new-key');
$check($provider->calls===2,'Invalid factual output must receive exactly one correction attempt.');
$check(isset($provider->requests[1]->payload()['validation_retry_instruction']),'Retry must contain a trusted fixed correction.');
$check($repo->completed===1,'Only the validated second response may reach persistence.');
$check($result['state']==='stale_model' && ($result['analysis']['headline']??'')==='Saved prior analysis','Persistence failure must retain a valid prior analysis.');
$lowItem=$projectItem;
$lowItem['why_not_fit_yet']=$lowItem['why_fit'];unset($lowItem['why_fit'],$lowItem['expected_outcome_codes']);
$lowItem['missing_conditions']=[];$lowItem['improvement_steps']=['Hãy viết bài truy vấn và nhờ giáo viên góp ý.'];
$lowProvider=new class($lowItem) implements RecommendationProvider {
    public int $calls=0;public function __construct(private array $item){}
    public function generate(ProviderRequest $r,ProviderAttemptAuthorizer $a):ProviderResponse{$a->beforeAttempt(1);$this->calls++;return $this->calls===1?ProviderResponse::success([$this->item]):ProviderResponse::failure('provider_unavailable');}
};
$beforeCompleted=$repo->completed;
$lowService=new OpportunityMatchService($repo,static fn()=>$authorizer->beforeAttempt(1),static fn()=>$input,static fn()=>[$candidateEvidence],static fn()=>new OpportunityScore(['skill_match'=>10,'assessment_alignment'=>0,'experience_relevance'=>0,'growth_potential'=>0,'feasibility'=>10]),new ModelOpportunityMatchEngine($lowProvider,$authorizer));
$lowService->generate('student','request','low-key');
$check($lowProvider->calls===1 && $repo->completed===$beforeCompleted+1,'A validated low-fit analysis must reach persistence without an unnecessary second generation.');
if($errors){fwrite(STDERR,"FAIL\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "learner_ai_matching_recovery_test: OK\n";
