<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Queue\AiRefreshWorker;

if (in_array('--healthcheck', $argv ?? [], true)) exit(0);
$bootstrap=(string)(getenv('TALENTHUB_AI_WORKER_BOOTSTRAP')?:dirname(__DIR__).'/config/learner-ai-worker.php');
if($bootstrap===''||!is_file($bootstrap)){fwrite(STDERR,"TALENTHUB_AI_WORKER_BOOTSTRAP must point to a worker factory.\n");exit(78);}
try {
    $worker=require $bootstrap;
} catch (Throwable) {
    fwrite(STDERR,"Learner AI worker bootstrap failed safely.\n");
    exit(78);
}
if(!$worker instanceof AiRefreshWorker){fwrite(STDERR,"Worker bootstrap must return AiRefreshWorker.\n");exit(78);}

$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
    pcntl_signal(SIGINT, static function () use (&$running): void { $running = false; });
}
$maxIterations = max(0, (int) (getenv('LEARNER_AI_WORKER_ITERATIONS') ?: 0));
$iterations = 0;
while ($running && ($maxIterations===0||$iterations++ < $maxIterations)) {
    try {
        $worked=$worker->runOnce('learner-ai-'.getmypid());
    } catch (Throwable) {
        fwrite(STDERR,"Learner AI worker iteration failed safely.\n");
        exit(70);
    }
    if(!$worked) usleep(250000);
}
exit(0);
