<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Rollout\AiPilotPolicy;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

$base = [
    'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => 'test', 'TALENTHUB_AI_MODEL' => 'test-model',
    'TALENTHUB_AI_API_URL' => 'https://gateway.example.test/v1', 'TALENTHUB_AI_ALLOWED_HOSTS' => 'gateway.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-key', 'TALENTHUB_AI_SHADOW' => 'true', 'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '10', 'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'approval-1', 'TALENTHUB_AI_PILOT_PAUSED' => 'false',
];
$events = [];
foreach (ConsentDecision::REQUIRED_SCOPES as $index => $scope) {
    $events[$scope] = ['action' => 'granted', 'policy_version' => 'consent-v1', 'occurred_at' => '2026-08-23T10:00:00.000000+00:00', 'request_id' => "request-{$index}"];
}
$consent = new ConsentDecision($events, '2026-08-23T10:00:00.000000+00:00');
$eligible = (new AiPilotPolicy())->eligibility('student-1', $consent, true, true, RecommendationConfig::fromEnvironment($base));
if (!$eligible->eligible() && !in_array($eligible->reason(), ['outside_bucket'], true)) throw new RuntimeException('Valid policy has unexpected denial');
$shadowDisabled = $base;
$shadowDisabled['TALENTHUB_AI_SHADOW'] = 'false';
$shadowDisabledDecision = (new AiPilotPolicy())->eligibility('student-1', $consent, true, true, RecommendationConfig::fromEnvironment($shadowDisabled));
if ($shadowDisabledDecision->eligible() || $shadowDisabledDecision->reason() !== 'shadow_disabled') throw new RuntimeException('Pilot eligibility must require shadow to be enabled');
$zero = $base; $zero['TALENTHUB_AI_VISIBLE_PERCENT'] = '0';
if ((new AiPilotPolicy())->eligibility('student-1', $consent, true, true, RecommendationConfig::fromEnvironment($zero))->reason() !== 'visibility_zero') throw new RuntimeException('Zero visibility must fail closed');
$paused = $base; $paused['TALENTHUB_AI_PILOT_PAUSED'] = 'true';
if ((new AiPilotPolicy())->eligibility('student-1', $consent, true, true, RecommendationConfig::fromEnvironment($paused))->reason() !== 'pilot_paused') throw new RuntimeException('Pause must fail closed');
$paused['TALENTHUB_AI_VISIBLE_PERCENT'] = '100';
if ((new RecommendationRolloutSelector())->canShowModel('student-1', RecommendationConfig::fromEnvironment($paused), ConsentDecision::REQUIRED_SCOPES, true)) throw new RuntimeException('Visible selector must enforce pilot pause');
$missingApproval = $base; unset($missingApproval['TALENTHUB_AI_PILOT_APPROVAL_REFERENCE']); $missingApproval['TALENTHUB_AI_VISIBLE_PERCENT']='100';
if ((new RecommendationRolloutSelector())->canShowModel('student-1', RecommendationConfig::fromEnvironment($missingApproval), ConsentDecision::REQUIRED_SCOPES, true)) throw new RuntimeException('Visible selector must require approval reference');

echo "learner_ai_pilot_policy_test: OK\n";
