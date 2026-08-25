<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use Throwable;

final class DatabaseOpportunitySource implements OpportunitySource
{
    private const REQUIRED_INTERNSHIP_COLUMNS = [
        'student_profiles' => ['id'],
        'internship_posts' => ['id', 'enterpriseId', 'title', 'location', 'deadline', 'status'],
        'enterprises' => ['id', 'status', 'verificationStatus'],
    ];

    private const REQUIRED_ACTIVITY_COLUMNS = [
        'student_profiles' => ['id', 'classId'],
        'classes' => ['id', 'schoolId'],
        'schools' => ['id', 'name'],
        'activities' => ['id', 'schoolId', 'title', 'category', 'startAt', 'capacity', 'status'],
    ];

    private const REQUIRED_REGISTRATION_COLUMNS = [
        'activity_registrations' => ['id', 'activityId', 'studentId', 'status'],
    ];

    private const INTERNSHIP_SQL = <<<'SQL'
SELECT
    post.id AS opportunity_id,
    enterprise.id AS enterprise_id,
    post.title,
    post.location,
    post.deadline
FROM internship_posts post
INNER JOIN enterprises enterprise ON enterprise.id = post.enterpriseId
WHERE EXISTS (SELECT 1 FROM student_profiles student WHERE student.id = :student_id)
  AND post.status IN ('active', 'published')
  AND enterprise.status = 'active'
  AND (enterprise.verificationStatus IN ('verified', 'approved') OR enterprise.verificationStatus IS NULL OR enterprise.verificationStatus = 'pending')
  AND (
      post.audience = 'public'
      OR post.audience IS NULL
      OR (
          post.audience = 'partner_schools'
          AND EXISTS (
              SELECT 1
              FROM student_profiles sp
              INNER JOIN classes c ON c.id = sp.classId
              INNER JOIN internship_post_target_schools ipts ON ipts.schoolId = c.schoolId AND ipts.postId = post.id
              INNER JOIN school_enterprise_partnerships sep ON sep.schoolId = c.schoolId AND sep.enterpriseId = enterprise.id
              WHERE sp.id = :student_id_target AND sep.status = 'approved'
          )
      )
  )
ORDER BY post.createdAt DESC, post.id DESC
SQL;

    private const INTERNSHIP_SQL_FALLBACK = <<<'SQL'
SELECT
    post.id AS opportunity_id,
    enterprise.id AS enterprise_id,
    post.title,
    post.location,
    post.deadline
FROM internship_posts post
INNER JOIN enterprises enterprise ON enterprise.id = post.enterpriseId
WHERE EXISTS (SELECT 1 FROM student_profiles student WHERE student.id = :student_id)
  AND post.status IN ('active', 'published')
  AND enterprise.status = 'active'
ORDER BY post.createdAt DESC, post.id DESC
SQL;

    private const ACTIVITY_SQL_WITH_REGISTRATIONS = <<<'SQL'
SELECT
    activity.id AS opportunity_id,
    activity.title,
    activity.category,
    school.name AS location,
    COALESCE(activity.endAt, activity.startAt) AS deadline,
    activity.status
FROM activities activity
INNER JOIN schools school ON school.id = activity.schoolId
INNER JOIN classes class ON class.schoolId = school.id
INNER JOIN student_profiles student ON student.classId = class.id
WHERE student.id = :student_id
  AND activity.status IN ('published', 'ongoing')
  AND (activity.endAt IS NULL OR activity.endAt >= :current_time)
  AND (SELECT COUNT(1) FROM activity_registrations reg WHERE reg.activityId = activity.id AND reg.status IN ('pending', 'approved', 'attended')) < activity.capacity
  AND NOT EXISTS (SELECT 1 FROM activity_registrations reg WHERE reg.activityId = activity.id AND reg.studentId = :reg_student_id AND reg.status IN ('pending', 'approved', 'attended'))
ORDER BY activity.startAt ASC, activity.id ASC
SQL;

    private const ACTIVITY_SQL_SIMPLE = <<<'SQL'
SELECT
    activity.id AS opportunity_id,
    activity.title,
    activity.category,
    school.name AS location,
    COALESCE(activity.endAt, activity.startAt) AS deadline,
    activity.status
FROM activities activity
INNER JOIN schools school ON school.id = activity.schoolId
INNER JOIN classes class ON class.schoolId = school.id
INNER JOIN student_profiles student ON student.classId = class.id
WHERE student.id = :student_id
  AND activity.status IN ('published', 'ongoing')
  AND (activity.endAt IS NULL OR activity.endAt >= :current_time)
ORDER BY activity.startAt ASC, activity.id ASC
SQL;

    private readonly DateTimeImmutable $clock;

    public function __construct(private readonly PDO $pdo, ?DateTimeImmutable $clock = null)
    {
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    public function forStudent(string $studentId): array
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return [];
        }

        $opportunities = [];

        // 1. Fetch internship posts if contract available
        if ($this->hasInternshipContract()) {
            try {
                $statement = $this->pdo->prepare(self::INTERNSHIP_SQL);
                $executed = $statement !== false && $statement->execute([
                    'student_id' => $studentId,
                    'student_id_target' => $studentId,
                ]);

                if (!$executed) {
                    $statement = $this->pdo->prepare(self::INTERNSHIP_SQL_FALLBACK);
                    $executed = $statement !== false && $statement->execute(['student_id' => $studentId]);
                }

                if ($executed) {
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $deadline = self::timestamp($row['deadline'] ?? null);
                        if ($deadline === null) {
                            continue;
                        }

                        $opportunities[] = [
                            'opportunity_id' => (string) $row['opportunity_id'],
                            'enterprise_id' => (string) $row['enterprise_id'],
                            'title' => (string) $row['title'],
                            'location' => (string) $row['location'],
                            'deadline_at' => $deadline,
                            'opportunity_type' => 'internship',
                            'status' => 'active',
                        ];
                    }
                }
            } catch (Throwable) {
                // Ignore and continue
            }
        }

        // 2. Fetch school activities if contract available
        if ($this->hasActivityContract()) {
            try {
                $currentTime = $this->clock->format('Y-m-d H:i:s');
                $hasReg = $this->hasRegistrationsContract();
                $sql = $hasReg ? self::ACTIVITY_SQL_WITH_REGISTRATIONS : self::ACTIVITY_SQL_SIMPLE;
                $params = ['student_id' => $studentId, 'current_time' => $currentTime];
                if ($hasReg) {
                    $params['reg_student_id'] = $studentId;
                }
                $statement = $this->pdo->prepare($sql);
                if ($statement !== false && $statement->execute($params)) {
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $deadline = self::timestamp($row['deadline'] ?? null);
                        if ($deadline === null) {
                            continue;
                        }

                        $opportunities[] = [
                            'opportunity_id' => (string) $row['opportunity_id'],
                            'title' => (string) $row['title'],
                            'category' => (string) $row['category'],
                            'location' => (string) ($row['location'] ?? 'Trường học'),
                            'deadline_at' => $deadline,
                            'opportunity_type' => 'activity',
                            'status' => (string) $row['status'],
                        ];
                    }
                }
            } catch (Throwable) {
                // Ignore and continue
            }
        }

        usort($opportunities, static fn (array $left, array $right): int => [
            $left['deadline_at'], $left['opportunity_id'],
        ] <=> [
            $right['deadline_at'], $right['opportunity_id'],
        ]);

        return $opportunities;
    }

    private function hasInternshipContract(): bool
    {
        foreach (self::REQUIRED_INTERNSHIP_COLUMNS as $table => $requiredColumns) {
            if (array_diff($requiredColumns, $this->columnsFor($table)) !== []) {
                return false;
            }
        }

        return true;
    }

    private function hasActivityContract(): bool
    {
        foreach (self::REQUIRED_ACTIVITY_COLUMNS as $table => $requiredColumns) {
            if (array_diff($requiredColumns, $this->columnsFor($table)) !== []) {
                return false;
            }
        }

        return true;
    }

    private function hasRegistrationsContract(): bool
    {
        foreach (self::REQUIRED_REGISTRATION_COLUMNS as $table => $requiredColumns) {
            if (array_diff($requiredColumns, $this->columnsFor($table)) !== []) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            return [];
        }

        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = match ($driver) {
                'sqlite' => "PRAGMA table_info({$table})",
                'mysql' => "SHOW COLUMNS FROM `{$table}`",
                default => null,
            };
            if ($sql === null) {
                return [];
            }

            $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? $row['Field'] ?? null;
            if (is_string($name)) {
                $columns[] = $name;
            }
        }

        return $columns;
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
