<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;

final class ShadowRunService
{
    public function __construct(
        private readonly RecommendationRepository $repository,
        private readonly RecommendationEngine $modelEngine,
        private readonly RecommendationEvaluator $evaluator,
    ) {
    }

    /** @return array{visible_result:RecommendationResult,shadow_result:RecommendationResult,evaluation:array<string,mixed>} */
    public function run(string $studentId, RecommendationInput $input, RecommendationContext $context, RecommendationResult $visibleResult): array
    {
        $shadowContext = new RecommendationContext(
            $context->allowedScopes(),
            $context->requestId(),
            'shadow-' . hash('sha256', $input->contentHash()),
            $studentId,
        );
        $shadow = $this->modelEngine->generate($input, $shadowContext);
        $evaluation = $this->evaluator->evaluate($shadow, $input);
        if ($shadow->engineType() === 'model' && $evaluation['valid'] === true) {
            $pending = $this->repository->createPendingRun($studentId, $input, $shadowContext);
            if (($pending['reused'] ?? false) !== true) {
                $this->repository->completeRun($studentId, (string) ($pending['runId'] ?? ''), $shadow);
            }
        }
        return ['visible_result' => $visibleResult, 'shadow_result' => $shadow, 'evaluation' => $evaluation];
    }
}
