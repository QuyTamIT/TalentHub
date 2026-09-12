<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;
use Throwable;

final class TeacherQrSessionRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findTeacherIdByUserId(string $userId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM teacher_profiles
             WHERE userId = :userId
             LIMIT 1'
        );
        $statement->execute(['userId' => $userId]);
        $teacherId = $statement->fetchColumn();

        return $teacherId === false ? null : (string) $teacherId;
    }

    /** @return list<array<string,mixed>> */
    public function listOngoingActivities(string $teacherId): array
    {
        $params = [];
        $whereScope = $this->activityScopeWhere($teacherId, $params, 'loa_');

        $statement = $this->pdo->prepare(
            "SELECT DISTINCT a.id, a.title, a.category, a.startAt, a.endAt
             FROM activities a
             LEFT JOIN activity_details d ON d.activityId = a.id
             WHERE a.status = 'ongoing'
               AND {$whereScope}
             ORDER BY a.startAt ASC, a.title ASC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function activityScopeWhere(string $teacherId, array &$params, string $prefix = 't_'): string
    {
        $params[$prefix . 'created'] = $teacherId;
        $params[$prefix . 'resp'] = $teacherId;

        $clauses = [
            "a.createdByTeacherId = :{$prefix}created",
            "d.responsibleTeacherId = :{$prefix}resp",
        ];

        $scope = $this->teacherScopeParams($teacherId);
        $schoolId = $scope['schoolId'];
        $userId = $scope['userId'];

        if ($userId !== null && $userId !== '') {
            $params[$prefix . 'ucreated'] = $userId;
            $params[$prefix . 'uresp'] = $userId;
            $clauses[] = "a.createdByTeacherId = :{$prefix}ucreated";
            $clauses[] = "d.responsibleTeacherId = :{$prefix}uresp";
        }

        if ($schoolId !== null && $schoolId !== '') {
            $params[$prefix . 'school'] = $schoolId;
            $clauses[] = "(a.schoolId IS NOT NULL AND a.schoolId = :{$prefix}school)";
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    /** @return array{schoolId: ?string, userId: ?string} */
    private function teacherScopeParams(string $teacherId): array
    {
        $schoolId = null;
        $userId = null;
        try {
            $statement = $this->pdo->prepare('SELECT schoolId, userId FROM teacher_profiles WHERE id = :teacherId OR userId = :teacherId2 LIMIT 1');
            $statement->execute(['teacherId' => $teacherId, 'teacherId2' => $teacherId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $schoolId = !empty($row['schoolId']) ? (string) $row['schoolId'] : null;
                $userId = !empty($row['userId']) ? (string) $row['userId'] : null;
            }
        } catch (Throwable) {}

        if ($schoolId === null) {
            try {
                $statement = $this->pdo->prepare('SELECT schoolId FROM school_members WHERE userId = :userId LIMIT 1');
                $statement->execute(['userId' => $teacherId]);
                $smSchoolId = $statement->fetchColumn();
                if ($smSchoolId !== false && $smSchoolId !== null && (string) $smSchoolId !== '') {
                    $schoolId = (string) $smSchoolId;
                }
            } catch (Throwable) {}
        }

        return ['schoolId' => $schoolId, 'userId' => $userId];
    }

    /** @return list<array<string,mixed>> */
    public function listSessions(string $teacherId): array
    {
        $params = ['teacherId' => $teacherId];
        $scopeWhere = $this->activityScopeWhere($teacherId, $params, 'ls_');
        $statement = $this->pdo->prepare(
            "SELECT
                s.id,
                s.activityId,
                s.status,
                s.expiresAt,
                s.maxScans,
                s.usedScans,
                s.createdAt,
                a.title AS activityTitle,
                a.category AS activityCategory,
                policy.confirmedHours
             FROM activity_qr_sessions s
             INNER JOIN activities a ON a.id = s.activityId
             LEFT JOIN activity_details d ON d.activityId = a.id
             LEFT JOIN activity_experience_policies policy ON policy.activityId = a.id
             WHERE (s.createdByTeacherId = :teacherId OR {$scopeWhere})
             ORDER BY s.createdAt DESC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function createSession(
        string $teacherId,
        string $activityId,
        string $sessionId,
        string $tokenHash,
        string $expiresAt,
        int $maxScans,
        string $confirmedHours,
    ): bool {
        $this->pdo->beginTransaction();
        try {
            $policyParams = [
                'confirmedHours' => $confirmedHours,
                'activityId' => $activityId,
            ];
            $policyScope = $this->activityScopeWhere($teacherId, $policyParams, 'cs_p_');
            $policy = $this->pdo->prepare(
                "INSERT INTO activity_experience_policies (activityId, confirmedHours, createdAt, updatedAt)
                 SELECT a.id, :confirmedHours, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
                 FROM activities a
                 LEFT JOIN activity_details d ON d.activityId = a.id
                 WHERE a.id = :activityId AND a.status = 'ongoing' AND {$policyScope}
                 ON DUPLICATE KEY UPDATE confirmedHours = VALUES(confirmedHours), updatedAt = UTC_TIMESTAMP(6)"
            );
            $policy->execute($policyParams);

            $sessionParams = [
                'sessionId' => $sessionId,
                'teacherId' => $teacherId,
                'tokenHash' => $tokenHash,
                'expiresAt' => $expiresAt,
                'maxScans' => $maxScans,
                'activityId' => $activityId,
            ];
            $sessionScope = $this->activityScopeWhere($teacherId, $sessionParams, 'cs_s_');
            $statement = $this->pdo->prepare(
                "INSERT INTO activity_qr_sessions
                    (id, activityId, createdByTeacherId, tokenHash, status, expiresAt, maxScans, usedScans)
                 SELECT
                    :sessionId,
                    a.id,
                    :teacherId,
                    :tokenHash,
                    'active',
                    :expiresAt,
                    :maxScans,
                    0
                 FROM activities a
                 LEFT JOIN activity_details d ON d.activityId = a.id
                 WHERE a.id = :activityId
                   AND a.status = 'ongoing'
                   AND {$sessionScope}"
            );
            $statement->execute($sessionParams);

            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function revokeSession(string $teacherId, string $sessionId): bool
    {
        $params = [
            'sessionId' => $sessionId,
            'teacherId' => $teacherId,
        ];
        $scopeWhere = $this->activityScopeWhere($teacherId, $params, 'rs_');
        $statement = $this->pdo->prepare(
            "UPDATE activity_qr_sessions s
             INNER JOIN activities a ON a.id = s.activityId
             LEFT JOIN activity_details d ON d.activityId = a.id
             SET s.status = 'revoked',
                 s.revokedAt = UTC_TIMESTAMP(6)
             WHERE s.id = :sessionId
               AND (s.createdByTeacherId = :teacherId OR {$scopeWhere})
               AND s.status = 'active'
               AND s.expiresAt > UTC_TIMESTAMP(6)"
        );
        $statement->execute($params);

        return $statement->rowCount() === 1;
    }

    /** @return list<array<string,mixed>> */
    public function listManagedCheckins(string $teacherId, int $limit = 50): array
    {
        $params = [];
        $scopeWhere = $this->activityScopeWhere($teacherId, $params, 'lmc_');
        $statement = $this->pdo->prepare(
            "SELECT c.id checkinId, c.checkedInAt, c.confirmedAt, c.status checkinStatus,
                    a.id activityId, a.title activityTitle,
                    el.hours confirmedHours, el.status experienceStatus
             FROM checkins c
             INNER JOIN activity_registrations ar ON ar.id = c.registrationId
             INNER JOIN activities a ON a.id = ar.activityId
             LEFT JOIN activity_details d ON d.activityId = a.id
             INNER JOIN experience_logs el ON el.checkinId = c.id AND el.status = 'confirmed'
             WHERE {$scopeWhere}
               AND c.status = 'confirmed'
             ORDER BY c.checkedInAt DESC
             LIMIT " . max(1, min(100, $limit))
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
