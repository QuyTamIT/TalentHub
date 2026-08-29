<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bin/bootstrap.php';
require_once dirname(__DIR__).'/app/learner/api/LearnerApiContext.php';
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Ai\Persistence\DatabaseAiCapabilityProfileRepository;
use TalentHub\Learner\Ai\Persistence\DatabaseAiRefreshStateRepository;
use TalentHub\Learner\Ai\Queue\AiDataOutboxConsumer;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\AiRefreshJob;
use TalentHub\Learner\Ai\Queue\AiRefreshWorker;
use TalentHub\Learner\Ai\Queue\DatabaseAiDataOutboxRepository;
use TalentHub\Learner\Ai\Queue\DatabaseAiRefreshJobRepository;
use TalentHub\Learner\Ai\Service\AiCapabilityProfileService;
use TalentHub\Learner\Ai\Service\ProfileAnalysisRefreshService;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Modules\School\Repository\SchoolAiAggregateRepository;
use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;
use TalentHub\Modules\School\Service\SchoolAiRefreshCoordinator;
use TalentHub\Rbac\Service\PermissionService;

$pdo=(new Connection(require __DIR__.'/database.php'))->connect();
$sessionConfig=require __DIR__.'/session.php';$sessionConfig['name']=SessionManager::SESSION_STUDENT;
$context=new LearnerApiContext($pdo,new SessionManager($sessionConfig),new PermissionService($pdo),'learner-ai-worker');
$jobs=new DatabaseAiRefreshJobRepository($pdo);
$schoolAggregates=new SchoolAiAggregateRepository($pdo);
$schoolJobs=new DatabaseSchoolAiRefreshJobRepository($pdo);
$schoolCoordinator=new SchoolAiRefreshCoordinator($pdo,$schoolAggregates,$schoolJobs,5);
$outbox=new AiDataOutboxConsumer(
 new DatabaseAiDataOutboxRepository($pdo),
 new AiRefreshDispatcher($jobs),
 static fn(string $studentId,string $capability):string=>$context->aiSnapshotHash($studentId,$capability),
 static function(string $studentId) use ($pdo): bool {
  $s=$pdo->prepare('SELECT 1 FROM student_profiles WHERE id=:id LIMIT 1');
  $s->execute(['id'=>$studentId]);
  return $s->fetchColumn()!==false;
 },
 AiMetricsCollector::shared(),
 static fn(array $studentIds)=>$schoolCoordinator->dispatchForStudents($studentIds),
);
$profiles=new DatabaseAiCapabilityProfileRepository($pdo);
$profileService=new AiCapabilityProfileService();
$refreshState=new DatabaseAiRefreshStateRepository($pdo);
$profileRefresh=new ProfileAnalysisRefreshService(
 static function(string $studentId,string $requestId,string $idempotencyKey,bool $forceRefresh,callable $leaseGuard,bool $propagateRetry,string $expectedSnapshotHash)use($context):array{
  $result=$context->roadmapService($studentId)->generateForProfile($studentId,$requestId,$idempotencyKey,$forceRefresh,$leaseGuard,$propagateRetry);
  if(!hash_equals($expectedSnapshotHash,$context->aiSnapshotHash($studentId,'profile_analysis')))throw new RuntimeException('superseded_snapshot');
  return $result;
 },
 static function(string $studentId,array $profile,string $snapshotHash,string $modelVersion,string $generatedAt)use($profiles,$profileService):void{$validated=$profileService->publish($studentId,$profile,$snapshotHash,$modelVersion,$generatedAt);$profiles->publish($studentId,$validated,$snapshotHash,$modelVersion,$generatedAt);},
);
 $handler=static function(AiRefreshJob $job,callable $leaseGuard)use($context,$profiles,$refreshState,$profileRefresh):void{
  if(!$leaseGuard())throw new RuntimeException('refresh_lease_lost');
  if(!in_array($job->capability,['recommendation','roadmap','profile_analysis'],true))throw new RuntimeException('capability_refresh_unavailable');
  if(!hash_equals($job->snapshotHash,$context->aiSnapshotHash($job->studentId,$job->capability)))throw new RuntimeException('superseded_snapshot');
 $key=$job->executionIdempotencyKey();
 if(!$leaseGuard())throw new RuntimeException('refresh_lease_lost');
 if($job->capability==='profile_analysis')$profiles->markPending($job->studentId,$job->snapshotHash,$job->jobKey);else $refreshState->pending($job->studentId,$job->capability,$job->snapshotHash,$job->jobKey);
 try {
  if($job->capability==='recommendation'){$result=$context->recommendationService($job->studentId)->generate($job->studentId,'worker',$key,$leaseGuard,true);}
  elseif($job->capability==='roadmap'){$result=$context->roadmapService($job->studentId)->generate($job->studentId,'worker',$key,true,$leaseGuard,true);}
  elseif($job->capability==='profile_analysis'){$result=$profileRefresh->refresh($job->studentId,$job->snapshotHash,$job->jobKey,$leaseGuard);}
  else{throw new RuntimeException('capability_refresh_unavailable');}
  if(($result['state']??null)!=='ready_model')throw new RuntimeException('capability_refresh_unavailable');
  if(!$leaseGuard())throw new RuntimeException('refresh_lease_lost');
  if(!hash_equals($job->snapshotHash,$context->aiSnapshotHash($job->studentId,$job->capability)))throw new RuntimeException('superseded_snapshot');
 } catch(Throwable $exception) { if($leaseGuard()){$retry=$exception instanceof ProviderRetryAfterException?$exception->retryAfterSeconds():60;$next=gmdate('Y-m-d H:i:s',time()+$retry);if($job->capability==='profile_analysis')$profiles->markFailed($job->studentId,$exception instanceof ProviderRetryAfterException?$exception->safeCategory():'refresh_failed',$next);else $refreshState->failed($job->studentId,$job->capability,$job->snapshotHash,$job->jobKey,$exception instanceof ProviderRetryAfterException?$exception->safeCategory():'refresh_failed',$next);}throw $exception; }
 if(!$leaseGuard())throw new RuntimeException('refresh_lease_lost');
 if($job->capability!=='profile_analysis')$refreshState->succeeded($job->studentId,$job->capability,$job->snapshotHash,$job->jobKey,(string)($result['model_version']??($result['engine']['model_version']??'')));
};
return new AiRefreshWorker($jobs,$handler,5,$outbox);
