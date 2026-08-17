<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use TalentHub\Learner\Data\Contracts\AssessmentWriteRepository;

final class LearnerAssessmentService
{
    public function __construct(private readonly AssessmentWriteRepository $repository)
    {
    }

    public function start(string $studentId, string $testId, string $version): array
    {
        return $this->repository->startAttempt($studentId, $testId, $version);
    }

    public function saveAnswer(string $studentId, string $attemptId, string $questionId, mixed $answer): array
    {
        return $this->repository->saveAnswer($studentId, $attemptId, $questionId, $answer);
    }

    public function submit(string $studentId, string $attemptId): array
    {
        return $this->repository->submitAttempt($studentId, $attemptId);
    }
}
