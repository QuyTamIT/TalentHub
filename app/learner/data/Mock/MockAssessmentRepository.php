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

    public function publishedCatalog(string $studentId, string $educationBand): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        $catalog = [];

        foreach ($this->definitions as $definition) {
            $status = (string) ($definition['status'] ?? 'published');
            if ($status !== 'published') {
                continue;
            }

            $band = (string) ($definition['education_band'] ?? $educationBand);
            if ($band !== $educationBand) {
                continue;
            }

            $code = (string) ($definition['code'] ?? $definition['id'] ?? '');
            $assessmentId = (string) ($definition['id'] ?? '');
            $version = (string) ($definition['version'] ?? '1.0.0');
            $scoringVersion = (string) ($definition['scoring_version'] ?? 'holland-riasec-1.0');

            $matchingQuestions = array_filter(
                $this->questions,
                static fn (array $q): bool => ($q['assessment_id'] ?? '') === $assessmentId
            );
            $questionCount = count($matchingQuestions);

            $attempts = array_values(array_filter(
                $this->attempts,
                static fn (array $a): bool => ($a['student_id'] ?? '') === $canonicalStudentId
                    && (($a['assessment_id'] ?? '') === $assessmentId || ($a['assessment_code'] ?? '') === $code)
            ));

            $attemptStatus = 'not_started';
            $progress = 0;
            $nextRetakeAt = null;

            foreach ($attempts as $a) {
                if (($a['status'] ?? '') === 'in_progress') {
                    $attemptStatus = 'in_progress';
                    $answers = (array) ($a['answers'] ?? []);
                    $progress = $questionCount > 0 ? (int) round((count($answers) / $questionCount) * 100) : 0;
                    break;
                }
                if (($a['status'] ?? '') === 'submitted') {
                    $attemptStatus = 'submitted';
                }
            }

            $catalog[] = [
                'code' => $code,
                'education_band' => $educationBand,
                'version' => $version,
                'scoring_version' => $scoringVersion,
                'question_count' => $questionCount,
                'status' => 'published',
                'attempt_status' => $attemptStatus,
                'progress' => $progress,
                'next_retake_at' => $nextRetakeAt,
            ];
        }

        return $catalog;
    }

    public function publishedAssessment(string $assessmentCode, string $educationBand): ?array
    {
        foreach ($this->definitions as $definition) {
            $status = (string) ($definition['status'] ?? 'published');
            if ($status !== 'published') {
                continue;
            }

            $code = (string) ($definition['code'] ?? $definition['id'] ?? '');
            $bandedCode = $assessmentCode . '_' . $educationBand;
            if ($code === $assessmentCode || $code === $bandedCode || MockRecordNormalizer::matches($definition, $assessmentCode)) {
                return $definition;
            }
        }

        return null;
    }

    public function questionsForVersion(string $versionId): array
    {
        return array_values(array_filter(
            $this->questions,
            static fn (array $question): bool => ($question['version_id'] ?? '') === $versionId
        ));
    }

    public function ownedAttempt(string $studentId, string $attemptId): ?array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        foreach ($this->attempts as $attempt) {
            if (MockRecordNormalizer::matches($attempt, $attemptId)) {
                if (($attempt['student_id'] ?? '') !== $canonicalStudentId) {
                    return null;
                }

                return $attempt;
            }
        }

        return null;
    }

    public function history(string $studentId, string $assessmentCode): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        return array_values(array_filter(
            $this->attempts,
            static function (array $attempt) use ($canonicalStudentId, $assessmentCode): bool {
                if (($attempt['student_id'] ?? '') !== $canonicalStudentId) {
                    return false;
                }
                if (($attempt['status'] ?? '') !== 'submitted') {
                    return false;
                }
                $code = (string) ($attempt['assessment_code'] ?? $attempt['assessment_id'] ?? '');
                return $code === $assessmentCode || str_starts_with($code, $assessmentCode . '_');
            }
        ));
    }

    public function completeHistory(string $studentId): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        $history = array_values(array_filter(
            $this->attempts,
            static function (array $attempt) use ($canonicalStudentId): bool {
                if (($attempt['student_id'] ?? '') !== $canonicalStudentId) {
                    return false;
                }
                if (($attempt['status'] ?? '') !== 'submitted') {
                    return false;
                }
                return ($attempt['result'] ?? null) !== null || ($attempt['result_id'] ?? null) !== null;
            }
        ));

        usort($history, static function (array $left, array $right): int {
            return strcmp((string) ($right['submitted_at'] ?? ''), (string) ($left['submitted_at'] ?? ''));
        });

        return $history;
    }

    public function publishedEvaluationsForStudent(string $studentId): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        $published = array_values(array_filter(
            $this->evaluations,
            static function (array $evaluation) use ($canonicalStudentId): bool {
                if (($evaluation['student_id'] ?? '') !== $canonicalStudentId) {
                    return false;
                }
                if (($evaluation['status'] ?? '') !== 'published') {
                    return false;
                }
                return trim((string) ($evaluation['published_at'] ?? '')) !== '';
            }
        ));

        usort($published, static function (array $left, array $right): int {
            return strcmp((string) ($right['published_at'] ?? ''), (string) ($left['published_at'] ?? ''));
        });

        return $published;
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
