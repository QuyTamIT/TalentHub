<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use JsonException;
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
        return array_map([$this, 'normalizeActivity'], $this->fetchAll(
            'all', $this->activitySelectSql() . ' ORDER BY activity.startAt, activity.id', $this->visibleStatusParameters()
        ));
    }

    public function findById(string $activityId): ?array
    {
        $activityId = Uuid::normalizeDatabase($activityId, 'activity_id');
        $row = $this->fetchOne('findById', $this->activitySelectSql() . ' AND activity.id = :activity_id LIMIT 1', [
            'activity_id' => $activityId,
        ] + $this->visibleStatusParameters());
        return $row === null ? null : $this->normalizeActivity($row);
    }

    public function registrationsFor(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $registeredAt = $this->hasColumn('activity_registrations', 'registeredAt') ? 'registeredAt' : 'NULL AS registeredAt';
        $updatedAt = $this->hasColumn('activity_registrations', 'updatedAt') ? 'updatedAt' : 'NULL AS updatedAt';
        $cancelledAt = $this->hasColumn('activity_registrations', 'cancelledAt') ? 'cancelledAt' : 'NULL AS cancelledAt';
        $reason = $this->hasColumn('activity_registrations', 'cancellationReason') ? 'cancellationReason' : 'NULL AS cancellationReason';
        $order = $this->hasColumn('activity_registrations', 'registeredAt') ? 'registeredAt, id' : 'id';
        $sql = "SELECT id, activityId, studentId, status, {$registeredAt}, {$updatedAt}, {$cancelledAt}, {$reason}
                FROM activity_registrations WHERE studentId = :student_id ORDER BY {$order}";
        return array_map([$this, 'normalizeRegistration'], $this->fetchAll('registrationsFor', $sql, ['student_id' => $studentId]));
    }

    public function discoverForStudent(string $studentId, DateTimeImmutable $now): array
    {
        if (!$this->supportsStudentScope()) {
            return [];
        }
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $timestamp = $now->format('Y-m-d H:i:s.u');
        $opensFallback = $this->hasColumn('activities', 'createdAt') ? 'activity.createdAt' : 'activity.startAt';
        $policyExists = $this->hasTable('activity_registration_policies');
        $opensAt = $policyExists ? "COALESCE(policy.registrationOpensAt, {$opensFallback})" : $opensFallback;
        $closesAt = $policyExists ? 'COALESCE(policy.registrationClosesAt, activity.startAt)' : 'activity.startAt';
        $sql = $this->scopedActivitySql("student.id = :student_id
            AND activity.status = :status_published
            AND {$this->scopeExpression()} = 'school_only'
            AND {$opensAt} <= :opens_now
            AND :closes_now < {$closesAt}
            AND :starts_now < activity.startAt
            AND :ends_now < COALESCE(activity.endAt, activity.startAt)
            AND {$this->occupiedSql()} < activity.capacity
            AND NOT EXISTS (
                SELECT 1
                FROM activity_registrations own_registration
                WHERE own_registration.activityId = activity.id
                  AND own_registration.studentId = :own_student_id
                  AND own_registration.status IN ('pending', 'approved', 'waitlisted', 'attended')
            )") . ' ORDER BY activity.startAt, activity.id';
        return array_map([$this, 'normalizeActivity'], $this->fetchAll('discoverForStudent', $sql, [
            'student_id' => $studentId,
            'status_published' => ActivityStatus::Published->value,
            // Separate names are required when native PDO prepares disallow parameter reuse.
            'opens_now' => $timestamp,
            'closes_now' => $timestamp,
            'starts_now' => $timestamp,
            'ends_now' => $timestamp,
            'own_student_id' => $studentId,
        ]));
    }

    public function findForStudent(string $studentId, string $activityId): ?array
    {
        if (!$this->supportsStudentScope()) {
            return null;
        }
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        if (!Uuid::isValid($activityId)) {
            return null;
        }
        $activityId = Uuid::normalizeDatabase($activityId, 'activity_id');
        $row = $this->fetchOne('findForStudent', $this->scopedActivitySql(
            'student.id = :student_id AND activity.id = :activity_id AND ' . self::VISIBLE_STATUS_SQL
        ) . ' LIMIT 1', ['student_id' => $studentId, 'activity_id' => $activityId] + $this->visibleStatusParameters());
        return $row === null ? null : $this->normalizeActivity($row);
    }

    public function registrationTimelineFor(string $studentId): array
    {
        if (!$this->supportsStudentScope()) {
            return [];
        }
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $sql = <<<SQL
            SELECT registration.id AS registrationId, registration.activityId AS registrationActivityId,
                   registration.studentId AS registrationStudentId, registration.status AS registrationStatus,
                   {$this->columnOrNull('registration', 'activity_registrations', 'registeredAt')},
                   {$this->columnOrNull('registration', 'activity_registrations', 'updatedAt')},
                   {$this->columnOrNull('registration', 'activity_registrations', 'cancelledAt')},
                   {$this->columnOrNull('registration', 'activity_registrations', 'cancellationReason')},
                   {$this->columnOrNull('registration', 'activity_registrations', 'attendanceResolvedAt')},
                   {$this->columnOrNull('registration', 'activity_registrations', 'attendanceResolutionReason')},
                   confirmed_checkin.checkedInAt AS checkedInAt,
                   confirmed_experience.hours AS experienceHours,
                   {$this->activityProjection('catalogActivityId')}
            FROM activity_registrations registration
            INNER JOIN student_profiles student ON student.id = registration.studentId
            INNER JOIN classes classroom ON classroom.id = student.classId
            INNER JOIN activities activity ON activity.id = registration.activityId AND activity.schoolId = classroom.schoolId
            INNER JOIN schools school ON school.id = activity.schoolId
            {$this->teacherJoin()}
            {$this->detailsJoin()}
            {$this->policyJoin()}
            {$this->experiencePolicyJoin()}
            {$this->confirmedCheckinJoin()}
            {$this->confirmedExperienceJoin()}
            WHERE student.id = :student_id
            ORDER BY registration.registeredAt DESC, registration.id DESC
            SQL;
        return array_map([$this, 'normalizeTimelineRegistration'], $this->fetchAll(
            'registrationTimelineFor', $sql, ['student_id' => $studentId]
        ));
    }

    private function normalizeActivity(array $activity): array
    {
        $activity['id'] = Uuid::normalizeDatabase((string) $activity['id'], 'activities.id');
        $activity['activity_id'] = $activity['id'];
        foreach (['school_id' => 'activities.schoolId', 'created_by_teacher_id' => 'activities.createdByTeacherId'] as $field => $source) {
            if (is_string($activity[$field] ?? null) && $activity[$field] !== '') {
                $activity[$field] = Uuid::normalizeDatabase((string) $activity[$field], $source);
            }
        }
        $activity['status'] = ActivityStatus::normalize($activity['status'] ?? null)->value;
        $activity['participants'] = (int) ($activity['participants'] ?? 0);
        $activity['capacity'] = (int) ($activity['capacity'] ?? 0);
        $activity['remaining'] = max(0, $activity['capacity'] - $activity['participants']);
        $activity['approval_mode'] = (string) ($activity['approval_mode'] ?? 'automatic');
        $activity['audience_scope'] = (string) ($activity['audience_scope'] ?? 'school_only');
        $activity['experience_highlights'] = $this->safeJsonList($activity['experience_highlights'] ?? null);
        $activity['skills'] = $this->safeJsonList($activity['skill_tags'] ?? ($activity['skills'] ?? null));
        $activity['requirements'] = $this->safeJsonList($activity['eligibility_rules'] ?? ($activity['requirements'] ?? null));
        $activity['benefits'] = $this->safeJsonList($activity['benefit_items'] ?? ($activity['benefits'] ?? null));
        $filter = trim((string) ($activity['filter_category'] ?? ''));
        $activity['filter_category'] = $filter === '' ? \learner_activity_category_label((string) ($activity['category'] ?? '')) : $filter;
        $activity['source'] = 'database';
        $activity['source_role'] = 'teacher';
        $activity['id_origin'] = 'database';
        return $activity;
    }

    private function normalizeTimelineRegistration(array $row): array
    {
        $activityRow = $row;
        $activityRow['id'] = $row['catalog_activity_id'] ?? null;
        $activity = $this->normalizeActivity($activityRow);
        $registration = $this->normalizeRegistration([
            'id' => $row['registration_id'] ?? null,
            'activity_id' => $row['registration_activity_id'] ?? null,
            'student_id' => $row['registration_student_id'] ?? null,
            'status' => $row['registration_status'] ?? null,
            'registered_at' => $row['registered_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'cancelled_at' => $row['cancelled_at'] ?? null,
            'cancellation_reason' => $row['cancellation_reason'] ?? null,
        ]);
        return array_merge($activity, $registration, [
            'checked_in_at' => $row['checked_in_at'] ?? null,
            'experience_hours' => isset($row['experience_hours']) ? (float) $row['experience_hours'] : null,
            'attendance_resolved_at' => $row['attendance_resolved_at'] ?? null,
            'attendance_resolution_reason' => $row['attendance_resolution_reason'] ?? null,
        ]);
    }

    /** @return array<string,string> */
    private function visibleStatusParameters(): array
    {
        return ['status_published' => ActivityStatus::Published->value, 'status_ongoing' => ActivityStatus::Ongoing->value, 'status_completed' => ActivityStatus::Completed->value];
    }

    private function normalizeRegistration(array $registration): array
    {
        $registration['id'] = Uuid::normalizeDatabase((string) $registration['id'], 'activity_registrations.id');
        $registration['activity_id'] = Uuid::normalizeDatabase((string) $registration['activity_id'], 'activity_registrations.activityId');
        $registration['student_id'] = Uuid::normalizeDatabase((string) $registration['student_id'], 'activity_registrations.studentId');
        $registration['status'] = ActivityRegistrationStatus::normalize($registration['status'] ?? null)->value;
        $registration['id_origin'] = 'database';
        return $registration;
    }

    private function activitySelectSql(): string
    {
        $occupied = $this->occupiedSql();
        if ($this->hasTable('activity_registration_policies')) {
            return 'SELECT ' . self::COLUMNS . ", {$occupied} AS participants, policy.registrationOpensAt,
                COALESCE(policy.registrationClosesAt, activity.startAt) AS registrationClosesAt,
                COALESCE(policy.cancellationClosesAt, activity.startAt) AS cancellationClosesAt,
                COALESCE(policy.approvalMode, 'automatic') AS approvalMode
                FROM activities activity LEFT JOIN activity_registration_policies policy ON policy.activityId = activity.id
                WHERE " . self::VISIBLE_STATUS_SQL;
        }
        return 'SELECT ' . self::COLUMNS . ", {$occupied} AS participants, NULL AS registrationOpensAt,
            activity.startAt AS registrationClosesAt, activity.startAt AS cancellationClosesAt, 'automatic' AS approvalMode
            FROM activities activity WHERE " . self::VISIBLE_STATUS_SQL;
    }

    private function scopedActivitySql(string $where): string
    {
        return 'SELECT ' . $this->activityProjection('id') . '
            FROM student_profiles student
            INNER JOIN classes classroom ON classroom.id = student.classId
            INNER JOIN activities activity ON activity.schoolId = classroom.schoolId
            INNER JOIN schools school ON school.id = activity.schoolId
            ' . $this->teacherJoin() . '
            ' . $this->detailsJoin() . '
            ' . $this->policyJoin() . '
            ' . $this->experiencePolicyJoin() . '
            WHERE ' . $where;
    }

    private function activityProjection(string $activityIdAlias): string
    {
        $teacherName = $this->hasTable('teacher_profiles') && $this->hasTable('users') ? 'teacherUser.fullName' : 'NULL';
        $opens = $this->hasTable('activity_registration_policies') ? 'policy.registrationOpensAt' : 'NULL';
        $closes = $this->hasTable('activity_registration_policies') ? 'COALESCE(policy.registrationClosesAt, activity.startAt)' : 'activity.startAt';
        $cancellation = $this->hasTable('activity_registration_policies') ? 'COALESCE(policy.cancellationClosesAt, activity.startAt)' : 'activity.startAt';
        $approval = $this->hasTable('activity_registration_policies') ? "COALESCE(policy.approvalMode, 'automatic')" : "'automatic'";
        $hours = $this->hasTable('activity_experience_policies') ? 'experience.confirmedHours' : 'NULL';
        return implode(', ', [
            "activity.id AS {$activityIdAlias}", 'activity.schoolId', 'activity.createdByTeacherId', 'activity.title', 'activity.category', 'activity.startAt', 'activity.endAt', 'activity.capacity', 'activity.status',
            'school.name AS schoolName', "{$teacherName} AS responsibleTeacherName",
            $this->detailColumn('responsibleTeacherId', 'activity.createdByTeacherId'), $this->detailColumn('audienceScope', "'school_only'"),
            $this->detailColumn('displayCategory', 'NULL'), $this->detailColumn('filterCategory', 'NULL'), $this->detailColumn('summary', 'NULL'), $this->detailColumn('description', 'NULL'),
            $this->detailColumn('experienceHighlights', 'NULL'), $this->detailColumn('skillTags', 'NULL'), $this->detailColumn('eligibilityRules', 'NULL'), $this->detailColumn('benefitItems', 'NULL'),
            $this->detailColumn('locationName', 'NULL'), $this->detailColumn('locationAddress', 'NULL'), $this->detailColumn('deliveryMode', 'NULL'), $this->detailColumn('onlineMeetingUrl', 'NULL'),
            $this->detailColumn('organizerName', 'NULL'), $this->detailColumn('organizerContact', 'NULL'), $this->detailColumn('organizerEmail', 'NULL'), $this->detailColumn('organizerPhone', 'NULL'),
            $this->detailColumn('coverImageUrl', 'NULL'), $this->detailColumn('coverImageAlt', 'NULL'), $this->detailColumn('feeAmount', 'NULL'), $this->detailColumn('currency', 'NULL'),
            $this->detailColumn('targetAudience', 'NULL'), $this->detailColumn('certificateLabel', 'NULL'),
            "{$opens} AS registrationOpensAt", "{$closes} AS registrationClosesAt", "{$cancellation} AS cancellationClosesAt", "{$approval} AS approvalMode", "{$hours} AS confirmedHours", $this->occupiedSql() . ' AS participants',
        ]);
    }

    private function detailColumn(string $column, string $default): string
    {
        $expression = $this->hasTable('activity_details') && $this->hasColumn('activity_details', $column) ? "details.{$column}" : $default;
        if ($column === 'responsibleTeacherId' && $expression !== $default) $expression = "COALESCE({$expression}, activity.createdByTeacherId)";
        if ($column === 'audienceScope' && $expression !== $default) $expression = "COALESCE({$expression}, 'school_only')";
        return "{$expression} AS {$column}";
    }

    private function scopeExpression(): string
    {
        return $this->hasTable('activity_details') && $this->hasColumn('activity_details', 'audienceScope') ? "COALESCE(details.audienceScope, 'school_only')" : "'school_only'";
    }

    private function detailsJoin(): string { return $this->hasTable('activity_details') ? 'LEFT JOIN activity_details details ON details.activityId = activity.id' : ''; }
    private function policyJoin(): string { return $this->hasTable('activity_registration_policies') ? 'LEFT JOIN activity_registration_policies policy ON policy.activityId = activity.id' : ''; }
    private function experiencePolicyJoin(): string { return $this->hasTable('activity_experience_policies') ? 'LEFT JOIN activity_experience_policies experience ON experience.activityId = activity.id' : ''; }
    private function teacherJoin(): string
    {
        return $this->hasTable('teacher_profiles') && $this->hasTable('users')
            ? 'LEFT JOIN teacher_profiles teacher ON teacher.id = activity.createdByTeacherId LEFT JOIN users teacherUser ON teacherUser.id = teacher.userId'
            : '';
    }

    private function confirmedCheckinJoin(): string
    {
        if (!$this->hasTable('checkins') || !$this->hasColumn('checkins', 'confirmedAt') || !$this->hasColumn('checkins', 'checkedInAt')) return 'LEFT JOIN (SELECT NULL AS registrationId, NULL AS checkedInAt) confirmed_checkin ON 1=0';
        return "LEFT JOIN (SELECT registrationId, MAX(id) AS checkinId, MAX(checkedInAt) AS checkedInAt FROM checkins WHERE status='confirmed' AND confirmedAt IS NOT NULL AND checkedInAt IS NOT NULL GROUP BY registrationId) confirmed_checkin ON confirmed_checkin.registrationId = registration.id";
    }

    private function confirmedExperienceJoin(): string
    {
        if (!$this->hasTable('experience_logs') || !$this->hasColumn('experience_logs', 'confirmedAt')) return 'LEFT JOIN (SELECT NULL AS checkinId, NULL AS hours) confirmed_experience ON 1=0';
        return "LEFT JOIN (SELECT checkinId, MAX(hours) AS hours FROM experience_logs WHERE status='confirmed' AND confirmedAt IS NOT NULL GROUP BY checkinId) confirmed_experience ON confirmed_experience.checkinId = confirmed_checkin.checkinId";
    }

    private function columnOrNull(string $alias, string $table, string $column): string { return $this->hasColumn($table, $column) ? "{$alias}.{$column} AS {$column}" : "NULL AS {$column}"; }
    private function occupiedSql(): string { return "(SELECT COUNT(*) FROM activity_registrations occupied WHERE occupied.activityId = activity.id AND occupied.status IN ('approved','attended'))"; }

    private function safeJsonList(mixed $value): array
    {
        if (is_array($value)) $decoded = $value;
        elseif ($value === null || $value === '') return [];
        else {
            try { $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR); } catch (JsonException) { return []; }
        }
        if (!is_array($decoded) || !array_is_list($decoded)) return [];
        return array_values(array_filter(array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $decoded), static fn (string $item): bool => $item !== ''));
    }

    private function supportsStudentScope(): bool
    {
        return $this->hasTable('schools') && $this->hasTable('classes') && $this->hasTable('student_profiles')
            && $this->hasColumn('student_profiles', 'classId') && $this->hasColumn('classes', 'schoolId') && $this->hasColumn('activities', 'schoolId');
    }

    private function hasTable(string $table): bool
    {
        $statement = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table")
            : $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            foreach ($statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [] as $row) if (($row['name'] ?? null) === $column) return true;
            return false;
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }
}
