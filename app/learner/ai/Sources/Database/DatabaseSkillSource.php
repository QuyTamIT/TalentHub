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

        return $skills;
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
