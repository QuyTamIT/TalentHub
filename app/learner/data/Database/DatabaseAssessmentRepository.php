<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Enums\AssessmentAttemptStatus;
use TalentHub\Learner\Data\Enums\EvaluationStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseAssessmentRepository extends AbstractDatabaseRepository implements AssessmentRepository
{
    private const ALL_SQL = 'SELECT id, code, name, type, status FROM talent_tests ORDER BY name, id';
    private const FIND_SQL = 'SELECT id, code, name, type, status FROM talent_tests WHERE id = :assessment_id LIMIT 1';
    private const QUESTIONS_SQL = <<<'SQL'
        SELECT id, testId, code, content, optionsJson, status
        FROM test_questions
        WHERE testId = :assessment_id
        ORDER BY id
        SQL;
    private const ATTEMPTS_SQL = <<<'SQL'
        SELECT
            ta.id,
            ta.testId,
            ta.studentId,
            ta.status,
            ta.startedAt,
            ta.submittedAt,
            tr.id AS resultId,
            tr.resultCode,
            tr.summary AS resultSummary,
            tr.dimensionScoresJson,
            tr.scoringVersion
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

    /**
     * Published-only Teacher evaluation read. Drafts and rows without a publish timestamp
     * are excluded in SQL, never in PHP, so a mapping change cannot leak a draft.
     */
    private const PUBLISHED_EVALUATIONS_SQL = <<<'SQL'
        SELECT
            a.id,
            a.teacherId,
            a.studentId,
            a.activityId,
            a.overallScore,
            a.comment,
            a.status,
            a.publishedAt,
            act.title AS activityTitle,
            u.fullName AS reviewerName,
            sc.id AS scoreId,
            sc.criteriaId,
            sc.score,
            c.code AS criteriaCode,
            c.name AS criteriaName,
            c.minScore,
            c.maxScore
        FROM assessments a
        LEFT JOIN activities act ON act.id = a.activityId
        LEFT JOIN teacher_profiles tp ON tp.id = a.teacherId
        LEFT JOIN users u ON u.id = tp.userId
        LEFT JOIN assessment_scores sc ON sc.assessmentId = a.id
        LEFT JOIN assessment_criteria c ON c.id = sc.criteriaId
        WHERE a.studentId = :student_id
          AND a.status = 'published'
          AND a.publishedAt IS NOT NULL
        ORDER BY a.publishedAt DESC, a.id DESC, sc.id ASC
        SQL;

    /** Canonical stable assessment codes: four frameworks, base plus three education bands. */
    private const CANONICAL_ASSESSMENT_CODES = [
        'holland',
        'holland_middle',
        'holland_high',
        'holland_college',
        'mbti',
        'mbti_middle',
        'mbti_high',
        'mbti_college',
        'disc',
        'disc_middle',
        'disc_high',
        'disc_college',
        'multiple_intelligence',
        'multiple_intelligence_middle',
        'multiple_intelligence_high',
        'multiple_intelligence_college',
    ];

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

    public function publishedEvaluationsForStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $rows = $this->fetchAll(
            'publishedEvaluationsForStudent',
            self::PUBLISHED_EVALUATIONS_SQL,
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
                    'activity_title' => $row['activity_title'],
                    'reviewer_name' => $row['reviewer_name'],
                    'overall_score' => $row['overall_score'],
                    'comment' => $row['comment'],
                    'status' => EvaluationStatus::normalize($row['status'] ?? null)->value,
                    'published_at' => $row['published_at'],
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
                    'criteria_code' => $row['criteria_code'],
                    'criteria_name' => $row['criteria_name'],
                    'score' => $row['score'],
                    'min_score' => $row['min_score'],
                    'max_score' => $row['max_score'],
                ];
            }
        }

        return array_values($evaluations);
    }

    public function publishedCatalog(string $studentId, string $educationBand): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $educationBand = strtolower(trim($educationBand));

        $tests = $this->fetchAll(
            'publishedCatalog.tests',
            <<<'SQL'
SELECT
    t.id AS test_id,
    t.code AS test_code,
    t.name AS test_name,
    t.type AS test_type,
    t.status AS test_status,
    v.id AS version_id,
    v.version AS assessment_version,
    v.scoringVersion AS scoring_version,
    (
        SELECT COUNT(*)
        FROM learner_assessment_question_versions qv
        WHERE qv.versionId = v.id
    ) AS question_count
FROM talent_tests t
INNER JOIN learner_assessment_versions v ON v.testId = t.id
WHERE t.status = 'published'
  AND v.status = 'published'
  AND t.code IN (:holland_code, :mbti_code, :disc_code, :multiple_intelligence_code)
  AND v.id = (
      SELECT newest.id
      FROM learner_assessment_versions newest
      WHERE newest.testId = t.id AND newest.status = 'published'
      ORDER BY newest.createdAt DESC, newest.id DESC
      LIMIT 1
  )
ORDER BY t.code, t.id
SQL,
            [
                'holland_code' => 'holland_' . $educationBand,
                'mbti_code' => 'mbti_' . $educationBand,
                'disc_code' => 'disc_' . $educationBand,
                'multiple_intelligence_code' => 'multiple_intelligence_' . $educationBand,
            ]
        );

        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $catalog = [];

        foreach ($tests as $row) {
            $testId = (string) $row['test_id'];
            $fullCode = (string) $row['test_code'];
            $baseCode = $fullCode;
            if (str_ends_with(strtolower($fullCode), '_' . $educationBand)) {
                $baseCode = substr($fullCode, 0, -strlen('_' . $educationBand));
            }

            $questionCount = (int) $row['question_count'];

            // Check active in_progress attempt
            $inProgress = $this->fetchOne(
                'publishedCatalog.inProgress',
                <<<'SQL'
SELECT a.id, m.expiresAt
FROM test_attempts a
INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
WHERE a.studentId = :student_id
  AND a.testId = :test_id
  AND a.status = 'in_progress'
  AND m.status = 'in_progress'
ORDER BY a.startedAt DESC, a.id DESC
LIMIT 1
SQL,
                ['student_id' => $studentId, 'test_id' => $testId]
            );

            $attemptStatus = 'not_started';
            $progress = 0;
            $nextRetakeAt = null;

            if ($inProgress !== null) {
                $expiresAtStr = (string) ($inProgress['expires_at'] ?? $inProgress['expiresAt'] ?? '');
                $isExpired = false;
                if ($expiresAtStr !== '') {
                    $expiresAt = new DateTimeImmutable($expiresAtStr, new DateTimeZone('UTC'));
                    if ($nowUtc > $expiresAt) {
                        $isExpired = true;
                    }
                }

                if (!$isExpired) {
                    $attemptStatus = 'in_progress';
                    $answeredRow = $this->fetchOne(
                        'publishedCatalog.answersCount',
                        'SELECT COUNT(*) AS answered_count FROM learner_assessment_answers WHERE attemptId = :attempt_id',
                        ['attempt_id' => $inProgress['id']]
                    );
                    $answeredCount = (int) ($answeredRow['answered_count'] ?? 0);
                    $progress = $questionCount > 0 ? (int) round(($answeredCount / $questionCount) * 100) : 0;
                }
            }

            if ($attemptStatus === 'not_started') {
                // Check submitted attempts for retake policy
                $latestSubmitted = $this->fetchOne(
                    'publishedCatalog.latestSubmitted',
                    <<<'SQL'
SELECT a.id, a.submittedAt AS submitted_at
FROM test_attempts a
INNER JOIN talent_tests t ON t.id = a.testId
WHERE a.studentId = :student_id
  AND a.status = 'submitted'
  AND a.submittedAt IS NOT NULL
  AND (
      t.id = :test_id
      OR t.code = :middle_code
      OR t.code = :high_code
      OR t.code = :college_code
  )
ORDER BY a.submittedAt DESC, a.id DESC
LIMIT 1
SQL,
                    [
                        'student_id' => $studentId,
                        'test_id' => $testId,
                        'middle_code' => $baseCode . '_middle',
                        'high_code' => $baseCode . '_high',
                        'college_code' => $baseCode . '_college',
                    ]
                );

                $submittedAtVal = $latestSubmitted['submitted_at'] ?? $latestSubmitted['submittedAt'] ?? null;
                if ($latestSubmitted !== null && $submittedAtVal !== null) {
                    $submittedAt = new DateTimeImmutable((string) $submittedAtVal, new DateTimeZone('UTC'));
                    $retakeAt = $submittedAt->modify('+90 days');
                    if ($nowUtc < $retakeAt) {
                        $attemptStatus = 'retake_locked';
                        $progress = 100;
                        $nextRetakeAt = $retakeAt->format('Y-m-d\TH:i:s\Z');
                    } else {
                        $attemptStatus = 'submitted';
                        $progress = 0;
                    }
                }
            }

            $catalog[] = [
                'code' => $baseCode,
                'education_band' => $educationBand,
                'version' => (string) $row['assessment_version'],
                'scoring_version' => (string) $row['scoring_version'],
                'question_count' => $questionCount,
                'status' => (string) $row['test_status'],
                'attempt_status' => $attemptStatus,
                'progress' => $progress,
                'next_retake_at' => $nextRetakeAt,
            ];
        }

        return $catalog;
    }

    public function publishedAssessment(string $assessmentCode, string $educationBand): ?array
    {
        $educationBand = strtolower(trim($educationBand));
        $bandedCode = $assessmentCode . '_' . $educationBand;

        $row = $this->fetchOne(
            'publishedAssessment',
            <<<'SQL'
SELECT
    t.id AS test_id,
    t.code AS test_code,
    t.name AS test_name,
    t.type AS test_type,
    t.status AS test_status,
    v.id AS version_id,
    v.version AS assessment_version,
    v.scoringVersion AS scoring_version,
    v.schemaHash AS schema_hash,
    (
        SELECT COUNT(*)
        FROM learner_assessment_question_versions qv
        WHERE qv.versionId = v.id
    ) AS question_count
FROM talent_tests t
INNER JOIN learner_assessment_versions v ON v.testId = t.id
WHERE t.status = 'published'
  AND v.status = 'published'
  AND t.code = :banded_code
ORDER BY v.createdAt DESC, v.id DESC
LIMIT 1
SQL,
            ['banded_code' => $bandedCode]
        );

        if ($row === null) {
            return null;
        }

        return [
            'id' => Uuid::normalizeDatabase((string) $row['test_id'], 'talent_tests.id'),
            'code' => (string) $row['test_code'],
            'name' => (string) $row['test_name'],
            'type' => (string) $row['test_type'],
            'education_band' => $educationBand,
            'version_id' => (string) $row['version_id'],
            'version' => (string) $row['assessment_version'],
            'scoring_version' => (string) $row['scoring_version'],
            'schema_hash' => (string) $row['schema_hash'],
            'question_count' => (int) $row['question_count'],
            'status' => (string) $row['test_status'],
        ];
    }

    public function questionsForVersion(string $versionId): array
    {
        $rows = $this->fetchAll(
            'questionsForVersion',
            <<<'SQL'
SELECT
    q.id AS question_id,
    qv.position,
    qv.dimensionCode AS dimension_code,
    qv.required,
    q.content,
    q.optionsJson AS options_json
FROM learner_assessment_question_versions qv
INNER JOIN test_questions q ON q.id = qv.questionId
WHERE qv.versionId = :version_id
ORDER BY qv.position ASC, qv.questionId ASC
SQL,
            ['version_id' => $versionId]
        );

        return array_map(function (array $row) use ($versionId): array {
            $qId = (string) $row['question_id'];
            return [
                'id' => Uuid::isValid($qId) ? Uuid::normalizeDatabase($qId, 'test_questions.id') : $qId,
                'question_id' => Uuid::isValid($qId) ? Uuid::normalizeDatabase($qId, 'test_questions.id') : $qId,
                'version_id' => $versionId,
                'position' => (int) $row['position'],
                'dimension_code' => (string) $row['dimension_code'],
                'required' => (int) $row['required'] === 1,
                'prompt' => (string) $row['content'],
                'content' => (string) $row['content'],
                'options' => $this->decodeJson($row['options_json'] ?? null, 'test_questions.optionsJson'),
            ];
        }, $rows);
    }

    public function ownedAttempt(string $studentId, string $attemptId): ?array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $attemptId = Uuid::normalizeDatabase($attemptId, 'attempt_id');

        $row = $this->fetchOne(
            'ownedAttempt',
            <<<'SQL'
SELECT
    a.id AS attempt_id,
    a.studentId AS student_id,
    a.testId AS test_id,
    t.code AS test_code,
    t.name AS test_name,
    t.type AS test_type,
    a.status AS attempt_status,
    a.startedAt AS started_at,
    a.submittedAt AS attempt_submitted_at,
    m.versionId AS version_id,
    m.status AS metadata_status,
    m.expiresAt AS expires_at,
    m.submittedAt AS metadata_submitted_at,
    m.inputHash AS input_hash,
    v.version AS assessment_version,
    v.scoringVersion AS scoring_version,
    r.id AS result_id,
    r.resultCode AS result_code,
    r.summary AS result_summary,
    r.dimensionScoresJson AS dimension_scores_json,
    r.scoringVersion AS result_scoring_version,
    r.createdAt AS result_created_at
FROM test_attempts a
INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
INNER JOIN learner_assessment_versions v ON v.id = m.versionId
INNER JOIN talent_tests t ON t.id = a.testId
LEFT JOIN test_results r ON r.attemptId = a.id
WHERE a.id = :attempt_id AND a.studentId = :student_id
LIMIT 1
SQL,
            ['attempt_id' => $attemptId, 'student_id' => $studentId]
        );

        if ($row === null) {
            return null;
        }

        $answersRows = $this->fetchAll(
            'ownedAttempt.answers',
            'SELECT questionId AS question_id, answerJson AS answer_json FROM learner_assessment_answers WHERE attemptId = :attempt_id',
            ['attempt_id' => $attemptId]
        );

        $answers = [];
        foreach ($answersRows as $ans) {
            $qId = (string) ($ans['question_id'] ?? $ans['questionId'] ?? '');
            $answers[$qId] = $this->decodeAnswer($ans['answer_json'] ?? $ans['answerJson'] ?? null);
        }

        $result = null;
        if (($row['result_id'] ?? null) !== null) {
            $resId = (string) $row['result_id'];
            $result = [
                'id' => Uuid::isValid($resId) ? Uuid::normalizeDatabase($resId, 'test_results.id') : $resId,
                'result_code' => (string) $row['result_code'],
                'summary' => (string) $row['result_summary'],
                'dimension_scores' => $this->decodeJson($row['dimension_scores_json'] ?? null, 'test_results.dimensionScoresJson'),
                'scoring_version' => (string) ($row['result_scoring_version'] ?? $row['scoring_version']),
                'submitted_at' => (string) $row['result_created_at'],
            ];
        }

        $attId = (string) $row['attempt_id'];
        $stId = (string) $row['student_id'];
        $tId = (string) $row['test_id'];

        return [
            'id' => Uuid::isValid($attId) ? Uuid::normalizeDatabase($attId, 'test_attempts.id') : $attId,
            'student_id' => Uuid::isValid($stId) ? Uuid::normalizeDatabase($stId, 'test_attempts.studentId') : $stId,
            'assessment_id' => Uuid::isValid($tId) ? Uuid::normalizeDatabase($tId, 'test_attempts.testId') : $tId,
            'assessment_code' => (string) $row['test_code'],
            'assessment_name' => (string) $row['test_name'],
            'assessment_type' => (string) $row['test_type'],
            'version_id' => (string) $row['version_id'],
            'assessment_version' => (string) $row['assessment_version'],
            'scoring_version' => (string) $row['scoring_version'],
            'status' => (string) ($row['metadata_status'] ?? $row['attempt_status']),
            'started_at' => (string) $row['started_at'],
            'expires_at' => $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            'submitted_at' => $row['metadata_submitted_at'] ?? $row['attempt_submitted_at'],
            'input_hash' => $row['input_hash'] !== null ? (string) $row['input_hash'] : null,
            'answers' => $answers,
            'result' => $result,
        ];
    }

    public function history(string $studentId, string $assessmentCode): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $rows = $this->fetchAll(
            'history',
            <<<'SQL'
SELECT
    a.id AS attempt_id,
    a.studentId AS student_id,
    a.testId AS test_id,
    t.code AS test_code,
    t.name AS test_name,
    t.type AS test_type,
    a.status AS attempt_status,
    a.startedAt AS started_at,
    a.submittedAt AS submitted_at,
    v.version AS assessment_version,
    v.scoringVersion AS scoring_version,
    r.id AS result_id,
    r.resultCode AS result_code,
    r.summary AS result_summary,
    r.dimensionScoresJson AS dimension_scores_json,
    r.createdAt AS result_created_at
FROM test_attempts a
INNER JOIN talent_tests t ON t.id = a.testId
INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
INNER JOIN learner_assessment_versions v ON v.id = m.versionId
INNER JOIN test_results r ON r.attemptId = a.id
WHERE a.studentId = :student_id
  AND a.status = 'submitted'
  AND (
      t.code = :base_code
      OR t.code = :middle_code
      OR t.code = :high_code
      OR t.code = :college_code
  )
ORDER BY a.submittedAt DESC, a.id DESC
SQL,
            [
                'student_id' => $studentId,
                'base_code' => $assessmentCode,
                'middle_code' => $assessmentCode . '_middle',
                'high_code' => $assessmentCode . '_high',
                'college_code' => $assessmentCode . '_college',
            ]
        );

        return array_map(function (array $row): array {
            $attId = (string) $row['attempt_id'];
            $stId = (string) $row['student_id'];
            $tId = (string) $row['test_id'];
            $resId = (string) $row['result_id'];
            return [
                'id' => Uuid::isValid($attId) ? Uuid::normalizeDatabase($attId, 'test_attempts.id') : $attId,
                'student_id' => Uuid::isValid($stId) ? Uuid::normalizeDatabase($stId, 'test_attempts.studentId') : $stId,
                'assessment_id' => Uuid::isValid($tId) ? Uuid::normalizeDatabase($tId, 'test_attempts.testId') : $tId,
                'assessment_code' => (string) $row['test_code'],
                'assessment_name' => (string) $row['test_name'],
                'assessment_type' => (string) $row['test_type'],
                'assessment_version' => (string) $row['assessment_version'],
                'scoring_version' => (string) $row['scoring_version'],
                'status' => 'submitted',
                'started_at' => (string) $row['started_at'],
                'submitted_at' => (string) $row['submitted_at'],
                'result_id' => Uuid::isValid($resId) ? Uuid::normalizeDatabase($resId, 'test_results.id') : $resId,
                'result_code' => (string) $row['result_code'],
                'summary' => (string) $row['result_summary'],
                'dimension_scores' => $this->decodeJson($row['dimension_scores_json'] ?? null, 'test_results.dimensionScoresJson'),
            ];
        }, $rows);
    }

    /**
     * Complete own history across every framework and band.
     *
     * Filters on the canonical stable codes (base plus banded variants) exactly as
     * publishedCatalog()/history() already do, so the read never depends on the free-form
     * talent_tests.type column.
     */
    public function completeHistory(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $parameters = ['student_id' => $studentId];
        $placeholders = [];
        foreach (self::CANONICAL_ASSESSMENT_CODES as $position => $code) {
            $placeholder = 'code_' . $position;
            $placeholders[] = ':' . $placeholder;
            $parameters[$placeholder] = $code;
        }

        $rows = $this->fetchAll(
            'completeHistory',
            <<<SQL
SELECT
    a.id AS attempt_id,
    a.studentId AS student_id,
    a.testId AS test_id,
    t.code AS test_code,
    t.name AS test_name,
    t.type AS test_type,
    a.startedAt AS started_at,
    a.submittedAt AS submitted_at,
    v.id AS version_id,
    v.version AS assessment_version,
    v.scoringVersion AS scoring_version,
    r.id AS result_id,
    r.resultCode AS result_code,
    r.summary AS result_summary,
    r.dimensionScoresJson AS dimension_scores_json,
    r.createdAt AS result_created_at
FROM test_attempts a
INNER JOIN talent_tests t ON t.id = a.testId
INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
INNER JOIN learner_assessment_versions v ON v.id = m.versionId
INNER JOIN test_results r ON r.attemptId = a.id
WHERE a.studentId = :student_id
  AND a.status = 'submitted'
  AND t.code IN (
SQL
            . implode(', ', $placeholders)
            . ")\nORDER BY a.submittedAt DESC, a.id DESC",
            $parameters
        );

        return array_map([$this, 'normalizeHistoryRow'], $rows);
    }

    private function normalizeHistoryRow(array $row): array
    {
        return [
            'id' => $this->normalizeOptionalUuid($row['attempt_id'] ?? null, 'test_attempts.id'),
            'student_id' => $this->normalizeOptionalUuid($row['student_id'] ?? null, 'test_attempts.studentId'),
            'assessment_id' => $this->normalizeOptionalUuid($row['test_id'] ?? null, 'test_attempts.testId'),
            'assessment_code' => (string) $row['test_code'],
            'assessment_name' => (string) $row['test_name'],
            'assessment_type' => (string) $row['test_type'],
            'version_id' => $this->normalizeOptionalUuid($row['version_id'] ?? null, 'learner_assessment_versions.id'),
            'assessment_version' => (string) $row['assessment_version'],
            'scoring_version' => (string) $row['scoring_version'],
            'status' => 'submitted',
            'started_at' => (string) $row['started_at'],
            'submitted_at' => (string) $row['submitted_at'],
            'result_id' => $this->normalizeOptionalUuid($row['result_id'] ?? null, 'test_results.id'),
            'result_code' => (string) $row['result_code'],
            'summary' => (string) $row['result_summary'],
            'result_created_at' => (string) $row['result_created_at'],
            'dimension_scores' => $this->decodeJson($row['dimension_scores_json'] ?? null, 'test_results.dimensionScoresJson'),
            'id_origin' => 'database',
        ];
    }

    private function normalizeOptionalUuid(mixed $value, string $context): string
    {
        $value = (string) $value;
        return Uuid::isValid($value) ? Uuid::normalizeDatabase($value, $context) : $value;
    }

    private function normalizeDefinition(array $definition): array
    {
        $definition['id'] = Uuid::normalizeDatabase((string) $definition['id'], 'talent_tests.id');
        $definition['id_origin'] = 'database';

        return $definition;
    }

    private function normalizeQuestion(array $question): array
    {
        $question['id'] = Uuid::normalizeDatabase((string) $question['id'], 'test_questions.id');
        $question['test_id'] = Uuid::normalizeDatabase((string) $question['test_id'], 'test_questions.testId');
        $question['assessment_id'] = $question['test_id'];
        $question['options'] = $this->decodeJson($question['options_json'] ?? null, 'test_questions.optionsJson');
        $question['id_origin'] = 'database';

        unset($question['options_json']);

        return $question;
    }

    private function normalizeAttempt(array $attempt): array
    {
        $attempt['id'] = Uuid::normalizeDatabase((string) $attempt['id'], 'test_attempts.id');
        $attempt['test_id'] = Uuid::normalizeDatabase((string) $attempt['test_id'], 'test_attempts.testId');
        $attempt['assessment_id'] = $attempt['test_id'];
        $attempt['student_id'] = Uuid::normalizeDatabase((string) $attempt['student_id'], 'test_attempts.studentId');
        $attempt['status'] = AssessmentAttemptStatus::normalize($attempt['status'] ?? null)->value;
        $attempt['id_origin'] = 'database';

        if (($attempt['result_id'] ?? null) !== null) {
            $resultId = Uuid::normalizeDatabase((string) $attempt['result_id'], 'test_results.id');
            $attempt['result'] = [
                'id' => $resultId,
                'result_code' => $attempt['result_code'],
                'summary' => $attempt['result_summary'],
                'dimension_scores' => $this->decodeJson(
                    $attempt['dimension_scores_json'] ?? null,
                    'test_results.dimensionScoresJson'
                ),
                'scoring_version' => $attempt['scoring_version'],
            ];
        } else {
            $attempt['result'] = null;
        }

        unset(
            $attempt['result_id'],
            $attempt['result_code'],
            $attempt['result_summary'],
            $attempt['dimension_scores_json'],
            $attempt['scoring_version']
        );

        return $attempt;
    }

    private function decodeAnswer(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
    }
}
