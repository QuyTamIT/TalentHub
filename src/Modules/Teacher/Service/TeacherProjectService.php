<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherProjectRepository;
use TalentHub\Support\Uuid;

final class TeacherProjectService
{
    public function __construct(
        private readonly TeacherProjectRepository $repository
    ) {}

    public function addMember(string $teacherUserId, string $projectId, array $input, string $requestId): array
    {
        if (!Uuid::isValid($projectId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'projectId không đúng định dạng UUID.');
        }
        return $this->repository->addMember($teacherUserId, $projectId, $input, $requestId);
    }

    public function listMembers(string $projectId): array
    {
        if (!Uuid::isValid($projectId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'projectId không đúng định dạng UUID.');
        }
        return $this->repository->listMembers($projectId);
    }
}
