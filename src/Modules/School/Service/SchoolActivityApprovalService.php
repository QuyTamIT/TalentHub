<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolActivityApprovalRepository;
use TalentHub\Support\Uuid;

final class SchoolActivityApprovalService
{
    public function __construct(private readonly SchoolActivityApprovalRepository $repository) {}

    /** @return list<array<string,mixed>> */
    public function listPending(string $schoolUserId, string $search = ''): array
    {
        $schoolId = $this->schoolId($schoolUserId);
        return $this->repository->listForSchool($schoolId, 'pending_school_review', trim($search));
    }

    /** @return array<string,mixed> */
    public function review(string $schoolUserId, string $activityId, string $decision, ?string $reason, string $requestId): array
    {
        if (!Uuid::isValid($schoolUserId) || !Uuid::isValid($activityId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã người dùng hoặc hoạt động không hợp lệ.');
        }
        if (preg_match('/\A[A-Za-z0-9_-]{16,64}\z/', $requestId) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Request ID không hợp lệ.');
        }
        return $this->repository->review(
            strtolower($schoolUserId),
            $this->schoolId($schoolUserId),
            strtolower($activityId),
            trim($decision),
            $reason,
            substr($requestId, 0, 64),
        );
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
