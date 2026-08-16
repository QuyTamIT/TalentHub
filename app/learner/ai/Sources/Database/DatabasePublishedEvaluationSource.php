<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Sources\PublishedEvaluationSource;
use Throwable;

final class DatabasePublishedEvaluationSource implements PublishedEvaluationSource
{
    private const REQUIRED_COLUMNS = ['id', 'studentId', 'activityId', 'overallScore', 'status', 'publishedAt'];
    private const BASE_SQL = <<<'SQL'
SELECT id AS evaluation_id, activityId AS activity_id, overallScore AS overall_score, publishedAt AS published_at
FROM assessments
WHERE studentId = :student_id
  AND status = 'published'
  AND publishedAt IS NOT NULL
ORDER BY publishedAt DESC, id DESC
SQL;
    private const PRESENTATION_SQL = <<<'SQL'
SELECT
    assessment.id AS evaluation_id,
    assessment.activityId AS activity_id,
    assessment.overallScore AS overall_score,
    assessment.publishedAt AS published_at,
    MAX(CASE WHEN LOWER(criteria.code) = 'presentation' THEN score.score END) AS presentation_score
FROM assessments assessment
LEFT JOIN assessment_scores score ON score.assessmentId = assessment.id
LEFT JOIN assessment_criteria criteria ON criteria.id = score.criteriaId
WHERE assessment.studentId = :student_id
  AND assessment.status = 'published'
  AND assessment.publishedAt IS NOT NULL
GROUP BY assessment.id, assessment.activityId, assessment.overallScore, assessment.publishedAt
ORDER BY assessment.publishedAt DESC, assessment.id DESC
SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forStudent(string $studentId): array
    {
        if (!$this->hasPublishedEvaluationContract()) {
            return [];
        }

        $statement = $this->pdo->prepare($this->hasPresentationScoreContract() ? self::PRESENTATION_SQL : self::BASE_SQL);
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return [];
        }

        $evaluations = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $publishedAt = self::timestamp($row['published_at'] ?? null);
            if ($publishedAt === null || !is_numeric($row['overall_score'] ?? null)) {
                continue;
            }

            $evaluation = [
                'evaluation_id' => (string) $row['evaluation_id'],
                'activity_id' => (string) $row['activity_id'],
                'overall_score' => (float) $row['overall_score'],
                'published_at' => $publishedAt,
            ];
            if (is_numeric($row['presentation_score'] ?? null)) {
                $evaluation['presentation_score'] = (float) $row['presentation_score'];
            }
            $evaluations[] = $evaluation;
        }

        return $evaluations;
    }

    private function hasPublishedEvaluationContract(): bool
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $rows = match ($driver) {
                'sqlite' => $this->pdo->query('PRAGMA table_info(assessments)')?->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'mysql' => $this->pdo->query('SHOW COLUMNS FROM assessments')?->fetchAll(PDO::FETCH_ASSOC) ?: [],
                default => [],
            };
        } catch (Throwable) {
            return false;
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? $row['Field'] ?? null;
            if (is_string($name)) {
                $columns[] = $name;
            }
        }

        return array_diff(self::REQUIRED_COLUMNS, $columns) === [];
    }

    private function hasPresentationScoreContract(): bool
    {
        return $this->hasColumns('assessment_scores', ['assessmentId', 'criteriaId', 'score'])
            && $this->hasColumns('assessment_criteria', ['id', 'code']);
    }

    /** @param list<string> $requiredColumns */
    private function hasColumns(string $table, array $requiredColumns): bool
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $rows = match ($driver) {
                'sqlite' => $this->pdo->query('PRAGMA table_info(' . $table . ')')?->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'mysql' => $this->pdo->query('SHOW COLUMNS FROM ' . $table)?->fetchAll(PDO::FETCH_ASSOC) ?: [],
                default => [],
            };
        } catch (Throwable) {
            return false;
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? $row['Field'] ?? null;
            if (is_string($name)) {
                $columns[] = $name;
            }
        }
        return array_diff($requiredColumns, $columns) === [];
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
