<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Provider\ProviderResponse;

require __DIR__ . '/learner_ai_opportunity_service_test.php';

$summaryFallbackPdo = service_test_pdo();
$summaryFallbackSeed = service_make_scenario(
    $summaryFallbackPdo,
    service_test_engine(
        ProviderResponse::success($noFitDiagnosticItems),
        ProviderResponse::success([$noFitSummaryItem]),
    ),
    candidateEvidence: $noFitCandidates,
    scorer: $noFitScorer,
);
$seeded = $summaryFallbackSeed->generate(
    'student-1',
    'request-summary-fallback-seed-0001',
    'idempotency-summary-fallback-seed-01',
);
service_assert(($seeded['state'] ?? '') === 'no_fit_model', 'summary fallback fixture creates a completed diagnostic run');

$summaryFailureService = service_make_scenario(
    $summaryFallbackPdo,
    service_test_engine(
        ProviderResponse::success($noFitDiagnosticItems),
        ProviderResponse::failure('provider_unavailable'),
    ),
    candidateEvidence: $noFitCandidates,
    scorer: $noFitScorer,
);
$result = $summaryFailureService->generate(
    'student-1',
    'request-summary-fallback-failure-0001',
    'idempotency-summary-fallback-failure-01',
);

service_assert(
    ($result['state'] ?? '') === 'stale_model',
    'summary provider failure falls back to the latest completed diagnostic opportunities',
);
service_assert(
    count($result['items'] ?? []) === 2,
    'summary provider failure never replaces visible projects with a blank error panel',
);

echo "learner_ai_opportunity_summary_fallback_test: OK\n";
