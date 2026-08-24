<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;

interface RoadmapRepository
{
    /** @param array<string,mixed> $providerAudit @return array<string,mixed> */
    public function saveCompleted(string $studentId, string $runId, RoadmapAnalysis $analysis, array $providerAudit): array;
    /** @return array<string,mixed>|null */
    public function latestForStudent(string $studentId): ?array;
    /** @return array<string,mixed> */
    public function appendTaskEvent(string $studentId, string $taskId, string $status, string $requestId): array;
}
