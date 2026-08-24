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

    public function publishedCatalog(string $studentId, string $educationBand): array;

    public function publishedAssessment(string $assessmentCode, string $educationBand): ?array;

    public function questionsForVersion(string $versionId): array;

    public function ownedAttempt(string $studentId, string $attemptId): ?array;

    public function history(string $studentId, string $assessmentCode): array;

    /**
     * Every submitted-and-scored attempt the Student owns, across all frameworks and bands,
     * ordered submittedAt DESC. Read-only.
     */
    public function completeHistory(string $studentId): array;

    /**
     * Teacher evaluations the Student is allowed to see: published with a publish timestamp,
     * ordered publishedAt DESC. Drafts are never returned.
     */
    public function publishedEvaluationsForStudent(string $studentId): array;
}
