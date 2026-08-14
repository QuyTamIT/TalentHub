<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Enums\AssessmentAttemptStatus;
use TalentHub\Learner\Data\Enums\EvaluationStatus;
use TalentHub\Learner\Data\Support\MockRecordNormalizer;

final class MockAssessmentRepository implements AssessmentRepository
{
    private array $definitions;
    private array $questions;
    private array $attempts;
    private array $evaluations;

    public function __construct(array $definitions, array $questions, array $attempts, array $evaluations = [])
    {
        $this->definitions = array_map(
            static fn (array $record): array => MockRecordNormalizer::primary($record, 'assessment'),
            $definitions
        );
        $this->questions = array_map([$this, 'normalizeQuestion'], $questions);
        $this->attempts = array_map([$this, 'normalizeAttempt'], $attempts);
        $this->evaluations = array_map([$this, 'normalizeEvaluation'], $evaluations);
    }

    public function all(): array
    {
        return $this->definitions;
    }

    public function findById(string $assessmentId): ?array
    {
        foreach ($this->definitions as $definition) {
            if (MockRecordNormalizer::matches($definition, $assessmentId)) {
                return $definition;
            }
        }

        return null;
    }

    public function questionsFor(string $assessmentId): array
    {
        $canonicalId = MockRecordNormalizer::lookupId('assessment', $assessmentId);
        return array_values(array_filter(
            $this->questions,
            static fn (array $question): bool => ($question['assessment_id'] ?? '') === $canonicalId
        ));
    }

    public function attemptsFor(string $studentId, string $assessmentId): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        $canonicalAssessmentId = MockRecordNormalizer::lookupId('assessment', $assessmentId);

        return array_values(array_filter(
            $this->attempts,
            static fn (array $attempt): bool => ($attempt['student_id'] ?? '') === $canonicalStudentId
                && ($attempt['assessment_id'] ?? '') === $canonicalAssessmentId
        ));
    }

    public function evaluationsForStudent(string $studentId): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        return array_values(array_filter(
            $this->evaluations,
            static fn (array $evaluation): bool => ($evaluation['student_id'] ?? '') === $canonicalStudentId
        ));
    }

    private function normalizeQuestion(array $question): array
    {
        $question = MockRecordNormalizer::primary($question, 'assessment_question');
        return MockRecordNormalizer::foreign($question, 'assessment_id', 'assessment');
    }

    private function normalizeAttempt(array $attempt): array
    {
        $attempt = MockRecordNormalizer::primary($attempt, 'assessment_attempt');
        $attempt = MockRecordNormalizer::foreign($attempt, 'student_id', 'student');
        $attempt = MockRecordNormalizer::foreign($attempt, 'assessment_id', 'assessment');
        $attempt['status'] = AssessmentAttemptStatus::normalize($attempt['status'] ?? null)->value;

        return $attempt;
    }

    private function normalizeEvaluation(array $evaluation): array
    {
        $evaluation = MockRecordNormalizer::primary($evaluation, 'evaluation');
        $evaluation = MockRecordNormalizer::foreign($evaluation, 'student_id', 'student');
        $evaluation = MockRecordNormalizer::foreign($evaluation, 'activity_id', 'activity');
        $evaluation = MockRecordNormalizer::foreign($evaluation, 'teacher_id', 'teacher');
        $evaluation['status'] = EvaluationStatus::normalize($evaluation['status'] ?? null)->value;

        return $evaluation;
    }
}
