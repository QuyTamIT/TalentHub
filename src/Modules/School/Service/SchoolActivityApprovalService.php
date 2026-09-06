<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolActivityApprovalRepository;
use TalentHub\Support\Uuid;

final class SchoolActivityApprovalService
{
    private const STATUSES = ['draft', 'pending_school_review', 'changes_requested', 'approved', 'rejected'];

    public function __construct(private readonly SchoolActivityApprovalRepository $repository) {}

    /** @return list<array<string,mixed>> */
    public function listPending(string $schoolUserId, ?string $search = null): array
    {
        return $this->list($schoolUserId, 'pending_school_review', $search);
    }

    /** @return list<array<string,mixed>> */
    public function list(string $schoolUserId, ?string $status = null, ?string $search = null): array
    {
        $schoolId = $this->schoolId($schoolUserId);
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Bộ lọc trạng thái duyệt không hợp lệ.');
        }
        return $this->repository->listForSchool($schoolId, $status, $search);
    }

    /** @return array<string,mixed> */
    public function review(string $schoolUserId, string $activityId, string $decision, ?string $reason, string $requestId): array
    {
        if (!Uuid::isValid($activityId) || !Uuid::isValid($schoolUserId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã người dùng hoặc hoạt động không hợp lệ.');
        }
        if (preg_match('/\A[A-Za-z0-9_-]{16,64}\z/', $requestId) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Request ID không hợp lệ.');
        }
        return $this->repository->review($schoolUserId, $this->schoolId($schoolUserId), $activityId, $decision, $reason, substr($requestId, 0, 26));
    }

    private function schoolId(string $userId): string
    {
        $schoolId = $this->repository->schoolIdForUser($userId);
        if ($schoolId === null) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản không thuộc Nhà trường nào.');
        }
        return $schoolId;
    }
}
