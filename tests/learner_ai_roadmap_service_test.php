<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Quality\RoadmapQualityGate;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Service\RoadmapService;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function roadmap_service_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function roadmap_service_input(string $hashSeed = 'a', array $missingConsent = ['activity','evaluation','skills']): RecommendationInput
{
    $records = $evidence = [];
    foreach (['holland','mbti','disc','multiple_intelligence'] as $index => $code) {
        $record = ['test_type'=>$code,'result_code'=>strtoupper(substr($code,0,3)),'dimension_scores'=>['A'=>70+$index],'submitted_at'=>'2026-08-20T00:00:00+00:00','seed'=>$hashSeed];
        $records[] = $record;
        $evidence[] = ['source_type'=>'assessment','source_id'=>sprintf('%s0000000-0000-4000-8000-%012d',$hashSeed,$index+1),'observed_at'=>$record['submitted_at'],'safe_value'=>$record];
    }
    return new RecommendationInput(['profile'=>['study_status'=>'active'],'assessments'=>$records,'skills'=>[],'activities'=>[],'evaluations'=>[],'opportunities'=>[]], ['assessment'=>'2026-08-20T00:00:00+00:00'], ['allowed_scopes'=>array_values(array_diff(['activity','assessment','evaluation','skills'],$missingConsent)),'missing_consent_scopes'=>$missingConsent,'source_counts'=>['assessments'=>4]], $evidence);
}

function roadmap_service_consent(bool $assessment = true): ConsentDecision
{
    return new ConsentDecision($assessment ? ['assessment'=>['action'=>'granted','policy_version'=>'v1','occurred_at'=>'2026-08-24T00:00:00+00:00','request_id'=>'consent']] : [], '2026-08-24T00:01:00+00:00');
}

function roadmap_service_model(RecommendationInput $input): RoadmapAnalysis
{
    $ids=[]; foreach(array_keys($input->evidenceReferences()) as $index) $ids[]=sprintf('evidence-%03d',$index+1);
    return (new RoadmapAnalysisValidator($ids, []))->fromProviderPayload(learner_ai_roadmap_provider_fixture(), ['origin'=>'model','provider'=>'9router_gemini','model_version'=>'model-test','prompt_version'=>'learner-roadmap-prompt-1.0.0','confidence_band'=>'high','provider_request_id'=>'router_service','response_hash'=>str_repeat('a',64)]);
}

function roadmap_service_repository(?array $latest = null, ?array $pending = null, array $signals = []): RoadmapRepository
{
    return new class($latest, $pending, $signals) implements RoadmapRepository {
        public int $saveCalls=0; public array $events=[];
        public function __construct(public ?array $latest, public ?array $pending, public array $signals) {}
        public function saveCompleted(string $studentId,string $runId,RoadmapAnalysis $analysis,array $providerAudit): array {
            $this->saveCalls++; $this->latest=['roadmap_id'=>'roadmap-'.$this->saveCalls,'run_id'=>$runId,'version'=>$this->saveCalls,'status'=>'active','analysis_origin'=>$analysis->origin(),'executive_summary'=>$analysis->executiveSummary(),'confidence_band'=>$analysis->confidenceBand(),'primary_direction'=>$analysis->primaryDirection()->toArray(),'alternative_directions'=>array_map(fn($x)=>$x->toArray(),$analysis->alternativeDirections()),'insights'=>array_map(fn($x)=>$x->toArray(),$analysis->insights()),'evidence_summary'=>['assessment_count'=>4],'generated_at'=>'2026-08-24','engine'=>$analysis->engineMetadata(),'phases'=>array_map(fn($x)=>$x->toArray(),$analysis->phases()),'progress'=>['completed_tasks'=>0,'total_tasks'=>9],'input_hash'=>$providerAudit['input_hash']??null]; return $this->latest;
        }
        public function latestForStudent(string $studentId): ?array { return $this->latest; }
        public function latestPendingForStudent(string $studentId): ?array { return $this->pending; }
        public function historyForStudent(string $studentId): array { return $this->latest === null ? [] : [['roadmap_id'=>$this->latest['roadmap_id'],'version'=>$this->latest['version']??1,'changed_sections'=>[]]]; }
        public function versionForStudent(string $studentId,int $version): ?array { return (($this->latest['version']??null)===$version) ? $this->latest : null; }
        public function appendTaskEvent(string $studentId,string $taskId,string $status,string $requestId): array { $event=['event_id'=>'event-1','task_id'=>$taskId,'student_id'=>$studentId,'status'=>$status,'request_id'=>$requestId,'reused'=>false]; $this->events[]=$event; return $event; }
        public function appendRoadmapFeedback(string $studentId,string $roadmapId,string $verdict,string $reasonCode,string $requestId): array { return ['state'=>'feedback_saved','roadmap_id'=>$roadmapId]; }
        public function feedbackSignalsForStudent(string $studentId): array { return $this->signals; }
    };
}

function roadmap_service_engine(RoadmapAnalysis $analysis): RoadmapEngine
{
    return new class($analysis) implements RoadmapEngine { public int $calls=0; public ?RecommendationInput $lastInput=null; public function __construct(private RoadmapAnalysis $analysis){} public function generate(RecommendationInput $input,RecommendationContext $context):RoadmapAnalysis{$this->calls++;$this->lastInput=$input;return $this->analysis;} };
}

function roadmap_service_build(RoadmapRepository $repository,RoadmapEngine $engine,RecommendationInput $input,ConsentDecision $consent,bool $authorized=true,bool $pendingReused=false): RoadmapService
{
    return new RoadmapService(
        $repository,$engine,
        static fn(string $studentId):bool=>$authorized,
        static fn(string $studentId):ConsentDecision=>$consent,
        static fn(string $studentId,array $scopes):RecommendationInput=>$input,
        static fn(RecommendationInput $value)=>(new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00')))->evaluate($value),
        static fn(string $studentId,RecommendationInput $value,RecommendationContext $context):array=>['runId'=>'run-service','snapshotId'=>'snapshot-service','status'=>'pending','reused'=>$pendingReused],
        static fn(string $studentId,string $runId,RoadmapAnalysis $analysis):array=>['runId'=>$runId,'status'=>$analysis->origin()==='model'?'completed':'fallback'],
        static function(string $studentId,string $runId,string $code):void {},
    );
}

roadmap_service_assert(class_exists(RoadmapService::class), 'roadmap service is loaded');
$roadmapConfig=RecommendationConfig::fromEnvironment(['APP_ENV'=>'test','TALENTHUB_AI_ENABLED'=>'true','TALENTHUB_AI_PROVIDER'=>'9router','TALENTHUB_AI_MODEL'=>'model-test','TALENTHUB_AI_API_URL'=>'http://127.0.0.1:20128/v1/chat/completions','TALENTHUB_AI_API_KEY'=>'test-key','TALENTHUB_AI_ALLOWED_HOSTS'=>'127.0.0.1','TALENTHUB_AI_ROADMAP_TIMEOUT_SECONDS'=>'30','TALENTHUB_AI_ROADMAP_PER_STUDENT_LIMIT'=>'2','TALENTHUB_AI_ROADMAP_GLOBAL_LIMIT'=>'20']);
roadmap_service_assert($roadmapConfig->roadmapTimeoutSeconds()===30 && $roadmapConfig->roadmapPerStudentLimit()===2 && $roadmapConfig->roadmapGlobalLimit()===20,'roadmap provider limits are explicit and non-secret');
$inputA=roadmap_service_input('a'); $modelA=roadmap_service_model($inputA);
$pendingLatest = roadmap_service_build(roadmap_service_repository(null, ['state'=>'pending','started_at'=>'2026-08-24T00:00:00Z']),roadmap_service_engine($modelA),$inputA,roadmap_service_consent())->latest('student-a');
roadmap_service_assert(($pendingLatest['state'] ?? null)==='pending' && isset($pendingLatest['started_at']),'latest exposes a roadmap-specific pending run');
$forbidden=roadmap_service_build(roadmap_service_repository(),roadmap_service_engine($modelA),$inputA,roadmap_service_consent(),false)->generate('student-a','request-a','idempotency-key-a');
roadmap_service_assert($forbidden['state']==='forbidden','owner authorization runs first');

$noConsentInput=roadmap_service_input('a',['activity','assessment','evaluation','skills']);
$noConsent=roadmap_service_build(roadmap_service_repository(),roadmap_service_engine($modelA),$noConsentInput,roadmap_service_consent(false))->generate('student-a','request-a','idempotency-key-a');
roadmap_service_assert($noConsent['state']==='consent_required' && $noConsent['missing_consent_scopes']===['assessment'],'missing assessment consent is explicit');

$repository=roadmap_service_repository(); $engine=roadmap_service_engine($modelA);
$ready=roadmap_service_build($repository,$engine,$inputA,roadmap_service_consent())->generate('student-a','request-a','idempotency-key-a');
roadmap_service_assert($ready['state']==='ready_model' && $engine->calls===1 && $repository->saveCalls===1,'four assessments generate and persist a real model roadmap');
foreach (['input_hash','provider_request_id','response_hash','raw_snapshot','api_url','run_id'] as $privateField) {
    roadmap_service_assert(!array_key_exists($privateField, $ready), "public roadmap response strips {$privateField}");
}

$preferenceRepository=roadmap_service_repository(null,null,[['verdict'=>'not_helpful','reason_code'=>'too_generic','count'=>2]]);
$preferenceEngine=roadmap_service_engine($modelA);
roadmap_service_build($preferenceRepository,$preferenceEngine,$inputA,roadmap_service_consent())->generate('student-a','request-pref','idempotency-pref');
roadmap_service_assert(($preferenceEngine->lastInput?->payload()['preference_signals'] ?? null) == [['verdict'=>'not_helpful','reason_code'=>'too_generic','count'=>2]], 'aggregate allowlisted feedback is part of the next immutable roadmap snapshot');

$sameRepository=roadmap_service_repository(array_merge($repository->latest,['input_hash'=>$inputA->contentHash()])); $sameEngine=roadmap_service_engine($modelA);
$same=roadmap_service_build($sameRepository,$sameEngine,$inputA,roadmap_service_consent())->generate('student-a','request-b','different-idempotency');
roadmap_service_assert($same['state']==='ready_model' && $same['reused']===true && $sameEngine->calls===0,'unchanged source snapshot reuses active roadmap');

$fallbackRoadmap = array_merge($repository->latest, [
    'analysis_origin' => 'rule_fallback',
    'engine' => ['rule_version' => 'learner-roadmap-rules-1', 'fallback_reason' => 'rule_only'],
    'input_hash' => $inputA->contentHash(),
]);
$retryRepository = roadmap_service_repository($fallbackRoadmap);
$retryEngine = roadmap_service_engine($modelA);
$retried = roadmap_service_build($retryRepository, $retryEngine, $inputA, roadmap_service_consent())
    ->generate('student-a', 'request-fallback-retry', 'idempotency-fallback-retry', true);
roadmap_service_assert(
    $retried['state'] === 'ready_model' && $retryEngine->calls === 1 && $retryRepository->saveCalls === 1,
    'an explicit refresh replaces an unchanged saved rule fallback with a real AI roadmap',
);

$pendingEngine=roadmap_service_engine($modelA);
$pending=roadmap_service_build(roadmap_service_repository(),$pendingEngine,$inputA,roadmap_service_consent(),true,true)->generate('student-a','request-p','idempotency-pending');
roadmap_service_assert($pending['state']==='pending' && $pending['reused']===true && $pendingEngine->calls===0,'in-flight idempotent run is reused');

$prior=array_merge($repository->latest,['input_hash'=>'older-hash']);
$fallback=(new RuleRoadmapEngine())->generate($inputA,new RecommendationContext(['assessment'],null,null,'student-a'))->withFallbackReason('provider_unavailable');
$fallbackEngine=roadmap_service_engine($fallback); $retainedRepo=roadmap_service_repository($prior);
$retained=roadmap_service_build($retainedRepo,$fallbackEngine,$inputA,roadmap_service_consent())->generate('student-a','request-c','idempotency-refresh');
roadmap_service_assert($retained['roadmap_id']===$prior['roadmap_id'] && $retained['refresh_state']==='fallback_not_applied' && $retainedRepo->saveCalls===0,'provider failure retains last completed roadmap');

$rateFallback=(new RuleRoadmapEngine())->generate($inputA,new RecommendationContext(['assessment'],null,null,'student-a'))->withFallbackReason('rate_limited');
$rate=roadmap_service_build(roadmap_service_repository($prior),roadmap_service_engine($rateFallback),$inputA,roadmap_service_consent())->generate('student-a','request-rate','idempotency-rate');
roadmap_service_assert($rate['fallback_reason']==='rate_limited' && $rate['roadmap_id']===$prior['roadmap_id'],'rate limiting also retains the active roadmap');

$event=roadmap_service_build($repository,$engine,$inputA,roadmap_service_consent())->updateTask('student-a','task-1','completed','task-request-1');
roadmap_service_assert($event['state']==='task_updated' && $event['status']==='completed','task progress is mapped');
roadmap_service_assert(!isset($event['student_id'], $event['request_id']), 'public task response strips owner and idempotency internals');

echo "learner_ai_roadmap_service_test: OK\n";
