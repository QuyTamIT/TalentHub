<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface InternshipApplicationCommandRepository
{
    /** @return array<string,mixed> */
    public function grantApplicationProfileConsent(string $studentId, string $userId, string $requestId): array;

    /** @return array<string,mixed> */
    public function submit(string $studentId, string $userId, string $requestId, string $postId, string $message): array;

    /** @return array<string,mixed> */
    public function readForStudent(string $studentId): array;

    /** @return array<string,mixed> */
    public function readOneForStudent(string $studentId, string $applicationId): array;

    /** @return array<string,mixed> */
    public function withdraw(string $studentId, string $userId, string $requestId, string $applicationId, string $reason): array;
}
