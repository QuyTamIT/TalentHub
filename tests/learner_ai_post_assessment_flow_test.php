<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Service\PostAssessmentAiTrigger;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function post_assessment_ai_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function onboarding_progress(int $completed, string $status): array
{
    return ['required'=>true,'status'=>$status,'completed_count'=>$completed,'required_count'=>4];
}

for ($completed = 0; $completed < 3; $completed++) {
    $metadata = PostAssessmentAiTrigger::metadata(
        onboarding_progress($completed, 'accepted'),
        onboarding_progress($completed + 1, 'accepted'),
    );
    post_assessment_ai_assert($metadata === ['required'=>false,'state'=>'not_required'], "assessment " . ($completed + 1) . ' does not trigger AI');
}

$fourth = PostAssessmentAiTrigger::metadata(onboarding_progress(3, 'accepted'), onboarding_progress(4, 'completed'));
post_assessment_ai_assert($fourth === ['required'=>true,'state'=>'not_generated'], 'fourth assessment requests one AI analysis');
$alreadyCompleted = PostAssessmentAiTrigger::metadata(onboarding_progress(4, 'completed'), onboarding_progress(4, 'completed'));
post_assessment_ai_assert($alreadyCompleted['required'] === false, 'replay after completion never requests another analysis');
$legacy = PostAssessmentAiTrigger::metadata(['required'=>false], ['required'=>false]);
post_assessment_ai_assert($legacy['required'] === false, 'legacy learner without onboarding is not auto-triggered');

$endpoint = (string) file_get_contents(dirname(__DIR__) . '/app/learner/api/v1/assessment-submit.php');
post_assessment_ai_assert(str_contains($endpoint, 'PostAssessmentAiTrigger::metadata'), 'submit endpoint uses the transition policy');
post_assessment_ai_assert(str_contains($endpoint, "['ai_analysis']"), 'submit response includes AI trigger metadata');
post_assessment_ai_assert(str_contains($endpoint, 'discover.php?onboarding=completed&ai=analyze'), 'fourth submit routes to AI summary flow');
post_assessment_ai_assert(!str_contains($endpoint, 'roadmapService('), 'assessment transaction never calls the AI provider');

echo "learner_ai_post_assessment_flow_test: OK\n";
