<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Enums\AssessmentAttemptStatus;
use TalentHub\Learner\Data\Enums\EvaluationStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseAssessmentRepository extends AbstractDatabaseRepository implements AssessmentRepository
{
    private const ALL_SQL = 'SELECT id, name, type, dimensions FROM talent_tests ORDER BY name, id';
    private const FIND_SQL = 'SELECT id, name, type, dimensions FROM talent_tests WHERE id = :assessment_id LIMIT 1';
    private const QUESTIONS_SQL = <<<'SQL'
        SELECT id, testId, content, options
        FROM test_questions
        WHERE testId = :assessment_id
        ORDER BY id
        SQL;
    private const ATTEMPTS_SQL = <<<'SQL'
        SELECT
            ta.id,
            ta.testId,
            ta.studentId,
            ta.startedAt,
            ta.completedAt,
            tr.id AS resultId,
            tr.resultCode,
            tr.summary AS resultSummary,
            tr.dimensionScores
        FROM test_attempts ta
        LEFT JOIN test_results tr ON tr.attemptId = ta.id
        WHERE ta.studentId = :student_id AND ta.testId = :assessment_id
        ORDER BY ta.startedAt DESC, ta.id DESC
        SQL;
    private const EVALUATIONS_SQL = <<<'SQL'
        SELECT
            a.id,
            a.teacherId,
            a.studentId,
            a.activityId,
            a.overallScore,
            a.comment,
            a.status,
            sc.id AS scoreId,
            sc.criteriaId,
            sc.score,
            c.name AS criteriaName,
            c.minScore,
            c.maxScore
        FROM assessments a
        LEFT JOIN assessment_scores sc ON sc.assessmentId = a.id
        LEFT JOIN assessment_criteria c ON c.id = sc.criteriaId
        WHERE a.studentId = :student_id
        ORDER BY a.id, sc.id
        SQL;

    public function all(): array
    {
        return array_map([$this, 'normalizeDefinition'], $this->fetchAll('all', self::ALL_SQL));
    }

    public function findById(string $assessmentId): ?array
    {
        $assessmentId = Uuid::normalizeDatabase($assessmentId, 'assessment_id');
        $definition = $this->fetchOne('findById', self::FIND_SQL, ['assessment_id' => $assessmentId]);
        return $definition === null ? null : $this->normalizeDefinition($definition);
    }

    public function questionsFor(string $assessmentId): array
    {
        $assessmentId = Uuid::normalizeDatabase($assessmentId, 'assessment_id');
        return array_map(
            [$this, 'normalizeQuestion'],
            $this->fetchAll('questionsFor', self::QUESTIONS_SQL, ['assessment_id' => $assessmentId])
        );
    }

    public function attemptsFor(string $studentId, string $assessmentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $assessmentId = Uuid::normalizeDatabase($assessmentId, 'assessment_id');
        return array_map(
            [$this, 'normalizeAttempt'],
            $this->fetchAll('attemptsFor', self::ATTEMPTS_SQL, [
                'student_id' => $studentId,
                'assessment_id' => $assessmentId,
            ])
        );
    }

    public function evaluationsForStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $rows = $this->fetchAll(
            'evaluationsForStudent',
            self::EVALUATIONS_SQL,
            ['student_id' => $studentId]
        );
        $evaluations = [];

        foreach ($rows as $row) {
            $evaluationId = Uuid::normalizeDatabase((string) $row['id'], 'assessments.id');
            if (!isset($evaluations[$evaluationId])) {
                $evaluations[$evaluationId] = [
                    'id' => $evaluationId,
                    'teacher_id' => Uuid::normalizeDatabase((string) $row['teacher_id'], 'assessments.teacherId'),
                    'student_id' => Uuid::normalizeDatabase((string) $row['student_id'], 'assessments.studentId'),
                    'activity_id' => Uuid::normalizeDatabase((string) $row['activity_id'], 'assessments.activityId'),
                    'overall_score' => $row['overall_score'],
                    'comment' => $row['comment'],
                    'status' => EvaluationStatus::normalize($row['status'] ?? null)->value,
                    'scores' => [],
                    'id_origin' => 'database',
                ];
            }

            if (($row['score_id'] ?? null) !== null) {
                $evaluations[$evaluationId]['scores'][] = [
                    'id' => Uuid::normalizeDatabase((string) $row['score_id'], 'assessment_scores.id'),
                    'criteria_id' => Uuid::normalizeDatabase(
                        (string) $row['criteria_id'],
                        'assessment_scores.criteriaId'
                    ),
                    'score' => $row['score'],
                    'criteria_name' => $row['criteria_name'],
                    'min_score' => $row['min_score'],
                    'max_score' => $row['max_score'],
                ];
            }
        }

        return array_values($evaluations);
    }

    private function normalizeDefinition(array $definition): array
    {
        $definition['id'] = Uuid::normalizeDatabase((string) $definition['id'], 'talent_tests.id');
        $definition['dimensions'] = $this->decodeJson($definition['dimensions'] ?? null, 'talent_tests.dimensions');
        $definition['id_origin'] = 'database';

        return $definition;
    }

    private function normalizeQuestion(array $question): array
    {
        $question['id'] = Uuid::normalizeDatabase((string) $question['id'], 'test_questions.id');
        $question['test_id'] = Uuid::normalizeDatabase((string) $question['test_id'], 'test_questions.testId');
        $question['assessment_id'] = $question['test_id'];
        $question['options'] = $this->decodeJson($question['options'] ?? null, 'test_questions.options');
        $question['id_origin'] = 'database';

        return $question;
    }

    private function normalizeAttempt(array $attempt): array
    {
        $attempt['id'] = Uuid::normalizeDatabase((string) $attempt['id'], 'test_attempts.id');
        $attempt['test_id'] = Uuid::normalizeDatabase((string) $attempt['test_id'], 'test_attempts.testId');
        $attempt['assessment_id'] = $attempt['test_id'];
        $attempt['student_id'] = Uuid::normalizeDatabase((string) $attempt['student_id'], 'test_attempts.studentId');
        $attempt['status'] = ($attempt['completed_at'] ?? null) === null
            ? AssessmentAttemptStatus::InProgress->value
            : AssessmentAttemptStatus::Submitted->value;
        $attempt['id_origin'] = 'database';

        if (($attempt['result_id'] ?? null) !== null) {
            $resultId = Uuid::normalizeDatabase((string) $attempt['result_id'], 'test_results.id');
            $attempt['result'] = [
                'id' => $resultId,
                'result_code' => $attempt['result_code'],
                'summary' => $attempt['result_summary'],
                'dimension_scores' => $this->decodeJson(
                    $attempt['dimension_scores'] ?? null,
                    'test_results.dimensionScores'
                ),
            ];
        } else {
            $attempt['result'] = null;
        }

        unset(
            $attempt['result_id'],
            $attempt['result_code'],
            $attempt['result_summary'],
            $attempt['dimension_scores']
        );

        return $attempt;
    }
}
