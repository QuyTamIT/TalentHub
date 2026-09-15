<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\CareerRoleBenchmark;
use TalentHub\Learner\Ai\Matching\JobMatchScorer;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\SkillGapResolver;
use TalentHub\Learner\Ai\Matching\StructuredOpportunityScorer;
use TalentHub\Learner\Ai\Model\JobMatchPromptRegistry;
use TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry;

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void { if (!$ok) $failures[] = $message; };
$makeProfile = static fn (int $score) => LearnerOpportunityProfile::fromInput(new RecommendationInput([
    'education_band' => 'college', 'skills' => [['code' => 'python', 'score' => $score]],
    'assessments' => [
        ['test_type' => 'disc', 'dimension_scores' => ['I' => 20]],
        ['test_type' => 'holland', 'dimension_scores' => ['I' => 80]],
    ],
    'certificates' => [[
        'id' => 'cert-camel', 'title' => 'Python Associate', 'issuingOrganization' => 'Example Org',
        'issueDate' => '2026-08-03', 'verificationStatus' => 'unverified', 'updatedAt' => '2026-08-04',
    ], [
        'source_id' => 'cert-snake', 'title' => 'Data Basics', 'issuer' => 'Safe Institute',
        'issue_date' => '2026-08-01', 'verification_status' => 'pending', 'updated_at' => '2026-08-02',
    ]],
], [], [], [
    ['source_type' => 'skill', 'source_id' => 'python', 'safe_value' => ['code' => 'python']],
    ['source_type' => 'certificate', 'source_id' => 'cert-camel', 'observed_at' => '2026-08-04', 'safe_value' => [
        'title' => 'Python Associate', 'issuingOrganization' => 'Example Org', 'issueDate' => '2026-08-03', 'verificationStatus' => 'unverified',
    ]],
    ['source_type' => 'certificate', 'source_id' => 'cert-snake', 'observed_at' => '2026-08-02', 'safe_value' => [
        'title' => 'Data Basics', 'issuer' => 'Safe Institute', 'issue_date' => '2026-08-01', 'verification_status' => 'pending',
    ]],
]));
$makeCandidate = static fn (array $outcomes) => OpportunityCandidate::fromEvidence([
    'source_type' => 'opportunity', 'source_id' => 'project-1', 'safe_value' => [
        'catalog_id' => 'project-1', 'item_type' => 'project', 'title' => 'Python project',
        'provider_name' => 'School', 'status' => 'active', 'url' => '/projects/1',
        'required_skills' => [['code' => 'python', 'label' => 'Python', 'minimum_score' => 70]],
        'learning_outcomes' => $outcomes,
    ],
]);
$weak = $makeProfile(20);
$strong = $makeProfile(90);
$certificates = $weak->certificates();
$assert(count($certificates) === 2, 'Certificate context must accept camelCase and snake_case aliases.');
$assert($certificates[0] === [
    'source_id' => 'cert-camel', 'title' => 'Python Associate', 'issuer' => 'Example Org',
    'issue_date' => '2026-08-03', 'verification_status' => 'unverified',
], 'Newest certificate must preserve bounded metadata and unverified status.');
$assert($weak->skills() === ['python' => 20] && $weak->confirmedExperienceTags() === [], 'Unverified certificate must not create or boost a scored skill or confirmed experience.');
$plain = $makeCandidate([]);
$training = $makeCandidate([['code' => 'python', 'label' => 'Python']]);
$scorer = new StructuredOpportunityScorer();
$weakPlain = $scorer->score($weak, $plain);
$weakTraining = $scorer->score($weak, $training);
$assert($weakPlain->finalScore() === $weakTraining->finalScore(), 'Project learning outcomes must not award fit points for a learner weakness.');
$assert($scorer->score($strong, $plain)->finalScore() > $weakTraining->finalScore(), 'Existing requirement attainment must rank above missing skills.');
$assert($scorer->score($strong, $plain)->breakdown()['experience_relevance'] === 0, 'A scored skill alone is not confirmed project experience.');

$role = new CareerRoleBenchmark('python_developer', 'Python developer', 'technology', [
    ['code' => 'python', 'label' => 'Python', 'minimum_score' => 70, 'weight' => 100.0, 'required' => true],
], []);
$job = (new JobMatchScorer())->score($weak, $plain, $role);
$verifiedOnly = LearnerOpportunityProfile::fromInput(new RecommendationInput([
    'education_band' => 'college',
    'portfolio_skills' => [['code' => 'python', 'verification_status' => 'verified']],
], [], [], []));
$verifiedJob = (new JobMatchScorer())->score($verifiedOnly, $plain, $role);
$assert($verifiedOnly->skillScore('python') === null, 'Verified portfolio skill without a score must remain unscored.');
$assert($verifiedJob->score()->skillScore() === 0 && $verifiedJob->score()->experienceScore() === 100, 'Confirmed portfolio evidence earns experience only, never an invented mastery score.');
$assert($scorer->score($verifiedOnly, $plain)->breakdown()['skill_match'] === 0 && $scorer->score($verifiedOnly, $plain)->breakdown()['experience_relevance'] === 15, 'Project matching must separate verified experience from requirement mastery.');
$context = new RecommendationContext(['skills', 'assessments'], 'strength-test', 'strength-test', 'student-1');
$requests = [
    JobMatchPromptRegistry::create($weak, [$plain], ['project-1' => $job], ['project-1' => (new SkillGapResolver())->resolve($job)], $context),
    OpportunityMatchPromptRegistry::create($weak, [$plain], ['project-1' => $weakPlain], $context, 'recommendation'),
];
foreach ($requests as $request) {
    $payload = $request->payload();
    $student = $payload['input']['student_profile'];
    $assert(($student['assessment_signals'] ?? null) === $weak->assessmentSignals(), 'Scorer and explanation must receive identical family-specific assessment signals.');
    $assert(!array_key_exists('assessment_dimensions', $student), 'Ambiguous cross-family dimensions must not reach the model.');
    $assert(($student['confirmed_experience_tags'] ?? null) === [], 'Prompt must distinguish confirmed experience from standalone skill scores.');
    $assert(($student['certificates'] ?? null) === $weak->certificates(), 'Project and job prompts must serialize current certificate metadata.');
    $instructionText = implode("\n", $payload['instructions'] ?? []);
    $assert(str_contains($instructionText, 'không tự tăng điểm'), 'Prompt must forbid certificate-based automatic score boosts.');
    $assert(($payload['input']['matching_objective'] ?? '') === 'current_strengths_and_requirement_attainment', 'Prompt must explicitly ground matching in existing strengths and requirement attainment.');
}
if ($failures !== []) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "learner_ai_strength_matching_test: OK\n";
