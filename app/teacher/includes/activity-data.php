<?php
/**
 * Teacher activities data helpers.
 * Uses the existing Teacher PDO connection and activity tables only.
 */

use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

function teacherActivitiesService(PDO $pdo): TeacherActivityService
{
    return new TeacherActivityService(new TeacherActivityRepository($pdo));
}

function teacherActivitiesDate(?string $value): ?DateTimeImmutable
{
    if (!$value) {
        return null;
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
    } catch (Throwable $exception) {
        return null;
    }
}

function teacherActivitiesNormalize(array $row, ?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $startAt = teacherActivitiesDate($row['startAt'] ?? null);
    $endAt = teacherActivitiesDate($row['endAt'] ?? null);
    $rawStatus = strtolower(trim((string) ($row['status'] ?? '')));
    $approvalStatus = strtolower(trim((string) ($row['approvalStatus'] ?? 'approved')));
    $approvalLabels = [
        'draft' => 'Chưa gửi duyệt', 'pending_school_review' => 'Chờ Nhà trường duyệt',
        'changes_requested' => 'Cần chỉnh sửa', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối',
    ];

    $statusLabels = [
        'draft' => 'Bản nháp',
        'published' => 'Đã công bố',
        'ongoing' => 'Đang diễn ra',
        'completed' => 'Đã hoàn tất',
        'archived' => 'Đã lưu trữ',
    ];
    $statusClasses = [
        'draft' => 'warning',
        'published' => 'info',
        'ongoing' => 'success',
        'completed' => 'muted',
        'archived' => 'muted',
    ];
    $statusKey = array_key_exists($rawStatus, $statusLabels) ? $rawStatus : 'unknown';

    $registeredCount = (int) ($row['registered_count'] ?? 0);
    $capacity = (int) ($row['capacity'] ?? 0);
    $registrationAvailable = false;
    $registrationLabel = 'Không xác định';
    $registrationOpensAt = teacherActivitiesDate($row['registrationOpensAt'] ?? null);
    $registrationClosesAt = teacherActivitiesDate($row['registrationClosesAt'] ?? null);

    if ($rawStatus === 'draft') {
        $registrationLabel = 'Bản nháp — cần công bố';
    } elseif ($rawStatus === 'published' && $startAt && $now >= $startAt) {
        $registrationLabel = $endAt && $now >= $endAt ? 'Đã kết thúc' : 'Đang diễn ra';
    } elseif ($rawStatus === 'published' && (!$registrationOpensAt || !$registrationClosesAt)) {
        $registrationLabel = 'Thiếu cấu hình đăng ký';
    } elseif ($rawStatus === 'published' && $registrationOpensAt && $now < $registrationOpensAt) {
        $registrationLabel = 'Chưa mở đăng ký';
    } elseif ($rawStatus === 'published' && $registrationClosesAt && $now >= $registrationClosesAt) {
        $registrationLabel = 'Đã hết hạn đăng ký';
    } elseif ($rawStatus === 'published' && $registeredCount >= $capacity) {
        $registrationLabel = 'Đã đủ chỗ';
    } elseif ($rawStatus === 'published') {
        $registrationAvailable = true;
        $registrationLabel = 'Đang nhận đăng ký';
    } elseif ($rawStatus === 'ongoing') {
        $registrationLabel = 'Đã đóng đăng ký';
    } elseif ($rawStatus === 'completed') {
        $registrationLabel = 'Đã kết thúc';
    } elseif ($rawStatus === 'archived') {
        $registrationLabel = 'Đã lưu trữ';
    }

    return array_merge($row, [
        'raw_status' => $rawStatus,
        'status_key' => $statusKey,
        'status_label' => $statusLabels[$statusKey] ?? 'Không xác định',
        'status_class' => $statusClasses[$statusKey] ?? 'muted',
        'registered_count' => $registeredCount,
        'capacity' => $capacity,
        'registration_available' => $registrationAvailable,
        'registration_label' => $registrationLabel,
        'start_label' => $startAt ? $startAt->format('d/m/Y H:i') : 'Chưa xác định',
        'end_label' => $endAt ? $endAt->format('d/m/Y H:i') : 'Chưa xác định',
        'start_input' => $startAt ? $startAt->format('Y-m-d\TH:i') : '',
        'end_input' => $endAt ? $endAt->format('Y-m-d\TH:i') : '',
        'registration_opens_input' => teacherActivitiesDate($row['registrationOpensAt'] ?? null)?->format('Y-m-d\TH:i') ?? '',
        'registration_closes_input' => teacherActivitiesDate($row['registrationClosesAt'] ?? null)?->format('Y-m-d\TH:i') ?? '',
        'cancellation_closes_input' => teacherActivitiesDate($row['cancellationClosesAt'] ?? null)?->format('Y-m-d\TH:i') ?? '',
        'experience_highlights_list' => teacherActivitiesJsonList($row['experienceHighlights'] ?? null),
        'skill_tags_list' => teacherActivitiesJsonList($row['skillTags'] ?? null),
        'eligibility_rules_list' => teacherActivitiesJsonList($row['eligibilityRules'] ?? null),
        'benefit_items_list' => teacherActivitiesJsonList($row['benefitItems'] ?? null),
        'location_label' => trim((string) ($row['locationName'] ?? '')) ?: 'Chưa cập nhật',
        'delivery_mode_label' => teacherActivitiesDeliveryMode((string) ($row['deliveryMode'] ?? '')),
        'policy_configured' => ($row['registrationOpensAt'] ?? null) !== null && ($row['registrationClosesAt'] ?? null) !== null && ($row['cancellationClosesAt'] ?? null) !== null,
        'approval_status' => $approvalStatus,
        'approval_status_label' => $approvalLabels[$approvalStatus] ?? 'Không xác định',
    ]);
}

/** @return list<string> */
function teacherActivitiesJsonList(mixed $value): array
{
    if (is_string($value)) {
        try { $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR); } catch (Throwable) { return []; }
    }
    if (!is_array($value) || !array_is_list($value)) return [];
    return array_values(array_filter(array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value), static fn (string $item): bool => $item !== ''));
}

function teacherActivitiesDeliveryMode(string $value): string
{
    return match (strtolower(trim($value))) {
        'in_person' => 'Trực tiếp',
        'online' => 'Trực tuyến',
        'hybrid' => 'Kết hợp',
        default => 'Chưa cập nhật',
    };
}

function teacherActivitiesRead(PDO $pdo, string $teacherId, string $search = ''): array
{
    try {
        $rows = teacherActivitiesService($pdo)->list($teacherId, $search);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));

        return array_map(static function (array $row) use ($now): array {
            return teacherActivitiesNormalize($row, $now);
        }, $rows);
    } catch (Throwable $exception) {
        return [];
    }
}

function teacherActivitiesFind(PDO $pdo, string $teacherId, string $activityId): ?array
{
    try {
        $row = teacherActivitiesService($pdo)->find($teacherId, $activityId);
        return is_array($row) ? teacherActivitiesNormalize($row) : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function teacherActivitiesSchoolId(PDO $pdo, string $teacherId): ?string
{
    $rows = teacherDashboardRows($pdo, "
        SELECT schoolId
        FROM teacher_profiles
        WHERE id = :teacherId
        LIMIT 1
    ", ['teacherId' => $teacherId]);

    return !empty($rows[0]['schoolId']) ? (string) $rows[0]['schoolId'] : null;
}

function teacherActivitiesRegistrations(PDO $pdo, string $teacherId, string $activityId): array
{
    try {
        return teacherActivitiesService($pdo)->registrations($teacherId, $activityId);
    } catch (Throwable $exception) {
        return [];
    }
}

function teacherActivitiesUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
