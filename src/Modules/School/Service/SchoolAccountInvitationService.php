<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;

final class SchoolAccountInvitationService
{
    public function __construct(private readonly SchoolRepository $repository) {}

    /** @return array<string,mixed> */
    public function inspect(string $rawToken): array
    {
        $tokenHash = $this->tokenHash($rawToken);
        $invitation = $this->repository->findAccountInvitation($tokenHash);
        if ($invitation === null) {
            throw new ApiException(404, 'INVITATION_NOT_FOUND', 'Không tìm thấy lời mời.');
        }
        if ((string) $invitation['accountRole'] !== (string) $invitation['actualRole']) {
            throw new ApiException(409, 'INVITATION_ROLE_MISMATCH', 'Vai trò của lời mời không còn hợp lệ.');
        }

        $status = 'pending';
        if ($invitation['acceptedAt'] !== null) {
            $status = 'accepted';
        } elseif ($invitation['revokedAt'] !== null) {
            $status = 'revoked';
        } elseif (new DateTimeImmutable((string) $invitation['expiresAt'], new DateTimeZone('UTC')) <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            $status = 'expired';
        }

        return [
            'status' => $status,
            'email' => (string) $invitation['email'],
            'fullName' => (string) $invitation['fullName'],
            'role' => (string) $invitation['accountRole'],
            'schoolName' => (string) $invitation['schoolName'],
            'expiresAt' => (string) $invitation['expiresAt'],
        ];
    }

    /** @return array{accepted:bool,userId:string} */
    public function accept(string $rawToken, array $input, string $requestId = 'invitation-web'): array
    {
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $confirmation = is_string($input['passwordConfirmation'] ?? null) ? $input['passwordConfirmation'] : '';
        if (strlen($password) < 12 || strlen($password) > 255) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mật khẩu phải có từ 12 đến 255 ký tự.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Xác nhận mật khẩu không khớp.');
        }

        $inspection = $this->inspect($rawToken);
        if ($inspection['status'] !== 'pending') {
            throw new ApiException(410, 'INVITATION_NOT_ACTIVE', 'Lời mời đã hết hạn, bị thu hồi hoặc đã được sử dụng.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($passwordHash)) {
            throw new ApiException(500, 'INTERNAL_ERROR', 'Không thể thiết lập mật khẩu.');
        }
        $acceptedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $userId = $this->repository->acceptAccountInvitation($this->tokenHash($rawToken), $passwordHash, $acceptedAt, $requestId);
        return ['accepted' => true, 'userId' => $userId];
    }

    private function tokenHash(string $rawToken): string
    {
        $rawToken = trim($rawToken);
        if (preg_match('/\A[a-f0-9]{64}\z/', $rawToken) !== 1) {
            throw new ApiException(422, 'INVALID_INVITATION_TOKEN', 'Token lời mời không hợp lệ.');
        }
        return hash('sha256', $rawToken);
    }
}
