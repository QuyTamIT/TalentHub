<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;
use TalentHub\Http\ApiException;
use Throwable;

final class SchoolActivityApprovalRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function schoolIdForUser(string $userId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT schoolId FROM school_members WHERE userId = :userId LIMIT 1'
        );
        $statement->execute(['userId' => $userId]);
        $schoolId = $statement->fetchColumn();
        return is_string($schoolId) && $schoolId !== '' ? $schoolId : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForSchool(string $schoolId, string $status = 'pending_school_review', string $search = ''): array
    {
        $sql = "
            SELECT a.id, a.schoolId, a.createdByTeacherId, a.title, a.category,
                   a.startAt, a.endAt, a.capacity, a.status, a.approvalStatus,
                   a.approvalRequestedAt, a.approvalReason,
                   u.fullName AS teacherName,
                   d.summary, d.description, d.locationName, d.locationAddress
            FROM activities a
            INNER JOIN teacher_profiles tp ON tp.id = a.createdByTeacherId
            INNER JOIN users u ON u.id = tp.userId
            LEFT JOIN activity_details d ON d.activityId = a.id
            WHERE a.schoolId = :schoolId
              AND a.approvalStatus = :approvalStatus
        ";
        $parameters = ['schoolId' => $schoolId, 'approvalStatus' => $status];
        if ($search !== '') {
            $sql .= ' AND LOWER(a.title) LIKE :search';
            $parameters['search'] = '%' . mb_strtolower($search) . '%';
        }
        $sql .= ' ORDER BY a.approvalRequestedAt DESC, a.startAt ASC, a.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string,mixed> */
    public function review(
        string $schoolUserId,
        string $schoolId,
        string $activityId,
        string $decision,
        ?string $reason,
        string $requestId,
    ): array {
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Quyết định duyệt hoạt động không hợp lệ.');
        }
        $reason = $reason !== null ? trim($reason) : null;
        if ($decision === 'reject' && ($reason === null || $reason === '')) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng nhập lý do khi từ chối hoạt động.');
        }
        if ($reason !== null && mb_strlen($reason) > 1000) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Lý do không được vượt quá 1000 ký tự.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $select = $this->pdo->prepare(
                "SELECT a.id, a.title, a.status, a.approvalStatus
                 FROM activities a
                 WHERE a.id = :activityId AND a.schoolId = :schoolId
                 LIMIT 1{$lock}"
            );
            $select->execute(['activityId' => $activityId, 'schoolId' => $schoolId]);
            $activity = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($activity)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc trường hiện tại.');
            }
            if ((string) $activity['approvalStatus'] !== 'pending_school_review' || (string) $activity['status'] !== 'draft') {
                throw new ApiException(409, 'APPROVAL_STATUS_CONFLICT', 'Hoạt động không còn ở trạng thái chờ Nhà trường duyệt.');
            }

            $approved = $decision === 'approve';
            $now = gmdate('Y-m-d H:i:s.u');
            $update = $this->pdo->prepare(
                'UPDATE activities
                 SET approvalStatus = :nextApprovalStatus,
                     approvalReason = :approvalReason,
                     approvedAt = :approvedAt,
                     approvedBy = :approvedBy,
                     status = :nextActivityStatus
                 WHERE id = :activityId AND schoolId = :schoolId
                   AND status = \'draft\' AND approvalStatus = \'pending_school_review\''
            );
            $update->execute([
                'nextApprovalStatus' => $approved ? 'approved' : 'rejected',
                'approvalReason' => $approved ? null : $reason,
                'approvedAt' => $approved ? $now : null,
                'approvedBy' => $approved ? $schoolUserId : null,
                'nextActivityStatus' => $approved ? 'published' : 'draft',
                'activityId' => $activityId,
                'schoolId' => $schoolId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'APPROVAL_STATUS_CONFLICT', 'Trạng thái duyệt đã thay đổi bởi một yêu cầu khác.');
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return [
                'id' => $activityId,
                'status' => $approved ? 'published' : 'draft',
                'approvalStatus' => $approved ? 'approved' : 'rejected',
                'approvalReason' => $approved ? null : $reason,
                'requestId' => $requestId,
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
