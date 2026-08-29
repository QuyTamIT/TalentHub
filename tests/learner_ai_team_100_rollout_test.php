<?php

declare(strict_types=1);

/**
 * Phase: Gemini roadmap 100% rollout configuration.
 *
 * Verifies that the canonical `AiAvailabilityPolicy` receives a complete,
 * machine-readable rollout evidence map when the local/team environment
 * declares the staged 100% rollout with all required verification flags.
 *
 * This test fails (class not found) until `RolloutEvidenceFactory` exists.
 */

use TalentHub\Learner\Ai\Availability\AiAvailabilityDecision;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Rollout\RolloutEvidenceFactory;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function team_100_rollout_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

/**
 * Build the complete, non-secret local/team environment required for the
 * 100% rollout. Mirrors the contract documented in
 * `docs/superpowers/plans/2026-08-27-gemini-roadmap-100-percent-team.md`.
 *
 * @return array<string,string>
 */
function team_100_rollout_environment(): array
{
    return [
        'APP_ENV' => 'test',
        'TALENTHUB_AI_ENABLED' => 'true',
        'TALENTHUB_AI_PROVIDER' => 'gemini',
        'TALENTHUB_AI_MODEL' => 'gemini-3.7-flash',
        'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent',
        'TALENTHUB_AI_API_KEY' => 'test-key-never-log',
        'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
        'TALENTHUB_AI_SHADOW' => 'false',
        'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
        'TALENTHUB_AI_VISIBLE_PERCENT' => '100',
        'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'team-develop-demo-2026-08-27',
        'TALENTHUB_AI_PILOT_PAUSED' => 'false',
        'TALENTHUB_AI_ROLLOUT_STAGE' => '50',
        'TALENTHUB_AI_ERROR_BUDGET_VERIFIED' => 'true',
        'TALENTHUB_AI_FRESHNESS_SLA_VERIFIED' => 'true',
        'TALENTHUB_AI_VALIDATOR_PASS_RATE_VERIFIED' => 'true',
        'TALENTHUB_AI_PRIVACY_REVIEW_VERIFIED' => 'true',
        'TALENTHUB_AI_ROLLBACK_DRILL_VERIFIED' => 'true',
        'TALENTHUB_AI_COMPLETED_STAGES' => 'pilot,10,25,50',
        'TALENTHUB_AI_UNIFIED_POLICY_VERIFIED' => 'true',
        'TALENTHUB_AI_LAST_KNOWN_GOOD_VERIFIED' => 'true',
        'TALENTHUB_AI_QUEUE_MONITORING_VERIFIED' => 'true',
    ];
}

team_100_rollout_assert(class_exists(RolloutEvidenceFactory::class), 'RolloutEvidenceFactory must exist for the 100% rollout configuration contract');

$config = RecommendationConfig::fromEnvironment(team_100_rollout_environment());
team_100_rollout_assert($config->enabled() === true, 'AI is enabled in the team 100% environment');
team_100_rollout_assert($config->visiblePercent() === 100, 'visiblePercent=100 is the only value allowed for 100% rollout');
team_100_rollout_assert($config->shadowGateApproved() === true, 'shadow gate approval is recorded');
team_100_rollout_assert($config->pilotPaused() === false, 'pilot is not paused in the team 100% environment');
team_100_rollout_assert($config->pilotApprovalReference() === 'team-develop-demo-2026-08-27', 'a non-secret pilot approval reference is recorded');

$evidence = RolloutEvidenceFactory::fromEnvironment($config, team_100_rollout_environment());
team_100_rollout_assert(is_array($evidence), 'factory returns a structured evidence array');

foreach (['stage', 'error_budget', 'freshness_sla', 'validator_pass_rate', 'privacy_review', 'rollback_drill', 'approval_reference', 'enabled', 'shadow_gate_approved', 'pilot_paused', 'completed_stages', 'visible_percent', 'unified_policy_verified', 'last_known_good_verified', 'queue_monitoring_verified'] as $required) {
    team_100_rollout_assert(array_key_exists($required, $evidence), "evidence must carry the canonical key: {$required}");
}
team_100_rollout_assert($evidence['visible_percent'] === 100, 'visible_percent mirrors the runtime configuration');
team_100_rollout_assert($evidence['stage'] === '50', 'stage is the canonical token consumed by StagedRolloutGate');
team_100_rollout_assert($evidence['approval_reference'] === 'team-develop-demo-2026-08-27', 'approval reference is the documented non-secret token');
team_100_rollout_assert($evidence['completed_stages'] === ['pilot', '10', '25', '50'], 'completed_stages is normalized into a sorted, allow-listed list');
team_100_rollout_assert($evidence['unified_policy_verified'] === true, 'unified policy is verified for the 100% stage');
team_100_rollout_assert($evidence['last_known_good_verified'] === true, 'last-known-good is verified for the 100% stage');
team_100_rollout_assert($evidence['queue_monitoring_verified'] === true, 'queue monitoring is verified for the 100% stage');
team_100_rollout_assert($evidence['enabled'] === true, 'evidence mirrors the live enabled flag');
team_100_rollout_assert($evidence['shadow_gate_approved'] === true, 'evidence mirrors the live shadow gate flag');
team_100_rollout_assert($evidence['pilot_paused'] === false, 'evidence mirrors the live pilot-paused flag');
team_100_rollout_assert($evidence['error_budget'] === true, 'error budget is verified');
team_100_rollout_assert($evidence['freshness_sla'] === true, 'freshness SLA is verified');
team_100_rollout_assert($evidence['validator_pass_rate'] === true, 'validator pass rate is verified');
team_100_rollout_assert($evidence['privacy_review'] === true, 'privacy review is verified');
team_100_rollout_assert($evidence['rollback_drill'] === true, 'rollback drill is verified');

// Incomplete evidence must fail closed.
$incompleteEnvironment = team_100_rollout_environment();
$incompleteEnvironment['TALENTHUB_AI_FRESHNESS_SLA_VERIFIED'] = 'false';
$incompleteConfig = RecommendationConfig::fromEnvironment($incompleteEnvironment);
$incompleteEvidence = RolloutEvidenceFactory::fromEnvironment($incompleteConfig, $incompleteEnvironment);
team_100_rollout_assert(($incompleteEvidence['freshness_sla'] ?? null) === false, 'factory records falsy verification values instead of dropping them');
$incompleteDecision = (new AiAvailabilityPolicy())->decide(
    'student-incomplete-evidence',
    $incompleteConfig,
    ConsentDecision::REQUIRED_SCOPES,
    true,
    false,
    true,
    ['assessment'],
    $incompleteEvidence,
);
team_100_rollout_assert($incompleteDecision->canShowModel() === false, 'incomplete 100% rollout evidence fails closed at the policy boundary');

// Stale rollout stage is normalized to null and fails closed.
$invalidEnvironment = team_100_rollout_environment();
$invalidEnvironment['TALENTHUB_AI_ROLLOUT_STAGE'] = 'unknown-stage';
$invalidEvidence = RolloutEvidenceFactory::fromEnvironment($config, $invalidEnvironment);
team_100_rollout_assert($invalidEvidence === null, 'unknown rollout stage returns null so the policy falls back to fail-closed defaults');

// The factory must allow the recommendation selector to admit every learner
// in the team 100% environment. The `ruleFallbackCompleted=true` argument
// reflects that the rule engine is always available to run, so the selector
// has all signals it needs to make a model admission decision.
$selector = new RecommendationRolloutSelector(new AiAvailabilityPolicy(), RolloutEvidenceFactory::fromEnvironment($config, team_100_rollout_environment()));
foreach (['student-team-100-rollout', 'learner-alpha', 'learner-beta', 'learner-gamma', '00000000-0000-4000-8000-000000000001'] as $studentId) {
    $decision = $selector->decision(
        $studentId,
        $config,
        ConsentDecision::REQUIRED_SCOPES,
        true,
        false,
        true,
        ['assessment'],
    );
    team_100_rollout_assert($decision instanceof AiAvailabilityDecision, 'selector returns a typed AiAvailabilityDecision');
    team_100_rollout_assert($decision->canShowModel() === true, "canShowModel is true for {$studentId} under VISIBLE_PERCENT=100 with complete evidence");
    team_100_rollout_assert($decision->canRefresh() === true, 'a refresh is allowed when the staged rollout is fully approved');
    team_100_rollout_assert($decision->canServeActiveModel() === false, 'no active model exists on the first call');
}

echo "learner_ai_team_100_rollout_test: OK\n";
