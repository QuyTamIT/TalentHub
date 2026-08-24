<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface AssessmentWriteRepository
{
    public function startOrResumeAttempt(string $studentId, string $assessmentCode, string $educationBand): array;

    public function saveAnswer(string $studentId, string $attemptId, string $questionId, mixed $answer): array;

    public function submitAttempt(string $studentId, string $attemptId): array;
}
