<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\Enums\ActivityRegistrationStatus;
use TalentHub\Learner\Data\Enums\ActivityStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseActivityRepository extends AbstractDatabaseRepository implements ActivityRepository
{
    private const COLUMNS = 'activity.id, activity.schoolId, activity.createdByTeacherId, activity.title, activity.category, activity.startAt, activity.endAt, activity.capacity, activity.status';
    private const VISIBLE_STATUS_SQL = 'activity.status IN (:status_published, :status_ongoing, :status_completed)';

    public function all(): array
    {
        return array_map(
            [$this, 'normalizeActivity'],
            $this->fetchAll('all', $this->activitySelectSql() . ' ORDER BY activity.startAt, activity.id', $this->visibleStatusParameters())
        );
    }

    public function findById(string $activityId): ?array
    {
        $activityId = Uuid::normalizeDatabase($activityId, 'activity_id');
        $activity = $this->fetchOne(
            'findById',
            $this->activitySelectSql() . ' AND activity.id = :activity_id LIMIT 1',
            ['activity_id' => $activityId] + $this->visibleStatusParameters()
        );
        return $activity === null ? null : $this->normalizeActivity($activity);
    }

    public function registrationsFor(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $hasCancellation = $this->hasColumn('activity_registrations', 'cancelledAt')
            && $this->hasColumn('activity_registrations', 'cancellationReason');
        $cancellationColumns = $hasCancellation
            ? 'cancelledAt, cancellationReason'
            : 'NULL AS cancelledAt, NULL AS cancellationReason';
        $registeredAt = $this->hasColumn('activity_registrations', 'registeredAt')
            ? 'registeredAt'
            : 'NULL AS registeredAt';
        $updatedAt = $this->hasColumn('activity_registrations', 'updatedAt')
            ? 'updatedAt'
            : 'NULL AS updatedAt';
        $order = $this->hasColumn('activity_registrations', 'registeredAt') ? 'registeredAt, id' : 'id';
        $sql = "SELECT id, activityId, studentId, status, {$registeredAt}, {$updatedAt}, {$cancellationColumns}
                FROM activity_registrations WHERE studentId = :student_id ORDER BY {$order}";
        return array_map(
            [$this, 'normalizeRegistration'],
            $this->fetchAll('registrationsFor', $sql, ['student_id' => $studentId])
        );
    }

    private function normalizeActivity(array $activity): array
    {
        $activity['id'] = Uuid::normalizeDatabase((string) $activity['id'], 'activities.id');
        $activity['activity_id'] = $activity['id'];
        $activity['school_id'] = Uuid::normalizeDatabase((string) $activity['school_id'], 'activities.schoolId');
        $activity['created_by_teacher_id'] = Uuid::normalizeDatabase(
            (string) $activity['created_by_teacher_id'],
            'activities.createdByTeacherId'
        );
        $activity['status'] = ActivityStatus::normalize($activity['status'] ?? null)->value;
        $activity['participants'] = (int) ($activity['participants'] ?? 0);
        $activity['capacity'] = (int) ($activity['capacity'] ?? 0);
        $activity['approval_mode'] = (string) ($activity['approval_mode'] ?? 'automatic');
        $activity['source'] = 'database';
        $activity['source_role'] = 'teacher';
        $activity['id_origin'] = 'database';

        return $activity;
    }

    /** @return array<string, string> */
    private function visibleStatusParameters(): array
    {
        return [
            'status_published' => ActivityStatus::Published->value,
            'status_ongoing' => ActivityStatus::Ongoing->value,
            'status_completed' => ActivityStatus::Completed->value,
        ];
    }

    private function normalizeRegistration(array $registration): array
    {
        $registration['id'] = Uuid::normalizeDatabase((string) $registration['id'], 'activity_registrations.id');
        $registration['activity_id'] = Uuid::normalizeDatabase(
            (string) $registration['activity_id'],
            'activity_registrations.activityId'
        );
        $registration['student_id'] = Uuid::normalizeDatabase(
            (string) $registration['student_id'],
            'activity_registrations.studentId'
        );
        $registration['status'] = ActivityRegistrationStatus::normalize($registration['status'] ?? null)->value;
        $registration['id_origin'] = 'database';

        return $registration;
    }

    private function activitySelectSql(): string
    {
        $occupied = <<<'SQL'
            (SELECT COUNT(*) FROM activity_registrations occupied
             WHERE occupied.activityId = activity.id
               AND occupied.status IN ('approved','attended')) AS participants
            SQL;
        if ($this->hasTable('activity_registration_policies')) {
            return 'SELECT ' . self::COLUMNS . ", {$occupied},
                    policy.registrationOpensAt,
                    COALESCE(policy.registrationClosesAt, activity.startAt) AS registrationClosesAt,
                    COALESCE(policy.cancellationClosesAt, activity.startAt) AS cancellationClosesAt,
                    COALESCE(policy.approvalMode, 'automatic') AS approvalMode
                    FROM activities activity
                    LEFT JOIN activity_registration_policies policy ON policy.activityId = activity.id
                    WHERE " . self::VISIBLE_STATUS_SQL;
        }

        return 'SELECT ' . self::COLUMNS . ", {$occupied},
                NULL AS registrationOpensAt, activity.startAt AS registrationClosesAt,
                activity.startAt AS cancellationClosesAt, 'automatic' AS approvalMode
                FROM activities activity WHERE " . self::VISIBLE_STATUS_SQL;
    }

    private function hasTable(string $table): bool
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table");
        } else {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        }
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            foreach ($statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [] as $row) {
                if (($row['name'] ?? null) === $column) {
                    return true;
                }
            }
            return false;
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }
}
