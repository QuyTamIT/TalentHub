<?php
declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;

final class DatabaseSchoolAiRefreshJobRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enqueue(string $schoolId, string $aggregateHash): ?int
    {
        // First check if an existing job with this exact hash is already pending or processing
        $stmt = $this->pdo->prepare(
            "SELECT id FROM school_ai_refresh_jobs WHERE school_id = ? AND aggregate_hash = ? AND status IN ('pending', 'processing') LIMIT 1"
        );
        $stmt->execute([$schoolId, $aggregateHash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        // Cancel older pending jobs for this school with different hash
        $this->cancelSuperseded($schoolId, $aggregateHash);

        $now = gmdate('Y-m-d H:i:s');
        $sql = "INSERT INTO school_ai_refresh_jobs (school_id, aggregate_hash, status, attempts, created_at, updated_at) VALUES (?, ?, 'pending', 0, ?, ?)";
        $this->pdo->prepare($sql)->execute([$schoolId, $aggregateHash, $now, $now]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function claim(): ?array
    {
        $now = gmdate('Y-m-d H:i:s');
        $s = $this->pdo->prepare(
            "SELECT id, school_id, aggregate_hash FROM school_ai_refresh_jobs WHERE status = 'pending' AND (next_retry_at IS NULL OR next_retry_at <= ?) ORDER BY created_at, id LIMIT 1"
        );
        $s->execute([$now]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $u = $this->pdo->prepare(
            "UPDATE school_ai_refresh_jobs SET status = 'processing', attempts = attempts + 1, updated_at = ? WHERE id = ? AND status = 'pending'"
        );
        $u->execute([$now, $row['id']]);
        return $u->rowCount() === 1 ? $row : null;
    }

    public function cancelSuperseded(string $schoolId, string $currentAggregateHash): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "UPDATE school_ai_refresh_jobs SET status = 'cancelled', updated_at = ? WHERE school_id = ? AND aggregate_hash != ? AND status = 'pending'"
        );
        $stmt->execute([$now, $schoolId, $currentAggregateHash]);
    }

    public function complete(int $id): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "UPDATE school_ai_refresh_jobs SET status = 'completed', next_retry_at = NULL, updated_at = ? WHERE id = ?"
        );
        $stmt->execute([$now, $id]);
        return $stmt->rowCount() > 0;
    }

    public function cancel(int $id): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "UPDATE school_ai_refresh_jobs SET status = 'cancelled', updated_at = ? WHERE id = ? AND status IN ('pending', 'processing')"
        );
        $stmt->execute([$now, $id]);
        return $stmt->rowCount() > 0;
    }

    public function fail(int $id): bool
    {
        $s = $this->pdo->prepare('SELECT attempts FROM school_ai_refresh_jobs WHERE id = ?');
        $s->execute([$id]);
        $attempts = (int) $s->fetchColumn();
        $dead = $attempts >= 3;
        $now = gmdate('Y-m-d H:i:s');
        $nextRetry = $dead ? null : gmdate('Y-m-d H:i:s', time() + min(3600, 2 ** max(1, $attempts)));
        $stmt = $this->pdo->prepare(
            "UPDATE school_ai_refresh_jobs SET status = ?, next_retry_at = ?, updated_at = ? WHERE id = ?"
        );
        $stmt->execute([$dead ? 'dead_letter' : 'pending', $nextRetry, $now, $id]);
        return $stmt->rowCount() > 0;
    }
}
