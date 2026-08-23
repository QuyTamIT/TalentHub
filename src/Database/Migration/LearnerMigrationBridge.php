<?php

declare(strict_types=1);

namespace TalentHub\Database\Migration;

use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;

final class LearnerMigrationBridge
{
    public static function migrate(PDO $pdo, string $version): void
    {
        self::loadLearnerMigrationRuntime();
        $database = $pdo->query('SELECT DATABASE()')?->fetchColumn();
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('Learner migration bridge requires a selected database.');
        }
        $root = dirname(__DIR__, 3);
        $runner = new LearnerForwardMigrationRunner(
            $pdo,
            $root . '/Database/migrations/learner',
            new SchemaInspector($pdo, $database),
        );
        $runner->migrateApproved([$version]);
    }

    private static function loadLearnerMigrationRuntime(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            '/app/learner/data/Database/SchemaInspector.php',
            '/app/learner/data/Readiness/AiScopePolicy.php',
            '/app/learner/data/Migrations/LearnerForwardMigration.php',
            '/app/learner/data/Migrations/LearnerMigrationPreflight.php',
            '/app/learner/data/Migrations/ForwardMigrationDefinition.php',
            '/app/learner/data/Migrations/LearnerMigrationChecksum.php',
            '/app/learner/data/Migrations/LearnerForwardMigrationRunner.php',
        ] as $path) {
            require_once $root . $path;
        }
    }
}
