<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Quality\RoadmapQualityGate;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\ActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\AssessmentSource;
use TalentHub\Learner\Ai\Sources\ConsentSource;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use TalentHub\Learner\Ai\Sources\PublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\SkillSource;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function roadmap_quality_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

/** @param list<string> $codes @param list<string> $missingConsent */
function roadmap_quality_input(array $codes, array $missingConsent = [], string $submittedAt = '2026-08-20T09:00:00.000000+00:00'): RecommendationInput
{
    $assessments = [];
    $evidence = [];
    foreach ($codes as $index => $code) {
        $id = 'result-' . ($index + 1);
        $record = [
            'test_code' => $code,
            'test_type' => preg_replace('/_(middle|high|college)$/', '', $code),
            'assessment_version' => '1.0.0',
            'scoring_version' => 'score-1.0.0',
            'result_code' => strtoupper(substr($code, 0, 3)),
            'dimension_scores' => ['A' => 70 + $index],
            'submitted_at' => $submittedAt,
        ];
        $assessments[] = $record;
        $evidence[] = [
            'source_type' => 'assessment',
            'source_id' => $id,
            'observed_at' => $submittedAt,
            'safe_value' => $record,
        ];
    }

    return new RecommendationInput(
        ['profile' => ['study_status' => 'active'], 'skills' => [], 'assessments' => $assessments, 'activities' => [], 'evaluations' => [], 'opportunities' => []],
        ['assessment' => $submittedAt],
        [
            'allowed_scopes' => array_values(array_diff(['activity', 'assessment', 'evaluation', 'skills'], $missingConsent)),
            'missing_consent_scopes' => $missingConsent,
            'source_counts' => ['skills' => 0, 'assessments' => count($assessments), 'activities' => 0, 'evaluations' => 0, 'opportunities' => 0],
        ],
        $evidence,
    );
}

roadmap_quality_assert(class_exists(RoadmapQualityGate::class), 'roadmap quality gate is loaded');
$gate = new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00'));

$three = $gate->evaluate(roadmap_quality_input(['holland_high', 'mbti_high', 'disc_high']));
roadmap_quality_assert($three->state() === 'insufficient_data', 'three assessment families are insufficient');
roadmap_quality_assert($three->missingCategories() === ['multiple_intelligence'], 'missing family is explicit');

$fourOnly = $gate->evaluate(roadmap_quality_input(['holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high']));
roadmap_quality_assert($fourOnly->state() === 'ready', 'four assessment families need no skills, activities or evaluations');

$duplicate = $gate->evaluate(roadmap_quality_input(['holland_high', 'mbti_high', 'disc_high', 'disc_high']));
roadmap_quality_assert($duplicate->state() === 'insufficient_data', 'duplicate test family does not replace missing intelligence assessment');

$stale = $gate->evaluate(roadmap_quality_input(
    ['holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high'],
    [],
    '2025-08-20T09:00:00.000000+00:00',
));
roadmap_quality_assert($stale->state() === 'insufficient_data', 'stale assessments are rejected');
roadmap_quality_assert($stale->missingCategories() === ['disc', 'holland', 'mbti', 'multiple_intelligence'], 'all stale families are reported');

$missingAssessmentConsent = $gate->evaluate(roadmap_quality_input(
    ['holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high'],
    ['assessment'],
));
roadmap_quality_assert($missingAssessmentConsent->state() === 'consent_required', 'assessment consent is mandatory');
roadmap_quality_assert($missingAssessmentConsent->missingConsentScopes() === ['assessment'], 'only assessment consent is requested');

$optionalConsentMissing = $gate->evaluate(roadmap_quality_input(
    ['holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high'],
    ['activity', 'evaluation', 'skills'],
));
roadmap_quality_assert($optionalConsentMissing->state() === 'ready', 'optional enrichment consent does not block version one');

$events = [[
    'scope' => 'assessment',
    'action' => 'granted',
    'policy_version' => 'learner-ai-consent-1.0',
    'occurred_at' => '2026-08-24T00:00:00.000000+00:00',
    'request_id' => 'request-assessment-consent',
]];
$consentSource = new class($events) implements ConsentSource {
    public function __construct(private readonly array $events) {}
    public function forStudent(string $studentId): array { return $this->events; }
};
$policy = new ConsentPolicy($consentSource, static fn (): string => '2026-08-24T00:01:00.000000+00:00');
$decision = $policy->decision('student-roadmap');
roadmap_quality_assert($decision->permitsScopes(['assessment']), 'decision permits an explicitly granted assessment scope');
roadmap_quality_assert(!$decision->permitsAllRequiredScopes(), 'assessment-only decision does not change the all-scope default');

$input = roadmap_quality_input(['holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high'], ['activity', 'evaluation', 'skills']);
$context = new RecommendationContext(
    ['assessment'],
    'request-roadmap-consent',
    'idempotency-roadmap-consent',
    'student-roadmap',
    $decision->decisionHash(),
    $decision->policyVersion(),
);
$roadmapConsentGate = new ProviderConsentGate($policy, ['assessment']);
roadmap_quality_assert(
    $roadmapConsentGate->authorize('student-roadmap', $input, $context)->permitsScopes(['assessment']),
    'roadmap provider gate authorizes assessment-only context',
);

$legacyRejected = false;
try {
    (new ProviderConsentGate($policy))->authorize('student-roadmap', $input, $context);
} catch (ProviderConsentDenied) {
    $legacyRejected = true;
}
roadmap_quality_assert($legacyRejected, 'legacy provider gate still requires every existing scope');

$assessments = [
    ['result_id' => 'result-holland-old', 'test_code' => 'holland_high', 'test_type' => 'holland', 'assessment_version' => '1.0.0', 'scoring_version' => 'score', 'result_code' => 'OLD', 'dimension_scores' => ['R' => 50], 'submitted_at' => '2026-07-01T00:00:00+00:00'],
    ['result_id' => 'result-holland-new', 'test_code' => 'holland_high', 'test_type' => 'holland', 'assessment_version' => '1.0.0', 'scoring_version' => 'score', 'result_code' => 'NEW', 'dimension_scores' => ['R' => 80], 'submitted_at' => '2026-08-20T00:00:00+00:00'],
    ['result_id' => 'result-mbti', 'test_code' => 'mbti_high', 'test_type' => 'mbti', 'assessment_version' => '1.0.0', 'scoring_version' => 'score', 'result_code' => 'INTJ', 'dimension_scores' => ['I' => 70], 'submitted_at' => '2026-08-19T00:00:00+00:00'],
    ['result_id' => 'result-disc', 'test_code' => 'disc_high', 'test_type' => 'disc', 'assessment_version' => '1.0.0', 'scoring_version' => 'score', 'result_code' => 'D', 'dimension_scores' => ['D' => 75], 'submitted_at' => '2026-08-18T00:00:00+00:00'],
    ['result_id' => 'result-mi', 'test_code' => 'multiple_intelligence_high', 'test_type' => 'multiple_intelligence', 'assessment_version' => '1.0.0', 'scoring_version' => 'score', 'result_code' => 'LOGICAL', 'dimension_scores' => ['logical' => 78], 'submitted_at' => '2026-08-17T00:00:00+00:00'],
];
$emptyStudent = new class implements StudentProfileSource { public function forStudent(string $studentId): array { return ['study_status' => 'active']; } };
$emptySkill = new class implements SkillSource { public function forStudent(string $studentId): array { return []; } };
$assessmentSource = new class($assessments) implements AssessmentSource { public function __construct(private readonly array $records) {} public function forStudent(string $studentId): array { return $this->records; } };
$emptyActivity = new class implements ActivityExperienceSource { public function forStudent(string $studentId): array { return []; } };
$emptyEvaluation = new class implements PublishedEvaluationSource { public function forStudent(string $studentId): array { return []; } };
$emptyOpportunity = new class implements OpportunitySource { public function forStudent(string $studentId): array { return []; } };
$builder = new RecommendationSnapshotBuilder($emptyStudent, $emptySkill, $assessmentSource, $emptyActivity, $emptyEvaluation, $emptyOpportunity);
$roadmapInput = $builder->buildForRoadmap('student-roadmap', ['assessment']);
roadmap_quality_assert(count($roadmapInput->payload()['assessments']) === 4, 'roadmap snapshot contains one latest result per family');
$holland = array_values(array_filter($roadmapInput->payload()['assessments'], static fn (array $row): bool => str_starts_with($row['test_code'], 'holland')));
roadmap_quality_assert(($holland[0]['result_code'] ?? null) === 'NEW', 'roadmap snapshot retains the newest family result');
roadmap_quality_assert(count($roadmapInput->evidenceReferences()) === 4, 'discarded historic result is not provider evidence');

echo "learner_ai_roadmap_quality_test: OK\n";
