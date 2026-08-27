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
        'activities' => ['id', 'schoolId', 'title', 'category', 'startAt', 'endAt', 'capacity', 'status'],
        'activity_details' => ['activityId', 'audienceScope', 'filterCategory', 'locationName'],
        'activity_registration_policies' => ['activityId', 'registrationOpensAt', 'registrationClosesAt'],
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
  AND enterprise.verificationStatus IN ('verified', 'approved')
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
  AND enterprise.verificationStatus IN ('verified', 'approved')
ORDER BY post.createdAt DESC, post.id DESC
SQL;

    private const ACTIVITY_SQL_WITH_REGISTRATIONS = <<<'SQL'
SELECT
    activity.id AS opportunity_id,
    activity.title,
    activity.category,
    details.locationName AS location,
    policy.registrationClosesAt AS deadline,
    activity.status,
    activity.capacity,
    (SELECT COUNT(1) FROM activity_registrations occupied WHERE occupied.activityId = activity.id AND occupied.status IN ('approved', 'attended')) AS enrolled_count
FROM activities activity
INNER JOIN schools school ON school.id = activity.schoolId
INNER JOIN classes class ON class.schoolId = school.id
INNER JOIN student_profiles student ON student.classId = class.id
INNER JOIN activity_details details ON details.activityId = activity.id
INNER JOIN activity_registration_policies policy ON policy.activityId = activity.id
WHERE student.id = :student_id
  AND activity.status = 'published'
  AND details.audienceScope = 'school_only'
  AND policy.registrationOpensAt <= :registration_opened_at
  AND :registration_closes_at < policy.registrationClosesAt
  AND :activity_starts_at < activity.startAt
  AND :activity_ends_at < COALESCE(activity.endAt, activity.startAt)
  AND (SELECT COUNT(1) FROM activity_registrations reg WHERE reg.activityId = activity.id AND reg.status IN ('approved', 'attended')) < activity.capacity
  AND NOT EXISTS (SELECT 1 FROM activity_registrations reg WHERE reg.activityId = activity.id AND reg.studentId = :reg_student_id AND reg.status IN ('pending', 'approved', 'waitlisted', 'attended'))
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
                $supportsAudience = in_array('audience', $this->columnsFor('internship_posts'), true);
                $supportsCreatedAt = in_array('createdAt', $this->columnsFor('internship_posts'), true);
                $supportsSlots = in_array('slots', $this->columnsFor('internship_posts'), true);
                $orderBy = $supportsCreatedAt ? 'ORDER BY post.createdAt DESC, post.id DESC' : 'ORDER BY post.id DESC';
                $query = $supportsAudience ? self::INTERNSHIP_SQL : self::INTERNSHIP_SQL_FALLBACK;
                $query = str_replace("post.deadline\n", 'post.deadline, ' . ($supportsSlots ? 'post.slots' : '1') . " AS capacity\n", $query);
                $query = str_replace('ORDER BY post.createdAt DESC, post.id DESC', $orderBy, $query);
                $statement = $this->pdo->prepare(
                    $query,
                );
                $parameters = ['student_id' => $studentId];
                if ($supportsAudience) {
                    $parameters['student_id_target'] = $studentId;
                }
                $executed = $statement !== false && $statement->execute($parameters);

                if ($executed) {
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $deadline = self::timestamp($row['deadline'] ?? null);
                        if ($deadline === null || $deadline <= $this->clock->format('Y-m-d\\TH:i:s.uP')) {
                            continue;
                        }

                        $capacity = max(1, (int) ($row['capacity'] ?? 1));
                        $applicationState = $this->internshipApplicationState((string) $row['opportunity_id'], $studentId);
                        if ($applicationState['already_applied'] || $applicationState['accepted'] >= $capacity) continue;
                        $opportunities[] = [
                            'opportunity_id' => (string) $row['opportunity_id'],
                            'catalog_id' => (string) $row['opportunity_id'],
                            'enterprise_id' => (string) $row['enterprise_id'],
                            'title' => (string) $row['title'],
                            'location' => (string) $row['location'],
                            'deadline_at' => $deadline,
                            'opportunity_type' => 'internship',
                            'status' => 'active',
                            'availability' => ['capacity' => $capacity, 'enrolled' => $applicationState['accepted'], 'remaining' => max(0, $capacity - $applicationState['accepted'])],
                            'url' => '/app/learner/opportunities.php?postId=' . rawurlencode((string) $row['opportunity_id']),
                            'action' => ['type' => 'view_opportunity', 'post_id' => (string) $row['opportunity_id']],
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
                $statement = $this->pdo->prepare(self::ACTIVITY_SQL_WITH_REGISTRATIONS);
                $params = [
                    'student_id' => $studentId,
                    'registration_opened_at' => $currentTime,
                    'registration_closes_at' => $currentTime,
                    'activity_starts_at' => $currentTime,
                    'activity_ends_at' => $currentTime,
                    'reg_student_id' => $studentId,
                ];
                if ($statement !== false && $statement->execute($params)) {
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $deadline = self::timestamp($row['deadline'] ?? null);
                        if ($deadline === null) {
                            continue;
                        }

                        $opportunities[] = [
                            'opportunity_id' => (string) $row['opportunity_id'],
                            'catalog_id' => (string) $row['opportunity_id'],
                            'title' => (string) $row['title'],
                            'category' => (string) $row['category'],
                            'location' => (string) ($row['location'] ?? 'Trường học'),
                            'deadline_at' => $deadline,
                            'opportunity_type' => 'activity',
                            'status' => (string) $row['status'],
                            'availability' => [
                                'capacity' => (int) ($row['capacity'] ?? 0),
                                'enrolled' => (int) ($row['enrolled_count'] ?? 0),
                                'remaining' => max(0, (int) ($row['capacity'] ?? 0) - (int) ($row['enrolled_count'] ?? 0)),
                            ],
                            'url' => '/app/learner/activity-detail.php?id=' . rawurlencode((string) $row['opportunity_id']),
                            'action' => ['type' => 'register_activity', 'activity_source_id' => (string) $row['opportunity_id']],
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

    /** @return array{accepted:int,already_applied:bool} */
    private function internshipApplicationState(string $postId, string $studentId): array
    {
        $columns = $this->columnsFor('internship_applications');
        if (array_diff(['postId', 'studentId', 'status'], $columns) !== []) return ['accepted' => 0, 'already_applied' => false];
        try {
            $statement = $this->pdo->prepare(
                "SELECT SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS accepted, "
                . 'MAX(CASE WHEN studentId=:student THEN 1 ELSE 0 END) AS already_applied '
                . 'FROM internship_applications WHERE postId=:post'
            );
            $statement->execute(['student' => $studentId, 'post' => $postId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
            return ['accepted' => (int) ($row['accepted'] ?? 0), 'already_applied' => (int) ($row['already_applied'] ?? 0) === 1];
        } catch (Throwable) {
            return ['accepted' => PHP_INT_MAX, 'already_applied' => true];
        }
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
