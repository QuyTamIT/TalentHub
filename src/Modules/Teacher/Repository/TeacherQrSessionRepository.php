<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;

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
        $statement = $this->pdo->prepare(
            'SELECT id, title, category, startAt, endAt
             FROM activities
             WHERE createdByTeacherId = :teacherId
               AND status = \'ongoing\'
             ORDER BY startAt ASC, title ASC'
        );
        $statement->execute(['teacherId' => $teacherId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function listSessions(string $teacherId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                s.id,
                s.activityId,
                s.status,
                s.expiresAt,
                s.maxScans,
                s.usedScans,
                s.createdAt,
                a.title AS activityTitle,
                a.category AS activityCategory
             FROM activity_qr_sessions s
             INNER JOIN activities a ON a.id = s.activityId
             WHERE s.createdByTeacherId = :teacherId
               AND a.createdByTeacherId = :activityTeacherId
             ORDER BY s.createdAt DESC'
        );
        $statement->execute([
            'teacherId' => $teacherId,
            'activityTeacherId' => $teacherId,
        ]);

        return $statement->fetchAll();
    }

    public function createSession(
        string $teacherId,
        string $activityId,
        string $sessionId,
        string $tokenHash,
        string $expiresAt,
        int $maxScans,
    ): bool {
        $statement = $this->pdo->prepare(
            'INSERT INTO activity_qr_sessions
                (id, activityId, createdByTeacherId, tokenHash, status, expiresAt, maxScans, usedScans)
             SELECT
                :sessionId,
                a.id,
                :teacherId,
                :tokenHash,
                \'active\',
                :expiresAt,
                :maxScans,
                0
             FROM activities a
             WHERE a.id = :activityId
               AND a.createdByTeacherId = :activityTeacherId
               AND a.status = \'ongoing\''
        );
        $statement->execute([
            'sessionId' => $sessionId,
            'teacherId' => $teacherId,
            'tokenHash' => $tokenHash,
            'expiresAt' => $expiresAt,
            'maxScans' => $maxScans,
            'activityId' => $activityId,
            'activityTeacherId' => $teacherId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revokeSession(string $teacherId, string $sessionId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE activity_qr_sessions
             SET status = \'revoked\',
                 revokedAt = UTC_TIMESTAMP(6)
             WHERE id = :sessionId
               AND createdByTeacherId = :teacherId
               AND status = \'active\'
               AND expiresAt > UTC_TIMESTAMP(6)'
        );
        $statement->execute([
            'sessionId' => $sessionId,
            'teacherId' => $teacherId,
        ]);

        return $statement->rowCount() === 1;
    }
}
