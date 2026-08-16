<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface AssessmentWriteRepository
{
    public function startAttempt(string $studentId, string $testId, string $version): array;

    public function saveAnswer(string $studentId, string $attemptId, string $questionId, mixed $answer): array;

    public function submitAttempt(string $studentId, string $attemptId): array;
}
