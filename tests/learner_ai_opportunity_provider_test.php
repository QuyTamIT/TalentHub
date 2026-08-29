<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityMatch;
use TalentHub\Learner\Ai\Matching\OpportunityMatchValidator;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Model\ModelOpportunityMatchEngine;
use TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry;
use TalentHub\Learner\Ai\Provider\FakeRecommendationProvider;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function provider_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "provider_contract_violation={$message}\n");
        exit(1);
    }
}

function provider_expect_invalid(callable $operation, string $expected, string $message): void
{
    try {
        $operation();
    } catch (\Throwable $caught) {
        if ($caught instanceof $expected) {
            return;
        }
        fwrite(STDERR, "provider_contract_violation={$message} (got " . $caught::class . ")\n");
        exit(1);
    }
    fwrite(STDERR, "provider_contract_violation={$message} (no exception)\n");
    exit(1);
}

function provider_test_input(): RecommendationInput
{
    $payload = [
        'education_band' => 'high',
        'profile' => ['grade_level' => 11],
        'skills' => [
            ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
            ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
        ],
        'assessments' => [
            ['dimension_scores' => ['logical_thinking' => 88], 'submitted_at' => '2026-08-01T00:00:00.000000+00:00'],
        ],
        'activities' => [
            ['experience_id' => 'exp-1', 'activity_category' => 'STEM', 'tags' => ['python']],
        ],
    ];
    return new RecommendationInput($payload, [], [], [
        ['source_type' => 'skill', 'source_id' => 'python-skill-1', 'observed_at' => '2026-08-01T00:00:00.000000+00:00', 'safe_value' => ['code' => 'python']],
        ['source_type' => 'opportunity', 'source_id' => 'internship-1', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Data Internship']],
        ['source_type' => 'opportunity', 'source_id' => 'internship-2', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'AI Marketing Analytics']],
        ['source_type' => 'opportunity', 'source_id' => 'internship-3', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Green School Design']],
    ]);
}

function provider_test_candidate(string $catalogId, array $overrides = []): OpportunityCandidate
{
    $safe = array_merge([
        'catalog_id' => $catalogId,
        'item_type' => 'internship',
        'title' => "Title for {$catalogId}",
        'provider_name' => "Provider for {$catalogId}",
        'required_skills' => [
            ['code' => 'python', 'minimum_score' => 60],
            ['code' => 'sql', 'minimum_score' => 50],
        ],
        'learning_outcomes' => [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']],
        'education_bands' => ['high', 'college'],
        'deadline_at' => '2026-10-01T00:00:00.000000+00:00',
        'availability' => ['remaining' => 2],
        'status' => 'active',
        'url' => '/app/learner/opportunity.php?id=' . $catalogId,
    ], $overrides);
    return OpportunityCandidate::fromEvidence(['source_type' => 'opportunity', 'source_id' => $catalogId, 'safe_value' => $safe]);
}

function provider_test_context(): RecommendationContext
{
    return new RecommendationContext(
        ConsentDecision::REQUIRED_SCOPES,
        'request-opportunity-0001',
        'idempotency-opportunity-0001',
        'student-1',
        null,
        null,
        false,
    );
}

$stubAuthorizer = new class implements ProviderAttemptAuthorizer {
    public function beforeAttempt(int $attemptNumber): ConsentDecision
    {
        return new ConsentDecision(
            [
                'activity' => ['action' => 'granted', 'policy_version' => 'learner-ai-consent-decision-v1', 'occurred_at' => '2026-08-29T00:00:00.000000+00:00', 'request_id' => 'req-1'],
                'assessment' => ['action' => 'granted', 'policy_version' => 'learner-ai-consent-decision-v1', 'occurred_at' => '2026-08-29T00:00:00.000000+00:00', 'request_id' => 'req-1'],
                'evaluation' => ['action' => 'granted', 'policy_version' => 'learner-ai-consent-decision-v1', 'occurred_at' => '2026-08-29T00:00:00.000000+00:00', 'request_id' => 'req-1'],
                'skills' => ['action' => 'granted', 'policy_version' => 'learner-ai-consent-decision-v1', 'occurred_at' => '2026-08-29T00:00:00.000000+00:00', 'request_id' => 'req-1'],
            ],
            '2026-08-29T00:00:00.000000+00:00',
            ConsentDecision::REQUIRED_SCOPES,
        );
    }
};

$profile = LearnerOpportunityProfile::fromInput(provider_test_input());
$candidates = [
    provider_test_candidate('internship-1'),
    provider_test_candidate('internship-2', [
        'required_skills' => [
            ['code' => 'python', 'minimum_score' => 60],
            ['code' => 'marketing', 'minimum_score' => 40],
        ],
        'learning_outcomes' => [['code' => 'insight', 'label' => 'Customer insight']],
    ]),
    provider_test_candidate('internship-3', [
        'required_skills' => [
            ['code' => 'python', 'minimum_score' => 60],
            ['code' => 'user_research', 'minimum_score' => 40],
        ],
        'learning_outcomes' => [['code' => 'prototype', 'label' => 'Prototype']],
    ]),
];
$scored = [
    'internship-1' => new OpportunityScore(['skill_match' => 30, 'assessment_alignment' => 20, 'experience_relevance' => 10, 'growth_potential' => 12, 'feasibility' => 8]),
    'internship-2' => new OpportunityScore(['skill_match' => 25, 'assessment_alignment' => 15, 'experience_relevance' => 10, 'growth_potential' => 10, 'feasibility' => 8]),
    'internship-3' => new OpportunityScore(['skill_match' => 20, 'assessment_alignment' => 10, 'experience_relevance' => 5, 'growth_potential' => 8, 'feasibility' => 8]),
];

$request = OpportunityMatchPromptRegistry::create($profile, $candidates, $scored, provider_test_context());
provider_assert($request instanceof ProviderRequest, 'prompt registry returns a ProviderRequest');
provider_assert($request->promptVersion() === 'learner-opportunity-match-1.1.0', 'prompt version is fixed');
$payloadJson = json_encode($request->payload(), JSON_THROW_ON_ERROR);
provider_assert(!str_contains($payloadJson, 'student@example.com'), 'prompt payload excludes email');
provider_assert(!str_contains($payloadJson, 'gender'), 'prompt payload excludes protected traits');
$schema = $request->payload()['output_schema']['properties']['items'];
provider_assert($schema['minItems'] === 3, 'schema minItems=3');
provider_assert($schema['maxItems'] === 3, 'schema maxItems=3');
$itemSchema = $schema['items'];
provider_assert(($itemSchema['additionalProperties'] ?? null) === false, 'item additionalProperties=false');
$required = $itemSchema['required'] ?? [];
sort($required);
provider_assert($required === ['catalog_id', 'evidence_ref_ids', 'expected_outcome_codes', 'gemini_score', 'matched_skill_codes', 'missing_skill_codes', 'why_fit'], 'item required fields');
$allowList = $request->payload()['input']['candidate_allow_list'] ?? [];
provider_assert(count($allowList) === 3, 'candidate allow-list contains the supplied candidates');
provider_assert(array_column($allowList, 'catalog_id') === ['internship-1', 'internship-2', 'internship-3'], 'candidate allow-list preserves order and ids');
provider_assert(in_array('skill:python-skill-1', $request->payload()['input']['evidence_allow_list'] ?? [], true), 'prompt exposes profile evidence references to Gemini');
provider_assert(in_array('skill:python-skill-1', $request->evidenceReferenceIds(), true), 'provider request resolves profile evidence references');

$extraCandidates = $candidates;
for ($i = 4; $i <= 12; $i++) {
    $extraCandidates[] = provider_test_candidate("internship-{$i}");
}
$truncatedRequest = OpportunityMatchPromptRegistry::create($profile, $extraCandidates, $scored, provider_test_context());
provider_assert(count($truncatedRequest->payload()['input']['candidate_allow_list']) === 10, 'prompt truncates to at most 10 candidates');

$validator = new OpportunityMatchValidator();
$positiveItems = [
    [
        'catalog_id' => 'internship-1',
        'gemini_score' => 90,
        'why_fit' => 'Python phu hop voi ky nang da xac minh cua ban.',
        'matched_skill_codes' => ['python'],
        'missing_skill_codes' => ['sql'],
        'expected_outcome_codes' => ['dashboard'],
        'evidence_ref_ids' => ['opportunity:internship-1', 'skill:python-skill-1'],
    ],
    [
        'catalog_id' => 'internship-2',
        'gemini_score' => 80,
        'why_fit' => 'AI Marketing phu hop voi kinh nghiem STEM va logic.',
        'matched_skill_codes' => ['python'],
        'missing_skill_codes' => ['marketing'],
        'expected_outcome_codes' => ['insight'],
        'evidence_ref_ids' => ['opportunity:internship-2', 'skill:python-skill-1'],
    ],
    [
        'catalog_id' => 'internship-3',
        'gemini_score' => 70,
        'why_fit' => 'Design Sprint giup ban phat trien ky nang sang tao va lam viec nhom.',
        'matched_skill_codes' => ['python'],
        'missing_skill_codes' => ['user_research'],
        'expected_outcome_codes' => ['prototype'],
        'evidence_ref_ids' => ['opportunity:internship-3', 'skill:python-skill-1'],
    ],
];
$validated = $validator->validate($positiveItems, $candidates, $profile);
provider_assert(count($validated) === 3, 'validator returns 3 matches on positive input');
$ranked = array_map(static fn (OpportunityMatch $match): int => $match->candidate()->catalogId() === 'internship-1' ? 1 : ($match->candidate()->catalogId() === 'internship-2' ? 2 : 3), $validated);
provider_assert($ranked === [1, 2, 3], 'validator preserves the model order from positive input');
provider_assert($validated[0]->whyFit() === 'Python phu hop voi ky nang da xac minh cua ban.', 'why_fit round-trips from validator');
$attached = $validated[0]->withScore($scored['internship-1']->withGeminiScore(90));
provider_assert($attached->score() !== null, 'withScore attaches deterministic score');
provider_assert($attached->score()->finalScore() === 83, 'withScore composes 70/30 with attached gemini score');
provider_assert($validated[0]->score() === null, 'original match has no score before withScore');
provider_assert($validated[0]->candidate()->title() === 'Title for internship-1', 'match canonical title is server-owned');

$positiveItem = $positiveItems[0];
$positiveItem['title'] = 'Fabricated by model';
$positiveItem['url'] = 'https://malicious.example.com';
provider_expect_invalid(
    static fn () => $validator->validate([$positiveItem, $positiveItems[1], $positiveItems[2]], $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects extra properties from model (title/url)'
);

provider_expect_invalid(
    static fn () => $validator->validate(array_slice($positiveItems, 0, 2), $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects when not exactly 3 items'
);
$fourth = $positiveItems[0];
$fourth['catalog_id'] = 'internship-3';
provider_expect_invalid(
    static fn () => $validator->validate([$positiveItems[0], $positiveItems[1], $fourth, $positiveItems[2]], $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects 4 items'
);

$invented = $positiveItems;
$invented[0]['catalog_id'] = 'invented-9999';
provider_expect_invalid(
    static fn () => $validator->validate($invented, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects invented catalog id'
);

$duplicate = $positiveItems;
$duplicate[2]['catalog_id'] = 'internship-1';
provider_expect_invalid(
    static fn () => $validator->validate($duplicate, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects duplicate catalog id'
);

$badScore = $positiveItems;
$badScore[0]['gemini_score'] = -1;
provider_expect_invalid(
    static fn () => $validator->validate($badScore, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects negative gemini score'
);
$badScore[0]['gemini_score'] = 101;
provider_expect_invalid(
    static fn () => $validator->validate($badScore, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects gemini score above 100'
);

$badSkill = $positiveItems;
$badSkill[0]['matched_skill_codes'] = ['unknown_skill'];
provider_expect_invalid(
    static fn () => $validator->validate($badSkill, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects unsupported matched skill code'
);

$badOutcome = $positiveItems;
$badOutcome[0]['expected_outcome_codes'] = ['unknown_outcome'];
provider_expect_invalid(
    static fn () => $validator->validate($badOutcome, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects unsupported outcome code'
);

$missingEvidence = $positiveItems;
$missingEvidence[0]['evidence_ref_ids'] = [];
provider_expect_invalid(
    static fn () => $validator->validate($missingEvidence, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects missing evidence'
);

$foreignEvidence = $positiveItems;
$foreignEvidence[0]['evidence_ref_ids'] = ['opportunity:internship-2'];
provider_expect_invalid(
    static fn () => $validator->validate($foreignEvidence, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects evidence from other candidate'
);

$foreignSkill = $positiveItems;
$foreignSkill[0]['missing_skill_codes'] = ['marketing'];
provider_expect_invalid(
    static fn () => $validator->validate($foreignSkill, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects a required skill belonging to another candidate'
);

$foreignOutcome = $positiveItems;
$foreignOutcome[0]['expected_outcome_codes'] = ['insight'];
provider_expect_invalid(
    static fn () => $validator->validate($foreignOutcome, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects a learning outcome belonging to another candidate'
);

$sameSkill = $positiveItems;
$sameSkill[0]['matched_skill_codes'] = ['python'];
$sameSkill[0]['missing_skill_codes'] = ['python'];
provider_expect_invalid(
    static fn () => $validator->validate($sameSkill, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects same skill in matched and missing'
);

$repeated = $positiveItems;
$repeated[0]['why_fit'] = 'Ban se hoc duoc nhieu ky nang moi tu du an nay.';
$repeated[1]['why_fit'] = 'Ban se hoc duoc nhieu ky nang moi tu du an nay.';
$repeated[2]['why_fit'] = 'Ban se hoc duoc nhieu ky nang moi tu du an nay.';
provider_expect_invalid(
    static fn () => $validator->validate($repeated, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects identical why_fit across items'
);

$nearDuplicate = $positiveItems;
$nearDuplicate[0]['why_fit'] = 'Ky nang Python phu hop voi kinh nghiem STEM da xac minh cua ban trong du an A.';
$nearDuplicate[1]['why_fit'] = 'Ky nang Python phu hop voi kinh nghiem STEM da xac minh cua ban trong du an B.';
$nearDuplicate[2]['why_fit'] = 'Thiet ke thuan loi cho nhom sang tao trong khoa hoc Green School.';
$whyFitA = 'Ky nang Python phu hop voi kinh nghiem STEM da xac minh cua ban trong du an A.';
$whyFitB = 'Ky nang Python phu hop voi kinh nghiem STEM da xac minh cua ban trong du an B.';
$tokensA = preg_split('/[\s\p{P}]+/u', mb_strtolower($whyFitA, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
$tokensB = preg_split('/[\s\p{P}]+/u', mb_strtolower($whyFitB, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
$intersection = count(array_intersect($tokensA, $tokensB));
$union = count(array_unique(array_merge($tokensA, $tokensB)));
$jaccard = $intersection / $union;
provider_assert($jaccard >= 0.85, 'fixture simulates jaccard >= 0.85');
provider_expect_invalid(
    static fn () => $validator->validate($nearDuplicate, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects near-duplicate why_fit (jaccard >= 0.85)'
);

$unsafeVietnamese = $positiveItems;
$unsafeVietnamese[0]['why_fit'] = 'Ban se duoc tuyen vao doanh nghiep sau khi tham gia.';
provider_expect_invalid(
    static fn () => $validator->validate($unsafeVietnamese, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects Vietnamese hiring promise'
);

$unsafeAward = $positiveItems;
$unsafeAward[1]['why_fit'] = 'Ban se dat giai neu hoan thanh xuat sac cac bai tap.';
provider_expect_invalid(
    static fn () => $validator->validate($unsafeAward, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects award promise'
);

$unsafeGuarantee = $positiveItems;
$unsafeGuarantee[2]['why_fit'] = 'Dam bao ban se nhan hoc bong neu dang ky ngay hom nay.';
provider_expect_invalid(
    static fn () => $validator->validate($unsafeGuarantee, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects guarantee claim'
);

foreach ([
    'Bạn được tuyển ngay sau dự án này.',
    'TalentHub cam kết bạn nhận được cơ hội tốt.',
    'Bạn đậu đại học sau dự án này.',
] as $unsafeClaim) {
    $unsafeExistingPattern = $positiveItems;
    $unsafeExistingPattern[0]['why_fit'] = $unsafeClaim;
    provider_expect_invalid(
        static fn () => $validator->validate($unsafeExistingPattern, $candidates, $profile),
        InvalidArgumentException::class,
        'validator reuses existing unsupported-claim coverage'
    );
}

$extraProps = $positiveItems;
$extraProps[0]['fabricated'] = 'value';
provider_expect_invalid(
    static fn () => $validator->validate($extraProps, $candidates, $profile),
    InvalidArgumentException::class,
    'validator rejects extra properties'
);

$engineResponse = ProviderResponse::success($positiveItems);
$engineProvider = new FakeRecommendationProvider($engineResponse);
$engine = new ModelOpportunityMatchEngine($engineProvider, $stubAuthorizer);
$generated = $engine->generate($profile, $candidates, $scored, provider_test_context());
provider_assert(count($generated) === 3, 'engine returns 3 matches');
$geminiScores = array_map(static fn (OpportunityMatch $match): int => $match->geminiScore(), $generated);
provider_assert($geminiScores === [90, 80, 70], 'engine preserves gemini scores in order');
provider_assert($generated[0]->candidate()->title() === 'Title for internship-1', 'engine preserves server-owned title');
$attachedGenerated = $generated[0]->withScore($scored['internship-1']);
provider_assert($attachedGenerated->score() === $scored['internship-1'], 'engine match can carry deterministic score');
$engineRequest = $engineProvider->requests()[0];
provider_assert($engineRequest->promptVersion() === 'learner-opportunity-match-1.1.0', 'engine sends fixed prompt version');
$enginePayloadJson = json_encode($engineRequest->payload(), JSON_THROW_ON_ERROR);
provider_assert(!str_contains($enginePayloadJson, 'student@example.com'), 'engine payload excludes email');
provider_assert(!str_contains($enginePayloadJson, 'phone'), 'engine payload excludes phone field');
provider_assert(!str_contains($enginePayloadJson, 'gender'), 'engine payload excludes gender');

$partialRequest = OpportunityMatchPromptRegistry::create(
    $profile,
    $candidates,
    $scored,
    provider_test_context(),
    'recommendation',
);
$partialSchema = $partialRequest->payload()['output_schema']['properties']['items'];
provider_assert($partialSchema['minItems'] === 1 && $partialSchema['maxItems'] === 3, 'recommendation mode accepts one to three analyses');
$partialValidated = $validator->validate(array_slice($positiveItems, 0, 2), $candidates, $profile, 'recommendation');
provider_assert(count($partialValidated) === 2, 'recommendation mode validates a partial result');

$lowFitItems = [
    [
        'catalog_id' => 'internship-1',
        'gemini_score' => 55,
        'why_not_fit_yet' => 'Python da co nen tang nhung SQL van chua dat muc yeu cau cua du an.',
        'matched_skill_codes' => ['python'],
        'missing_skill_codes' => ['sql'],
        'missing_conditions' => ['sql_minimum_score'],
        'improvement_steps' => ['Luyen truy van SQL voi du lieu mau.'],
        'evidence_ref_ids' => ['opportunity:internship-1', 'skill:python-skill-1'],
    ],
    [
        'catalog_id' => 'internship-2',
        'gemini_score' => 48,
        'why_not_fit_yet' => 'Ky nang Python phu hop mot phan nhung ban con thieu marketing nen tang.',
        'matched_skill_codes' => ['python'],
        'missing_skill_codes' => ['marketing'],
        'missing_conditions' => ['marketing_basics'],
        'improvement_steps' => ['Hoan thanh khoa marketing can ban.'],
        'evidence_ref_ids' => ['opportunity:internship-2', 'skill:python-skill-1'],
    ],
];
$lowFitRequest = OpportunityMatchPromptRegistry::create(
    $profile,
    $candidates,
    $scored,
    provider_test_context(),
    'low_fit',
);
$lowFitSchema = $lowFitRequest->payload()['output_schema']['properties']['items'];
provider_assert($lowFitSchema['minItems'] === 1 && $lowFitSchema['maxItems'] === 3, 'low-fit mode accepts one to three diagnostics');
$lowFitValidated = $validator->validate($lowFitItems, $candidates, $profile, 'low_fit');
provider_assert(count($lowFitValidated) === 2, 'low-fit mode validates project diagnostics');
provider_assert($lowFitValidated[0]->analysisKind() === 'low_fit', 'low-fit analysis kind round-trips');
provider_assert($lowFitValidated[0]->missingConditions() === ['sql_minimum_score'], 'low-fit missing conditions round-trip');

$noFitRequest = OpportunityMatchPromptRegistry::create(
    $profile,
    [],
    [],
    provider_test_context(),
    'no_fit',
    [
        'catalog_demands' => ['sql', 'marketing'],
        'exclusion_reasons' => ['education_band_mismatch' => 4],
    ],
);
$noFitSchema = $noFitRequest->payload()['output_schema'];
provider_assert(($noFitSchema['required'] ?? []) === ['items'], 'no-fit summary keeps the provider items envelope');
$noFitItemSchema = $noFitSchema['properties']['items']['items'] ?? [];
provider_assert(($noFitSchema['properties']['items']['minItems'] ?? null) === 1 && ($noFitSchema['properties']['items']['maxItems'] ?? null) === 1, 'no-fit response contains exactly one summary item');
provider_assert(($noFitItemSchema['required'] ?? []) === ['headline', 'explanation', 'learner_strengths', 'catalog_demands', 'main_gaps', 'next_steps', 'evidence_ref_ids'], 'no-fit summary item schema is explicit');
provider_assert(str_contains(json_encode($noFitRequest->payload(), JSON_THROW_ON_ERROR), 'education_band_mismatch'), 'no-fit prompt carries safe exclusion aggregates');
$noFitInstructions = implode("\n", $noFitRequest->payload()['instructions'] ?? []);
provider_assert(str_contains($noFitInstructions, 'vi-VN'), 'no-fit prompt fixes the learner-facing locale to vi-VN');
provider_assert(str_contains($noFitInstructions, 'tiếng Việt có dấu'), 'no-fit prompt requires natural Vietnamese with diacritics');
provider_assert(str_contains($noFitInstructions, 'Không hiển thị mã kỹ năng'), 'no-fit prompt forbids raw skill codes in learner-facing prose');
$summary = $validator->validateSummary([
    'headline' => 'Chua co co hoi du phu hop',
    'explanation' => 'Cac co hoi hien tai yeu cau SQL va marketing nhieu hon ky nang da xac minh cua ban.',
    'learner_strengths' => ['python'],
    'catalog_demands' => ['sql', 'marketing'],
    'main_gaps' => ['sql'],
    'next_steps' => ['Luyen SQL co ban.'],
    'evidence_ref_ids' => ['skill:python-skill-1'],
], $profile, ['skill:python-skill-1']);
provider_assert(($summary['headline'] ?? '') === 'Chua co co hoi du phu hop', 'no-fit summary validates and round-trips');

$countingAuthorizer = new class($stubAuthorizer) implements ProviderAttemptAuthorizer {
    public int $calls = 0;

    public function __construct(private readonly ProviderAttemptAuthorizer $delegate)
    {
    }

    public function beforeAttempt(int $attemptNumber): ConsentDecision
    {
        $this->calls++;
        return $this->delegate->beforeAttempt($attemptNumber);
    }
};
$countingEngine = new ModelOpportunityMatchEngine(new FakeRecommendationProvider($engineResponse), $countingAuthorizer);
$countingEngine->generate($profile, $candidates, $scored, provider_test_context());
provider_assert($countingAuthorizer->calls === 1, 'one provider attempt performs exactly one consent authorization');

$elevenCandidates = $candidates;
for ($i = 4; $i <= 11; $i++) {
    $elevenCandidates[] = provider_test_candidate("internship-{$i}");
}
$outsideTopTen = $positiveItems;
$outsideTopTen[2] = [
    'catalog_id' => 'internship-11',
    'gemini_score' => 70,
    'why_fit' => 'Du an thu muoi mot nam ngoai danh sach ung vien da gui toi Gemini.',
    'matched_skill_codes' => ['python'],
    'missing_skill_codes' => [],
    'expected_outcome_codes' => ['dashboard'],
    'evidence_ref_ids' => ['opportunity:internship-11'],
];
$outsideTopTenEngine = new ModelOpportunityMatchEngine(
    new FakeRecommendationProvider(ProviderResponse::success($outsideTopTen)),
    $stubAuthorizer,
);
provider_expect_invalid(
    static fn () => $outsideTopTenEngine->generate($profile, $elevenCandidates, $scored, provider_test_context()),
    InvalidArgumentException::class,
    'engine rejects a candidate outside the exact Top 10 sent to Gemini'
);

$invalidResponse = ProviderResponse::success([
    ['catalog_id' => 'invented-9999', 'gemini_score' => 50, 'why_fit' => 'a', 'matched_skill_codes' => ['python'], 'missing_skill_codes' => ['sql'], 'expected_outcome_codes' => ['dashboard'], 'evidence_ref_ids' => ['opportunity:internship-1']],
    ['catalog_id' => 'internship-2', 'gemini_score' => 50, 'why_fit' => 'b', 'matched_skill_codes' => ['python'], 'missing_skill_codes' => ['sql'], 'expected_outcome_codes' => ['dashboard'], 'evidence_ref_ids' => ['opportunity:internship-2']],
    ['catalog_id' => 'internship-3', 'gemini_score' => 50, 'why_fit' => 'c', 'matched_skill_codes' => ['python'], 'missing_skill_codes' => ['sql'], 'expected_outcome_codes' => ['dashboard'], 'evidence_ref_ids' => ['opportunity:internship-3']],
]);
$invalidEngine = new ModelOpportunityMatchEngine(new FakeRecommendationProvider($invalidResponse), $stubAuthorizer);
provider_expect_invalid(
    static fn () => $invalidEngine->generate($profile, $candidates, $scored, provider_test_context()),
    InvalidArgumentException::class,
    'engine rejects invented id from provider'
);

$fabricatedResponse = ProviderResponse::success($positiveItems);
$fabricatedResponseItems = $fabricatedResponse->items();
$fabricatedResponseItems[0]['title'] = 'Fabricated Title';
$fabricatedResponseItems[0]['url'] = 'https://malicious.example.com';
$fabricatedEngine = new ModelOpportunityMatchEngine(new FakeRecommendationProvider(ProviderResponse::success($fabricatedResponseItems)), $stubAuthorizer);
$fabricatedMatches = $fabricatedEngine->generate($profile, $candidates, $scored, provider_test_context());
provider_assert($fabricatedMatches[0]->candidate()->title() === 'Title for internship-1', 'engine ignores fabricated title from provider');
provider_assert($fabricatedMatches[0]->candidate()->canonicalUrl() === '/app/learner/opportunity.php?id=internship-1', 'engine ignores fabricated url from provider');

$tooFewCandidates = [provider_test_candidate('internship-1'), provider_test_candidate('internship-2')];
provider_expect_invalid(
    static fn () => $engine->generate($profile, $tooFewCandidates, $scored, provider_test_context()),
    InvalidArgumentException::class,
    'engine rejects fewer than 3 candidates'
);

echo "learner_ai_opportunity_provider_test: OK\n";
