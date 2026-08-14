<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\Enums\ActivityRegistrationStatus;
use TalentHub\Learner\Data\Enums\ActivityStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseActivityRepository extends AbstractDatabaseRepository implements ActivityRepository
{
    private const COLUMNS = 'id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status';
    private const VISIBLE_STATUS_SQL = 'status IN (:status_published, :status_active, :status_closed, :status_completed)';
    private const ALL_SQL = 'SELECT ' . self::COLUMNS . ' FROM activities WHERE ' . self::VISIBLE_STATUS_SQL . ' ORDER BY startAt, id';
    private const FIND_SQL = 'SELECT ' . self::COLUMNS . ' FROM activities WHERE id = :activity_id AND ' . self::VISIBLE_STATUS_SQL . ' LIMIT 1';
    private const REGISTRATIONS_SQL = <<<'SQL'
        SELECT id, activityId, studentId, status
        FROM activity_registrations
        WHERE studentId = :student_id
        ORDER BY id
        SQL;

    public function all(): array
    {
        return array_map(
            [$this, 'normalizeActivity'],
            $this->fetchAll('all', self::ALL_SQL, $this->visibleStatusParameters())
        );
    }

    public function findById(string $activityId): ?array
    {
        $activityId = Uuid::normalizeDatabase($activityId, 'activity_id');
        $activity = $this->fetchOne(
            'findById',
            self::FIND_SQL,
            ['activity_id' => $activityId] + $this->visibleStatusParameters()
        );
        return $activity === null ? null : $this->normalizeActivity($activity);
    }

    public function registrationsFor(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        return array_map(
            [$this, 'normalizeRegistration'],
            $this->fetchAll('registrationsFor', self::REGISTRATIONS_SQL, ['student_id' => $studentId])
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
        $activity['id_origin'] = 'database';

        return $activity;
    }

    /** @return array<string, string> */
    private function visibleStatusParameters(): array
    {
        return [
            'status_published' => ActivityStatus::Published->value,
            'status_active' => ActivityStatus::Active->value,
            'status_closed' => ActivityStatus::Closed->value,
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
}
