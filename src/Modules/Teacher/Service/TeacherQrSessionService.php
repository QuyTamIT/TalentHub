<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;

final class TeacherQrSessionService
{
    public const DEFAULT_DURATION_MINUTES = 15;
    public const MIN_DURATION_MINUTES = 1;
    public const MAX_DURATION_MINUTES = 120;
    public const DEFAULT_MAX_SCANS = 100;
    public const MIN_MAX_SCANS = 1;
    public const MAX_MAX_SCANS = 10000;
    public const DEFAULT_CONFIRMED_HOURS = '1.00';

    public function __construct(private readonly TeacherQrSessionRepository $repository) {}

    /** @return array{activities:list<array<string,mixed>>,sessions:list<array<string,mixed>>} */
    public function pageData(string $userId): array
    {
        $teacherId = $this->teacherId($userId);

        return [
            'activities' => $this->repository->listOngoingActivities($teacherId),
            'sessions' => array_map(
                fn (array $row): array => $this->presentSession($row),
                $this->repository->listSessions($teacherId)
            ),
            'managedCheckins' => array_map(
                fn (array $row): array => $this->presentManagedCheckin($row),
                $this->repository->listManagedCheckins($teacherId)
            ),
        ];
    }

    /** @return array{sessionId:string,rawToken:string} */
    public function create(string $userId, mixed $activityId, mixed $durationMinutes, mixed $maxScans, mixed $confirmedHours = self::DEFAULT_CONFIRMED_HOURS): array
    {
        $activityId = $this->validateUuid($activityId, 'activity_id', 'Mã hoạt động không hợp lệ.');
        $durationMinutes = $this->validateInteger(
            $durationMinutes,
            self::MIN_DURATION_MINUTES,
            self::MAX_DURATION_MINUTES,
            'Thời hạn phải là số nguyên từ 1 đến 120 phút.'
        );
        $maxScans = $this->validateInteger(
            $maxScans,
            self::MIN_MAX_SCANS,
            self::MAX_MAX_SCANS,
            'Số lượt quét phải là số nguyên từ 1 đến 10.000.'
        );
        $confirmedHours = $this->validateHours($confirmedHours);

        $teacherId = $this->teacherId($userId);
        $rawToken = $this->base64Url(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $sessionId = self::uuid();
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$durationMinutes} minutes")
            ->format('Y-m-d H:i:s.u');

        if (!$this->repository->createSession($teacherId, $activityId, $sessionId, $tokenHash, $expiresAt, $maxScans, $confirmedHours)) {
            throw new ApiException(422, 'INVALID_ACTIVITY', 'Chỉ có thể tạo QR cho hoạt động đang diễn ra do bạn quản lý.');
        }

        return ['sessionId' => $sessionId, 'rawToken' => $rawToken];
    }

    public function revoke(string $userId, mixed $sessionId): void
    {
        $sessionId = $this->validateUuid($sessionId, 'session_id', 'Mã phiên QR không hợp lệ.');

        if (!$this->repository->revokeSession($this->teacherId($userId), $sessionId)) {
            throw new ApiException(409, 'QR_SESSION_NOT_REVOCABLE', 'Phiên QR không còn hoạt động, đã hết hạn hoặc không thuộc giáo viên hiện tại.');
        }
    }

    private function teacherId(string $userId): string
    {
        $teacherId = $this->repository->findTeacherIdByUserId($userId);
        if ($teacherId === null || $teacherId === '') {
            throw new ApiException(404, 'TEACHER_PROFILE_NOT_FOUND', 'Không tìm thấy hồ sơ giáo viên của phiên đăng nhập.');
        }

        return $teacherId;
    }

    private function validateUuid(mixed $value, string $field, string $message): string
    {
        if (!is_string($value) || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $value) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', $message, [[
                'field' => $field,
                'code' => 'INVALID_UUID',
                'message' => $message,
            ]]);
        }

        return $value;
    }

    private function validateInteger(mixed $value, int $min, int $max, string $message): int
    {
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', $message);
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => $min,
                'max_range' => $max,
            ],
        ]);
        if ($validated === false) {
            throw new ApiException(422, 'VALIDATION_FAILED', $message);
        }

        return $validated;
    }

    private function validateHours(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Confirmed hours are invalid.');
        }

        $raw = trim((string) $value);
        if (preg_match('/\A\d{1,2}(?:\.\d{1,2})?\z/', $raw) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Confirmed hours must be between 0 and 24.');
        }

        $hours = (float) $raw;
        if ($hours < 0 || $hours > 24) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Confirmed hours must be between 0 and 24.');
        }

        return number_format($hours, 2, '.', '');
    }

    /** @param array<string,mixed> $row */
    private function presentSession(array $row): array
    {
        $expiresAt = $this->parseUtc((string) ($row['expiresAt'] ?? ''));
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($status === 'active' && $expiresAt !== null && $expiresAt <= $now) {
            $status = 'expired';
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'activityTitle' => (string) ($row['activityTitle'] ?? 'Hoạt động không tên'),
            'activityCategory' => (string) ($row['activityCategory'] ?? ''),
            'status' => in_array($status, ['active', 'expired', 'revoked'], true) ? $status : 'revoked',
            'expiresAt' => $expiresAt?->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i'),
            'expiresAtIso' => $expiresAt?->format(DateTimeImmutable::ATOM),
            'maxScans' => (int) ($row['maxScans'] ?? 0),
            'usedScans' => (int) ($row['usedScans'] ?? 0),
            'confirmedHours' => number_format((float) ($row['confirmedHours'] ?? 0), 2, '.', ''),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function presentManagedCheckin(array $row): array
    {
        return [
            'checkinId' => (string) ($row['checkinId'] ?? ''),
            'activityId' => (string) ($row['activityId'] ?? ''),
            'activityTitle' => (string) ($row['activityTitle'] ?? 'Hoạt động không tên'),
            'status' => (string) ($row['checkinStatus'] ?? ''),
            'checkedInAt' => (string) ($row['checkedInAt'] ?? ''),
            'confirmedHours' => number_format((float) ($row['confirmedHours'] ?? 0), 2, '.', ''),
            'experienceStatus' => (string) ($row['experienceStatus'] ?? ''),
        ];
    }

    private function parseUtc(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
