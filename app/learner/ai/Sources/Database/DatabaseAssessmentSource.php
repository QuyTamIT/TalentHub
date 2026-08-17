<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use TalentHub\Learner\Ai\Sources\AssessmentSource;
use Throwable;

final class DatabaseAssessmentSource implements AssessmentSource
{
    private const SQL = <<<'SQL'
SELECT
    ta.id AS attempt_id,
    tt.id AS test_id,
    tt.code AS test_code,
    tt.type AS test_type,
    v.id AS version_id,
    v.version AS assessment_version,
    v.scoringVersion AS scoring_version,
    tr.id AS result_id,
    tr.resultCode AS result_code,
    tr.dimensionScoresJson AS dimension_scores_json,
    metadata.submittedAt AS submitted_at
FROM test_attempts ta
INNER JOIN learner_assessment_attempt_metadata metadata ON metadata.attemptId = ta.id
INNER JOIN learner_assessment_versions v ON v.id = metadata.versionId
INNER JOIN talent_tests tt ON tt.id = ta.testId
INNER JOIN test_results tr ON tr.attemptId = ta.id
WHERE ta.studentId = :student_id
  AND ta.status = 'submitted'
  AND metadata.status = 'submitted'
  AND metadata.submittedAt IS NOT NULL
  AND v.status = 'published'
  AND v.publishedAt IS NOT NULL
  AND tt.status = 'published'
ORDER BY metadata.submittedAt DESC, ta.id DESC
SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forStudent(string $studentId): array
    {
        $statement = $this->pdo->prepare(self::SQL);
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return [];
        }

        $assessments = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scores = self::decodeScores($row['dimension_scores_json'] ?? null);
            $submittedAt = self::timestamp($row['submitted_at'] ?? null);
            if ($scores === null || $submittedAt === null) {
                continue;
            }

            $assessments[] = [
                'attempt_id' => (string) $row['attempt_id'],
                'test_id' => (string) $row['test_id'],
                'test_code' => (string) $row['test_code'],
                'test_type' => (string) $row['test_type'],
                'version_id' => (string) $row['version_id'],
                'assessment_version' => (string) $row['assessment_version'],
                'scoring_version' => (string) $row['scoring_version'],
                'result_id' => (string) $row['result_id'],
                'result_code' => (string) $row['result_code'],
                'dimension_scores' => $scores,
                'submitted_at' => $submittedAt,
            ];
        }

        return $assessments;
    }

    /** @return array<string|int, mixed>|null */
    private static function decodeScores(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private static function timestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.uP');
        } catch (Throwable) {
            return null;
        }
    }
}
