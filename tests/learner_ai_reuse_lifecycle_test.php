<?php
declare(strict_types=1);
require __DIR__.'/learner_ai_grounded_output_integration_test.php';
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Consent\{ProviderAttemptAuthorizer,ConsentDecision};
use TalentHub\Learner\Ai\Provider\{ProviderRequest,ProviderResponse};
use TalentHub\Learner\Ai\Domain\{RecommendationInput,RecommendationContext};
use TalentHub\Learner\Ai\Persistence\OpportunityMatchRepository;
use TalentHub\Learner\Ai\Model\ModelOpportunityMatchEngine;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Service\OpportunityMatchService;

$projectItem['gap_reasons']=['SQL hiện đạt 45 điểm, thấp hơn yêu cầu 75 điểm.'];
$provider=new class($projectItem) implements RecommendationProvider {
    public int $calls=0;
    public function __construct(private array $item){}
    public function generate(ProviderRequest $r,ProviderAttemptAuthorizer $a):ProviderResponse{$this->calls++;$a->beforeAttempt(1);return ProviderResponse::success([$this->item]);}
};
$auth=new class implements ProviderAttemptAuthorizer {public function beforeAttempt(int $n):ConsentDecision{return new ConsentDecision([],gmdate('c'),ConsentDecision::REQUIRED_SCOPES);}};
$repo=new class implements OpportunityMatchRepository {
    public ?array $cache=null;public int $created=0;private string $hash='';
    public function latestValid(string $s,array $ids):?array{return $this->cache;}
    public function createPendingRun(string $s,RecommendationInput $i,RecommendationContext $c):array{$this->hash=$i->contentHash();$this->created++;return ['runId'=>'run','status'=>'pending'];}
    public function completeRun(string $s,string $r,array $m,array $a=[],string $state='ready_model'):array{
        return $this->cache=['status'=>'completed','generationCurrent'=>true,'inputHash'=>$this->hash,'state'=>$state,'analysis'=>$a,'items'=>array_map(static fn($x)=>['catalogId'=>$x->candidate()->catalogId(),'rankPosition'=>1,'matchScore'=>$x->score()->finalScore(),'analysisJson'=>json_encode(['why_fit'=>$x->whyFit(),'fit_reasons'=>$x->fitReasons(),'gap_reasons'=>$x->gapReasons(),'skills_to_develop'=>$x->skillsToDevelop()])],$m)];
    }
    public function failRun(string $s,string $r,string $c):void{}
};
$evidence=['source_type'=>'opportunity','source_id'=>'project-1','safe_value'=>$project->providerPayload()+['item_type'=>'project']];
$source=[$evidence];$charges=0;
$service=new OpportunityMatchService($repo,static fn()=>$auth->beforeAttempt(1),static fn()=>$input,static function()use(&$source){return $source;},static fn()=>new OpportunityScore(['skill_match'=>35,'assessment_alignment'=>15,'experience_relevance'=>10,'growth_potential'=>0,'feasibility'=>10]),new ModelOpportunityMatchEngine($provider,$auth));
$charge=static function()use(&$charges):void{$charges++;};
$first=$service->generate('student','request','first',$charge);
$check=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$check($provider->calls===1&&$repo->created===1,'First generation must create and save a validated model result.');
$start=hrtime(true);
for($i=0;$i<50;$i++){
    $cached=$service->generate('student','request','new-key-'.$i,$charge);
    $check(($cached['reuse_reason']??'')==='inputs_unchanged'&&$cached['items']===$first['items'],'Repeated clicks must preserve exact content.');
}
$check($provider->calls===1&&$repo->created===1&&$charges===1,'50 repeated requests must produce zero additional provider calls, runs, or generation-limit charges.');
$check($service->latest('student')['state']==='partial_model','Freshness reads must use the same matching snapshot as generation.');
$source[0]['safe_value']['summary']='Mô tả cơ hội đã được cập nhật.';
$check($service->latest('student')['state']==='stale_model','Changed opportunity descriptions must invalidate latest even when scores are unchanged.');
$service->generate('student','request','changed',$charge);
$check($provider->calls===2&&$repo->created===2&&$charges===2,'Changed catalog must trigger a new analysis.');
echo "learner_ai_reuse_lifecycle_test: OK (50 cache hits, zero extra Gemini calls; in-memory repositories)\n";
