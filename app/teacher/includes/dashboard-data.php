<?php
/**
 * Teacher Dashboard - Read-only data provider
 *
 * This file stays inside the Teacher module and only runs SELECT queries.
 * It intentionally catches database errors so the dashboard never exposes PHP
 * warnings or SQL details to users.
 */

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherRepository;
use TalentHub\Modules\Teacher\Service\TeacherProfileService;
use TalentHub\Rbac\Service\PermissionService;

function teacherDashboardDefaults(): array
{
    return [
        'dbStatus' => [
            'connected' => false,
            'label' => 'Dữ liệu dự phòng',
            'message' => 'Chưa kết nối dữ liệu thực tế. Dashboard đang hiển thị trạng thái rỗng an toàn.',
        ],
        'teacherInfo' => [
            'id' => null,
            'user_id' => null,
            'full_name' => 'Giáo viên TalentHub',
            'role_label' => 'Giáo viên / Hướng dẫn viên',
            'school_name' => 'Chưa kết nối trường',
            'avatar_initials' => 'GV',
            'notification_count' => 0,
        ],
        'metrics' => [
            'total_students' => 0,
            'open_activities' => 0,
            'pending_assessments' => 0,
            'average_score' => null,
            'managed_activities' => 0,
            'registrations' => 0,
            'checkins' => 0,
            'experience_hours' => 0,
            'upcoming_activities' => 0,
            'pending_registrations' => 0,
            'qr_tokens_expiring' => 0,
        ],
        'recentActivities' => [],
    ];
}

function teacherDashboardBackendContext(): array
{
    static $context = null;

    if (is_array($context)) {
        return $context;
    }

    try {
        $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
        $session = new SessionManager(require dirname(__DIR__, 3) . '/config/session.php');
        $session->start();
        $user = $session->requireUser();

        if (($user['role'] ?? '') !== 'teacher') {
            $session->destroy();
            $target = app_href($_SERVER['REQUEST_URI'] ?? '/app/teacher/');
            $loginUrl = app_href('/login.php') . '?next=' . urlencode($target) . '&role_required=teacher';
            header('Location: ' . $loginUrl);
            exit;
        }

        (new PermissionService($pdo))->require($user['id'], 'teacher_dashboard.read_own');
        $profile = (new TeacherProfileService(new TeacherRepository($pdo)))->get($user['id']);

        $context = [
            'pdo' => $pdo,
            'session' => $session,
            'user' => $user,
            'profile' => $profile,
            'error' => null,
        ];
    } catch (Throwable $exception) {
        $context = [
            'pdo' => null,
            'session' => null,
            'user' => null,
            'profile' => null,
            'error' => $exception instanceof ApiException ? $exception->getMessage() : 'Chưa thể kết nối backend xác thực mới.',
        ];
    }

    return $context;
}

function teacherDashboardConnect(): ?PDO
{
    $context = teacherDashboardBackendContext();
    return $context['pdo'] instanceof PDO ? $context['pdo'] : null;
}

function teacherDashboardScalar(PDO $pdo, string $sql, array $params = []): int|float|null
{
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        if ($value === false || $value === null) {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function teacherDashboardRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function teacherDashboardReadData(): array
{
    $data = teacherDashboardDefaults();
    $context = teacherDashboardBackendContext();
    $pdo = $context['pdo'] instanceof PDO ? $context['pdo'] : null;

    if (!$pdo) {
        if (is_string($context['error']) && $context['error'] !== '') {
            $data['dbStatus']['label'] = 'Chưa sẵn sàng';
            $data['dbStatus']['message'] = $context['error'];
        }
        return $data;
    }

    $data['dbStatus'] = [
        'connected' => true,
        'label' => 'Đọc từ database',
        'message' => 'Đã kết nối MySQL và chỉ sử dụng SELECT trong phạm vi Giáo viên.',
    ];

    $profile = is_array($context['profile']) ? $context['profile'] : null;

    if (!$profile) {
        $data['dbStatus']['label'] = 'Chưa có hồ sơ giáo viên';
        $data['dbStatus']['message'] = 'Database kết nối thành công nhưng chưa tìm thấy hồ sơ giáo viên theo phiên đăng nhập.';
        return $data;
    }

    $teacherName = trim((string) ($profile['fullName'] ?? 'Giáo viên TalentHub'));
    $school = is_array($profile['school'] ?? null) ? $profile['school'] : [];

    $data['teacherInfo'] = [
        'id' => $profile['id'] ?? null,
        'user_id' => $profile['userId'] ?? null,
        'full_name' => $teacherName !== '' ? $teacherName : 'Giáo viên TalentHub',
        'role_label' => !empty($profile['isSchoolAdmin']) ? 'Giáo viên / Quản trị trường' : 'Giáo viên / Hướng dẫn viên',
        'school_name' => ($school['name'] ?? '') ?: 'Chưa kết nối trường',
        'avatar_initials' => teacherDashboardInitials($teacherName),
        'notification_count' => 0,
    ];

    $teacherId = (string) ($profile['id'] ?? '');
    $schoolId = (string) ($school['id'] ?? '');
    $userId = (string) ($profile['userId'] ?? '');

    $data['metrics']['total_students'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(DISTINCT sp.id)
        FROM student_profiles sp
        INNER JOIN classes c ON c.id = sp.classId
        WHERE c.schoolId = :schoolId
    ", ['schoolId' => $schoolId]) ?? 0);

    $data['metrics']['managed_activities'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM activities
        WHERE createdByTeacherId = :teacherId
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['open_activities'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM activities
        WHERE createdByTeacherId = :teacherId
          AND status IN ('published', 'ongoing')
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['pending_assessments'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM assessments
        WHERE teacherId = :teacherId
          AND LOWER(status) IN ('pending', 'draft', 'new', 'need_review', 'awaiting_review', 'cho_cham', 'chua_cham')
    ", ['teacherId' => $teacherId]) ?? 0);

    $averageScore = teacherDashboardScalar($pdo, "
        SELECT AVG(overallScore)
        FROM assessments
        WHERE teacherId = :teacherId
          AND LOWER(status) NOT IN ('pending', 'draft', 'new', 'need_review', 'awaiting_review', 'cho_cham', 'chua_cham')
    ", ['teacherId' => $teacherId]);
    $data['metrics']['average_score'] = $averageScore !== null ? round((float) $averageScore, 1) : null;

    $data['metrics']['registrations'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM activity_registrations ar
        INNER JOIN activities a ON a.id = ar.activityId
        WHERE a.createdByTeacherId = :teacherId
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['pending_registrations'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM activity_registrations ar
        INNER JOIN activities a ON a.id = ar.activityId
        WHERE a.createdByTeacherId = :teacherId
          AND LOWER(ar.status) IN ('pending', 'waiting', 'new', 'cho_duyet', 'cho_xac_nhan')
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['checkins'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM checkins c
        INNER JOIN activity_registrations ar ON ar.id = c.registrationId
        INNER JOIN activities a ON a.id = ar.activityId
        WHERE a.createdByTeacherId = :teacherId
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['experience_hours'] = (float) (teacherDashboardScalar($pdo, "
        SELECT COALESCE(SUM(el.hours), 0)
        FROM experience_logs el
        INNER JOIN activities a ON a.id = el.activityId
        WHERE a.createdByTeacherId = :teacherId
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['upcoming_activities'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM activities
        WHERE createdByTeacherId = :teacherId
          AND status = 'published'
          AND startAt >= NOW()
          AND startAt < DATE_ADD(NOW(), INTERVAL 7 DAY)
    ", ['teacherId' => $teacherId]) ?? 0);

    // QR tables are not part of the current migration contract.
    $data['metrics']['qr_tokens_expiring'] = 0;

    $data['teacherInfo']['notification_count'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM notifications
        WHERE userId = :userId
          AND isRead = FALSE
    ", ['userId' => $userId]) ?? 0);

    $data['recentActivities'] = teacherDashboardRecentActivities($pdo, $teacherId);

    return $data;
}

function teacherDashboardRecentActivities(PDO $pdo, string $teacherId): array
{
    $activities = [];

    $checkins = teacherDashboardRows($pdo, "
        SELECT
            u.fullName AS student_name,
            a.title AS activity_title,
            c.checkedInAt AS happened_at
        FROM checkins c
        INNER JOIN activity_registrations ar ON ar.id = c.registrationId
        INNER JOIN activities a ON a.id = ar.activityId
        INNER JOIN student_profiles sp ON sp.id = ar.studentId
        INNER JOIN users u ON u.id = sp.userId
        WHERE a.createdByTeacherId = :teacherId
        ORDER BY c.checkedInAt DESC
        LIMIT 5
    ", ['teacherId' => $teacherId]);

    foreach ($checkins as $row) {
        $activities[] = [
            'type' => 'checkin',
            'icon' => 'qr',
            'title' => sprintf('%s đã check-in hoạt động %s', $row['student_name'] ?: 'Học viên', $row['activity_title'] ?: 'chưa đặt tên'),
            'meta' => 'Điểm danh QR',
            'time' => teacherDashboardRelativeTime($row['happened_at'] ?? null),
            'sort_time' => strtotime((string) ($row['happened_at'] ?? '')) ?: 0,
        ];
    }

    $upcoming = teacherDashboardRows($pdo, "
        SELECT title, startAt, status
        FROM activities
        WHERE createdByTeacherId = :teacherId
          AND startAt >= NOW()
        ORDER BY startAt ASC
        LIMIT 5
    ", ['teacherId' => $teacherId]);

    foreach ($upcoming as $row) {
        $activities[] = [
            'type' => 'activity',
            'icon' => 'trophy',
            'title' => sprintf('Hoạt động %s sắp diễn ra', $row['title'] ?: 'chưa đặt tên'),
            'meta' => 'Trạng thái: ' . ($row['status'] ?: 'chưa rõ'),
            'time' => teacherDashboardRelativeTime($row['startAt'] ?? null),
            'sort_time' => strtotime((string) ($row['startAt'] ?? '')) ?: 0,
        ];
    }

    usort($activities, static function (array $left, array $right): int {
        return $right['sort_time'] <=> $left['sort_time'];
    });

    return array_slice($activities, 0, 6);
}

function teacherDashboardInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'GV';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $first = $parts[0] ?? '';
    $last = $parts[count($parts) - 1] ?? '';

    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: 'GV';
}

function teacherDashboardRelativeTime(?string $datetime): string
{
    if (!$datetime) {
        return 'Chưa rõ thời gian';
    }

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return 'Chưa rõ thời gian';
    }

    $diff = $timestamp - time();
    $absolute = abs($diff);

    if ($absolute < 60) {
        return $diff >= 0 ? 'Sắp diễn ra' : 'Vừa xong';
    }

    if ($absolute < 3600) {
        $minutes = (int) floor($absolute / 60);
        return $diff >= 0 ? "Sau {$minutes} phút" : "{$minutes} phút trước";
    }

    if ($absolute < 86400) {
        $hours = (int) floor($absolute / 3600);
        return $diff >= 0 ? "Sau {$hours} giờ" : "{$hours} giờ trước";
    }

    return date('d/m/Y H:i', $timestamp);
}

function teacherDashboardPercent(float|int $value, float|int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return max(0, min(100, (int) round(($value / $total) * 100)));
}
