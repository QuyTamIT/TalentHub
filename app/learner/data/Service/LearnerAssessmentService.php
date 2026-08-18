<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use RuntimeException;
use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Contracts\AssessmentWriteRepository;

final class LearnerAssessmentService
{
    public function __construct(
        private readonly AssessmentRepository $reads,
        private readonly AssessmentWriteRepository $writes
    ) {
    }

    public function startOrResume(string $studentId, string $assessmentCode, string $band): array
    {
        return $this->writes->startOrResumeAttempt($studentId, $assessmentCode, $band);
    }

    public function saveAnswer(string $studentId, string $attemptId, string $questionId, mixed $answer): array
    {
        return $this->writes->saveAnswer($studentId, $attemptId, $questionId, $answer);
    }

    public function submit(string $studentId, string $attemptId): array
    {
        return $this->writes->submitAttempt($studentId, $attemptId);
    }

    public function ownedAttempt(string $studentId, string $attemptId): array
    {
        $attempt = $this->reads->ownedAttempt($studentId, $attemptId);
        if ($attempt === null) {
            throw new RuntimeException('Assessment attempt was not found for this learner.');
        }

        return $attempt;
    }

    public function history(string $studentId, string $assessmentCode): array
    {
        return $this->reads->history($studentId, $assessmentCode);
    }
}
