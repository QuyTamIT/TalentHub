<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationResult;

final class PersistedRecommendationRun
{
    public function __construct(
        private readonly string $studentId,
        private readonly string $runId,
        private readonly string $snapshotId,
        private readonly string $status,
        private readonly RecommendationResult $result,
        private readonly RecommendationInput $input,
    ) {
        foreach ([$studentId, $runId, $snapshotId, $status] as $value) {
            if (trim($value) === '') throw new \InvalidArgumentException('Persisted recommendation run fields are required.');
        }
        if (!in_array($status, ['completed', 'fallback', 'failed'], true)) {
            throw new \InvalidArgumentException('Persisted recommendation run status is not terminal.');
        }
    }

    public function studentId(): string { return $this->studentId; }
    public function runId(): string { return $this->runId; }
    public function snapshotId(): string { return $this->snapshotId; }
    public function status(): string { return $this->status; }
    public function result(): RecommendationResult { return $this->result; }
    public function input(): RecommendationInput { return $this->input; }
}
