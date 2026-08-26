<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
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
        $statement = $this->pdo->prepare(self::SQL);
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return [];
        }

        $experiences = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $confirmedAt = self::timestamp($row['confirmed_at'] ?? null);
            if ($confirmedAt === null) {
                continue;
            }

            $experiences[] = [
                'experience_id' => (string) $row['experience_id'],
                'activity_id' => (string) $row['activity_id'],
                'activity_category' => (string) $row['activity_category'],
                'hours' => (float) $row['hours'],
                'confirmed_at' => $confirmedAt,
            ];
        }

        return $experiences;
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
