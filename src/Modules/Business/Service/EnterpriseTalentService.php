<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Support\Uuid;

final class EnterpriseTalentService
{
    public function __construct(
        private readonly EnterpriseTalentRepository $repository
    ) {}

    public function repository(): EnterpriseTalentRepository
    {
        return $this->repository;
    }

    /**
     * @param array<string,mixed> $params
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listTalents(string $userId, array $params = []): array
    {
        $enterprise = $this->repository->enterpriseForUser($userId);
        return $this->repository->listTalents($enterprise['id'], $params);
    }

    /**
     * @return array<string,mixed>
     */
    public function getTalent(string $userId, string $studentId): array
    {
        $studentId = $this->uuid($studentId, 'studentId');
        $enterprise = $this->repository->enterpriseForUser($userId);
        $talent = $this->repository->getTalentDetail($enterprise['id'], $studentId);

        if ($talent === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng viên hoặc ứng viên chưa cấp quyền truy cập.');
        }

        return $talent;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function requestContact(string $userId, string $studentId, array $input, string $requestId): array
    {
        $studentId = $this->uuid($studentId, 'studentId');
        $enterprise = $this->repository->enterpriseForUser($userId);

        $idempotencyKey = isset($input['idempotencyKey']) && is_string($input['idempotencyKey']) && trim($input['idempotencyKey']) !== ''
            ? trim($input['idempotencyKey'])
            : $requestId;

        $message = isset($input['message']) && is_string($input['message'])
            ? trim($input['message'])
            : null;

        if ($message !== null && mb_strlen($message) > 1000) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tin nhắn liên hệ không được vượt quá 1000 ký tự.');
        }

        return $this->repository->createContactRequest($enterprise['id'], $userId, $studentId, $idempotencyKey, $message);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function grantAccess(string $userId, array $input): array
    {
        $studentId = $this->repository->studentIdForUser($userId);
        $enterpriseId = $this->uuid((string) ($input['enterpriseId'] ?? ''), 'enterpriseId');
        $scope = trim((string) ($input['scope'] ?? 'enterprise_talent_discovery'));
        $days = (int) ($input['durationDays'] ?? 30);

        return $this->repository->grantAccess($studentId, $enterpriseId, $scope, $days);
    }

    /**
     * @return array{revoked:bool}
     */
    public function revokeGrant(string $userId, string $grantId): array
    {
        $studentId = $this->repository->studentIdForUser($userId);
        $this->repository->revokeGrant($studentId, $grantId);
        return ['revoked' => true];
    }

    /**
     * @return array{items:list<array<string,mixed>>}
     */
    public function listGrants(string $userId): array
    {
        $studentId = $this->repository->studentIdForUser($userId);
        return ['items' => $this->repository->listGrants($studentId)];
    }

    private function uuid(string $value, string $field): string
    {
        $value = strtolower(trim($value));
        if (!Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        return $value;
    }
}
