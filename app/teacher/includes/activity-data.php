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
        return new DateTimeImmutable($value, new DateTimeZone('Asia/Ho_Chi_Minh'));
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

    if ($rawStatus === 'draft') {
        $registrationLabel = 'Chưa mở đăng ký';
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
        'registration_deadline_input' => isset($row['registration_deadline']) && $row['registration_deadline'] 
            ? teacherActivitiesDate($row['registration_deadline'])->format('Y-m-d\TH:i') 
            : '',
        'cancel_deadline_input' => isset($row['cancel_deadline']) && $row['cancel_deadline'] 
            ? teacherActivitiesDate($row['cancel_deadline'])->format('Y-m-d\TH:i') 
            : '',
    ]);
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
