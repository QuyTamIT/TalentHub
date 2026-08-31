<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Matching\JobSkillNormalizer;
use TalentHub\Learner\Ai\Sources\ActivityExperienceSource;
use Throwable;

final class DatabaseActivityExperienceSource implements ActivityExperienceSource
{
    private const SQL = <<<'SQL'
SELECT
    experience.id AS experience_id,
    activity.id AS activity_id,
    activity.category AS activity_category,
    experience.hours,
    experience.confirmedAt AS confirmed_at
FROM experience_logs experience
INNER JOIN checkins checkin_record ON checkin_record.id = experience.checkinId
INNER JOIN activity_registrations registration ON registration.id = checkin_record.registrationId
    AND registration.studentId = experience.studentId
    AND registration.activityId = experience.activityId
INNER JOIN activities activity ON activity.id = experience.activityId
WHERE experience.studentId = :student_id
  AND experience.status = 'confirmed'
  AND experience.confirmedAt IS NOT NULL
  AND registration.status = 'attended'
  AND checkin_record.status = 'confirmed'
  AND checkin_record.confirmedAt IS NOT NULL
  AND activity.status IN ('published', 'ongoing', 'completed')
ORDER BY experience.confirmedAt DESC, experience.id DESC
SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forStudent(string $studentId): array
    {
        $statement = $this->pdo->prepare($this->sql());
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return [];
        }

        $experiences = [];
        $skillNormalizer = $this->skillNormalizer();
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $confirmedAt = self::timestamp($row['confirmed_at'] ?? null);
            if ($confirmedAt === null) {
                continue;
            }

            $experience = [
                'experience_id' => (string) $row['experience_id'],
                'activity_id' => (string) $row['activity_id'],
                'activity_category' => (string) $row['activity_category'],
                'hours' => (float) $row['hours'],
                'confirmed_at' => $confirmedAt,
            ];
            $skillTags = $skillNormalizer?->normalize(self::decodeSkillTags($row['skill_tags'] ?? null))->codes() ?? [];
            if ($skillTags !== []) {
                $experience['skill_tags'] = $skillTags;
            }
            $experiences[] = $experience;
        }

        return $experiences;
    }

    private function sql(): string
    {
        if (!$this->hasColumn('activity_details', 'skillTags')) {
            return self::SQL;
        }

        return str_replace(
            'activity.category AS activity_category,',
            "activity.category AS activity_category,\n    (SELECT details.skillTags FROM activity_details details WHERE details.activityId = activity.id LIMIT 1) AS skill_tags,",
            self::SQL,
        );
    }

    /** @return list<mixed> */
    private static function decodeSkillTags(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            return false;
        }
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $statement = $this->pdo->query("PRAGMA table_info({$table})");
            } elseif ($driver === 'mysql') {
                $statement = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
            } else {
                return false;
            }
            foreach ($statement?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (($row['name'] ?? $row['Field'] ?? null) === $column) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }
        return false;
    }

    /**
     * Activity detail tags are authored as display labels. Resolve them only
     * through the active canonical skills registry; if that registry cannot
     * be read, omit the tags so the scorer fails closed instead of inventing
     * codes from prose.
     */
    private function skillNormalizer(): ?JobSkillNormalizer
    {
        try {
            $statement = $this->pdo->prepare("SELECT code FROM skills WHERE status = 'active'");
            if ($statement === false || !$statement->execute()) {
                return null;
            }
            return new JobSkillNormalizer(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        } catch (Throwable) {
            return null;
        }
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
