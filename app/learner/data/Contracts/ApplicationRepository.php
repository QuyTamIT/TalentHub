<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface ApplicationRepository
{
    public function forStudent(string $studentId): array;

    public function findByIdForStudent(string $applicationId, string $studentId): ?array;
}
