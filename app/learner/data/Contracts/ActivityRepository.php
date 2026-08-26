<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

use DateTimeImmutable;

interface ActivityRepository
{
    public function all(): array;

    public function findById(string $activityId): ?array;

    public function registrationsFor(string $studentId): array;

    public function discoverForStudent(string $studentId, DateTimeImmutable $now): array;

    public function findForStudent(string $studentId, string $activityId): ?array;

    public function registrationTimelineFor(string $studentId): array;
}
