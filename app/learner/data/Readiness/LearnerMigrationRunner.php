<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class LearnerMigrationRunner
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureRegistry(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS learner_schema_migrations (version TEXT NOT NULL PRIMARY KEY, appliedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)'
            : 'CREATE TABLE IF NOT EXISTS learner_schema_migrations (version VARCHAR(100) NOT NULL, appliedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->pdo->exec($sql);
    }

    public function apply(string $version): bool
    {
        if (preg_match('/\A[0-9]{3}_[A-Za-z0-9_]+\z/', $version) !== 1) {
            throw new InvalidArgumentException('Migration version must be an internal validated value.');
        }

        $this->ensureRegistry();
        $exists = $this->pdo->prepare('SELECT 1 FROM learner_schema_migrations WHERE version = :version LIMIT 1');
        if ($exists === false || !$exists->execute(['version' => $version])) {
            throw new RuntimeException('Unable to check learner migration registry.');
        }
        if ($exists->fetchColumn() !== false) {
            return false;
        }

        $insert = $this->pdo->prepare('INSERT INTO learner_schema_migrations (version) VALUES (:version)');
        if ($insert === false || !$insert->execute(['version' => $version])) {
            throw new RuntimeException('Unable to record learner migration registry version.');
        }

        return true;
    }
}
