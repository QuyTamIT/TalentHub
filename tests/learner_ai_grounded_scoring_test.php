<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\CareerRoleBenchmark;
use TalentHub\Learner\Ai\Matching\JobMatchScorer;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Matching\SkillGapResolver;
use TalentHub\Learner\Ai\Matching\StructuredOpportunityScorer;

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};

$profile = LearnerOpportunityProfile::fromInput(new RecommendationInput([
    'education_band' => 'college',
    'skills' => [['code' => 'python', 'score' => 80], ['code' => 'sql', 'score' => 80]],
    'assessments' => [['test_type' => 'holland', 'dimension_scores' => ['I' => 100]]],
], [], [], []));
$role = new CareerRoleBenchmark('data_analyst', 'Data Analyst', 'data', [
    ['code' => 'python', 'label' => 'Python', 'minimum_score' => 70, 'weight' => 50.0, 'required' => true],
    ['code' => 'sql', 'label' => 'SQL', 'minimum_score' => 75, 'weight' => 50.0, 'required' => true],
], [['family' => 'holland', 'dimension' => 'I', 'target' => 80.0, 'weight' => 100.0]]);
$candidate = static function (string $id, array $skills, string $category = ''): OpportunityCandidate {
    return OpportunityCandidate::fromEvidence(['source_type' => 'opportunity', 'source_id' => $id, 'safe_value' => [
        'catalog_id' => $id, 'item_type' => 'internship', 'enterprise_id' => 'ent-1', 'title' => $id,
        'provider_name' => 'Test', 'status' => 'active', 'url' => '/jobs/' . $id,
        'category' => $category, 'required_skills' => $skills,
    ]]);
};
$baseline = $candidate('baseline', [
    ['code' => 'python', 'minimum_score' => 70, 'label' => 'Python'],
    ['code' => 'sql', 'minimum_score' => 75, 'label' => 'SQL'],
]);
$demanding = $candidate('demanding', [
    ['code' => 'python', 'minimum_score' => 95, 'label' => 'Python'],
    ['code' => 'sql', 'minimum_score' => 95, 'label' => 'SQL'],
    ['code' => 'docker', 'minimum_score' => 90, 'label' => 'Docker'],
]);
$jobScorer = new JobMatchScorer();
$baselineMatch = $jobScorer->score($profile, $baseline, $role);
$demandingMatch = $jobScorer->score($profile, $demanding, $role);
$assert($demandingMatch->score()->skillScore() < $baselineMatch->score()->skillScore(), 'Candidate-specific higher thresholds and extra skills must reduce skill readiness.');
$assert(count($demandingMatch->skillEvaluations()) === 3, 'Candidate-only requirements must be merged into scored skill evaluations.');
$docker = array_values(array_filter($demandingMatch->skillEvaluations(), static fn (array $e): bool => $e['code'] === 'docker'))[0] ?? [];
$assert(($docker['target_score'] ?? null) === 90 && ($docker['target_basis'] ?? null) === 'candidate', 'Candidate-only targets must retain candidate provenance.');
$assert($demandingMatch->score()->tier() !== 'good_fit' && $demandingMatch->score()->tier() !== 'strong_fit', 'A missing mandatory candidate skill must prevent a good-fit result despite assessment alignment.');
$lowerTarget = $candidate('lower-target', [['code' => 'python', 'minimum_score' => 40, 'label' => 'Python']]);
$lowerEvaluation = array_values(array_filter($jobScorer->score($profile, $lowerTarget, $role)->skillEvaluations(), static fn (array $e): bool => $e['code'] === 'python'))[0] ?? [];
$assert(($lowerEvaluation['target_score'] ?? null) === 40 && ($lowerEvaluation['target_basis'] ?? null) === 'candidate', 'A positive candidate threshold must replace the role threshold and retain candidate authority.');

$unspecified = $candidate('unspecified', [['code' => 'docker', 'minimum_score' => 0, 'label' => 'Docker']]);
$unspecifiedMatch = $jobScorer->score($profile, $unspecified, new CareerRoleBenchmark('platform_role', 'Platform', 'technology', [], []));
$unknown = $unspecifiedMatch->skillEvaluations()[0] ?? [];
$assert(($unknown['target_is_approximate'] ?? null) === true && ($unknown['target_basis'] ?? null) === 'candidate_unspecified', 'A source minimum_score of zero must be labeled approximate/unspecified.');
$assert(array_key_exists('target_score', $unknown) && $unknown['target_score'] === null, 'An unknown candidate target must remain null.');
$assert(array_key_exists('current_score', $unknown) && $unknown['current_score'] === null, 'A missing learner observation must remain null.');
$assert(($unknown['is_met'] ?? true) === false, 'An unknown candidate target must not be treated as attained.');
$unknownGap = (new SkillGapResolver())->resolve($unspecifiedMatch)['skills_missing'][0] ?? [];
$assert(array_key_exists('target_score', $unknownGap) && $unknownGap['target_score'] === null && ($unknownGap['target_basis'] ?? null) === 'candidate_unspecified', 'Skill gap output must preserve unknown target and provenance.');
$assert(array_key_exists('current_score', $unknownGap) && $unknownGap['current_score'] === null && $unknownGap['gap_score'] === null, 'Skill gap output must preserve unknown observation and gap.');
$approximateRole = $candidate('approximate-role', [['code' => 'python', 'minimum_score' => 0, 'label' => 'Python']]);
$approximateEvaluation = $jobScorer->score($profile, $approximateRole, $role)->skillEvaluations()[0] ?? [];
$assert(array_key_exists('target_score', $approximateEvaluation) && $approximateEvaluation['target_score'] === null && ($approximateEvaluation['target_is_approximate'] ?? null) === true, 'A source minimum_score of zero must remain unknown even when a role benchmark exists.');

$projectCandidate = $candidate('project-i', [], 'I');
$profile0 = LearnerOpportunityProfile::fromInput(new RecommendationInput(['education_band' => 'college', 'assessments' => [['test_type' => 'holland', 'dimension_scores' => ['I' => 0]]]], [], [], []));
$profile100 = LearnerOpportunityProfile::fromInput(new RecommendationInput(['education_band' => 'college', 'assessments' => [['test_type' => 'holland', 'dimension_scores' => ['I' => 100]]]], [], [], []));
$structured = new StructuredOpportunityScorer(new DateTimeImmutable('2026-09-05T00:00:00Z'));
$score0 = $structured->score($profile0, $projectCandidate)->breakdown()['assessment_alignment'];
$score100 = $structured->score($profile100, $projectCandidate)->breakdown()['assessment_alignment'];
$assert($score0 === 0 && $score100 === OpportunityScore::MAX['assessment_alignment'], 'Project assessment alignment must use the numeric Holland value.');
$unmapped = $candidate('project-unmapped', [], 'software');
$assert($structured->score($profile100, $unmapped)->breakdown()['assessment_alignment'] === 0, 'Unmapped project categories must receive no inferred assessment credit.');
$unknownThresholdCandidate = $candidate('unknown-threshold', [['code' => 'python', 'minimum_score' => 0, 'label' => 'Python']]);
$unknownStructured = $structured->score($profile, $unknownThresholdCandidate)->breakdown();
$assert($unknownStructured['skill_match'] === 0 && $unknownStructured['growth_potential'] === 0, 'An unspecified project threshold must not count as met or award full growth potential.');

$deterministic = new OpportunityScore(['skill_match' => 20, 'assessment_alignment' => 10, 'experience_relevance' => 5, 'growth_potential' => 5, 'feasibility' => 5]);
$assert($deterministic->finalScore() === 45, 'Structured score must be publishable without Gemini.');
$assert($deterministic->withGeminiScore(0)->finalScore() === 45 && $deterministic->withGeminiScore(100)->finalScore() === 45, 'Gemini score must remain diagnostic and never alter finalScore.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK learner AI grounded scoring\n";
