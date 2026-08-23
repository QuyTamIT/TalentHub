<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\InternshipApplicationCommandRepository;
use TalentHub\Learner\Data\Support\Uuid;

final class ApplicationCommandService
{
    public function __construct(private readonly InternshipApplicationCommandRepository $repository) {}

    /** @return array<string,mixed> */
    public function grantConsent(string $studentId, string $userId, string $requestId, bool $confirmed): array
    {
        if (!$confirmed) {
            throw new ApiException(422, 'CONSENT_CONFIRMATION_REQUIRED', 'Bạn phải xác nhận rõ việc chia sẻ hồ sơ ứng tuyển.');
        }

        return $this->repository->grantApplicationProfileConsent(
            $this->uuid($studentId, 'studentId'),
            $this->uuid($userId, 'userId'),
            $requestId,
        );
    }

    /** @return array<string,mixed> */
    public function submit(string $studentId, string $userId, string $requestId, string $postId, string $message): array
    {
        $message = trim($message);
        if (mb_strlen($message) > 500) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Lời nhắn ứng tuyển không được vượt quá 500 ký tự.');
        }

        return $this->repository->submit(
            $this->uuid($studentId, 'studentId'),
            $this->uuid($userId, 'userId'),
            $requestId,
            $this->uuid($postId, 'postId'),
            $message,
        );
    }

    /** @return array<string,mixed> */
    public function list(string $studentId): array
    {
        return $this->repository->readForStudent($this->uuid($studentId, 'studentId'));
    }

    /** @return array<string,mixed> */
    public function detail(string $studentId, string $applicationId): array
    {
        return $this->repository->readOneForStudent(
            $this->uuid($studentId, 'studentId'),
            $this->uuid($applicationId, 'applicationId'),
        );
    }

    /** @return array<string,mixed> */
    public function withdraw(string $studentId, string $userId, string $requestId, string $applicationId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) > 500) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Lý do rút hồ sơ không được vượt quá 500 ký tự.');
        }

        return $this->repository->withdraw(
            $this->uuid($studentId, 'studentId'),
            $this->uuid($userId, 'userId'),
            $requestId,
            $this->uuid($applicationId, 'applicationId'),
            $reason,
        );
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
