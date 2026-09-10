<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Sources\SkillSource;
use Throwable;

final class DatabaseSkillSource implements SkillSource
{
    private const SQL = <<<'SQL'
SELECT
    ss.id AS student_skill_id,
    s.id AS skill_id,
    s.code,
    s.name,
    s.category,
    ss.levelScore AS level_score,
    ss.sourceType AS source_type,
    ss.verificationStatus AS verification_status,
    ss.verifiedAt AS verified_at,
    ss.updatedAt AS source_updated_at
FROM student_skills ss
INNER JOIN skills s ON s.id = ss.skillId
WHERE ss.studentId = :student_id
  AND s.status = 'active'
  AND ss.verificationStatus IN ('self_declared', 'pending', 'verified')
ORDER BY CASE WHEN ss.verificationStatus = 'verified' THEN 0 ELSE 1 END, ss.updatedAt ASC, ss.id ASC
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

        $skills = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $updatedAt = self::timestamp($row['source_updated_at'] ?? null);
            if ($updatedAt === null) {
                continue;
            }

            $skills[] = [
                'student_skill_id' => (string) $row['student_skill_id'],
                'skill_id' => (string) $row['skill_id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'category' => (string) $row['category'],
                'level_score' => (float) $row['level_score'],
                'source_type' => (string) $row['source_type'],
                'verification_status' => (string) $row['verification_status'],
                'verified_at' => self::timestamp($row['verified_at'] ?? null),
                'source_updated_at' => $updatedAt,
            ];
        }

        return $this->excludeRevokedProjections($skills, trim($studentId));
    }

    /**
     * Projection scores copied from teacher evaluations must disappear when
     * their latest evidence is revoked. Independently declared skills stay.
     *
     * @param list<array<string,mixed>> $skills
     * @return list<array<string,mixed>>
     */
    private function excludeRevokedProjections(array $skills, string $studentId): array
    {
        if ($skills === [] || !$this->hasColumns('learner_skill_evidence', ['studentSkillId', 'verificationStatus', 'observedAt'])) {
            return $skills;
        }
        $hasRevokedAt = $this->hasColumns('learner_skill_evidence', ['revokedAt']);
        $sql = 'SELECT e.studentSkillId, e.verificationStatus, e.observedAt, e.id'
            . ($hasRevokedAt ? ', e.revokedAt' : '')
            . ' FROM learner_skill_evidence e
               INNER JOIN student_skills ss ON ss.id = e.studentSkillId
               WHERE ss.studentId = :student_id
               ORDER BY e.observedAt DESC, e.id DESC';
        try {
            $statement = $this->pdo->prepare($sql);
            if ($statement === false || !$statement->execute(['student_id' => $studentId])) {
                return $skills;
            }
            $latest = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (string) ($row['studentSkillId'] ?? '');
                if ($id === '' || isset($latest[$id])) {
                    continue;
                }
                $latest[$id] = $row;
            }
        } catch (Throwable) {
            return $skills;
        }
        $projections = ['assessment', 'teacher_assessment', 'teacher', 'evaluation', 'published_evaluation'];
        $kept = [];
        foreach ($skills as $skill) {
            $evidence = $latest[(string) ($skill['student_skill_id'] ?? '')] ?? null;
            $source = strtolower(trim((string) ($skill['source_type'] ?? '')));
            if (is_array($evidence) && in_array($source, $projections, true)) {
                $revoked = $hasRevokedAt && is_string($evidence['revokedAt'] ?? null) && trim((string) $evidence['revokedAt']) !== '';
                $rejected = strtolower(trim((string) ($evidence['verificationStatus'] ?? ''))) === 'rejected';
                if ($revoked || $rejected) {
                    continue;
                }
            }
            $kept[] = $skill;
        }
        return $kept;
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
