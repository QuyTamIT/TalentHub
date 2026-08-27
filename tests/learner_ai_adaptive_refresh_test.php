<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Events\LearnerAiDataChanged;
use TalentHub\Learner\Ai\Listeners\LearnerAiDataChangedListener;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;
use TalentHub\Learner\Ai\Service\AdaptiveRefreshCoordinator;
function adaptive_assert(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$now=1000;$repo=new InMemoryAiRefreshJobRepository();$coordinator=new AdaptiveRefreshCoordinator(new AiRefreshDispatcher($repo),30,static function()use(&$now):int{return $now;});$hash=str_repeat('a',64);$listener=new LearnerAiDataChangedListener($coordinator,static function(string $student)use(&$hash):string{return $hash;});
$first=$listener(new LearnerAiDataChanged('student-1','checkin','checkin-1',1,'2026-08-27T00:00:00Z'));adaptive_assert(count($first)===3,'first change enqueues all capabilities');
$second=$listener(new LearnerAiDataChanged('student-1','feedback','feedback-1',1,'2026-08-27T00:00:01Z'));adaptive_assert($second===[],'burst change is debounced');$now=1031;$flushed=$listener->flush();adaptive_assert(count($flushed)===3,'latest debounced change is flushed');
$hash=str_repeat('b',64);$now=1062;$listener(new LearnerAiDataChanged('student-1','consent','consent-1',2,'2026-08-27T00:01:02Z'));$cancelled=array_filter($repo->all(),static fn($j):bool=>$j->status==='cancelled');adaptive_assert(count($cancelled)>=3,'old pending snapshot jobs are cancelled');
echo 'learner_ai_adaptive_refresh_test: OK',PHP_EOL;
