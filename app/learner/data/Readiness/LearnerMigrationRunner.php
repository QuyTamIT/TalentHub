<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use InvalidArgumentException;
use PDO;

final class LearnerMigrationRunner
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureRegistry(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS learner_schema_migrations (version VARCHAR(191) PRIMARY KEY, appliedAt VARCHAR(40) NOT NULL)',
        );
    }

    public function apply(string $version): bool
    {
        if (preg_match('/^\d{3}_[a-z][a-z0-9_]*$/', $version) !== 1) {
            throw new InvalidArgumentException('Learner migration version is invalid.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO learner_schema_migrations (version, appliedAt) '
            . 'SELECT :version, :appliedAt '
            . 'WHERE NOT EXISTS (SELECT 1 FROM learner_schema_migrations WHERE version = :existingVersion)',
        );
        $statement->execute([
            'version' => $version,
            'appliedAt' => gmdate('c'),
            'existingVersion' => $version,
        ]);

        return $statement->rowCount() === 1;
    }
}
