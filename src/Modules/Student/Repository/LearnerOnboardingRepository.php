<?php

declare(strict_types=1);

namespace TalentHub\Modules\Student\Repository;

use PDO;
use TalentHub\Support\Uuid;

final class LearnerOnboardingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{studentId:string,status:string,acceptedAt:?string,completedAt:?string}|null */
    public function find(string $studentId): ?array
    {
        // Local SQLite fixtures created before this feature represent legacy accounts.
        // Production MySQL deliberately fails fast if migration-before-code was violated.
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
            'status' => (string) $row['status'],
            'acceptedAt' => isset($row['acceptedAt']) ? (string) $row['acceptedAt'] : null,
            'completedAt' => isset($row['completedAt']) ? (string) $row['completedAt'] : null,
        ];
    }

    /** @return list<string> */
    public function submittedCodes(string $studentId): array
    {
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
    }

    public function accept(string $studentId): bool
    {
        $timestamp = $this->timestampExpression();
        $statement = $this->pdo->prepare(
            "UPDATE learner_onboarding_states "
            . "SET status = 'accepted', acceptedAt = COALESCE(acceptedAt, {$timestamp}) "
            . "WHERE studentId = :studentId AND status = 'pending'",
        );
        $statement->execute(['studentId' => $studentId]);

        return $statement->rowCount() === 1;
    }

    public function complete(string $studentId): bool
    {
        $timestamp = $this->timestampExpression();
        $statement = $this->pdo->prepare(
            "UPDATE learner_onboarding_states "
            . "SET status = 'completed', completedAt = COALESCE(completedAt, {$timestamp}) "
            . "WHERE studentId = :studentId AND status = 'accepted'",
        );
        $statement->execute(['studentId' => $studentId]);

        return $statement->rowCount() === 1;
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
    }

    private function timestampExpression(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'CURRENT_TIMESTAMP'
            : 'UTC_TIMESTAMP(6)';
    }

    private function sqliteTableExists(): bool
    {
        $statement = $this->pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'learner_onboarding_states'",
        );
        return $statement !== false && $statement->fetchColumn() !== false;
    }
}
