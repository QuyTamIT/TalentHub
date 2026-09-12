<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Support\Uuid;

final class SchoolProjectService
{
    public function __construct(
        private readonly SchoolProjectRepository $repository
    ) {}

    public function schoolId(string $userId): string
    {
        return $this->repository->schoolIdForUser($userId);
    }

    public function createProject(string $userId, array $input, string $requestId): array
    {
        $schoolId = $this->schoolId($userId);
        return $this->repository->createProject($schoolId, $userId, $input, $requestId);
    }

    public function listProjects(string $userId): array
    {
        $schoolId = $this->schoolId($userId);
        return $this->repository->listSchoolProjects($schoolId);
    }

    /** @return array{totalRaised:string,totalFundingGoal:string,projectsWithFunding:int,goalReachedProjects:int,activeSponsors:int} */
    public function fundingSummary(string $userId): array
    {
        return $this->repository->fundingSummary($this->schoolId($userId));
    }

    public function getProject(string $userId, string $projectId): array
    {
        if (!Uuid::isValid($projectId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'projectId không đúng định dạng UUID.');
        }
        $schoolId = $this->schoolId($userId);
        return $this->repository->getProject($schoolId, $projectId);
    }

    public function updateProject(string $userId, string $projectId, array $input): array
    {
        if (!Uuid::isValid($projectId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'projectId không đúng định dạng UUID.');
        }
        $schoolId = $this->schoolId($userId);
        return $this->repository->updateProject($schoolId, $userId, $projectId, $input);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProjectMembers(string $userId, string $projectId): array
    {
        if (!Uuid::isValid($projectId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'projectId không đúng định dạng UUID.');
        }
        $schoolId = $this->schoolId($userId);
        return $this->repository->listProjectMembers($schoolId, $projectId);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateMemberStatus(string $userId, string $projectId, string $studentId, string $status): array
    {
        if (!Uuid::isValid($projectId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'projectId không đúng định dạng UUID.');
        }
        if (!Uuid::isValid($studentId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'studentId không đúng định dạng UUID.');
        }
        $schoolId = $this->schoolId($userId);
        return $this->repository->updateMemberStatus($schoolId, $userId, $projectId, $studentId, $status);
    }
}
