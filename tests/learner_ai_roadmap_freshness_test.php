<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Domain\{RecommendationInput,RecommendationContext,RoadmapAnalysis,RoadmapEditorDraft};
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Service\RoadmapService;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
$input=new RecommendationInput(['skills'=>[['code'=>'sql','score'=>45]]],[],[],[]);
$config=RecommendationConfig::fromEnvironment(['APP_ENV'=>'test','TALENTHUB_AI_ENABLED'=>'true','TALENTHUB_AI_PROVIDER'=>'fake','TALENTHUB_AI_MODEL'=>'fake','TALENTHUB_AI_API_URL'=>'http://127.0.0.1:20128','TALENTHUB_AI_API_KEY'=>'x','TALENTHUB_AI_ALLOWED_HOSTS'=>'127.0.0.1']);
$repo=new class implements RoadmapRepository {
    public array $active=[];public array $signals=[];
    public function saveCompleted(string $s,string $r,RoadmapAnalysis $a,array $audit):array{return $this->active;}
    public function latestForStudent(string $s):?array{return $this->active;}
    public function latestPendingForStudent(string $s):?array{return null;}
    public function historyForStudent(string $s):array{return [];}
    public function versionForStudent(string $s,int $v):?array{return $this->active;}
    public function appendTaskEvent(string $s,string $t,string $status,string $r):array{return [];}
    public function appendRoadmapFeedback(string $s,string $id,string $v,string $c,string $r):array{return [];}
    public function feedbackSignalsForStudent(string $s):array{return $this->signals;}
    public function storeRefinementPreview(string $s,string $id,int $v,RoadmapEditorDraft $d,RoadmapEditorDraft $a,array $audit):array{return [];}
    public function refinementPreview(string $s,string $id):?array{return null;}
    public function applyCustomization(string $s,string $id,int $v,string $source,RoadmapEditorDraft $d,?array $r,string $request):array{return [];}
};
$engine=new class implements RoadmapEngine {public int $calls=0;public function generate(RecommendationInput $i,RecommendationContext $c):RoadmapAnalysis{$this->calls++;throw new RuntimeException('synthetic provider failure');}};
$service=new RoadmapService($repo,$engine,static fn()=>true,static fn()=>['assessment','skills','activity','evaluation'],static fn()=>$input,static fn()=>new DataQualityResult('ready'),static fn()=>['runId'=>'run','status'=>'pending'],static fn()=>null,static fn()=>null,$engine,$config,new AiAvailabilityPolicy());
$current=['roadmap_id'=>'roadmap-1','version'=>2,'analysis_origin'=>'model','executive_summary'=>'Lộ trình đã lưu.','input_hash'=>$input->contentHash(),'engine'=>['provider'=>$config->provider(),'model_version'=>$config->model(),'prompt_version'=>RoadmapPromptRegistry::VERSION]];
$errors=[];$check=static function(bool $ok,string $m)use(&$errors):void{if(!$ok)$errors[]=$m;};
$repo->active=$current;
$check($service->latest('student')['state']==='ready_model','Current roadmap must remain fresh.');
$reused=$service->generate('student','request','manual-refresh',true,beforeGenerate:static fn()=>throw new RuntimeException('Unchanged roadmap must not consume generation rate limit'));
$check(($reused['reuse_reason']??'')==='inputs_unchanged'&&($reused['data_changed']??null)===false&&$engine->calls===0,'Manual refresh with identical data must reuse and avoid a provider call.');
$repo->active['freshness_status']='stale_model';
$check($service->generate('student','request','recheck-same-data',true)['state']==='ready_model','A current hash and engine must clear a historical stale marker in the response.');
foreach(['provider','model_version','prompt_version'] as $field){$repo->active=$current;$repo->active['engine'][$field]='old';$check($service->latest('student')['state']==='stale_model','Old '.$field.' must be stale.');}
$repo->active=$current;$repo->active['input_hash']=str_repeat('0',64);
$check($service->latest('student')['state']==='stale_model','Changed learner evidence must make roadmap stale.');
$repo->active=$current;$repo->signals=[['verdict'=>'not_helpful','reason_code'=>'not_relevant','count'=>1]];
$check($service->latest('student')['state']==='stale_model','New learner feedback must invalidate the prior roadmap snapshot.');
$repo->signals=[];$repo->active=$current;$repo->active['engine']['prompt_version']='old';
$result=$service->generate('student','request','new-key');
$check($engine->calls===1,'Matching input hash must not reuse an outdated prompt version.');
$check($result['state']==='stale_model'&&$result['executive_summary']===$current['executive_summary'],'Failed refresh must retain the original content.');
if($errors){fwrite(STDERR,"FAIL\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "learner_ai_roadmap_freshness_test: OK\n";
