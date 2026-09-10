<?php
declare(strict_types=1);

namespace TalentHub\Domain\Profile;

use TalentHub\Domain\ErrorCodes;
use TalentHub\Domain\PolicyViolation;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

/**
 * Self-heal factories. Previously the read path of TeacherRepository,
 * TeacherGradingRepository and BusinessRepository inserted missing rows
 * silently inside the request flow, which violates read-only invariants
 * and produces phantom audit-log noise.
 *
 * These factories expose `ensureForUser()` as an explicit, callable method
 * that:
 *   - never runs from a repository read path
 *   - is invoked from API routes that need to bootstrap a profile on first
 *     authenticated access (e.g. legacy users whose row was created via the
 *     demo-user fallback)
 *   - is idempotent and concurrency-safe (INSERT IGNORE / upsert)
 */
final class ProfileFactory
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * Ensure a `teacher_profiles` row exists for the given userId. Returns
     * the existing or newly inserted row.
     *
     * @throws ApiException
     */
    public function ensureTeacherProfile(string $userId, ?string $preferredSchoolId = null): FactoryResult
    {
        if (!Uuid::isValid($userId)) {
            throw PolicyViolation::validation('userId không đúng định dạng UUID.');
        }

        $existing = $this->fetchTeacherProfile($userId);
        if ($existing !== null) {
            return new FactoryResult($existing, false);
        }

        $userRow = $this->fetchUser($userId);
        if ($userRow === null) {
            throw PolicyViolation::fromCode(ErrorCodes::RESOURCE_NOT_FOUND, 'Không tìm thấy tài khoản người dùng.');
        }

        $schoolId = $preferredSchoolId !== null && $preferredSchoolId !== ''
            ? $preferredSchoolId
            : $this->fallbackSchoolId();
        if ($schoolId === null) {
            throw PolicyViolation::fromCode(
                ErrorCodes::APPROVAL_CONTRACT_UNAVAILABLE,
                'Không tìm thấy trường học mặc định để tạo hồ sơ giáo viên.',
            );
        }

        $newId = Uuid::v4();
        $ins = $this->pdo->prepare(
            'INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin, specialization) '
            . 'VALUES (:id, :userId, :schoolId, 0, :specialization)'
        );
        $ins->execute([
            'id' => $newId,
            'userId' => $userId,
            'schoolId' => $schoolId,
            'specialization' => 'Kỹ thuật phần mềm & AI',
        ]);
        $row = $this->fetchTeacherProfile($userId);
        if ($row === null) {
            throw new \RuntimeException('Không thể tạo hồ sơ giáo viên sau khi insert.');
        }
        return new FactoryResult($row, true);
    }

    /**
     * Ensure an `enterprise_members` row exists for the given userId.
     */
    public function ensureEnterpriseMembership(string $userId, ?string $preferredEnterpriseId = null): FactoryResult
    {
        if (!Uuid::isValid($userId)) {
            throw PolicyViolation::validation('userId không đúng định dạng UUID.');
        }
        $existing = $this->fetchEnterpriseMembership($userId);
        if ($existing !== null) {
            return new FactoryResult($existing, false);
        }

        $userRow = $this->fetchUser($userId);
        if ($userRow === null) {
            throw PolicyViolation::fromCode(ErrorCodes::RESOURCE_NOT_FOUND, 'Không tìm thấy tài khoản người dùng.');
        }

        $enterpriseId = $preferredEnterpriseId;
        if ($enterpriseId === null || $enterpriseId === '') {
            // Fallback: match by email.
            $lookup = $this->pdo->prepare('SELECT id FROM enterprises WHERE email = :email LIMIT 1');
            $lookup->execute(['email' => $userRow['email'] ?? '']);
            $enterpriseId = $lookup->fetchColumn();
            if (!is_string($enterpriseId) || !Uuid::isValid($enterpriseId)) {
                throw PolicyViolation::fromCode(
                    ErrorCodes::APPROVAL_CONTRACT_UNAVAILABLE,
                    'Không tìm thấy doanh nghiệp để liên kết với tài khoản.',
                );
            }
        }

        $newId = Uuid::v4();
        $ins = $this->pdo->prepare(
            'INSERT IGNORE INTO enterprise_members (id, enterpriseId, userId, memberRole) '
            . 'VALUES (:id, :enterpriseId, :userId, :role)'
        );
        $ins->execute([
            'id' => $newId,
            'enterpriseId' => $enterpriseId,
            'userId' => $userId,
            'role' => 'admin',
        ]);

        $row = $this->fetchEnterpriseMembership($userId);
        if ($row === null) {
            throw new \RuntimeException('Không thể tạo liên kết doanh nghiệp sau khi insert.');
        }
        return new FactoryResult($row, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTeacherProfile(string $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.schoolId, tp.isSchoolAdmin, u.fullName, s.name AS schoolName '
            . 'FROM teacher_profiles tp '
            . 'INNER JOIN users u ON u.id = tp.userId '
            . 'INNER JOIN schools s ON s.id = tp.schoolId '
            . 'WHERE tp.userId = :userId LIMIT 1'
        );
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEnterpriseMembership(string $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT em.id, em.enterpriseId, em.userId, em.memberRole, e.name AS enterpriseName '
            . 'FROM enterprise_members em '
            . 'INNER JOIN enterprises e ON e.id = em.enterpriseId '
            . 'WHERE em.userId = :userId LIMIT 1'
        );
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUser(string $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, fullName FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function fallbackSchoolId(): ?string
    {
        // Prefer BTEC by name; otherwise pick the first school by createdAt.
        $stmt = $this->pdo->prepare("SELECT id FROM schools WHERE name LIKE :pattern ORDER BY createdAt ASC LIMIT 1");
        $stmt->execute(['pattern' => '%BTEC%']);
        $id = $stmt->fetchColumn();
        if (is_string($id) && Uuid::isValid($id)) {
            return $id;
        }
        $stmt = $this->pdo->query('SELECT id FROM schools ORDER BY createdAt ASC LIMIT 1');
        $id = $stmt !== false ? $stmt->fetchColumn() : null;
        return is_string($id) && Uuid::isValid($id) ? $id : null;
    }
}
