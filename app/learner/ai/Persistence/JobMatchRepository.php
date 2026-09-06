<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

interface JobMatchRepository
{
    /** @param list<string> $activeCatalogIds @return array<string,mixed>|null */
    public function latestValid(string $studentId, array $activeCatalogIds): ?array;
    /** @return array<string,mixed> */
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array;
    /** @param list<array<string,mixed>> $records @param array<string,mixed> $runAnalysis @return array<string,mixed> */
    public function completeRun(string $studentId, string $runId, array $records, array $runAnalysis = []): array;
    public function failRun(string $studentId, string $runId, string $safeCode): void;
}
