<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Enterprise and Opportunity demo dataset seeder.
 *
 * Populates 6 diverse enterprises, 6 enterprise users,
 * 21 internship/recruitment posts, 4 projects, 4 sponsorships, and 8 partnerships.
 *
 * Idempotent: safe to run multiple times.
 */
final class EnterpriseDemoSeeder
{
    public const PASSWORD_ENV = 'TALENTHUB_TEST_PASSWORD';

    public function run(PDO $pdo, string $environment, ?string $password = null): void
    {
        if (!in_array(strtolower($environment), ['test', 'testing', 'development', 'local'], true)) {
            throw new RuntimeException('Enterprise demo seed is forbidden outside local/test environments.');
        }

        $sqlFile = dirname(__DIR__) . '/enterprise_demo.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException("Enterprise demo SQL file not found at: {$sqlFile}");
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Failed to read enterprise demo SQL file: {$sqlFile}");
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);

            if ($password !== null && strlen($password) >= 8) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($hash !== false) {
                    $enterpriseEmails = [
                        'business@test.talenthub.local',
                        'vng.careers@talenthub.local',
                        'vinamilk@talenthub.local',
                        'biz@talenthub.local',
                        'vinamilk.careers@talenthub.local',
                        'techcombank.careers@talenthub.local',
                        'viettel.cyber@talenthub.local',
                        'dentsu.careers@talenthub.local',
                    ];
                    $inPlaceholders = implode(',', array_fill(0, count($enterpriseEmails), '?'));
                    $stmt = $pdo->prepare("UPDATE users SET passwordHash = ? WHERE email IN ($inPlaceholders)");
                    $stmt->execute(array_merge([$hash], $enterpriseEmails));
                }
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}