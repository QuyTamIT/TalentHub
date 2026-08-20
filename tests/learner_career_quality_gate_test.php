<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Quality\DataQualityGate;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

$input = new RecommendationInput(
    [
        'profile' => ['study_status' => 'active'],
        'skills' => [],
        'assessments' => [[
            'test_code' => 'holland',
            'submitted_at' => '2026-08-18T10:00:00.000000+00:00',
            'dimension_scores' => ['R' => 90, 'I' => 80, 'A' => 40, 'S' => 30, 'E' => 50, 'C' => 45],
        ]],
        'activities' => [],
        'evaluations' => [],
        'opportunities' => [],
    ],
    [],
    [
        'missing_consent_scopes' => [],
    ],
    [],
);

$gate = new DataQualityGate(new \DateTimeImmutable('2026-08-18T12:00:00+00:00', new \DateTimeZone('UTC')), true);
$result = $gate->evaluate($input);
if ($result->state() !== 'ready') {
    fwrite(STDERR, 'Expected assessment-only Holland flow to be ready, got ' . $result->state() . "\n");
    exit(1);
}

echo "learner_career_quality_gate_test: OK\n";

$opportunitySource = new class implements \TalentHub\Learner\Ai\Sources\OpportunitySource {
    public function forStudent(string $studentId): array
    {
        return [
            ['opportunity_id' => '00000000-0000-4000-8000-000000000301', 'title' => 'Career activity', 'location' => 'School', 'deadline_at' => '2026-09-01T00:00:00.000000+00:00', 'opportunity_type' => 'activity', 'category' => 'career_technical'],
            ['opportunity_id' => '00000000-0000-4000-8000-000000000901', 'title' => 'Internship', 'location' => 'Company', 'deadline_at' => '2026-09-01T00:00:00.000000+00:00', 'opportunity_type' => 'internship'],
        ];
    }
};
$emptyProfile = new class implements \TalentHub\Learner\Ai\Sources\StudentProfileSource {
    public function forStudent(string $studentId): array { return ['study_status' => 'active']; }
};
$emptyListSource = new class implements \TalentHub\Learner\Ai\Sources\SkillSource, \TalentHub\Learner\Ai\Sources\AssessmentSource, \TalentHub\Learner\Ai\Sources\ActivityExperienceSource, \TalentHub\Learner\Ai\Sources\PublishedEvaluationSource {
    public function forStudent(string $studentId): array { return []; }
};
$snapshot = new \TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder(
    $emptyProfile,
    $emptyListSource,
    $emptyListSource,
    $emptyListSource,
    $emptyListSource,
    $opportunitySource,
);
$withoutActivityConsent = $snapshot->build('student-1', ['assessment', 'skills', 'evaluation']);
$opportunityTypes = array_map(
    static fn (array $opportunity): string => (string) ($opportunity['opportunity_type'] ?? ''),
    $withoutActivityConsent->payload()['opportunities']
);
if ($opportunityTypes !== ['internship']) {
    fwrite(STDERR, 'Activity opportunities must be filtered when activity consent is absent.\n');
    exit(1);
}

echo "learner_career_quality_gate_test: consent filtering OK\n";
