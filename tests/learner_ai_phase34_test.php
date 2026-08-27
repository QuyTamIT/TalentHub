<?php
declare(strict_types=1);
use TalentHub\Learner\Ai\Provider\RetryPolicy;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\AiDataOutbox;
use TalentHub\Learner\Ai\Domain\AiFreshness;
use TalentHub\Learner\Ai\Events\LearnerAiDataChanged;
use TalentHub\Learner\Ai\Service\AdaptiveRefreshCoordinator;
use TalentHub\Learner\Ai\Service\AiCapabilityProfileService;
use TalentHub\Learner\Ai\Provider\ProviderHealthStore;
use TalentHub\Learner\Ai\Queue\AiRefreshWorker;
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';
function phase34_assert(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$policy=new RetryPolicy(3,100,1000); phase34_assert($policy->shouldRetry(503,null,1),'503 retried'); phase34_assert(!$policy->shouldRetry(400,null,1),'400 not retried'); phase34_assert($policy->delayMs(1,2)===1000,'retry-after bounded');
$now=1000; $cb=new CircuitBreaker(2,10,static function()use(&$now):int{return $now;}); phase34_assert($cb->allow(),'closed'); $cb->recordFailure();$cb->recordFailure();phase34_assert(!$cb->allow(),'open');$now=1011;phase34_assert($cb->allow(),'half-open');$cb->recordSuccess();phase34_assert($cb->state()==='closed','closed after success');
$repo=new InMemoryAiRefreshJobRepository();$dispatcher=new AiRefreshDispatcher($repo);$a=$dispatcher->dispatch('s1','h1',['recommendation','roadmap']);$dispatcher->dispatch('s1','h1',['recommendation','roadmap']);phase34_assert(count($a)===2&&count($repo->all())===2,'idempotent queue');$job=$repo->claimNext('w1');phase34_assert($job!==null&&$job->attempts===1,'claim lease');$repo->complete($job->jobKey);phase34_assert($repo->all()[$job->jobKey]->status==='completed','complete');
$workerRepo=new InMemoryAiRefreshJobRepository();$workerRepo->enqueue('s2','h2','roadmap');$worker=new AiRefreshWorker($workerRepo,static function():void{throw new RuntimeException('secret provider detail');},1);phase34_assert($worker->runOnce('worker-1'),'worker claims job');phase34_assert(array_values($workerRepo->all())[0]->status==='dead_letter','worker dead-letters exhausted jobs');phase34_assert(array_values($workerRepo->all())[0]->errorCode==='runtimeexception','worker stores only safe error category');
$out=AiDataOutbox::create('activity','a1',2,['s1'],'activity.completed',['x'=>1]);phase34_assert(strlen($out->payloadHash)===64,'outbox hash');
$fresh=new AiFreshness(AiFreshness::STALE_MODEL,'2026-08-27T00:00:00Z','quota');phase34_assert($fresh->isUsable(),'stale LKG usable');
$health=new ProviderHealthStore();$health->record(false,120,2,'quota','open');phase34_assert($health->snapshot()['failure_count']===1,'provider health records safe metrics');
$coord=new AdaptiveRefreshCoordinator(new AiRefreshDispatcher(new InMemoryAiRefreshJobRepository()));$e=new LearnerAiDataChanged('s1','checkin','c1',1,'2026-08-27T00:00:00Z');phase34_assert(count($coord->onDataChanged($e,'h2'))===2,'adaptive refresh');phase34_assert($coord->onDataChanged($e,'h2')===[],'duplicate source version debounced');
$profile=new AiCapabilityProfileService();$p=$profile->publish('s1',['talent_map'=>[],'strengths'=>[],'improvements'=>[],'potential_paths'=>[],'trend_signals'=>[],'evidence'=>[]],'h2','model-v1','2026-08-27T00:00:00Z');phase34_assert($p['status']==='ready_model','profile published');$profile->markStale('s1','provider_unavailable','2026-08-27T01:00:00Z');phase34_assert($profile->get('s1')['status']==='stale_model','profile stale');
echo "learner_ai_phase34_test: OK\n";
