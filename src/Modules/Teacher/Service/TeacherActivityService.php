<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;

final class TeacherActivityService
{
    private const ALLOWED_STATUSES = ['draft', 'open', 'closed'];

    public function __construct(private readonly TeacherActivityRepository $repository) {}

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

    /** @param array{title:string,category:string,startAt:DateTimeImmutable,endAt:DateTimeImmutable,capacity:int,status:string} $input */
    public function create(string $teacherId, string $schoolId, array $input): void
    {
        $this->repository->create($teacherId, $schoolId, self::uuid(), $this->payload($input));
    }

    /** @param array{title:string,category:string,startAt:DateTimeImmutable,endAt:DateTimeImmutable,capacity:int,status:string} $input */
    public function update(string $teacherId, string $activityId, array $input): void
    {
        if (!$this->repository->update($teacherId, trim($activityId), $this->payload($input))) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.');
        }
    }

    public function setRegistrationStatus(string $teacherId, string $activityId, string $status): void
    {
        $status = trim($status);
        if (!in_array($status, ['open', 'closed'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái đăng ký không hợp lệ.');
        }

        if (!$this->repository->setRegistrationStatus($teacherId, trim($activityId), $status)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.');
        }
    }

    /**
     * @param array{title:string,category:string,startAt:DateTimeImmutable,endAt:DateTimeImmutable,capacity:int,status:string} $input
     * @return array{title:string,category:string,startAt:string,endAt:string,capacity:int,status:string}
     */
    private function payload(array $input): array
    {
        $title = trim($input['title']);
        $category = trim($input['category']);
        $capacity = $input['capacity'];
        $status = trim($input['status']);

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
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái hoạt động không hợp lệ.');
        }

        return [
            'title' => $title,
            'category' => $category,
            'startAt' => $input['startAt']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'endAt' => $input['endAt']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'capacity' => $capacity,
            'status' => $status,
        ];
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
