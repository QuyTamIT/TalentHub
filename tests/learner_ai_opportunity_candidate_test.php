<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "candidate_contract_violation={$message}\n");
        exit(1);
    }
}

function candidate_expect_invalid(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    candidate_assert(false, $message);
}

function candidate_test_input(array $overrides = []): RecommendationInput
{
    $payload = array_merge([
        'education_band' => 'high',
        'skills' => [
            ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
            ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
        ],
        'assessments' => [
            [
                'dimension_scores' => ['Logical Thinking' => 88, 'creativity' => 61],
                'submitted_at' => '2026-08-01T00:00:00.000000+00:00',
            ],
        ],
        'activities' => [
            ['experience_id' => 'exp-1', 'activity_category' => 'Robotics Club', 'hours' => 20],
            ['experience_id' => 'exp-2', 'activity_category' => 'STEM', 'tags' => ['IoT', 'python']],
        ],
        'profile' => ['grade_level' => 11, 'class_name' => '12A1'],
        'gender' => 'female',
        'email' => 'student@example.com',
    ], $overrides);

    return new RecommendationInput($payload, [], [], [
        ['source_type' => 'skill', 'source_id' => 'python-skill-1', 'observed_at' => '2026-08-01T00:00:00.000000+00:00', 'safe_value' => ['code' => 'python', 'level_score' => 82]],
        ['source_type' => 'opportunity', 'source_id' => 'internship-1', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Data Internship']],
    ]);
}

$profile = LearnerOpportunityProfile::fromInput(candidate_test_input([
    'education_band' => 'high',
    'skills' => [
        ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
        ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
    ],
]));
candidate_assert($profile->educationBand() === 'high', 'profile education band');
candidate_assert($profile->skillScore('python') === 82, 'verified python skill score');
candidate_assert($profile->skillScore('email') === null, 'unknown skill score is null');
candidate_assert($profile->skills() === ['python' => 82, 'sql' => 35], 'skills map');
candidate_assert($profile->assessmentDimensions() === ['logical_thinking' => 88.0, 'creativity' => 61.0], 'assessment dimensions');
candidate_assert($profile->experienceTags() === ['robotics_club', 'stem', 'iot', 'python'], 'experience tags');
candidate_assert($profile->evidenceRefs() === ['skill:python-skill-1', 'opportunity:internship-1'], 'evidence refs');
candidate_assert($profile->skillScore('PYTHON') === 82, 'skill lookup is code-normalized');

$normalizedProfile = LearnerOpportunityProfile::fromInput(candidate_test_input([
    'skills' => [['code' => 'IoT cơ bản', 'score' => 55], ['code' => 'data_analysis', 'level_score' => 70.4]],
]));
candidate_assert($normalizedProfile->skillScore('iot_co_ban') === 55, 'vietnamese display name normalizes to canonical code');
candidate_assert($normalizedProfile->skillScore('data_analysis') === 70, 'level_score normalizes to integer');

$gradeProfile = LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => null]));
candidate_assert($gradeProfile->educationBand() === 'high', 'grade 11 derives high band');
$collegeProfile = LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => null, 'profile' => ['grade_level' => 13]]));
candidate_assert($collegeProfile->educationBand() === 'college', 'grade 13 derives college band');

candidate_expect_invalid(
    static fn (): LearnerOpportunityProfile => LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => 'doctorate'])),
    'unknown education band rejected'
);
candidate_expect_invalid(
    static fn (): LearnerOpportunityProfile => LearnerOpportunityProfile::fromInput(candidate_test_input([
        'skills' => [['code' => 'python', 'score' => 82], ['code' => 'Python', 'score' => 40]],
    ])),
    'duplicate canonical skill code rejected'
);
candidate_expect_invalid(
    static fn (): LearnerOpportunityProfile => LearnerOpportunityProfile::fromInput(candidate_test_input([
        'skills' => [['code' => 'python', 'score' => 150]],
    ])),
    'out of range skill score rejected'
);

$candidate = OpportunityCandidate::fromEvidence([
    'source_type' => 'opportunity',
    'source_id' => 'internship-1',
    'safe_value' => [
        'catalog_id' => 'internship-1',
        'item_type' => 'internship',
        'title' => 'Data Internship',
        'provider_name' => 'Verified Enterprise',
        'required_skills' => [['code' => 'python', 'minimum_score' => 60], ['code' => 'sql', 'minimum_score' => 50]],
        'learning_outcomes' => [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']],
        'education_bands' => ['high', 'college'],
        'deadline_at' => '2026-10-01T00:00:00.000000+00:00',
        'availability' => ['remaining' => 2],
        'status' => 'active',
        'url' => '/app/learner/ecosystem.php?tab=opportunities&focus=internship-1',
    ],
]);
candidate_assert($candidate->catalogId() === 'internship-1', 'candidate catalog id');
candidate_assert($candidate->catalogType() === 'internship', 'candidate catalog type');
candidate_assert($candidate->title() === 'Data Internship', 'candidate title');
candidate_assert($candidate->providerName() === 'Verified Enterprise', 'candidate provider name');
candidate_assert($candidate->canonicalUrl() === '/app/learner/ecosystem.php?tab=opportunities&focus=internship-1', 'candidate canonical url');
candidate_assert($candidate->requiredSkills() === [
    ['code' => 'python', 'minimum_score' => 60, 'label' => 'python'],
    ['code' => 'sql', 'minimum_score' => 50, 'label' => 'sql'],
], 'candidate required skills');
candidate_assert($candidate->learningOutcomes() === [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']], 'candidate learning outcomes');
candidate_assert($candidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === true, 'active candidate is eligible');

$payload = $candidate->providerPayload();
candidate_assert(($payload['catalog_id'] ?? '') === 'internship-1' && ($payload['title'] ?? '') === 'Data Internship', 'provider payload exposes canonical fields');
candidate_assert(!array_key_exists('gender', $payload) && !array_key_exists('eligibility', $payload), 'provider payload is allow-listed');

$displaySkillCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => [
        'catalog_id' => 'project-7',
        'item_type' => 'project',
        'title' => 'Smart Campus IoT',
        'required_skills' => [['code' => 'IoT cơ bản', 'minimum_score' => 0]],
        'url' => 'https://talenthub.vn/projects/smart-campus-iot',
    ],
]);
candidate_assert(array_column($displaySkillCandidate->requiredSkills(), 'code') === ['iot_co_ban'], 'display skill name normalizes to canonical code');
candidate_assert($displaySkillCandidate->canonicalUrl() === 'https://talenthub.vn/projects/smart-campus-iot', 'verified https external url accepted');

$closedCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c2', 'title' => 'Closed', 'status' => 'closed', 'url' => '/x'],
]);
candidate_assert($closedCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'closed candidate is not eligible');

$expiredCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c3', 'title' => 'Expired', 'deadline_at' => '2026-08-01T00:00:00.000000+00:00', 'url' => '/x'],
]);
candidate_assert($expiredCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'expired candidate is not eligible');

$fullCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c4', 'title' => 'Full', 'availability' => ['remaining' => 0], 'url' => '/x'],
]);
candidate_assert($fullCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'full candidate is not eligible');

$collegeOnlyCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c5', 'title' => 'College only', 'education_bands' => ['college'], 'url' => '/x'],
]);
candidate_assert($collegeOnlyCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'education band mismatch is not eligible');

$noBandProfile = LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => null, 'profile' => []]));
candidate_assert($collegeOnlyCandidate->isEligibleFor($noBandProfile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'unknown learner band is not eligible against restricted candidate');
$openCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c6', 'title' => 'Open', 'education_bands' => ['high', 'college'], 'url' => '/x'],
]);
candidate_assert($openCandidate->isEligibleFor($noBandProfile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'restricted candidate rejects null learner band');
$unrestrictedCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c7', 'title' => 'Unrestricted', 'url' => '/x'],
]);
candidate_assert($unrestrictedCandidate->isEligibleFor($noBandProfile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === true, 'candidate without education bands accepts null learner band');

candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c8', 'title' => '', 'url' => '/x']]),
    'empty title rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => '', 'title' => 'X', 'url' => '/x']]),
    'empty catalog id rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c9', 'title' => 'X', 'url' => 'javascript:alert(1)']]),
    'javascript url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c10', 'title' => 'X', 'url' => 'data:text/html,x']]),
    'data url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c11', 'title' => 'X', 'url' => '//evil.example.com/x']]),
    'protocol-relative url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c12', 'title' => 'X', 'url' => 'http://external.example.com/x']]),
    'plain http external url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c13', 'title' => 'X', 'education_bands' => ['phd'], 'url' => '/x']]),
    'unknown education band rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c14', 'title' => 'X', 'required_skills' => [['code' => 'python', 'minimum_score' => 101]], 'url' => '/x']]),
    'out of range minimum score rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c15', 'title' => 'X', 'required_skills' => [['code' => 'python', 'minimum_score' => 10], ['code' => 'Python', 'minimum_score' => 20]], 'url' => '/x']]),
    'duplicate canonical skill code rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c16', 'title' => 'X', 'required_skills' => [['code' => 'Must know SQL and be able to write production ready queries against relational databases', 'minimum_score' => 0]], 'url' => '/x']]),
    'requirement prose rejected as skill code'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c17', 'title' => 'X', 'gender' => 'female', 'url' => '/x']]),
    'protected trait key rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c18', 'title' => 'X', 'deadline_at' => 'not-a-date', 'url' => '/x']]),
    'malformed deadline rejected'
);

echo "learner_ai_opportunity_candidate_test: OK\n";
