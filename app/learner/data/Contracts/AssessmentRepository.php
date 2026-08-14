<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface AssessmentRepository
{
    public function all(): array;

    public function findById(string $assessmentId): ?array;

    public function questionsFor(string $assessmentId): array;

    public function attemptsFor(string $studentId, string $assessmentId): array;

    public function evaluationsForStudent(string $studentId): array;
}
