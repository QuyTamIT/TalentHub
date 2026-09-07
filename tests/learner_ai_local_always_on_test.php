<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ConsentMode;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Provider\ProviderRuntimeMode;
use TalentHub\Learner\Ai\Sources\ConsentSource;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(class_exists(ConsentMode::class), 'Local AI consent mode must exist.');
$assert(class_exists(ProviderRuntimeMode::class), 'Provider runtime mode must exist.');
if (class_exists(ConsentMode::class)) {
    $assert(ConsentMode::serviceScopes('local') === ConsentDecision::REQUIRED_SCOPES, 'Local mode must provide all learner AI scopes.');
    $assert(ConsentMode::serviceScopes('production') === ConsentDecision::REQUIRED_SCOPES, 'Production learners receive built-in service access.');
    $assert(ConsentMode::serviceScopes('staging') === ConsentDecision::REQUIRED_SCOPES, 'Staging learners receive built-in service access.');
}
if (class_exists(ProviderRuntimeMode::class)) {
    $assert(ProviderRuntimeMode::alwaysAttempt('local'), 'Local AI must not be blocked by a persisted provider circuit.');
    $assert(!ProviderRuntimeMode::alwaysAttempt('production'), 'Production may retain provider circuit protection.');
    $assert(!ProviderRuntimeMode::alwaysAttempt('staging'), 'Staging may retain provider circuit protection.');
}

$localConfig = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'local',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-test',
    'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent',
    'TALENTHUB_AI_API_KEY' => 'synthetic-test-key',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
    'TALENTHUB_AI_SHADOW' => 'false',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'false',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
    'TALENTHUB_AI_PILOT_PAUSED' => 'true',
]);
$localAvailability = (new AiAvailabilityPolicy())->decide(
    'student-local',
    $localConfig,
    ConsentDecision::REQUIRED_SCOPES,
    true,
    false,
    false,
);
$assert($localAvailability->canShowModel(), 'Local enabled AI must run the model without rollout, approval, shadow, or fallback gates.');
$assert($localAvailability->canRefresh(), 'Local enabled AI must permit an explicit refresh.');

$productionConfig = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'production',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-test',
    'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent',
    'TALENTHUB_AI_API_KEY' => 'synthetic-test-key',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
    'TALENTHUB_AI_SHADOW' => 'false',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'false',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
    'TALENTHUB_AI_PILOT_PAUSED' => 'true',
]);
$productionAvailability = (new AiAvailabilityPolicy())->decide(
    'student-production',
    $productionConfig,
    ConsentDecision::REQUIRED_SCOPES,
    true,
    false,
    false,
);
$assert($productionAvailability->canShowModel(), 'Authenticated production learner access must not depend on pilot or visibility experiments.');
$assert($productionAvailability->canRefresh(), 'Authenticated production learners may explicitly refresh when the provider is configured.');
$assert($productionAvailability->state() === 'pending', 'A configured learner capability without a prior result is honestly pending.');

$staleProductionAvailability = (new AiAvailabilityPolicy())->decide(
    'student-production',
    $productionConfig,
    ConsentDecision::REQUIRED_SCOPES,
    false,
    true,
    false,
);
$assert($staleProductionAvailability->canServeStaleModel(), 'A valid prior learner result remains available while refreshed inputs are pending.');
$assert($staleProductionAvailability->state() === 'stale_model', 'Changed learner inputs make the prior result explicitly stale.');
$assert($staleProductionAvailability->canRefresh(), 'A stale learner snapshot must remain refreshable.');

$source = new class implements ConsentSource {
    public function forStudent(string $studentId): array
    {
        return [];
    }
};
$strict = new ConsentPolicy($source, static fn (): string => '2026-09-05T00:00:00.000000+00:00');
$assert(!$strict->decision('student-1')->permitsAllRequiredScopes(), 'Default consent policy must fail closed.');

if (class_exists(ConsentMode::class)) {
    $local = new ConsentPolicy(
        $source,
        static fn (): string => '2026-09-05T00:00:00.000000+00:00',
        ConsentMode::serviceScopes('local'),
    );
    $assert($local->decision('student-1')->permitsAllRequiredScopes(), 'Local service scopes must enable learner AI without writing consent events.');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_ai_local_always_on_test: OK\n";
