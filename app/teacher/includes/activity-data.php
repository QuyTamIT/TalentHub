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

    $draftStatuses = ['draft', 'pending', 'new', 'nhap', 'ban_nhap'];
    $endedStatuses = ['completed', 'ended', 'finished', 'cancelled', 'canceled', 'da_ket_thuc'];

    if (in_array($rawStatus, $draftStatuses, true)) {
        $statusKey = 'draft';
    } elseif (($endAt && $endAt < $now) || in_array($rawStatus, $endedStatuses, true)) {
        $statusKey = 'ended';
    } elseif ($startAt && $startAt > $now) {
        $statusKey = 'upcoming';
    } else {
        $statusKey = 'ongoing';
    }

    $registeredCount = (int) ($row['registered_count'] ?? 0);
    $capacity = (int) ($row['capacity'] ?? 0);
    $registrationClosedStatuses = array_merge($draftStatuses, $endedStatuses, ['closed', 'registration_closed', 'closed_registration']);
    $registrationOpen = !in_array($rawStatus, $registrationClosedStatuses, true)
        && (!$endAt || $endAt >= $now)
        && $capacity > 0
        && $registeredCount < $capacity;

    return array_merge($row, [
        'raw_status' => $rawStatus,
        'status_key' => $statusKey,
        'status_label' => [
            'upcoming' => 'Sắp diễn ra',
            'ongoing' => 'Đang diễn ra',
            'ended' => 'Đã kết thúc',
            'draft' => 'Bản nháp',
        ][$statusKey],
        'status_class' => [
            'upcoming' => 'info',
            'ongoing' => 'success',
            'ended' => 'muted',
            'draft' => 'warning',
        ][$statusKey],
        'registered_count' => $registeredCount,
        'capacity' => $capacity,
        'registration_open' => $registrationOpen,
        'registration_label' => $registrationOpen ? 'Đang mở' : 'Đã đóng',
        'start_label' => $startAt ? $startAt->format('d/m/Y H:i') : 'Chưa xác định',
        'end_label' => $endAt ? $endAt->format('d/m/Y H:i') : 'Chưa xác định',
        'start_input' => $startAt ? $startAt->format('Y-m-d\TH:i') : '',
        'end_input' => $endAt ? $endAt->format('Y-m-d\TH:i') : '',
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
