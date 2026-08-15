<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Service;

use PDO;
use TalentHub\Http\ApiException;

/**
 * Authorization helper for the School dashboard.
 *
 * Currently the dashboard enforces role=='school' in SchoolAppContext.
 * For write actions (class CRUD, student CRUD, teacher invite, settings
 * update, report generation), we additionally require the user to be a
 * school admin (memberRole=='admin' OR teacher_profiles.isSchoolAdmin=1).
 *
 * Read-only pages remain accessible to non-admin members so they can still
 * see their school's data.
 */
final class SchoolAuthorization
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Throws ApiException(403) when the user cannot perform write actions
     * for the given school.
     */
    public function requireWriteAccess(string $userId, string $schoolId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT sm.memberRole, tp.isSchoolAdmin
             FROM school_members sm
             LEFT JOIN teacher_profiles tp ON tp.userId = sm.userId AND tp.schoolId = sm.schoolId
             WHERE sm.userId = :userId AND sm.schoolId = :schoolId LIMIT 1'
        );
        $stmt->execute(['userId' => $userId, 'schoolId' => $schoolId]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new ApiException(403, 'FORBIDDEN', 'Bạn không thuộc trường này.');
        }

        $isAdmin = ($row['memberRole'] ?? '') === 'admin'
            || (int) ($row['isSchoolAdmin'] ?? 0) === 1;

        if (!$isAdmin) {
            throw new ApiException(
                403,
                'NOT_SCHOOL_ADMIN',
                'Bạn cần quyền quản trị trường để thực hiện thao tác này.'
            );
        }
    }
}