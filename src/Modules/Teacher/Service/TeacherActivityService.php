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

    /** @return list<array{id:string,name:string}> */
    public function responsibleTeachers(string $teacherId): array
    {
        return $this->repository->responsibleTeachers($this->requireUuid($teacherId, 'teacherId'));
    }

    /** @param array<string,mixed> $input */
    public function create(string $teacherId, string $schoolId, array $input): void
    {
        $this->repository->create($this->requireUuid($teacherId, 'teacherId'), $schoolId, self::uuid(), $this->payload($input));
    }

    /** @param array<string,mixed> $input */
    public function update(string $teacherId, string $activityId, array $input): void
    {
        $teacherId = $this->requireUuid($teacherId, 'teacherId');
        $activityId = $this->requireUuid($activityId, 'activityId');
        $existing = $this->repository->find($teacherId, $activityId);
        if ($existing === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.');
        }
        $this->repository->update(
            $teacherId,
            $activityId,
            $this->payload($this->mergeExisting($input, $existing)),
        );
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

        if (!$this->repository->advanceStatus($this->requireUuid($teacherId, 'teacherId'), $this->requireUuid($activityId, 'activityId'), $currentStatus, $nextStatus)) {
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

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function payload(array $input): array
    {
        $title = $this->stringValue($input['title'] ?? null, 'Tên hoạt động', 255, true);
        $category = $this->stringValue($input['category'] ?? null, 'Nhóm hoạt động', 100, true);
        $startAt = $this->dateValue($input['startAt'] ?? null, 'Thời gian bắt đầu');
        $endAt = $this->dateValue($input['endAt'] ?? null, 'Thời gian kết thúc');
        if ($endAt <= $startAt) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian kết thúc phải sau thời gian bắt đầu.');
        }

        $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
        if ($capacity === false || $capacity < 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Sức chứa phải là số nguyên lớn hơn 0.');
        }

        $displayCategory = $this->stringValue($input['displayCategory'] ?? $category, 'Nhóm hiển thị', 120, false) ?: $category;
        $filterCategory = $this->stringValue($input['filterCategory'] ?? $category, 'Nhóm lọc', 120, false) ?: $category;
        $deliveryMode = $this->stringValue($input['deliveryMode'] ?? 'in_person', 'Hình thức', 24, false) ?: 'in_person';
        if (!in_array($deliveryMode, ['in_person', 'online', 'hybrid'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Hình thức tổ chức không hợp lệ.');
        }

        $onlineMeetingUrl = $this->nullableString($input['onlineMeetingUrl'] ?? null, 'Link trực tuyến', 500);
        if ($onlineMeetingUrl !== null && (filter_var($onlineMeetingUrl, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($onlineMeetingUrl, PHP_URL_SCHEME)) !== 'https') ) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Link trực tuyến phải là URL HTTPS hợp lệ.');
        }
        if ($onlineMeetingUrl !== null && $deliveryMode === 'in_person') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Chỉ nhập link trực tuyến khi hình thức là trực tuyến hoặc kết hợp.');
        }

        $coverImageUrl = $this->nullableString($input['coverImageUrl'] ?? null, 'Ảnh bìa', 500);
        if ($coverImageUrl !== null && preg_match('#\A(?:/app/learner/)?assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg)\z#i', $coverImageUrl) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ảnh bìa phải là asset cục bộ hợp lệ của hoạt động.');
        }
        $coverImageAlt = $this->nullableString($input['coverImageAlt'] ?? null, 'Alt ảnh bìa', 255);
        if ($coverImageUrl !== null && $coverImageAlt === null) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Cần nhập alt text khi có ảnh bìa.');
        }

        $feeAmount = $input['feeAmount'] ?? '0';
        if (!is_int($feeAmount) && !is_float($feeAmount) && !is_string($feeAmount)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Chi phí không hợp lệ.');
        }
        $feeText = trim((string) $feeAmount);
        if (preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/', $feeText) !== 1 || (float) $feeText > 9999999999.99) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Chi phí phải là số không âm, tối đa 2 chữ số thập phân.');
        }

        $currency = strtoupper($this->stringValue($input['currency'] ?? 'VND', 'Đơn vị tiền tệ', 3, false) ?: 'VND');
        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Đơn vị tiền tệ phải gồm đúng 3 chữ cái.');
        }

        $approvalMode = $this->stringValue($input['approvalMode'] ?? 'automatic', 'Cách duyệt', 32, false) ?: 'automatic';
        if (!in_array($approvalMode, ['automatic', 'teacher_review'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Cách duyệt đăng ký không hợp lệ.');
        }

        $registrationOpensAt = $this->dateValue($input['registrationOpensAt'] ?? $startAt, 'Thời gian mở đăng ký');
        $registrationClosesAt = $this->dateValue($input['registrationClosesAt'] ?? $startAt, 'Thời gian đóng đăng ký');
        $cancellationClosesAt = $this->dateValue($input['cancellationClosesAt'] ?? $startAt, 'Thời gian đóng hủy đăng ký');
        if ($registrationOpensAt > $registrationClosesAt) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian mở đăng ký phải trước hoặc bằng thời gian đóng đăng ký.');
        }
        if ($registrationClosesAt >= $startAt) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian đóng đăng ký phải trước thời gian bắt đầu hoạt động.');
        }
        if ($cancellationClosesAt > $startAt) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời gian đóng hủy đăng ký không được sau thời gian bắt đầu hoạt động.');
        }

        $confirmedHours = $input['confirmedHours'] ?? '0';
        if (!is_int($confirmedHours) && !is_float($confirmedHours) && !is_string($confirmedHours)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Số giờ trải nghiệm không hợp lệ.');
        }
        $hoursText = trim((string) $confirmedHours);
        if (preg_match('/\A(?:\d{1,2})(?:\.\d{1,2})?\z/', $hoursText) !== 1 || (float) $hoursText > 24) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Số giờ trải nghiệm phải từ 0 đến 24, tối đa 2 chữ số thập phân.');
        }

        $responsibleTeacherId = $this->nullableUuid($input['responsibleTeacherId'] ?? null, 'responsibleTeacherId');
        return [
            'title' => $title,
            'category' => $category,
            'startAt' => $this->utc($startAt),
            'endAt' => $this->utc($endAt),
            'capacity' => $capacity,
            'responsibleTeacherId' => $responsibleTeacherId,
            'audienceScope' => 'school_only',
            'displayCategory' => $displayCategory,
            'filterCategory' => $filterCategory,
            'summary' => $this->stringValue($input['summary'] ?? null, 'Tóm tắt', 500, false),
            'description' => $this->stringValue($input['description'] ?? null, 'Mô tả', 65535, false),
            'experienceHighlights' => $this->textList($input['experienceHighlights'] ?? []),
            'skillTags' => $this->textList($input['skillTags'] ?? []),
            'eligibilityRules' => $this->textList($input['eligibilityRules'] ?? []),
            'benefitItems' => $this->textList($input['benefitItems'] ?? []),
            'locationName' => $this->stringValue($input['locationName'] ?? null, 'Tên địa điểm', 255, false),
            'locationAddress' => $this->nullableString($input['locationAddress'] ?? null, 'Địa chỉ', 500),
            'deliveryMode' => $deliveryMode,
            'onlineMeetingUrl' => $onlineMeetingUrl,
            'organizerName' => $this->stringValue($input['organizerName'] ?? null, 'Đơn vị tổ chức', 255, false),
            'organizerContact' => $this->nullableString($input['organizerContact'] ?? null, 'Liên hệ', 255),
            'organizerEmail' => $this->email($input['organizerEmail'] ?? null),
            'organizerPhone' => $this->phone($input['organizerPhone'] ?? null),
            'coverImageUrl' => $coverImageUrl,
            'coverImageAlt' => $coverImageAlt,
            'feeAmount' => number_format((float) $feeText, 2, '.', ''),
            'currency' => $currency,
            'targetAudience' => $this->stringValue($input['targetAudience'] ?? null, 'Đối tượng tham gia', 255, false),
            'certificateLabel' => $this->nullableString($input['certificateLabel'] ?? null, 'Nhãn chứng nhận', 255),
            'registrationOpensAt' => $this->utc($registrationOpensAt),
            'registrationClosesAt' => $this->utc($registrationClosesAt),
            'cancellationClosesAt' => $this->utc($cancellationClosesAt),
            'approvalMode' => $approvalMode,
            'confirmedHours' => number_format((float) $hoursText, 2, '.', ''),
        ];
    }

    private function stringValue(mixed $value, string $label, int $maxLength, bool $required): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            $value = '';
        }
        $value = trim((string) $value);
        if ($required && $value === '') throw new ApiException(422, 'VALIDATION_FAILED', "Vui lòng nhập {$label}.");
        if (mb_strlen($value) > $maxLength) throw new ApiException(422, 'VALIDATION_FAILED', "{$label} không được dài quá {$maxLength} ký tự.");
        return $value;
    }

    private function nullableString(mixed $value, string $label, int $maxLength): ?string
    {
        $value = $this->stringValue($value, $label, $maxLength, false);
        return $value === '' ? null : $value;
    }

    private function email(mixed $value): ?string
    {
        $value = $this->nullableString($value, 'Email', 255);
        if ($value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false) throw new ApiException(422, 'VALIDATION_FAILED', 'Email liên hệ không hợp lệ.');
        return $value;
    }

    private function phone(mixed $value): ?string
    {
        $value = $this->nullableString($value, 'Số điện thoại', 30);
        if ($value !== null && preg_match('/\A[0-9+().\-\s]{7,30}\z/', $value) !== 1) throw new ApiException(422, 'VALIDATION_FAILED', 'Số điện thoại liên hệ không hợp lệ.');
        return $value;
    }

    /** @return list<string> */
    private function textList(mixed $value): array
    {
        if (is_string($value)) $value = preg_split('/\R/u', $value) ?: [];
        if (!is_array($value) || !array_is_list($value)) throw new ApiException(422, 'VALIDATION_FAILED', 'Các mục danh sách phải có định dạng hợp lệ.');
        $result = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_numeric($item)) throw new ApiException(422, 'VALIDATION_FAILED', 'Các mục danh sách phải là văn bản.');
            $item = trim((string) $item);
            if ($item !== '') $result[] = $item;
        }
        if (count($result) > 50) throw new ApiException(422, 'VALIDATION_FAILED', 'Mỗi danh sách không được có quá 50 mục.');
        return $result;
    }

    private function dateValue(mixed $value, string $label): DateTimeImmutable
    {
        if (!$value instanceof DateTimeImmutable) throw new ApiException(422, 'VALIDATION_FAILED', "{$label} không hợp lệ.");
        return $value;
    }

    private function utc(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function nullableUuid(mixed $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) return null;
        return $this->requireUuid((string) $value, $field);
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $existing @return array<string,mixed> */
    private function mergeExisting(array $input, array $existing): array
    {
        $values = [
            'title' => $existing['title'] ?? '', 'category' => $existing['category'] ?? '',
            'startAt' => $this->storedDate($existing['startAt'] ?? null), 'endAt' => $this->storedDate($existing['endAt'] ?? null),
            'capacity' => $existing['capacity'] ?? 0, 'responsibleTeacherId' => $existing['responsibleTeacherId'] ?? null,
            'displayCategory' => $existing['displayCategory'] ?? '', 'filterCategory' => $existing['filterCategory'] ?? '',
            'summary' => $existing['summary'] ?? '', 'description' => $existing['description'] ?? '',
            'experienceHighlights' => $this->storedList($existing['experienceHighlights'] ?? null), 'skillTags' => $this->storedList($existing['skillTags'] ?? null),
            'eligibilityRules' => $this->storedList($existing['eligibilityRules'] ?? null), 'benefitItems' => $this->storedList($existing['benefitItems'] ?? null),
            'locationName' => $existing['locationName'] ?? '', 'locationAddress' => $existing['locationAddress'] ?? null,
            'deliveryMode' => $existing['deliveryMode'] ?? 'in_person', 'onlineMeetingUrl' => $existing['onlineMeetingUrl'] ?? null,
            'organizerName' => $existing['organizerName'] ?? '', 'organizerContact' => $existing['organizerContact'] ?? null,
            'organizerEmail' => $existing['organizerEmail'] ?? null, 'organizerPhone' => $existing['organizerPhone'] ?? null,
            'coverImageUrl' => $existing['coverImageUrl'] ?? null, 'coverImageAlt' => $existing['coverImageAlt'] ?? null,
            'feeAmount' => $existing['feeAmount'] ?? '0', 'currency' => $existing['currency'] ?? 'VND',
            'targetAudience' => $existing['targetAudience'] ?? '', 'certificateLabel' => $existing['certificateLabel'] ?? null,
            'registrationOpensAt' => $this->storedDate($existing['registrationOpensAt'] ?? null),
            'registrationClosesAt' => $this->storedDate($existing['registrationClosesAt'] ?? null),
            'cancellationClosesAt' => $this->storedDate($existing['cancellationClosesAt'] ?? null),
            'approvalMode' => $existing['approvalMode'] ?? 'automatic', 'confirmedHours' => $existing['confirmedHours'] ?? '0',
        ];
        foreach ($values as $field => $value) if (!array_key_exists($field, $input)) $input[$field] = $value;
        return $input;
    }

    private function storedDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') return null;
        try { return new DateTimeImmutable($value, new DateTimeZone('UTC')); } catch (\Throwable) { return null; }
    }

    /** @return list<string> */
    private function storedList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') return is_array($value) ? $value : [];
        try { $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR); } catch (\JsonException) { return []; }
        return is_array($decoded) && array_is_list($decoded) ? array_values($decoded) : [];
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
