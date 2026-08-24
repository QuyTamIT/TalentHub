<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationResult;

interface RecommendationRepository
{
    /** @return array<string,mixed> */
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array;

    /** @return array<string,mixed> */
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array;

    public function failRun(string $studentId, string $runId, string $safeErrorCode): void;

    /** @return array<string,mixed>|null */
    public function latestForStudent(string $studentId): ?array;

    /** @return array<string,mixed> */
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array;
}
