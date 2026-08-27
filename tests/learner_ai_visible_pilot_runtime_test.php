<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;

require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';

$env=['TALENTHUB_AI_ENABLED'=>'true','TALENTHUB_AI_PROVIDER'=>'test','TALENTHUB_AI_MODEL'=>'model','TALENTHUB_AI_API_URL'=>'https://gateway.example.test/v1','TALENTHUB_AI_ALLOWED_HOSTS'=>'gateway.example.test','TALENTHUB_AI_API_KEY'=>'test-key','TALENTHUB_AI_SHADOW'=>'true','TALENTHUB_AI_SHADOW_GATE_APPROVED'=>'true','TALENTHUB_AI_VISIBLE_PERCENT'=>'0'];
$evidence=['stage'=>'50','error_budget'=>true,'freshness_sla'=>true,'validator_pass_rate'=>true,'privacy_review'=>true,'rollback_drill'=>true,'approval_reference'=>'test-approval','enabled'=>true,'shadow_gate_approved'=>true,'pilot_paused'=>false,'completed_stages'=>['pilot','10','25','50'],'visible_percent'=>100,'unified_policy_verified'=>true,'last_known_good_verified'=>true,'queue_monitoring_verified'=>true];
$selector=new RecommendationRolloutSelector(null, $evidence);
$config=RecommendationConfig::fromEnvironment($env);
if($selector->canShowModel('student-1',$config,['activity','assessment','evaluation','skills'],true))throw new RuntimeException('Visibility zero exposed model');
$env['TALENTHUB_AI_VISIBLE_PERCENT']='100';$env['TALENTHUB_AI_PILOT_APPROVAL_REFERENCE']='test-approval';$env['TALENTHUB_AI_PILOT_PAUSED']='true';
if($selector->canShowModel('student-1',RecommendationConfig::fromEnvironment($env),['activity','assessment','evaluation','skills'],true))throw new RuntimeException('Paused pilot exposed model');
$env['TALENTHUB_AI_PILOT_PAUSED']='false';
if(!$selector->canShowModel('student-1',RecommendationConfig::fromEnvironment($env),['activity','assessment','evaluation','skills'],true))throw new RuntimeException('Injected approved test pilot did not select eligible learner');
$env['TALENTHUB_AI_VISIBLE_PERCENT']='0';
if($selector->canShowModel('student-1',RecommendationConfig::fromEnvironment($env),['activity','assessment','evaluation','skills'],true))throw new RuntimeException('Rollback to zero was not immediate');

echo "learner_ai_visible_pilot_runtime_test: OK\n";
