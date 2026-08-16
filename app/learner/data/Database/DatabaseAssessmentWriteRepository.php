<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use JsonException;
use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Contracts\AssessmentWriteRepository;
use TalentHub\Learner\Data\Support\Uuid;
use Throwable;

final class DatabaseAssessmentWriteRepository implements AssessmentWriteRepository
{
    private const HOLLAND_DIMENSIONS = ['R', 'I', 'A', 'S', 'E', 'C'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function startAttempt(string $studentId, string $testId, string $version): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $testId = Uuid::normalizeDatabase($testId, 'test_id');
        $version = trim($version);
        if ($version === '') {
            throw new RuntimeException('Assessment version is required.');
        }

        return $this->transaction(function () use ($studentId, $testId, $version): array {
            $definition = $this->fetchOne(
                <<<'SQL'
SELECT v.id AS version_id, v.version AS assessment_version, v.scoringVersion AS scoring_version, v.schemaHash AS schema_hash
FROM learner_assessment_versions v
INNER JOIN talent_tests t ON t.id = v.testId
WHERE v.testId = :test_id
  AND v.version = :version
  AND v.status = 'published'
  AND t.status = 'published'
LIMIT 1
SQL,
                ['test_id' => $testId, 'version' => $version]
            );
            if ($definition === null) {
                throw new RuntimeException('Requested assessment version is unavailable.');
            }

            $now = $this->now();
            $attemptId = $this->newUuid();
            $metadataId = $this->newUuid();
            $this->execute(
                'INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt) VALUES (:id, :test_id, :student_id, :status, :started_at, NULL, :created_at, :updated_at)',
                [
                    'id' => $attemptId,
                    'test_id' => $testId,
                    'student_id' => $studentId,
                    'status' => 'in_progress',
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $this->execute(
                'INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt) VALUES (:id, :attempt_id, :version_id, :status, NULL, NULL, NULL, :created_at, :updated_at)',
                [
                    'id' => $metadataId,
                    'attempt_id' => $attemptId,
                    'version_id' => $definition['version_id'],
                    'status' => 'in_progress',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            return $this->attemptView($this->findOwnedAttempt($studentId, $attemptId));
        });
    }

    public function saveAnswer(string $studentId, string $attemptId, string $questionId, mixed $answer): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $attemptId = Uuid::normalizeDatabase($attemptId, 'attempt_id');
        $questionId = Uuid::normalizeDatabase($questionId, 'question_id');
        $encodedAnswer = $this->encodeJson($answer);

        return $this->transaction(function () use ($studentId, $attemptId, $questionId, $encodedAnswer): array {
            $attempt = $this->findOwnedAttempt($studentId, $attemptId, true);
            $this->assertInProgress($attempt);
            $question = $this->fetchOne(
                'SELECT questionId FROM learner_assessment_question_versions WHERE versionId = :version_id AND questionId = :question_id LIMIT 1',
                ['version_id' => $attempt['version_id'], 'question_id' => $questionId]
            );
            if ($question === null) {
                throw new RuntimeException('Question is not part of the approved assessment version.');
            }

            $now = $this->now();
            $updated = $this->execute(
                <<<'SQL'
UPDATE learner_assessment_answers
SET answerJson = :answer_json, answeredAt = :answered_at
WHERE attemptId = :attempt_id
  AND questionId = :question_id
  AND EXISTS (
      SELECT 1
      FROM test_attempts a
      INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
      WHERE a.id = learner_assessment_answers.attemptId
        AND a.id = :owned_attempt_id
        AND a.studentId = :student_id
        AND a.status = 'in_progress'
        AND m.status = 'in_progress'
  )
SQL,
                [
                    'answer_json' => $encodedAnswer,
                    'answered_at' => $now,
                    'attempt_id' => $attemptId,
                    'question_id' => $questionId,
                    'owned_attempt_id' => $attemptId,
                    'student_id' => $studentId,
                ]
            );
            if ($updated === 0) {
                $this->execute(
                    'INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson, answeredAt) VALUES (:id, :attempt_id, :question_id, :answer_json, :answered_at)',
                    [
                        'id' => $this->newUuid(),
                        'attempt_id' => $attemptId,
                        'question_id' => $questionId,
                        'answer_json' => $encodedAnswer,
                        'answered_at' => $now,
                    ]
                );
            }

            $touched = $this->execute(
                <<<'SQL'
UPDATE learner_assessment_attempt_metadata
SET updatedAt = :updated_at
WHERE attemptId = :attempt_id
  AND status = 'in_progress'
  AND EXISTS (
      SELECT 1
      FROM test_attempts a
      WHERE a.id = learner_assessment_attempt_metadata.attemptId
        AND a.id = :owned_attempt_id
        AND a.studentId = :student_id
        AND a.status = 'in_progress'
  )
SQL,
                [
                    'updated_at' => $now,
                    'attempt_id' => $attemptId,
                    'owned_attempt_id' => $attemptId,
                    'student_id' => $studentId,
                ]
            );
            if ($touched !== 1) {
                throw new RuntimeException('Assessment attempt is no longer writable.');
            }

            return $this->attemptView($this->findOwnedAttempt($studentId, $attemptId));
        });
    }

    public function submitAttempt(string $studentId, string $attemptId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $attemptId = Uuid::normalizeDatabase($attemptId, 'attempt_id');

        return $this->transaction(function () use ($studentId, $attemptId): array {
            $attempt = $this->findOwnedAttempt($studentId, $attemptId, true);
            if ($attempt['attempt_status'] === 'submitted' && $attempt['metadata_status'] === 'submitted') {
                return $this->resultView($studentId, $attemptId);
            }
            $this->assertInProgress($attempt);

            $questions = $this->questionsForAttempt($studentId, $attemptId, $attempt['version_id']);
            $answers = $this->answersForAttempt($studentId, $attemptId, true);
            foreach ($questions as $question) {
                if ((int) $question['required'] === 1 && !array_key_exists($question['question_id'], $answers)) {
                    throw new RuntimeException('All required assessment questions must be answered before submission.');
                }
            }

            $inputHash = $this->inputHash($attempt, $answers);
            $scored = $this->score($attempt['scoring_version'], $questions, $answers);
            $now = $this->now();
            $this->execute(
                'INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt) VALUES (:id, :attempt_id, :result_code, :summary, :dimension_scores_json, :scoring_version, :created_at)',
                [
                    'id' => $this->newUuid(),
                    'attempt_id' => $attemptId,
                    'result_code' => $scored['result_code'],
                    'summary' => $scored['summary'],
                    'dimension_scores_json' => $this->encodeJson($scored['dimension_scores']),
                    'scoring_version' => $attempt['scoring_version'],
                    'created_at' => $now,
                ]
            );
            $updatedAttempt = $this->execute(
                'UPDATE test_attempts SET status = :submitted_status, submittedAt = :submitted_at, updatedAt = :updated_at WHERE id = :attempt_id AND studentId = :student_id AND status = :in_progress_status',
                [
                    'submitted_status' => 'submitted',
                    'submitted_at' => $now,
                    'updated_at' => $now,
                    'attempt_id' => $attemptId,
                    'student_id' => $studentId,
                    'in_progress_status' => 'in_progress',
                ]
            );
            $updatedMetadata = $this->execute(
                <<<'SQL'
UPDATE learner_assessment_attempt_metadata
SET status = :submitted_status, submittedAt = :submitted_at, inputHash = :input_hash, updatedAt = :updated_at
WHERE attemptId = :attempt_id
  AND status = :in_progress_status
  AND EXISTS (
      SELECT 1
      FROM test_attempts a
      WHERE a.id = learner_assessment_attempt_metadata.attemptId
        AND a.id = :owned_attempt_id
        AND a.studentId = :student_id
        AND a.status = :submitted_attempt_status
  )
SQL,
                [
                    'submitted_status' => 'submitted',
                    'submitted_at' => $now,
                    'input_hash' => $inputHash,
                    'updated_at' => $now,
                    'attempt_id' => $attemptId,
                    'in_progress_status' => 'in_progress',
                    'owned_attempt_id' => $attemptId,
                    'student_id' => $studentId,
                    'submitted_attempt_status' => 'submitted',
                ]
            );
            if ($updatedAttempt !== 1 || $updatedMetadata !== 1) {
                throw new RuntimeException('Assessment attempt changed before submission could complete.');
            }

            return $this->resultView($studentId, $attemptId);
        });
    }

    private function findOwnedAttempt(string $studentId, string $attemptId, bool $lock = false): array
    {
        $attempt = $this->fetchOne(
            <<<'SQL'
SELECT a.id AS attempt_id, a.testId AS test_id, a.studentId AS student_id, a.status AS attempt_status,
       a.startedAt AS started_at, a.submittedAt AS attempt_submitted_at,
       m.versionId AS version_id, m.status AS metadata_status, m.submittedAt AS metadata_submitted_at,
       m.inputHash AS input_hash, v.version AS assessment_version, v.scoringVersion AS scoring_version,
       v.schemaHash AS schema_hash
FROM test_attempts a
INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
INNER JOIN learner_assessment_versions v ON v.id = m.versionId
WHERE a.id = :attempt_id AND a.studentId = :student_id
LIMIT 1
SQL,
            ['attempt_id' => $attemptId, 'student_id' => $studentId],
            $lock
        );
        if ($attempt === null) {
            throw new RuntimeException('Assessment attempt was not found for this learner.');
        }

        return $attempt;
    }

    private function attemptView(array $attempt): array
    {
        return [
            'id' => $attempt['attempt_id'],
            'student_id' => $attempt['student_id'],
            'assessment_id' => $attempt['test_id'],
            'assessment_version' => $attempt['assessment_version'],
            'scoring_version' => $attempt['scoring_version'],
            'status' => $attempt['metadata_status'],
            'started_at' => $attempt['started_at'],
            'submitted_at' => $attempt['metadata_submitted_at'] ?? $attempt['attempt_submitted_at'],
            'input_hash' => $attempt['input_hash'],
            'answers' => $this->answersForAttempt($attempt['student_id'], $attempt['attempt_id']),
            'result' => null,
        ];
    }

    private function resultView(string $studentId, string $attemptId): array
    {
        $row = $this->fetchOne(
            <<<'SQL'
SELECT r.id, r.attemptId AS attempt_id, a.testId AS assessment_id, v.version AS assessment_version,
       r.resultCode AS result_code, r.summary, r.dimensionScoresJson AS dimension_scores_json,
       r.scoringVersion AS scoring_version, m.inputHash AS input_hash, m.submittedAt AS submitted_at
FROM test_results r
INNER JOIN test_attempts a ON a.id = r.attemptId
INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id
INNER JOIN learner_assessment_versions v ON v.id = m.versionId
WHERE r.attemptId = :attempt_id AND a.studentId = :student_id
LIMIT 1
SQL,
            ['attempt_id' => $attemptId, 'student_id' => $studentId]
        );
        if ($row === null) {
            throw new RuntimeException('Submitted assessment result was not found for this learner.');
        }

        return [
            'id' => $row['id'],
            'attempt_id' => $row['attempt_id'],
            'assessment_id' => $row['assessment_id'],
            'assessment_version' => $row['assessment_version'],
            'scoring_version' => $row['scoring_version'],
            'input_hash' => $row['input_hash'],
            'result_code' => $row['result_code'],
            'summary' => $row['summary'],
            'dimension_scores' => $this->decodeJson($row['dimension_scores_json']),
            'submitted_at' => $row['submitted_at'],
        ];
    }

    private function questionsForAttempt(string $studentId, string $attemptId, string $versionId): array
    {
        return $this->fetchAll(
            <<<'SQL'
SELECT qv.questionId AS question_id, qv.position, qv.dimensionCode AS dimension_code, qv.required
FROM learner_assessment_question_versions qv
WHERE qv.versionId = :version_id
  AND EXISTS (
      SELECT 1
      FROM test_attempts a
      WHERE a.id = :attempt_id AND a.studentId = :student_id
  )
ORDER BY qv.position, qv.questionId
SQL,
            ['version_id' => $versionId, 'attempt_id' => $attemptId, 'student_id' => $studentId]
        );
    }

    private function answersForAttempt(string $studentId, string $attemptId, bool $lock = false): array
    {
        $rows = $this->fetchAll(
            <<<'SQL'
SELECT answer.questionId AS question_id, answer.answerJson AS answer_json
FROM learner_assessment_answers answer
INNER JOIN test_attempts a ON a.id = answer.attemptId
WHERE answer.attemptId = :attempt_id AND a.studentId = :student_id
ORDER BY answer.questionId
SQL,
            ['attempt_id' => $attemptId, 'student_id' => $studentId],
            $lock
        );
        $answers = [];
        foreach ($rows as $row) {
            $answers[$row['question_id']] = $this->decodeJson($row['answer_json']);
        }

        return $answers;
    }

    private function inputHash(array $attempt, array $answers): string
    {
        ksort($answers, SORT_STRING);
        return hash('sha256', $this->encodeJson([
            'assessment_version' => $attempt['assessment_version'],
            'scoring_version' => $attempt['scoring_version'],
            'schema_hash' => $attempt['schema_hash'],
            'answers' => $answers,
        ]));
    }

    private function score(string $scoringVersion, array $questions, array $answers): array
    {
        if ($scoringVersion !== 'holland-riasec-1.0') {
            throw new RuntimeException('Assessment scoring version is not approved.');
        }

        $totals = array_fill_keys(self::HOLLAND_DIMENSIONS, 0.0);
        $counts = array_fill_keys(self::HOLLAND_DIMENSIONS, 0);
        foreach ($questions as $question) {
            $dimension = strtoupper(trim((string) $question['dimension_code']));
            if (!in_array($dimension, self::HOLLAND_DIMENSIONS, true)) {
                throw new RuntimeException('Assessment version contains an unsupported Holland dimension.');
            }
            $answer = $answers[$question['question_id']] ?? null;
            if (!is_int($answer) && !is_float($answer) && !(is_string($answer) && is_numeric($answer))) {
                throw new RuntimeException('Holland answers must be numeric values.');
            }
            $value = (float) $answer;
            if ($value < 1 || $value > 5) {
                throw new RuntimeException('Holland answers must be between 1 and 5.');
            }
            $totals[$dimension] += $value;
            $counts[$dimension]++;
        }

        $scores = [];
        foreach (self::HOLLAND_DIMENSIONS as $dimension) {
            if ($counts[$dimension] === 0) {
                throw new RuntimeException('Assessment version is incomplete for approved Holland scoring.');
            }
            $scores[$dimension] = (int) round((($totals[$dimension] - $counts[$dimension]) / ($counts[$dimension] * 4)) * 100);
        }
        $ranked = self::HOLLAND_DIMENSIONS;
        usort($ranked, static function (string $left, string $right) use ($scores): int {
            return $scores[$right] <=> $scores[$left]
                ?: array_search($left, self::HOLLAND_DIMENSIONS, true) <=> array_search($right, self::HOLLAND_DIMENSIONS, true);
        });

        return [
            'result_code' => implode('', array_slice($ranked, 0, 3)),
            'summary' => 'Holland RIASEC assessment submitted.',
            'dimension_scores' => $scores,
        ];
    }

    private function assertInProgress(array $attempt): void
    {
        if ($attempt['attempt_status'] !== 'in_progress' || $attempt['metadata_status'] !== 'in_progress') {
            throw new RuntimeException('Assessment attempt is immutable after submission or closure.');
        }
    }

    private function fetchAll(string $sql, array $parameters, bool $lock = false): array
    {
        if ($lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->pdo->prepare($sql);
        if ($statement === false || !$statement->execute($parameters)) {
            throw new RuntimeException('Assessment persistence query failed.');
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchOne(string $sql, array $parameters, bool $lock = false): ?array
    {
        return $this->fetchAll($sql, $parameters, $lock)[0] ?? null;
    }

    private function execute(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false || !$statement->execute($parameters)) {
            throw new RuntimeException('Assessment persistence update failed.');
        }

        return $statement->rowCount();
    }

    private function transaction(callable $callback): array
    {
        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction && !$this->pdo->beginTransaction()) {
            throw new RuntimeException('Assessment persistence transaction could not start.');
        }
        try {
            $result = $callback();
            if ($startedTransaction && !$this->pdo->commit()) {
                throw new RuntimeException('Assessment persistence transaction could not commit.');
            }
            return $result;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Assessment answer cannot be encoded as JSON.', 0, $exception);
        }
    }

    private function decodeJson(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored assessment answer is not valid JSON.', 0, $exception);
        }
    }

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
