<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Service\ProfileAnalysisRefreshService;
function profile_refresh_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$published=[];
$service=new ProfileAnalysisRefreshService(
 static fn(string $studentId,string $requestId,string $idempotencyKey,bool $forceRefresh,?callable $leaseGuard,bool $propagateRetry):array=>[
  'state'=>'ready_model','input_hash'=>str_repeat('a',64),'generated_at'=>'2026-08-27T00:00:00Z','model_version'=>'gemini-test','talent_map'=>[],'strengths'=>[],'improvements'=>[],'potential_paths'=>[],'trend_signals'=>[],'evidence'=>[['source_type'=>'assessment','source_id'=>'a1']],
 ],
 static function(string $studentId,array $profile,string $snapshotHash,string $modelVersion,string $generatedAt)use(&$published):void{$published=compact('studentId','profile','snapshotHash','modelVersion','generatedAt');},
);
try{$service->refresh('s1',str_repeat('b',64),'job-1',static fn():bool=>true);throw new RuntimeException('snapshot mismatch must fail');}catch(RuntimeException $e){profile_refresh_assert($e->getMessage()==='profile_analysis_snapshot_mismatch','old analysis is rejected for a new snapshot');}
profile_refresh_assert($published===[],'mismatched analysis is never published');
$result=$service->refresh('s1',str_repeat('a',64),'job-2',static fn():bool=>true);
profile_refresh_assert(($result['input_hash']??null)===str_repeat('a',64)&&($published['snapshotHash']??null)===str_repeat('a',64),'profile publication preserves exact input provenance');
echo "learner_ai_profile_refresh_handler_test: OK\n";
