<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\ActivityCommandRepository;
use TalentHub\Support\Uuid;

final class ActivityRegistrationService
{
    /** @var Closure():DateTimeImmutable */
    private readonly Closure $clock;

    public function __construct(ActivityCommandRepository $repository, ?callable $clock = null)
    {
        $this->repository = $repository;
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    private readonly ActivityCommandRepository $repository;

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function register(string $studentId, string $actorUserId, string $requestId, array $input): array
    {
        $this->assertAllowedFields($input, ['activityId']);
        $activityId = $this->requireUuid($input['activityId'] ?? null, 'activityId');

        return $this->repository->register(
            $this->requireUuid($studentId, 'studentId'),
            $this->requireUuid($actorUserId, 'actorUserId'),
            $requestId,
            $activityId,
            ($this->clock)()->setTimezone(new DateTimeZone('UTC')),
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function cancel(string $studentId, string $actorUserId, string $requestId, array $input): array
    {
        $this->assertAllowedFields($input, ['registrationId', 'reason']);
        $registrationId = $this->requireUuid($input['registrationId'] ?? null, 'registrationId');
        $reason = $this->optionalReason($input['reason'] ?? null);

        return $this->repository->cancel(
            $this->requireUuid($studentId, 'studentId'),
            $this->requireUuid($actorUserId, 'actorUserId'),
            $requestId,
            $registrationId,
            $reason,
            ($this->clock)()->setTimezone(new DateTimeZone('UTC')),
        );
    }

    /** @param array<string,mixed> $input @param list<string> $allowed */
    private function assertAllowedFields(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu đăng ký không hợp lệ.', [[
                    'field' => (string) $field,
                    'code' => 'FIELD_NOT_ALLOWED',
                    'message' => 'Không được phép gửi field này.',
                ]]);
            }
        }
    }

    private function requireUuid(mixed $value, string $field): string
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải có định dạng UUID hợp lệ.", [[
                'field' => $field,
                'code' => 'INVALID_FORMAT',
                'message' => "{$field} phải có định dạng UUID hợp lệ.",
            ]]);
        }
        return strtolower($value);
    }

    private function optionalReason(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Lý do hủy đăng ký không hợp lệ.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 500) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Lý do hủy đăng ký không được vượt quá 500 ký tự.');
        }
        return $value;
    }
}
