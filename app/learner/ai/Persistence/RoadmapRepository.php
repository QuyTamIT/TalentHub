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
    /** @return array{state:string,started_at:string}|null */
    public function latestPendingForStudent(string $studentId): ?array;
    /** @return list<array<string,mixed>> */
    public function historyForStudent(string $studentId): array;
    /** @return array<string,mixed>|null */
    public function versionForStudent(string $studentId, int $version): ?array;
    /** @return array<string,mixed> */
    public function appendTaskEvent(string $studentId, string $taskId, string $status, string $requestId): array;
    /** @return array<string,mixed> */
    public function appendRoadmapFeedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array;
    /** @return list<array{verdict:string,reason_code:string,count:int}> */
    public function feedbackSignalsForStudent(string $studentId): array;
}
