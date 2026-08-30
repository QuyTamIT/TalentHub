<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Support\Uuid;

final class TeacherActivityService
{
    private const ALLOWED_STATUSES = ['draft', 'published', 'ongoing', 'completed', 'archived'];

    /** @var array<string,string> */
    private const NEXT_STATUSES = [
        'draft' => 'published',
        'published' => 'ongoing',
        'ongoing' => 'completed',
        'completed' => 'archived',
    ];

    public function __construct(private readonly TeacherActivityRepository $repository) {}

    public function teacherIdForUser(string $userId): string
    {
        $teacherId = $this->repository->teacherIdForUser($this->requireUuid($userId, 'userId'));
        if ($teacherId === null) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Không tìm thấy hồ sơ giáo viên hợp lệ.');
        }
        return $teacherId;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $teacherId, string $search = ''): array
    {
        return $this->repository->list($teacherId, trim($search));
    }

    /** @return array<string,mixed>|null */
    public function find(string $teacherId, string $activityId): ?array
    {
        return $this->repository->find($teacherId, trim($activityId));
    }

    /** @return list<array<string,mixed>> */
    public function registrations(string $teacherId, string $activityId): array
    {
        return $this->repository->registrations($teacherId, trim($activityId));
    }

    /** @param array{title:string,category:string,startAt:DateTimeImmutable,endAt:DateTimeImmutable,capacity:int} $input */
    public function create(string $teacherId, string $schoolId, array $input): void
    {
        $this->repository->create($teacherId, $schoolId, self::uuid(), $this->payload($input));
    }

    /** @param array{title:string,category:string,startAt:DateTimeImmutable,endAt:DateTimeImmutable,capacity:int} $input */
    public function update(string $teacherId, string $activityId, array $input): void
    {
        if (!$this->repository->update($teacherId, trim($activityId), $this->payload($input))) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.');
        }
    }

    public function advanceStatus(string $teacherId, string $activityId): string
    {
        $activityId = trim($activityId);
        $activity = $this->repository->find($teacherId, $activityId);
        if ($activity === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.');
        }

        $currentStatus = strtolower(trim((string) ($activity['status'] ?? '')));
        if (!in_array($currentStatus, self::ALLOWED_STATUSES, true)) {
            throw new ApiException(422, 'INVALID_STATUS', 'Trạng thái hiện tại của hoạt động không hợp lệ.');
        }

        $nextStatus = self::NEXT_STATUSES[$currentStatus] ?? null;
        if ($nextStatus === null) {
            throw new ApiException(422, 'INVALID_TRANSITION', 'Hoạt động đã lưu trữ và không thể chuyển tiếp.');
        }

        if (!$this->repository->advanceStatus($teacherId, $activityId, $currentStatus, $nextStatus)) {
            throw new ApiException(409, 'STATUS_CONFLICT', 'Hoạt động đã thay đổi hoặc không còn thuộc giáo viên này.');
        }

        return $nextStatus;
    }

    /** @param array<string,mixed> $input @return array{id:string,activityId:string,status:string,updatedAt:string} */
    public function transitionRegistration(
        string $teacherId,
        string $actorUserId,
        string $requestId,
        string $activityId,
        string $registrationId,
        array $input,
    ): array {
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, ['expectedStatus', 'action'], true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu xử lý đăng ký không hợp lệ.');
            }
        }
        $expectedStatus = is_string($input['expectedStatus'] ?? null) ? trim($input['expectedStatus']) : '';
        $action = is_string($input['action'] ?? null) ? trim($input['action']) : '';
        if ($expectedStatus !== 'pending' || !in_array($action, ['approve', 'reject'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Chỉ có thể duyệt hoặc từ chối đăng ký đang chờ.');
        }

        return $this->repository->transitionRegistration(
            $this->requireUuid($teacherId, 'teacherId'),
            $this->requireUuid($actorUserId, 'actorUserId'),
            $requestId,
            $this->requireUuid($activityId, 'activityId'),
            $this->requireUuid($registrationId, 'registrationId'),
            $expectedStatus,
            $action === 'approve' ? 'approved' : 'rejected',
        );
    }

    /**
     * @param array{title:string,category:string,startAt:DateTimeImmutable,endAt:DateTimeImmutable,capacity:int} $input
     * @return array{title:string,category:string,startAt:string,endAt:string,capacity:int}
     */
    private function payload(array $input): array
    {
        $title = trim($input['title']);
        $category = trim($input['category']);
        $capacity = $input['capacity'];

        if ($title === '') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng nhập tên hoạt động.');
        }
        if ($category === '') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng nhập nhóm hoạt động.');
        }
        if ($capacity < 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Sức chứa phải là số nguyên lớn hơn 0.');
        }
        if ($input['endAt'] <= $input['startAt']) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian kết thúc phải sau thời gian bắt đầu.');
        }

        if (isset($input['registration_deadline']) && $input['registration_deadline'] > $input['startAt']) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian đăng ký phải kết thúc trước hoặc cùng lúc với thời gian bắt đầu sự kiện.');
        }

        if (isset($input['cancel_deadline']) && isset($input['registration_deadline']) && $input['cancel_deadline'] > $input['registration_deadline']) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian hủy vé phải trước hoặc cùng lúc với hạn chót đăng ký.');
        }

        return [
            'title' => $title,
            'category' => $category,
            'startAt' => $input['startAt']->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s'),
            'endAt' => $input['endAt']->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s'),
            'registration_deadline' => isset($input['registration_deadline']) ? $input['registration_deadline']->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s') : null,
            'cancel_deadline' => isset($input['cancel_deadline']) ? $input['cancel_deadline']->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s') : null,
            'capacity' => $capacity,
        ];
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function requireUuid(mixed $value, string $field): string
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải có định dạng UUID hợp lệ.");
        }
        return strtolower($value);
    }
}
