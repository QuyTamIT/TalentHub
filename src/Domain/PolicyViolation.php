<?php
declare(strict_types=1);

namespace TalentHub\Domain;

use TalentHub\Http\ApiException;

/**
 * Domain-level exception thrown by policies. Wraps the canonical error code
 * with optional structured details (e.g. locked field names, pending count)
 * so front-end can render contextual UI without re-deriving business rules.
 *
 * ApiException is `final`, so we cannot extend it; PolicyViolation simply
 * wraps it through a static factory and exposes its public surface.
 */
final class PolicyViolation
{
    /**
     * @param array<string, mixed> $details
     */
    public static function fromCode(
        string $code,
        string $message,
        array $details = [],
        int $status = 422,
    ): ApiException {
        return new ApiException($status, $code, $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function activityNotStarted(string $message = 'Hoạt động chưa đến thời gian bắt đầu.'): ApiException
    {
        return self::fromCode(ErrorCodes::ACTIVITY_NOT_STARTED, $message);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function activityNotEnded(string $message = 'Hoạt động chưa kết thúc.'): ApiException
    {
        return self::fromCode(ErrorCodes::ACTIVITY_NOT_ENDED, $message);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function pendingRegistrationsExist(int $count, string $message = 'Vẫn còn đăng ký đang chờ duyệt.'): ApiException
    {
        return self::fromCode(
            ErrorCodes::PENDING_REGISTRATIONS_EXIST,
            $message,
            ['pendingCount' => $count],
            409,
        );
    }

    /**
     * @param list<string> $lockedFields
     */
    public static function activityEditLocked(array $lockedFields, string $message = 'Một số trường đã bị khóa theo trạng thái hoạt động.'): ApiException
    {
        return self::fromCode(
            ErrorCodes::ACTIVITY_EDIT_LOCKED,
            $message,
            ['lockedFields' => $lockedFields],
            409,
        );
    }

    public static function registrationRejected(string $message = 'Đăng ký đã bị giáo viên từ chối, không thể đăng ký lại.'): ApiException
    {
        return self::fromCode(ErrorCodes::REGISTRATION_REJECTED, $message, [], 409);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function validation(string $message, array $details = []): ApiException
    {
        return self::fromCode(ErrorCodes::VALIDATION_FAILED, $message, $details);
    }
}
