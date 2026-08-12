<?php
/**
 * Teacher Dashboard - Read-only data provider
 *
 * This file stays inside the Teacher module and only runs SELECT queries.
 * It intentionally catches database errors so the dashboard never exposes PHP
 * warnings or SQL details to users.
 */

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

function teacherDashboardConnect(): ?PDO
{
    if (!extension_loaded('pdo_mysql')) {
        return null;
    }

    $host = getenv('TALENTHUB_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('TALENTHUB_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
    $database = getenv('TALENTHUB_DB_NAME') ?: getenv('DB_DATABASE') ?: 'talenthub';
    $username = getenv('TALENTHUB_DB_USER') ?: getenv('DB_USERNAME') ?: 'root';
    $password = getenv('TALENTHUB_DB_PASS') ?: getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

    try {
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $exception) {
        return null;
    }
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
    $pdo = teacherDashboardConnect();

    if (!$pdo) {
        return $data;
    }

    $data['dbStatus'] = [
        'connected' => true,
        'label' => 'Đọc từ database',
        'message' => 'Đã kết nối MySQL và chỉ sử dụng SELECT trong phạm vi Giáo viên.',
    ];

    $teacher = teacherDashboardRows($pdo, "
        SELECT
            tp.id AS teacher_id,
            tp.userId AS user_id,
            tp.schoolId AS school_id,
            tp.isSchoolAdmin,
            u.fullName,
            s.name AS school_name
        FROM teacher_profiles tp
        INNER JOIN users u ON u.id = tp.userId
        INNER JOIN schools s ON s.id = tp.schoolId
        ORDER BY u.createdAt ASC
        LIMIT 1
    ");

    if (empty($teacher)) {
        $data['dbStatus']['label'] = 'Chưa có hồ sơ giáo viên';
        $data['dbStatus']['message'] = 'Database kết nối thành công nhưng bảng teacher_profiles chưa có bản ghi phù hợp.';
        return $data;
    }

    $teacher = $teacher[0];
    $teacherName = trim((string) ($teacher['fullName'] ?? 'Giáo viên TalentHub'));

    $data['teacherInfo'] = [
        'id' => $teacher['teacher_id'],
        'user_id' => $teacher['user_id'],
        'full_name' => $teacherName !== '' ? $teacherName : 'Giáo viên TalentHub',
        'role_label' => !empty($teacher['isSchoolAdmin']) ? 'Giáo viên / Quản trị trường' : 'Giáo viên / Hướng dẫn viên',
        'school_name' => $teacher['school_name'] ?: 'Chưa kết nối trường',
        'avatar_initials' => teacherDashboardInitials($teacherName),
        'notification_count' => 0,
    ];

    $teacherId = (string) $teacher['teacher_id'];
    $schoolId = (string) $teacher['school_id'];
    $userId = (string) $teacher['user_id'];

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
          AND LOWER(status) IN ('open', 'active', 'published', 'ongoing', 'in_progress', 'dang_mo', 'mo')
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
          AND startAt >= NOW()
          AND startAt < DATE_ADD(NOW(), INTERVAL 7 DAY)
    ", ['teacherId' => $teacherId]) ?? 0);

    $data['metrics']['qr_tokens_expiring'] = (int) (teacherDashboardScalar($pdo, "
        SELECT COUNT(*)
        FROM activity_qr_tokens qt
        INNER JOIN activities a ON a.id = qt.activityId
        WHERE a.createdByTeacherId = :teacherId
          AND qt.expiresAt >= NOW()
          AND qt.expiresAt < DATE_ADD(NOW(), INTERVAL 1 DAY)
    ", ['teacherId' => $teacherId]) ?? 0);

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
