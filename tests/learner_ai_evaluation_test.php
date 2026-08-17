<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Evaluation\ShadowRunService;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/learner_ai_rule_cases_fixture.php';

function evaluation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

evaluation_assert(class_exists(RecommendationEvaluator::class), 'recommendation evaluator exists');
evaluation_assert(class_exists(ShadowRunService::class), 'shadow run service exists');

$input = learner_rule_input(
    [learner_rule_iot_skill()],
    [learner_rule_holland()],
    [learner_rule_technical_activity()],
    [learner_rule_evaluation()],
);
$context = new RecommendationContext(['assessment', 'skills', 'activity', 'evaluation'], 'request-shadow-1', 'idempotency-shadow-1', 'student-shadow-1');
$rule = (new RuleRecommendationEngine())->generate($input, $context);
$evaluator = new RecommendationEvaluator();
$valid = $evaluator->evaluate($rule, $input, 125.0, 0.004);
evaluation_assert($valid['valid'] === true && $valid['metrics']['evidence_coverage'] === 1.0, 'rule result has complete source-backed evidence');

$hiddenSource = new RecommendationResult('model', null, 'fake', 'model-1', 'prompt-1', null, [
    new RecommendationItem('strength', 'Tiêu đề an toàn', 'Nội dung an toàn.', 20, 'medium', ['type' => 'develop_skill', 'skill_code' => 'iot'], [
        new RecommendationEvidence('skill', 'not-in-snapshot', null, 'model_source', ['code' => 'iot']),
    ]),
]);
$hidden = $evaluator->evaluate($hiddenSource, $input);
evaluation_assert($hidden['valid'] === false && in_array('hidden_source', $hidden['violations'], true), 'hidden source use fails evaluation');

$absoluteClaim = new RecommendationResult('model', null, 'fake', 'model-1', 'prompt-1', null, [
    new RecommendationItem('strength', 'Bạn chắc chắn được tuyển', 'Nội dung không an toàn.', 20, 'medium', ['type' => 'develop_skill', 'skill_code' => 'iot'], [
        new RecommendationEvidence('skill', 'skill-iot', null, 'model_source', ['code' => 'iot']),
    ]),
]);
$absolute = $evaluator->evaluate($absoluteClaim, $input);
evaluation_assert($absolute['valid'] === false && in_array('unsupported_claim', $absolute['violations'], true), 'absolute career claim fails evaluation');

$unsafeAdvice = new RecommendationResult('model', null, 'fake', 'model-1', 'prompt-1', null, [
    new RecommendationItem('improvement', 'Tiêu đề an toàn', 'Bạn nên bỏ học để tập trung làm dự án.', 20, 'medium', ['type' => 'develop_skill', 'skill_code' => 'iot'], [
        new RecommendationEvidence('skill', 'skill-iot', null, 'model_source', ['code' => 'iot']),
    ]),
]);
$unsafe = $evaluator->evaluate($unsafeAdvice, $input);
evaluation_assert($unsafe['valid'] === false && in_array('unsafe_advice', $unsafe['violations'], true), 'unsafe advice fails evaluation');

$groups = $evaluator->groupMetrics(['group-a' => [$valid, $hidden]], 3);
evaluation_assert($groups['group-a']['status'] === 'insufficient_sample', 'small groups are not scored for bias');

$model = new class implements RecommendationEngine {
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        return new RecommendationResult('model', null, 'fake', 'model-1', 'prompt-1', null, [
            new RecommendationItem('strength', 'Gợi ý shadow', 'Dựa trên kỹ năng đã xác minh.', 20, 'medium', ['type' => 'develop_skill', 'skill_code' => 'iot'], [
                new RecommendationEvidence('skill', 'skill-iot', null, 'model_source', ['code' => 'iot']),
            ]),
        ]);
    }
};
$repository = new class implements RecommendationRepository {
    public ?RecommendationResult $completed = null;
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array { return ['runId' => 'shadow-run-1', 'reused' => false]; }
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array { $this->completed = $result; return ['runId' => $runId]; }
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void {}
    public function latestForStudent(string $studentId): ?array { return null; }
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array { return []; }
};
$shadow = new ShadowRunService($repository, $model, $evaluator);
$shadowResult = $shadow->run('student-shadow-1', $input, $context, $rule);
evaluation_assert($shadowResult['visible_result'] === $rule && $repository->completed?->engineType() === 'model', 'shadow model run is persisted without replacing visible rules');

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/app/learner/tools/ai-evaluate.php')
    . ' --fixture=' . escapeshellarg(__DIR__ . '/learner_ai_rule_cases_fixture.php') . ' --format=json';
exec($command, $output, $exitCode);
$cli = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
evaluation_assert($exitCode === 0 && $cli['eligible_for_visible_rollout'] === false, 'evaluation CLI keeps model-visible rollout disabled without approvals');

echo "learner_ai_evaluation_test: OK\n";
