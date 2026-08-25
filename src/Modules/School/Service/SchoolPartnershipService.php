<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Support\Uuid;

final class SchoolPartnershipService
{
    public function __construct(private readonly SchoolPartnershipRepository $repository) {}

    public function listEnterprisePartnerships(string $userId, ?string $status = null): array
    {
        $enterpriseId = $this->repository->enterpriseIdForUser($userId);
        return [
            'items' => $this->repository->listEnterprisePartnerships($enterpriseId, $status),
            'approvedSchools' => $this->repository->listApprovedSchoolsForEnterprise($enterpriseId),
        ];
    }

    public function listApprovedSchoolsForEnterprise(string $userId): array
    {
        $enterpriseId = $this->repository->enterpriseIdForUser($userId);
        return [
            'items' => $this->repository->listApprovedSchoolsForEnterprise($enterpriseId),
        ];
    }

    public function requestPartnership(string $userId, array $input): array
    {
        $enterpriseId = $this->repository->enterpriseIdForUser($userId);
        $schoolId = trim((string) ($input['schoolId'] ?? ''));

        if (!Uuid::isValid($schoolId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'schoolId không hợp lệ.');
        }

        return $this->repository->createPartnershipRequest($enterpriseId, $userId, $schoolId);
    }

    public function listSchoolPartnerships(string $userId, ?string $status = null): array
    {
        $schoolId = $this->repository->schoolIdForUser($userId);
        return [
            'items' => $this->repository->listSchoolPartnerships($schoolId, $status),
        ];
    }

    public function reviewPartnership(string $userId, string $partnershipId, array $input): array
    {
        $partnershipId = trim($partnershipId);
        if (!Uuid::isValid($partnershipId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'partnershipId không hợp lệ.');
        }

        $schoolId = $this->repository->schoolIdForUser($userId);
        $status = trim((string) ($input['status'] ?? $input['targetStatus'] ?? ''));

        if (!in_array($status, ['approved', 'rejected', 'suspended'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái xét duyệt quan hệ đối tác không hợp lệ.');
        }

        return $this->repository->reviewPartnership($schoolId, $userId, $partnershipId, $status);
    }
}
