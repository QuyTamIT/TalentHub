<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Rollout\StagedRolloutGate;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Queue\AiRefreshWorker;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

$gate = new StagedRolloutGate();
$base = [
    'error_budget' => true, 'freshness_sla' => true, 'validator_pass_rate' => true,
    'privacy_review' => true, 'rollback_drill' => true,
    'approval_reference' => 'change-2026-08-27', 'enabled' => true,
    'shadow_gate_approved' => true, 'pilot_paused' => false,
    'completed_stages' => ['pilot', '10', '25', '50'],
];
$blocked = $gate->canAdvance('50', '100', $base);
if ($blocked['allowed'] || !in_array('visible_percent_missing', $blocked['reasons'], true)) {
    throw new RuntimeException('100% gate must require exact visible_percent=100.');
}
$approved = $gate->canAdvance('50', '100', $base + [
    'visible_percent' => 100,
    'unified_policy_verified' => true,
    'last_known_good_verified' => true,
    'queue_monitoring_verified' => true,
]);
if (!$approved['allowed']) throw new RuntimeException('Complete 100% gate should advance.');
foreach (['unified_policy_verified', 'last_known_good_verified', 'queue_monitoring_verified'] as $missing) {
    $checks = $base + ['visible_percent' => 100, 'unified_policy_verified' => true, 'last_known_good_verified' => true, 'queue_monitoring_verified' => true];
    $checks[$missing] = false;
    if ($gate->canAdvance('50', '100', $checks)['allowed']) throw new RuntimeException("Missing {$missing} must fail closed.");
}
if ($gate->canAdvance('50', '100', $base + ['visible_percent' => '100', 'unified_policy_verified' => true, 'last_known_good_verified' => true, 'queue_monitoring_verified' => true])['allowed']) {
    throw new RuntimeException('String visible_percent must not satisfy strict 100% gate.');
}
if ($gate->canAdvance('50', '100', array_merge($base, ['visible_percent' => 100, 'unified_policy_verified' => true, 'last_known_good_verified' => true, 'queue_monitoring_verified' => true, 'approval_reference' => 'bad value']))['allowed']) {
    throw new RuntimeException('Unsafe approval reference must fail closed.');
}
$config = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => 'gemini', 'TALENTHUB_AI_MODEL' => 'test',
    'TALENTHUB_AI_API_URL' => 'http://localhost:20128/generate', 'TALENTHUB_AI_API_KEY' => 'test-only', 'TALENTHUB_AI_ALLOWED_HOSTS' => 'localhost',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '100', 'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true', 'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'change-2026-08-27',
    'TALENTHUB_AI_PILOT_PAUSED' => 'false',
]);
$policy = new AiAvailabilityPolicy();
$scopes = ConsentDecision::REQUIRED_SCOPES;
if ($policy->decide('student-test', $config, $scopes, true, false, true)->canShowModel()) throw new RuntimeException('Production 100% visibility must require rollout evidence.');
$stage10 = RecommendationConfig::fromEnvironment(array_replace([
    'TALENTHUB_AI_VISIBLE_PERCENT' => '10',
], [
    'APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => 'gemini', 'TALENTHUB_AI_MODEL' => 'test',
    'TALENTHUB_AI_API_URL' => 'http://localhost:20128/generate', 'TALENTHUB_AI_API_KEY' => 'test-only', 'TALENTHUB_AI_ALLOWED_HOSTS' => 'localhost',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true', 'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'change-2026-08-27', 'TALENTHUB_AI_PILOT_PAUSED' => 'false',
]));
if ($policy->decide('student-test', $stage10, $scopes, true, false, true)->canShowModel()) throw new RuntimeException('Canonical staged percentage must require rollout evidence.');
$evidence = array_merge($base, ['stage' => '50', 'visible_percent' => 100, 'unified_policy_verified' => true, 'last_known_good_verified' => true, 'queue_monitoring_verified' => true]);
if (!$policy->decide('student-test', $config, $scopes, true, false, true, null, $evidence)->canShowModel()) throw new RuntimeException('Valid rollout evidence should pass the 100% visibility guard.');

$collector = new AiMetricsCollector();
$collector->record(['queue_depth' => 200, 'queue_oldest_age_seconds' => 600, 'freshness_age_seconds' => 90, 'provider_quota_remaining' => 0, 'circuit_state' => 'open']);
$collector->record(['recommendation_click' => true]);
$snapshot = $collector->snapshot();
if ($snapshot['queue_depth'] !== 200 || $snapshot['queue_oldest_age_seconds'] !== 600 || $snapshot['freshness_age_seconds'] !== 90 || $snapshot['circuit_state'] !== 'open') {
    throw new RuntimeException('Gauge state must survive later events without gauge fields.');
}
$sinkEvents = [];
$sinkCollector = new AiMetricsCollector(10, static function (array $event) use (&$sinkEvents): void { $sinkEvents[] = $event; });
$sinkCollector->record(['provider_error' => 'quota_exhausted', 'api_key' => 'must-not-appear', 'student_id' => 'must-not-appear']);
if (count($sinkEvents) !== 1 || isset($sinkEvents[0]['api_key'], $sinkEvents[0]['student_id'])) throw new RuntimeException('Sanitized telemetry sink contract failed.');
$typed = new AiMetricsCollector();
$typed->record(['stale' => true, 'fallback' => true]);
$typed->record(['recommendation_click' => true, 'queue_event' => 'idle']);
if ($typed->snapshot()['stale_ratio'] !== 1.0 || $typed->snapshot()['fallback_rate'] !== 1.0) throw new RuntimeException('Stale/fallback rates must use typed observation denominators.');
$collector->record(['queue_event' => 'completed']);
if (($collector->snapshot()['queue_events']['completed'] ?? 0) !== 1) throw new RuntimeException('Queue lifecycle event was not recorded.');

$jobs = new InMemoryAiRefreshJobRepository();
$jobs->enqueue('student-test', str_repeat('a', 64), 'recommendation');
$handled = 0;
$worker = new AiRefreshWorker($jobs, static function () use (&$handled): void { $handled++; }, 2, null, $collector);
if (!$worker->runOnce('phase8-test-worker') || $handled !== 1) throw new RuntimeException('Deterministic queue/provider seam did not execute.');
$jobStates = array_values($jobs->all());
if (($jobStates[0]->status ?? null) !== 'completed') throw new RuntimeException('Queue seam did not complete a refresh job.');

echo "learner_ai_phase8_review_fixes_test: OK\n";
