<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Rollout\StagedRolloutGate;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

$collector = new AiMetricsCollector();
$event = $collector->record([
    'queue_depth' => 4,
    'queue_oldest_age_seconds' => 31,
    'freshness_age_seconds' => 120,
    'stale' => true,
    'provider_latency_ms' => 240,
    'provider_error' => 'quota_exhausted',
    'provider_quota_remaining' => 0,
    'circuit_state' => 'open',
    'fallback' => true,
    'recommendation_click' => true,
    'recommendation_feedback' => 'helpful',
    'input_tokens' => 10,
    'output_tokens' => 20,
    'estimated_cost' => 0.004,
    'student_id' => 'student-secret',
    'api_key' => 'secret-key',
    'raw_response' => 'do not retain',
]);
if (isset($event['student_id'], $event['api_key'], $event['raw_response'])) {
    throw new RuntimeException('Telemetry must redact identifiers, secrets and raw output.');
}
if (($event['metric_schema'] ?? '') !== 'ai-observability-v1') {
    throw new RuntimeException('Telemetry schema marker missing.');
}
$snapshot = $collector->snapshot();
foreach (['queue_depth', 'queue_age_seconds', 'stale_ratio', 'provider_latency_ms', 'provider_error_rate', 'provider_quota_remaining', 'circuit_state', 'fallback_rate', 'recommendation_click_rate', 'recommendation_feedback_rate', 'token_cost'] as $metric) {
    if (!array_key_exists($metric, $snapshot)) throw new RuntimeException("Missing metric {$metric}");
}
if ($snapshot['stale_ratio'] !== 1.0 || $snapshot['fallback_rate'] !== 1.0 || $snapshot['token_cost'] !== 0.004) {
    throw new RuntimeException('Telemetry aggregation is incorrect.');
}

$gate = new StagedRolloutGate();
$checks = [
    'error_budget' => true,
    'freshness_sla' => true,
    'validator_pass_rate' => true,
    'privacy_review' => true,
    'rollback_drill' => true,
    'approval_reference' => 'review-8',
    'enabled' => true,
    'shadow_gate_approved' => true,
    'pilot_paused' => false,
    'visible_percent' => 100,
    'unified_policy_verified' => true,
    'last_known_good_verified' => true,
    'queue_monitoring_verified' => true,
];
if (!$gate->canAdvance('shadow', 'pilot', $checks)['allowed']) throw new RuntimeException('Approved pilot should advance.');
if ($gate->canAdvance('pilot', '100', $checks)['allowed']) throw new RuntimeException('100% must require all staged percentages.');
$checks['completed_stages'] = ['pilot', '10', '25', '50'];
if (!$gate->canAdvance('50', '100', $checks)['allowed']) throw new RuntimeException('100% should advance after all gates.');
$checks['pilot_paused'] = true;
if ($gate->canAdvance('50', '100', $checks)['allowed']) throw new RuntimeException('Paused rollout must fail closed.');

echo "learner_ai_phase8_observability_test: OK\n";
