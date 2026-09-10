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
        // TeacherGradingRepository persists published reviews on assessments.
        // Canonical learner_evaluations rows are read when present; otherwise
        // the assessments fallback below is the live write path.
        $canonical = $this->hasColumns('learner_evaluations', [
            'id', 'seriesId', 'revision', 'studentId', 'legacyAssessmentId', 'contextType',
            'contextId', 'overallScore', 'comment', 'status', 'publishedAt', 'updatedAt',
        ]);
        $evaluations = $this->legacyForStudent($studentId, $canonical);
        if ($canonical) {
            $statement = $this->pdo->prepare("SELECT ev.* FROM learner_evaluations ev
                WHERE ev.studentId = :student_id AND ev.status = 'published' AND ev.publishedAt IS NOT NULL
                AND ev.revision = (SELECT MAX(newer.revision) FROM learner_evaluations newer
                    WHERE newer.seriesId = ev.seriesId AND newer.studentId = ev.studentId)");
            $statement->execute(['student_id' => trim($studentId)]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $publishedAt = self::timestamp($row['publishedAt']);
                if ($publishedAt === null) {
                    continue;
                }
                $items = $this->skillItems((string) $row['id'], $publishedAt);
                $evaluations[] = [
                    'evaluation_id' => (string) $row['id'],
                    'context_type' => (string) $row['contextType'],
                    'context_id' => $row['contextId'],
                    'revision' => (int) $row['revision'],
                    'overall_score' => is_numeric($row['overallScore']) ? (float) $row['overallScore'] : null,
                    'published_at' => $publishedAt,
                    'updated_at' => self::timestamp($row['updatedAt']) ?? $publishedAt,
                    'feedback' => (string) ($row['comment'] ?? ''),
                    'skill_scores' => $items['scores'],
                    'skill_codes' => $items['codes'],
                    'tags' => $items['codes'],
                ];
            }
        }
        usort($evaluations, static fn (array $a, array $b): int =>
            strcmp($b['published_at'], $a['published_at']) ?: strcmp($b['evaluation_id'], $a['evaluation_id']));
        return $evaluations;
    }

    /** Only explicit, confirmed scores with a valid declared scale establish proficiency. */
    private function skillItems(string $evaluationId, string $publishedAt): array
    {
        $result = ['codes' => [], 'scores' => []];
        if (!$this->hasColumns('learner_evaluation_items', ['evaluationId', 'itemKind', 'skillId', 'score', 'maxScore', 'confirmed'])
            || !$this->hasColumns('skills', ['id', 'code', 'status'])) {
            return $result;
        }
        $statement = $this->pdo->prepare("SELECT s.code, item.score, item.maxScore
            FROM learner_evaluation_items item INNER JOIN skills s ON s.id = item.skillId AND s.status = 'active'
            WHERE item.evaluationId = :evaluation_id AND item.itemKind = 'skill' AND item.confirmed = 1
            ORDER BY s.code");
        $statement->execute(['evaluation_id' => $evaluationId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = strtolower(trim((string) $row['code']));
            if ($code === '') {
                continue;
            }
            $result['codes'][] = $code;
            if (!is_numeric($row['score']) || !is_numeric($row['maxScore'])) {
                continue;
            }
            $score = (float) $row['score'];
            $maxScore = (float) $row['maxScore'];
            if (!is_finite($score) || !is_finite($maxScore) || $maxScore <= 0 || $score < 0 || $score > $maxScore) {
                continue;
            }
            $result['scores'][] = [
                'code' => $code,
                'score' => round($score / $maxScore * 100, 4),
                'raw_score' => $score,
                'max_score' => $maxScore,
                'evaluation_id' => $evaluationId,
                'verification_status' => 'verified',
                'published_at' => $publishedAt,
            ];
        }
        $result['codes'] = array_values(array_unique($result['codes']));
        return $result;
    }

    private function legacyForStudent(string $studentId, bool $canonical): array
    {
        if (!$this->hasPublishedEvaluationContract()) {
            return [];
        }

        $sql = $this->hasPresentationScoreContract() ? self::PRESENTATION_SQL : self::BASE_SQL;
        $statement = $this->pdo->prepare($sql);
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return [];
        }

        $evaluations = [];
        $represented = [];
        if ($canonical) {
            // All revisions block fallback: a revocation must not resurrect its legacy assessment.
            $links = $this->pdo->prepare('SELECT legacyAssessmentId FROM learner_evaluations WHERE studentId = :student_id AND legacyAssessmentId IS NOT NULL');
            $links->execute(['student_id' => trim($studentId)]);
            $represented = $links->fetchAll(PDO::FETCH_COLUMN);
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (in_array($row['evaluation_id'], $represented, true)) {
                continue;
            }
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
            $optional = [];
            foreach (['comment', 'updatedAt'] as $column) {
                if ($this->hasColumns('assessments', [$column])) {
                    $optional[] = $column;
                }
            }
            if ($optional !== []) {
                $details = $this->pdo->prepare('SELECT ' . implode(', ', $optional) . ' FROM assessments WHERE id = :id AND studentId = :student_id');
                $details->execute(['id' => $row['evaluation_id'], 'student_id' => trim($studentId)]);
                $detail = $details->fetch(PDO::FETCH_ASSOC) ?: [];
                if (array_key_exists('comment', $detail)) {
                    $evaluation['feedback'] = (string) ($detail['comment'] ?? '');
                }
                if (array_key_exists('updatedAt', $detail)) {
                    $evaluation['updated_at'] = self::timestamp($detail['updatedAt']) ?? $publishedAt;
                }
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
