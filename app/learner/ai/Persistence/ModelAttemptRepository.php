<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Evaluation\EvaluationRecord;

interface ModelAttemptRepository
{
    /** @param array{provider:string,model_version:string,prompt_version:string} $versions @return array<string,mixed> */
    public function createPendingModelAttempt(string $studentId, RecommendationInput $input, RecommendationContext $context, array $versions): array;
    /** @return array<string,mixed> */
    public function completeModelAttemptWithEvaluation(string $studentId, string $runId, RecommendationResult $result, EvaluationRecord $evaluation): array;
    /** @return array<string,mixed> */
    public function failModelAttemptWithEvaluation(string $studentId, string $runId, string $failureCode, EvaluationRecord $evaluation): array;
}
