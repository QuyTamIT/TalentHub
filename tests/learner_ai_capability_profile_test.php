<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Service\AiCapabilityProfileService;
function profile_assert(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$service=new AiCapabilityProfileService();$base=['talent_map'=>['technical'=>80],'strengths'=>[['label'=>'Logic','evidence_ref_ids'=>['assessment:a1']]],'improvements'=>[],'potential_paths'=>[],'trend_signals'=>[],'evidence'=>[['ref_id'=>'assessment:a1','source_type'=>'assessment']]];
$v1=$service->publish('s1',$base,str_repeat('a',64),'gemini-v1','2026-08-27T00:00:00Z');$base['talent_map']['technical']=85;$v2=$service->publish('s1',$base,str_repeat('b',64),'gemini-v1','2026-08-27T01:00:00Z');profile_assert($v1['version']===1&&$v2['version']===2,'profiles are versioned');$service->markStale('s1','quota','2026-08-27T02:00:00Z');profile_assert($service->get('s1')['status']==='stale_model','last known good remains visible as stale');$rolled=$service->rollback('s1');profile_assert($rolled['version']===1&&$rolled['evidence'][0]['source_type']==='assessment','rollback preserves provenance');
$dashboard=file_get_contents(dirname(__DIR__).'/app/learner/index.php');profile_assert(is_string($dashboard)&&str_contains($dashboard,'data-dashboard-ai-talent-map')&&str_contains($dashboard,'data-dashboard-ai-strengths')&&str_contains($dashboard,"['talent_map']")&&str_contains($dashboard,"['strengths']"),'dashboard renders live AI capability profile fields instead of static copy');
echo 'learner_ai_capability_profile_test: OK',PHP_EOL;
