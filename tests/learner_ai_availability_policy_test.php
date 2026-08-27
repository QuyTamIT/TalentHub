<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Availability\AiAvailabilityDecision;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function availability_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

/** @param array<string,string> $overrides */
function availability_config(array $overrides = []): RecommendationConfig
{
    return RecommendationConfig::fromEnvironment(array_replace([
        'APP_ENV' => 'test',
        'TALENTHUB_AI_ENABLED' => 'true',
        'TALENTHUB_AI_PROVIDER' => 'fake',
        'TALENTHUB_AI_MODEL' => 'model-test',
        'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1',
        'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
        'TALENTHUB_AI_API_KEY' => 'test-key',
        'TALENTHUB_AI_SHADOW' => 'false',
        'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
        'TALENTHUB_AI_VISIBLE_PERCENT' => '100',
        'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'phase-1-approved',
        'TALENTHUB_AI_PILOT_PAUSED' => 'false',
    ], $overrides));
}

$allScopes = ConsentDecision::REQUIRED_SCOPES;
availability_assert(class_exists(AiAvailabilityDecision::class), 'availability decision is loaded');
availability_assert(class_exists(AiAvailabilityPolicy::class), 'one availability policy is loaded');
$policy = new AiAvailabilityPolicy();
$fullEvidence = [
    'stage' => '50', 'error_budget' => true, 'freshness_sla' => true, 'validator_pass_rate' => true,
    'privacy_review' => true, 'rollback_drill' => true, 'approval_reference' => 'phase-1-approved',
    'enabled' => true, 'shadow_gate_approved' => true, 'pilot_paused' => false,
    'completed_stages' => ['pilot', '10', '25', '50'], 'visible_percent' => 100,
    'unified_policy_verified' => true, 'last_known_good_verified' => true, 'queue_monitoring_verified' => true,
];

$disabled = $policy->decide('student-1', RecommendationConfig::fromEnvironment([]), $allScopes, true, false, false);
availability_assert($disabled->state() === 'ai_unavailable' && $disabled->reason() === 'ai_disabled', 'enabled=false fails closed');
availability_assert(!$disabled->canRefresh() && !$disabled->canRunShadow() && !$disabled->canShowModel(), 'disabled AI cannot call Gemini');

$shadowOff = $policy->decide('student-1', availability_config(['TALENTHUB_AI_VISIBLE_PERCENT'=>'0']), $allScopes, true, false, false);
availability_assert(!$shadowOff->canRunShadow() && !$shadowOff->canRefresh(), 'shadow=false and visibility=0 cannot refresh');

$shadowOn = $policy->decide('student-1', availability_config([
    'TALENTHUB_AI_SHADOW'=>'true',
    'TALENTHUB_AI_VISIBLE_PERCENT'=>'0',
]), $allScopes, true, false, false);
availability_assert($shadowOn->canRunShadow() && $shadowOn->canRefresh(), 'approved shadow may refresh at zero visibility');
availability_assert(!$shadowOn->canShowModel() && $shadowOn->state() === 'pending', 'shadow output is never user-visible');

$shadowUnapproved = $policy->decide('student-1', availability_config([
    'TALENTHUB_AI_SHADOW'=>'true',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED'=>'false',
    'TALENTHUB_AI_VISIBLE_PERCENT'=>'0',
]), $allScopes, true, false, false);
availability_assert(!$shadowUnapproved->canRunShadow() && !$shadowUnapproved->canRefresh(), 'unapproved shadow gate blocks Gemini');

$visible = $policy->decide('student-1', availability_config(), $allScopes, true, false, true, null, $fullEvidence);
availability_assert($visible->canShowModel() && $visible->canRefresh(), '100 percent approved pilot can refresh and show model');
availability_assert($visible->state() === 'ready_rule', 'completed rule remains explicit until model exists');

$active = $policy->decide('student-1', availability_config(), $allScopes, true, true, true, null, $fullEvidence);
availability_assert($active->state() === 'ready_model' && $active->canServeActiveModel(), 'fresh active model is served');
availability_assert(!$active->canServeStaleModel(), 'fresh active model is not labelled stale');

$stale = $policy->decide('student-1', availability_config(), $allScopes, false, true, true, null, $fullEvidence);
availability_assert($stale->state() === 'stale_model' && $stale->canServeStaleModel(), 'stale last-known-good model is retained');
availability_assert(!$stale->canRefresh(), 'stale snapshot cannot create a new model output');

$paused = $policy->decide('student-1', availability_config(['TALENTHUB_AI_PILOT_PAUSED'=>'true']), $allScopes, true, false, true, null, $fullEvidence);
availability_assert(!$paused->canShowModel() && $paused->reason() === 'pilot_paused', 'pilot pause blocks model-visible refresh');
$pausedWithActive = $policy->decide('student-1', availability_config(['TALENTHUB_AI_PILOT_PAUSED'=>'true']), $allScopes, true, true, true, null, $fullEvidence);
availability_assert($pausedWithActive->state() === 'ai_unavailable' && !$pausedWithActive->canServeActiveModel(), 'blocked active model is retained internally without silent rule replacement');

$missingApproval = $policy->decide('student-1', availability_config(['TALENTHUB_AI_PILOT_APPROVAL_REFERENCE'=>'']), $allScopes, true, false, true, null, $fullEvidence);
availability_assert(!$missingApproval->canShowModel() && $missingApproval->reason() === 'approval_missing', 'missing approval blocks model visibility');

$missingConsent = $policy->decide('student-1', availability_config(), ['assessment'], true, false, true);
availability_assert(!$missingConsent->canRefresh() && $missingConsent->reason() === 'consent_missing', 'missing required consent blocks provider input');
availability_assert($missingConsent->state() === 'ready_rule', 'available rule remains explicit when consent blocks AI');

$outsideStudent = null;
$onePercent = availability_config(['TALENTHUB_AI_VISIBLE_PERCENT'=>'1']);
for ($index = 0; $index < 500; $index++) {
    $candidate = 'outside-bucket-' . $index;
    $candidateDecision = $policy->decide($candidate, $onePercent, $allScopes, true, false, true);
    if ($candidateDecision->reason() === 'outside_bucket') {
        $outsideStudent = $candidate;
        availability_assert(!$candidateDecision->canShowModel(), 'student outside bucket cannot see model');
        break;
    }
}
availability_assert(is_string($outsideStudent), 'test finds a deterministic student outside one-percent bucket');

$selector = new RecommendationRolloutSelector($policy);
$recommendationVisible = $selector->canShowModel('student-1', availability_config(), $allScopes, true, $fullEvidence);
$roadmapVisible = $selector->canShowRoadmapModel('student-1', availability_config(), $allScopes, true, $fullEvidence);
availability_assert($recommendationVisible === $roadmapVisible && $recommendationVisible, 'roadmap and recommendation share one decision for identical inputs');

$mappedRule = (new RecommendationResponseMapper())->run([
    'runId'=>'run-rule', 'snapshotId'=>'snapshot-1', 'status'=>'completed', 'engineType'=>'rule',
    'ruleVersion'=>'rules-v1', 'modelVersion'=>null, 'fallbackReason'=>'provider_unavailable',
    'completedAt'=>'2026-08-26T00:00:00+00:00',
    'items'=>[['itemId'=>'item-1','itemType'=>'strength','title'=>'Rule','summary'=>'Rule output','priority'=>1,'confidenceBand'=>'medium','actionJson'=>'{}','evidence'=>[['source_type'=>'assessment','source_id'=>'evidence-1']]]],
]);
availability_assert(($mappedRule['analysis_origin'] ?? null) === 'rule', 'internal rule fallback maps to public rule origin');
availability_assert(($mappedRule['state'] ?? null) === 'ready_rule', 'internal fallback_rule maps to ready_rule');
availability_assert(($mappedRule['freshness_status'] ?? null) === 'fresh', 'ready rule has fresh status');
availability_assert(array_key_exists('model_version', $mappedRule) && $mappedRule['model_version'] === null && ($mappedRule['rule_version'] ?? null) === 'rules-v1', 'rule/model versions are mutually exclusive');

echo "learner_ai_availability_policy_test: OK\n";
