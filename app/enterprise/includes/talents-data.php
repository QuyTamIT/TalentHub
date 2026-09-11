<?php
/**
 * TalentHub Enterprise - Talent Data Bridge (Pure Database Connection)
 *
 * All talent data is queried directly from the database (student_profiles, users,
 * student_skills, skills, assessments, student_profile_details).
 * Static mockup data is completely eradicated.
 */
declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;

if (!function_exists('getTalentById')) {
    /**
     * Helper to retrieve a single student profile by ID directly from Database.
     *
     * @param string $studentId
     * @param string|null $enterpriseId
     * @return array<string,mixed>|null
     */
    function getTalentById(string $studentId, ?string $enterpriseId = null): ?array {
        static $pdo = null;
        if ($pdo === null) {
            $config = require dirname(__DIR__, 3) . '/config/database.php';
            $pdo = (new Connection($config))->connect();
        }

        $entId = $enterpriseId ?? ($_SESSION['enterprise_id'] ?? ($_SESSION['user']['enterpriseId'] ?? ''));
        if ($entId === '') {
            return null;
        }

        $repo = new EnterpriseTalentRepository($pdo);
        return $repo->getTalentDetail($entId, $studentId);
    }
}
