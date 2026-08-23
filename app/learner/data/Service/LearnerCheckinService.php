<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\CheckinRepository;
use TalentHub\Support\Uuid;

final class LearnerCheckinService
{
    public function __construct(private readonly CheckinRepository $repository) {}

    /** @return array<string,mixed> */
    public function submit(string $studentId, string $actorUserId, string $requestId, mixed &$rawToken): array
    {
        $token = null;
        try {
            $token = $this->token($rawToken);
            $tokenHash = hash('sha256', $token);
        } finally {
            $rawToken = null;
            if (is_string($token)) {
                if (function_exists('sodium_memzero')) {
                    sodium_memzero($token);
                } else {
                    $token = '';
                }
            }
            unset($token);
        }

        return $this->repository->createConfirmed(
            $this->uuid($studentId, 'studentId'),
            $this->uuid($actorUserId, 'actorUserId'),
            $requestId,
            $tokenHash,
        );
    }

    /** @return list<array<string,mixed>> */
    public function history(string $studentId, int $limit = 25, int $offset = 0): array
    {
        return $this->repository->history($this->uuid($studentId, 'studentId'), max(1, min(100, $limit)), max(0, $offset));
    }

    private function token(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Token QR khong hop le.', [[
                'field' => 'token',
                'code' => 'REQUIRED',
                'message' => 'Token QR la bat buoc.',
            ]]);
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > 512 || preg_match('/\s/', $value) === 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Token QR khong hop le.', [[
                'field' => 'token',
                'code' => 'INVALID_FORMAT',
                'message' => 'Token QR phai la chuoi opaque khong co khoang trang.',
            ]]);
        }
        return $value;
    }

    private function uuid(string $value, string $field): string
    {
        if (!Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phai co dinh dang UUID hop le.");
        }
        return strtolower($value);
    }
}
