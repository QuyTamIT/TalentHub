<?php

declare(strict_types=1);

namespace TalentHub\Modules\Student\Repository;

use PDO;
use TalentHub\Support\Uuid;
use Throwable;

final class LearnerOnboardingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS learner_onboarding_states (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'completed',
    step TEXT NOT NULL DEFAULT 'welcome',
    isCompleted INTEGER NOT NULL DEFAULT 1,
    acceptedAt TEXT NULL,
    completedAt TEXT NULL,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
            } else {
                $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `learner_onboarding_states` (
    `id` CHAR(36) NOT NULL,
    `studentId` CHAR(36) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'completed',
    `step` VARCHAR(50) NOT NULL DEFAULT 'welcome',
    `isCompleted` TINYINT(1) NOT NULL DEFAULT 1,
    `acceptedAt` DATETIME(6) NULL,
    `completedAt` DATETIME(6) NULL,
    `createdAt` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updatedAt` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_learner_onboarding_student` (`studentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            }
        } catch (Throwable $e) {
            error_log('LearnerOnboardingRepository::ensureTableExists failed: ' . $e->getMessage());
        }
    }

    /** @return array{studentId:string,status:string,acceptedAt:?string,completedAt:?string}|null */
    public function find(string $studentId): ?array
    {
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && !$this->sqliteTableExists()) {
                return null;
            }
            $statement = $this->pdo->prepare(
                'SELECT studentId, status, acceptedAt, completedAt '
                . 'FROM learner_onboarding_states WHERE studentId = :studentId LIMIT 1',
            );
            $statement->execute(['studentId' => $studentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return null;
            }

            return [
                'studentId' => (string) $row['studentId'],
                'status' => (string) ($row['status'] ?? 'completed'),
                'acceptedAt' => isset($row['acceptedAt']) ? (string) $row['acceptedAt'] : null,
                'completedAt' => isset($row['completedAt']) ? (string) $row['completedAt'] : null,
            ];
        } catch (Throwable $e) {
            error_log('LearnerOnboardingRepository::find error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return list<string> */
    public function submittedCodes(string $studentId): array
    {
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
SELECT DISTINCT tt.code
FROM test_attempts AS ta
INNER JOIN talent_tests AS tt ON tt.id = ta.testId
INNER JOIN test_results AS tr ON tr.attemptId = ta.id
WHERE ta.studentId = :studentId
  AND ta.status = 'submitted'
SQL);
            $statement->execute(['studentId' => $studentId]);

            return array_values(array_map(
                static fn (mixed $code): string => (string) $code,
                $statement->fetchAll(PDO::FETCH_COLUMN),
            ));
        } catch (Throwable $e) {
            error_log('LearnerOnboardingRepository::submittedCodes error: ' . $e->getMessage());
            return [];
        }
    }

    public function accept(string $studentId): bool
    {
        try {
            $timestamp = $this->timestampExpression();
            $statement = $this->pdo->prepare(
                "UPDATE learner_onboarding_states "
                . "SET status = 'accepted', acceptedAt = COALESCE(acceptedAt, {$timestamp}) "
                . "WHERE studentId = :studentId AND status = 'pending'",
            );
            $statement->execute(['studentId' => $studentId]);

            return $statement->rowCount() === 1;
        } catch (Throwable $e) {
            error_log('LearnerOnboardingRepository::accept error: ' . $e->getMessage());
            return false;
        }
    }

    public function complete(string $studentId): bool
    {
        try {
            $timestamp = $this->timestampExpression();
            $statement = $this->pdo->prepare(
                "UPDATE learner_onboarding_states "
                . "SET status = 'completed', completedAt = COALESCE(completedAt, {$timestamp}) "
                . "WHERE studentId = :studentId AND status = 'accepted'",
            );
            $statement->execute(['studentId' => $studentId]);

            return $statement->rowCount() === 1;
        } catch (Throwable $e) {
            error_log('LearnerOnboardingRepository::complete error: ' . $e->getMessage());
            return false;
        }
    }

    /** @param array{from:string,to:string,completedCodes:list<string>} $metadata */
    public function audit(
        string $studentId,
        string $userId,
        string $action,
        string $requestId,
        ?string $ip,
        array $metadata,
    ): void {
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO audit_logs(id, userId, action, entityType, entityId, requestId, ipAddress, metadata)
VALUES(:id, :userId, :action, 'learner_onboarding', :studentId, :requestId, :ipAddress, :metadata)
SQL);
            $statement->execute([
                'id' => Uuid::v4(),
                'userId' => $userId,
                'action' => $action,
                'studentId' => $studentId,
                'requestId' => $requestId,
                'ipAddress' => $ip,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $e) {
            error_log('LearnerOnboardingRepository::audit error: ' . $e->getMessage());
        }
    }

    private function timestampExpression(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'CURRENT_TIMESTAMP'
            : 'UTC_TIMESTAMP(6)';
    }

    private function sqliteTableExists(): bool
    {
        try {
            $statement = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'learner_onboarding_states'",
            );
            return $statement !== false && $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
