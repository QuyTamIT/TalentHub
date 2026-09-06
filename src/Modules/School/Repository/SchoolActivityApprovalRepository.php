<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class SchoolActivityApprovalRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null,
    ) {}

    public function schoolIdForUser(string $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT schoolId FROM school_members WHERE userId=:userId LIMIT 1');
        $statement->execute(['userId' => $userId]);
        $schoolId = $statement->fetchColumn();
        return is_string($schoolId) && $schoolId !== '' ? $schoolId : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForSchool(string $schoolId, ?string $status = null, ?string $search = null): array
    {
        $where = ['a.schoolId=:schoolId'];
        $parameters = ['schoolId' => $schoolId];
        if ($status !== null && $status !== '') {
            $where[] = 'a.approvalStatus=:approvalStatus';
            $parameters['approvalStatus'] = $status;
        }
        if ($search !== null && trim($search) !== '') {
            $where[] = 'LOWER(a.title) LIKE :search';
            $parameters['search'] = '%' . mb_strtolower(trim($search)) . '%';
        }

        $statement = $this->pdo->prepare(
            'SELECT a.id,a.schoolId,a.createdByTeacherId,a.title,a.category,a.startAt,a.endAt,a.capacity,a.status,a.visibility,'
            . 'a.approvalStatus,a.approvalRequestedAt,a.approvedAt,a.approvedBy,a.approvalReason,'
            . 'u.fullName AS teacherName,d.summary,d.description,d.locationName,d.locationAddress,d.deliveryMode,d.onlineMeetingUrl,'
            . 'd.organizerName,d.organizerContact,d.coverImageUrl,d.coverImageAlt,d.targetAudience,d.skillTags,d.eligibilityRules,d.benefitItems,'
            . 'p.registrationOpensAt,p.registrationClosesAt,p.cancellationClosesAt,p.approvalMode '
            . 'FROM activities a INNER JOIN teacher_profiles tp ON tp.id=a.createdByTeacherId '
            . 'INNER JOIN users u ON u.id=tp.userId LEFT JOIN activity_details d ON d.activityId=a.id '
            . 'LEFT JOIN activity_registration_policies p ON p.activityId=a.id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY a.approvalRequestedAt DESC,a.startAt,a.id'
        );
        $statement->execute($parameters);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string,mixed> */
    public function review(string $schoolUserId, string $schoolId, string $activityId, string $decision, ?string $reason, string $requestId): array
    {
        $nextStatus = match ($decision) {
            'approve' => 'approved',
            'request_changes' => 'changes_requested',
            'reject' => 'rejected',
            default => throw new ApiException(422, 'VALIDATION_FAILED', 'Quyết định duyệt hoạt động không hợp lệ.'),
        };
        $reason = $reason !== null ? trim($reason) : null;
        if ($nextStatus !== 'approved' && ($reason === null || $reason === '')) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng nhập lý do khi yêu cầu chỉnh sửa hoặc từ chối.');
        }
        if ($reason !== null && mb_strlen($reason) > 1000) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Lý do không được vượt quá 1000 ký tự.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $statement = $this->pdo->prepare(
                'SELECT a.id,a.title,a.schoolId,a.createdByTeacherId,a.approvalStatus,tp.userId AS teacherUserId '
                . 'FROM activities a INNER JOIN teacher_profiles tp ON tp.id=a.createdByTeacherId '
                . 'WHERE a.id=:activityId AND a.schoolId=:schoolId LIMIT 1' . $lock
            );
            $statement->execute(['activityId' => $activityId, 'schoolId' => $schoolId]);
            $activity = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($activity)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc trường hiện tại.');
            }

            $previous = (string) $activity['approvalStatus'];
            if ($previous === $nextStatus) {
                if ($ownsTransaction) $this->pdo->commit();
                return $activity + ['approvalStatus' => $nextStatus, 'approvalReason' => $reason];
            }
            if ($previous !== 'pending_school_review') {
                throw new ApiException(409, 'APPROVAL_STATUS_CONFLICT', 'Hoạt động không còn ở trạng thái chờ Nhà trường duyệt.');
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $update = $this->pdo->prepare(
                'UPDATE activities SET approvalStatus=:nextStatus,approvedAt=:approvedAt,approvedBy=:approvedBy,approvalReason=:reason '
                . 'WHERE id=:activityId AND schoolId=:schoolId AND approvalStatus=:previousStatus'
            );
            $update->execute([
                'nextStatus' => $nextStatus,
                'approvedAt' => $nextStatus === 'approved' ? $now : null,
                'approvedBy' => $nextStatus === 'approved' ? $schoolUserId : null,
                'reason' => $nextStatus === 'approved' ? null : $reason,
                'activityId' => $activityId,
                'schoolId' => $schoolId,
                'previousStatus' => $previous,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'APPROVAL_STATUS_CONFLICT', 'Trạng thái duyệt đã thay đổi bởi một yêu cầu khác.');
            }

            $audit = $this->pdo->prepare('INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt) VALUES (:id,:userId,:action,\'activity\',:entityId,:requestId,NULL,:metadata,:createdAt)');
            $audit->execute([
                'id' => Uuid::v4(), 'userId' => $schoolUserId,
                'action' => 'activity.' . $nextStatus, 'entityId' => $activityId, 'requestId' => $requestId,
                'metadata' => json_encode(['schoolId' => $schoolId, 'previousStatus' => $previous, 'status' => $nextStatus, 'reason' => $nextStatus === 'approved' ? null : $reason], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'createdAt' => $now,
            ]);

            $teacherType = match ($nextStatus) {
                'approved' => 'activity_approved',
                'changes_requested' => 'activity_changes_requested',
                default => 'activity_rejected',
            };
            $this->notificationService()->publish(
                (string) $activity['teacherUserId'], $teacherType,
                $nextStatus === 'approved' ? 'Hoạt động đã được duyệt' : ($nextStatus === 'changes_requested' ? 'Hoạt động cần chỉnh sửa' : 'Hoạt động bị từ chối'),
                'Hoạt động ' . (string) $activity['title'] . ($reason ? ': ' . $reason : ' đã được Nhà trường phê duyệt.'),
                '/app/teacher/activities/index.php', $teacherType . ':' . $activityId,
            );

            if ($nextStatus === 'approved') {
                $students = $this->pdo->prepare('SELECT sp.id,sp.userId FROM student_profiles sp INNER JOIN classes c ON c.id=sp.classId WHERE c.schoolId=:schoolId AND sp.studyStatus=\'active\'');
                $students->execute(['schoolId' => $schoolId]);
                foreach ($students->fetchAll(PDO::FETCH_ASSOC) ?: [] as $student) {
                    $this->notificationService()->publish(
                        (string) $student['userId'], 'activity_approved', 'Hoạt động mới đã được duyệt',
                        'Hoạt động ' . (string) $activity['title'] . ' đã được Nhà trường phê duyệt.',
                        '/app/learner/activities.php', 'activity_approved:' . $activityId . ':' . (string) $student['id'], (string) $student['id'],
                    );
                }
            }

            if ($ownsTransaction) $this->pdo->commit();
            return $activity + ['approvalStatus' => $nextStatus, 'approvalReason' => $nextStatus === 'approved' ? null : $reason, 'approvedAt' => $nextStatus === 'approved' ? $now : null, 'approvedBy' => $nextStatus === 'approved' ? $schoolUserId : null];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function notificationService(): NotificationService
    {
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }
}
