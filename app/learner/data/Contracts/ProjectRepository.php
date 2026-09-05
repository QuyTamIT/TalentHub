<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface ProjectRepository
{
    /** @return list<array<string,mixed>> */
    public function listVisibleForStudent(string $studentId): array;

    /** @return array<string,mixed>|null */
    public function findVisibleForStudent(string $studentId, string $projectId): ?array;

    /** @return array<string,mixed>|null */
    public function findActiveMembershipForStudent(string $studentId, string $projectId): ?array;
}
