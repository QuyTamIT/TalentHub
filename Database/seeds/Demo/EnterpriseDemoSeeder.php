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
 * 21 internship/recruitment posts, 4 projects, 4 sponsorships, and approved
 * partnerships for every targeted-school post.
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
            $this->assertPartnerTargetsAreApproved($pdo);

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

    /**
     * Fail the seed transaction when a partner-only post targets a school that
     * does not have an approved relationship with that post's exact owner.
     */
    public function assertPartnerTargetsAreApproved(PDO $pdo): void
    {
        $invalid = $pdo->query(<<<'SQL'
            SELECT post.id AS postId, target.schoolId, post.enterpriseId
            FROM internship_posts AS post
            LEFT JOIN internship_post_target_schools AS target
                ON target.postId = post.id
            LEFT JOIN school_enterprise_partnerships AS partnership
                ON partnership.schoolId = target.schoolId
               AND partnership.enterpriseId = post.enterpriseId
               AND partnership.status = 'approved'
            WHERE post.audience = 'partner_schools'
              AND (target.schoolId IS NULL OR partnership.id IS NULL)
            ORDER BY post.id, target.schoolId
            LIMIT 1
        SQL)?->fetch(PDO::FETCH_ASSOC);

        if (!is_array($invalid)) {
            return;
        }

        $postId = (string) ($invalid['postId'] ?? '<unknown>');
        $schoolId = (string) ($invalid['schoolId'] ?? '<missing>');
        $enterpriseId = (string) ($invalid['enterpriseId'] ?? '<unknown>');
        throw new RuntimeException(
            "Partner target integrity failed for post {$postId}, school {$schoolId}, enterprise {$enterpriseId}."
        );
    }
}
