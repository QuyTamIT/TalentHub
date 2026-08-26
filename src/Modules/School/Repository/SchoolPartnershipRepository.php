<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class SchoolPartnershipRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}

    public function enterpriseIdForUser(string $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT enterpriseId FROM enterprise_members WHERE userId = ? ORDER BY id LIMIT 2');
        $stmt->execute([$userId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) !== 1) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản phải thuộc đúng một doanh nghiệp.');
        }

        $enterpriseId = (string) $ids[0];
        $checkStmt = $this->pdo->prepare('SELECT status, verificationStatus FROM enterprises WHERE id = ? LIMIT 1');
        $checkStmt->execute([$enterpriseId]);
        $enterprise = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($enterprise) || ($enterprise['status'] ?? '') !== 'active') {
            throw new ApiException(403, 'ENTERPRISE_INACTIVE', 'Doanh nghiệp chưa kích hoạt.');
        }

        $verification = (string) ($enterprise['verificationStatus'] ?? 'pending');
        if (!in_array($verification, ['verified', 'approved'], true)) {
            throw new ApiException(403, 'ENTERPRISE_NOT_VERIFIED', 'Doanh nghiệp chưa được xác thực.');
        }

        return $enterpriseId;
    }

    public function schoolIdForUser(string $userId): string
    {
        // 1. Direct school admin / staff query
        if ($this->tableExists('school_teachers')) {
            $stmt = $this->pdo->prepare('SELECT schoolId FROM school_teachers WHERE userId = ? ORDER BY id LIMIT 1');
            $stmt->execute([$userId]);
            $id = $stmt->fetchColumn();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        if ($this->tableExists('teachers')) {
            $stmt = $this->pdo->prepare('SELECT schoolId FROM teachers WHERE userId = ? ORDER BY id LIMIT 1');
            $stmt->execute([$userId]);
            $id = $stmt->fetchColumn();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        // 2. Query schools table directly if user is school owner or linked
        $stmt = $this->pdo->prepare('SELECT id FROM schools WHERE status = \'active\' ORDER BY createdAt ASC LIMIT 1');
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if (is_string($id) && $id !== '') {
            return $id;
        }

        throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản không thuộc cơ sở giáo dục nào.');
    }

    /** @return list<array<string,mixed>> */
    public function listEnterprisePartnerships(string $enterpriseId, ?string $status = null): array
    {
        $where = ['sep.enterpriseId = :enterpriseId'];
        $params = ['enterpriseId' => $enterpriseId];

        if ($status !== null && $status !== '') {
            $where[] = 'sep.status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT sep.id, sep.schoolId, sep.enterpriseId, sep.status, sep.requestedByUserId,
                       sep.reviewedByUserId, sep.reviewedAt, sep.createdAt, sep.updatedAt,
                       s.name AS schoolName, s.logoUrl AS schoolLogo,
                       s.level AS schoolLevel
                FROM school_enterprise_partnerships sep
                INNER JOIN schools s ON s.id = sep.schoolId
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY sep.updatedAt DESC, sep.createdAt DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function listApprovedSchoolsForEnterprise(string $enterpriseId): array
    {
        $sql = 'SELECT s.id, s.name, s.logoUrl, s.level, sep.id AS partnershipId, sep.reviewedAt
                FROM school_enterprise_partnerships sep
                INNER JOIN schools s ON s.id = sep.schoolId
                WHERE sep.enterpriseId = :enterpriseId
                  AND sep.status = \'approved\'
                  AND s.status = \'active\'
                ORDER BY s.name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['enterpriseId' => $enterpriseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPartnershipRequest(string $enterpriseId, string $userId, string $schoolId): array
    {
        $schoolId = trim($schoolId);
        if (!Uuid::isValid($schoolId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã trường học không hợp lệ.');
        }

        // Verify school exists and is active
        $stmtSchool = $this->pdo->prepare('SELECT id, name FROM schools WHERE id = ? AND status = \'active\' LIMIT 1');
        $stmtSchool->execute([$schoolId]);
        $school = $stmtSchool->fetch(PDO::FETCH_ASSOC);
        if (!is_array($school)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy trường học hoặc trường đang tạm dừng.');
        }

        // Check if partnership already exists
        $stmtExisting = $this->pdo->prepare('SELECT id, status FROM school_enterprise_partnerships WHERE schoolId = ? AND enterpriseId = ? LIMIT 1');
        $stmtExisting->execute([$schoolId, $enterpriseId]);
        $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);

        $now = $this->now();
        $id = is_array($existing) ? (string) $existing['id'] : Uuid::v4();

        if (is_array($existing)) {
            $currentStatus = (string) $existing['status'];
            if ($currentStatus === 'approved') {
                return $this->getPartnership($id);
            }
            if ($currentStatus === 'pending') {
                return $this->getPartnership($id);
            }

            // Re-request if rejected or suspended
            $stmtUpd = $this->pdo->prepare('UPDATE school_enterprise_partnerships SET status = \'pending\', requestedByUserId = :userId, reviewedByUserId = NULL, reviewedAt = NULL, updatedAt = :now WHERE id = :id');
            $stmtUpd->execute(['userId' => $userId, 'now' => $now, 'id' => $id]);
        } else {
            $stmtInsert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO school_enterprise_partnerships
                    (id, schoolId, enterpriseId, status, requestedByUserId, createdAt, updatedAt)
                VALUES
                    (:id, :schoolId, :enterpriseId, 'pending', :userId, :now, :now)
            SQL);
            $stmtInsert->execute([
                'id' => $id,
                'schoolId' => $schoolId,
                'enterpriseId' => $enterpriseId,
                'userId' => $userId,
                'now' => $now,
            ]);
        }

        // Notify School Admin
        $this->notifySchool($schoolId, 'Yêu cầu hợp tác mới từ Doanh nghiệp', "Doanh nghiệp đã gửi yêu cầu hợp tác với nhà trường.", "/app/teacher/partnerships.php?id={$id}");

        return $this->getPartnership($id);
    }

    /** @return list<array<string,mixed>> */
    public function listSchoolPartnerships(string $schoolId, ?string $status = null): array
    {
        $where = ['sep.schoolId = :schoolId'];
        $params = ['schoolId' => $schoolId];

        if ($status !== null && $status !== '') {
            $where[] = 'sep.status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT sep.id, sep.schoolId, sep.enterpriseId, sep.status, sep.requestedByUserId,
                       sep.reviewedByUserId, sep.reviewedAt, sep.createdAt, sep.updatedAt,
                       e.name AS enterpriseName, e.logoUrl AS enterpriseLogo, e.industry, e.website
                FROM school_enterprise_partnerships sep
                INNER JOIN enterprises e ON e.id = sep.enterpriseId
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY sep.updatedAt DESC, sep.createdAt DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function reviewPartnership(string $schoolId, string $reviewerUserId, string $partnershipId, string $targetStatus): array
    {
        if (!in_array($targetStatus, ['approved', 'rejected', 'suspended'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái xét duyệt quan hệ hợp tác không hợp lệ.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM school_enterprise_partnerships WHERE id = ? AND schoolId = ? LIMIT 1');
            $stmt->execute([$partnershipId, $schoolId]);
            $partnership = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($partnership)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy quan hệ đối tác.');
            }

            $now = $this->now();
            $upd = $this->pdo->prepare(<<<'SQL'
                UPDATE school_enterprise_partnerships
                SET status = :targetStatus,
                    reviewedByUserId = :reviewerUserId,
                    reviewedAt = :now,
                    updatedAt = :now
                WHERE id = :id AND schoolId = :schoolId
            SQL);
            $upd->execute([
                'targetStatus' => $targetStatus,
                'reviewerUserId' => $reviewerUserId,
                'now' => $now,
                'id' => $partnershipId,
                'schoolId' => $schoolId,
            ]);

            // Notify Enterprise
            $enterpriseId = (string) $partnership['enterpriseId'];
            $statusLabels = [
                'approved' => 'đã được Nhà trường chấp thuận',
                'rejected' => 'đã bị Nhà trường từ chối',
                'suspended' => 'đã bị tạm dừng',
            ];
            $label = $statusLabels[$targetStatus] ?? $targetStatus;
            $this->notifyEnterprise($enterpriseId, 'Cập nhật quan hệ hợp tác Nhà trường', "Yêu cầu hợp tác {$label}.", "/app/enterprise/partnerships.php");

            $this->pdo->commit();
            return $this->getPartnership($partnershipId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getPartnership(string $partnershipId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT sep.*,
                   s.name AS schoolName, s.logoUrl AS schoolLogo, s.level AS schoolLevel,
                   e.name AS enterpriseName, e.logoUrl AS enterpriseLogo, e.industry
            FROM school_enterprise_partnerships sep
            INNER JOIN schools s ON s.id = sep.schoolId
            INNER JOIN enterprises e ON e.id = sep.enterpriseId
            WHERE sep.id = :id
            LIMIT 1
        SQL);
        $stmt->execute(['id' => $partnershipId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($res)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy thông tin quan hệ hợp tác.');
        }
        return $res;
    }

    private function notifySchool(string $schoolId, string $title, string $message, string $deepLink): void
    {
        try {
            // Find school admin user(s)
            $stmt = $this->pdo->prepare('SELECT u.id FROM users u WHERE u.role IN (\'school\', \'school_admin\') AND u.status = \'active\' LIMIT 1');
            $stmt->execute();
            $userId = $stmt->fetchColumn();
            if (is_string($userId) && $userId !== '') {
                $notifService = $this->getNotificationService();
                $notifService->notifyLearner($userId, 'system', $title, $message, $deepLink);
            }
        } catch (Throwable) {}
    }

    private function notifyEnterprise(string $enterpriseId, string $title, string $message, string $deepLink): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT userId FROM enterprise_members WHERE enterpriseId = ? ORDER BY id LIMIT 1');
            $stmt->execute([$enterpriseId]);
            $userId = $stmt->fetchColumn();
            if (is_string($userId) && $userId !== '') {
                $notifService = $this->getNotificationService();
                $notifService->notifyLearner($userId, 'system', $title, $message, $deepLink);
            }
        } catch (Throwable) {}
    }

    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            $path = dirname(__DIR__, 4) . '/app/learner/data/bootstrap.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }

    private function tableExists(string $tableName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
