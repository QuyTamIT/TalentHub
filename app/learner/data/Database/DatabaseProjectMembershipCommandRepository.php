<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

require_once dirname(__DIR__, 2) . '/ai/Queue/TransactionalAiOutboxPublisher.php';

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;
use TalentHub\Learner\Data\Contracts\ProjectMembershipCommandRepository;
use TalentHub\Learner\Data\Support\Uuid;
use TalentHub\Support\Uuid as UuidGenerator;
use Throwable;

final class DatabaseProjectMembershipCommandRepository implements ProjectMembershipCommandRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function registerActiveMember(string $studentId, string $projectId, DateTimeImmutable $now): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $projectId = Uuid::normalizeDatabase($projectId, 'project_id');
        $timestamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

        $this->pdo->beginTransaction();
        try {
            $studentSchoolId = $this->activeStudentSchoolId($studentId);
            if ($studentSchoolId === null) {
                throw $this->unavailable();
            }

            if (!$this->projectIsJoinable($projectId, $studentSchoolId)) {
                throw $this->unavailable();
            }

            $existing = $this->findMembership($projectId, $studentId);
            if ($existing !== null && ($existing['status'] ?? '') === 'active') {
                $this->pdo->commit();
                return $existing + ['created' => false];
            }

            if ($existing !== null) {
                $update = $this->pdo->prepare(<<<'SQL'
                    UPDATE project_members
                    SET status = 'active', role = 'member', joinedAt = :joinedAt,
                        leftAt = NULL, updatedAt = :updatedAt
                    WHERE id = :id AND projectId = :projectId AND studentId = :studentId
                SQL
                );
                $update->execute([
                    'joinedAt' => $timestamp,
                    'updatedAt' => $timestamp,
                    'id' => (string) $existing['id'],
                    'projectId' => $projectId,
                    'studentId' => $studentId,
                ]);
                $membership = $this->findMembership($projectId, $studentId)
                    ?? throw new ApiException(500, 'MEMBERSHIP_FAILED', 'Không thể cập nhật thành viên dự án vừa đăng ký.');
                $this->publishMembershipMutation($membership, $projectId, $studentId);
                $this->pdo->commit();
                return $membership + ['created' => false];
            }

            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO project_members (
                    id, projectId, studentId, role, status, joinedAt, leftAt, createdAt, updatedAt
                ) VALUES (
                    :id, :projectId, :studentId, 'member', 'active', :joinedAt, NULL, :createdAt, :updatedAt
                )
            SQL
            );
            $insert->execute([
                'id' => UuidGenerator::v4(),
                'projectId' => $projectId,
                'studentId' => $studentId,
                'joinedAt' => $timestamp,
                'createdAt' => $timestamp,
                'updatedAt' => $timestamp,
            ]);
            $membership = $this->findMembership($projectId, $studentId)
                ?? throw new ApiException(500, 'MEMBERSHIP_FAILED', 'Không thể đọc thành viên dự án vừa tạo.');
            $this->publishMembershipMutation($membership, $projectId, $studentId);
            $this->pdo->commit();
            return $membership + ['created' => true];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && $this->isDuplicate($exception)) {
                $existing = $this->findMembership($projectId, $studentId);
                if ($existing !== null && ($existing['status'] ?? '') === 'active') {
                    return $existing + ['created' => false];
                }
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function findMembership(string $projectId, string $studentId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT id, projectId, studentId, role, status, joinedAt, leftAt, createdAt, updatedAt
            FROM project_members
            WHERE projectId = :projectId AND studentId = :studentId
            LIMIT 1
        SQL . $this->lockSuffix());
        $statement->execute(['projectId' => $projectId, 'studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'projectId' => (string) $row['projectId'],
            'studentId' => (string) $row['studentId'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'joinedAt' => isset($row['joinedAt']) ? (string) $row['joinedAt'] : null,
            'leftAt' => isset($row['leftAt']) && $row['leftAt'] !== null ? (string) $row['leftAt'] : null,
            'createdAt' => isset($row['createdAt']) ? (string) $row['createdAt'] : null,
            'updatedAt' => isset($row['updatedAt']) ? (string) $row['updatedAt'] : null,
        ];
    }

    private function activeStudentSchoolId(string $studentId): ?string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT classes.schoolId
            FROM student_profiles student
            INNER JOIN classes ON classes.id = student.classId
            WHERE student.id = :studentId AND student.studyStatus = 'active'
            LIMIT 1
        SQL . $this->lockSuffix());
        $statement->execute(['studentId' => $studentId]);
        $schoolId = $statement->fetchColumn();
        return is_string($schoolId) && $schoolId !== '' ? $schoolId : null;
    }

    private function projectIsJoinable(string $projectId, string $schoolId): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT p.id
            FROM projects p
            INNER JOIN schools s ON s.id = p.schoolId AND s.status = 'active'
            WHERE p.id = :projectId AND p.schoolId = :schoolId AND p.status = 'in_progress'
            LIMIT 1
        SQL . $this->lockSuffix());
        $statement->execute(['projectId' => $projectId, 'schoolId' => $schoolId]);
        return $statement->fetchColumn() !== false;
    }

    private function unavailable(): ApiException
    {
        return new ApiException(404, 'PROJECT_NOT_AVAILABLE', 'Dự án không khả dụng để đăng ký.');
    }

    /** @param array<string,mixed> $membership */
    private function publishMembershipMutation(array $membership, string $projectId, string $studentId): void
    {
        TransactionalAiOutboxPublisher::publish(
            $this->pdo,
            'project_membership',
            (string) $membership['id'],
            TransactionalAiOutboxPublisher::version(),
            [$studentId],
            'project.membership_updated',
            ['project_id' => $projectId, 'status' => 'active'],
        );
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function isDuplicate(PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            || str_contains(strtolower($exception->getMessage()), 'unique constraint failed');
    }
}
