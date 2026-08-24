<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface ActivityRepository
{
    public function all(): array;

    public function findById(string $activityId): ?array;

    public function registrationsFor(string $studentId): array;
}
