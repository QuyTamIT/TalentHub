<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Queue\AiRefreshWorker;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;
function retry_queue_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$jobs=new InMemoryAiRefreshJobRepository();$job=$jobs->enqueue('s1',str_repeat('a',64),'roadmap');
$worker=new AiRefreshWorker($jobs,static function():void{throw new ProviderRetryAfterException('rate_limited',137);});
$worker->runOnce('worker');$stored=$jobs->all()[$job->jobKey];
retry_queue_assert($stored->errorCode==='rate_limited','safe provider category is retained');
$delay=(int)strtotime((string)$stored->nextRetryAt)-time();retry_queue_assert($delay>=135&&$delay<=138,'queue honors provider Retry-After scheduling');
echo "learner_ai_retry_after_queue_test: OK\n";
