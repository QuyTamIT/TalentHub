<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Support\Uuid;

date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Xác thực Giảng viên
$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/grading.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 2) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

$config = require dirname(__DIR__, 2) . '/config/database.php';
$pdo = (new Connection($config))->connect();

// 2. Đảm bảo đúng 4 tiêu chí cốt lõi (0 - 100 điểm, không đổi trọng số 40/20/20/20)
if (!function_exists('ensureAssessmentCriteria')) {
    function ensureAssessmentCriteria(PDO $pdo): array
    {
        $needed = [
            [
                'code' => 'chuyen_mon',
                'name' => 'Chuyên môn',
                'description' => 'Kiến thức chuyên môn và kỹ năng thực hành',
                'minScore' => 0.00,
                'maxScore' => 100.00,
                'displayOrder' => 1,
            ],
            [
                'code' => 'sang_tao',
                'name' => 'Sáng tạo',
                'description' => 'Tư duy đổi mới và khả năng sáng tạo giải pháp',
                'minScore' => 0.00,
                'maxScore' => 100.00,
                'displayOrder' => 2,
            ],
            [
                'code' => 'ky_luat',
                'name' => 'Kỷ luật',
                'description' => 'Tinh thần kỷ luật, tính chuyên cần và trách nhiệm',
                'minScore' => 0.00,
                'maxScore' => 100.00,
                'displayOrder' => 3,
            ],
            [
                'code' => 'lam_viec_nhom',
                'name' => 'Làm việc nhóm',
                'description' => 'Khả năng giao tiếp, hợp tác và phối hợp đội nhóm',
                'minScore' => 0.00,
                'maxScore' => 100.00,
                'displayOrder' => 4,
            ],
        ];

        try {
            foreach ($needed as $crit) {
                $stmt = $pdo->prepare("SELECT id FROM assessment_criteria WHERE code = ? LIMIT 1");
                $stmt->execute([$crit['code']]);
                $existingId = $stmt->fetchColumn();

                if (!$existingId) {
                    $newId = Uuid::v4();
                    $ins = $pdo->prepare("INSERT INTO assessment_criteria (id, code, name, description, minScore, maxScore, displayOrder, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                    $ins->execute([$newId, $crit['code'], $crit['name'], $crit['description'], $crit['minScore'], $crit['maxScore'], $crit['displayOrder']]);
                } else {
                    $upd = $pdo->prepare("UPDATE assessment_criteria SET name = ?, description = ?, minScore = ?, maxScore = ?, displayOrder = ?, status = 'active' WHERE id = ?");
                    $upd->execute([$crit['name'], $crit['description'], $crit['minScore'], $crit['maxScore'], $crit['displayOrder'], $existingId]);
                }
            }

            $pdo->exec("UPDATE assessment_criteria SET status = 'inactive' WHERE code NOT IN ('chuyen_mon', 'sang_tao', 'ky_luat', 'lam_viec_nhom')");
        } catch (\Throwable $e) {}

        $stmt = $pdo->prepare("SELECT id, code, name, description, minScore, maxScore, displayOrder FROM assessment_criteria WHERE status = 'active' ORDER BY displayOrder ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$activeCriteria = ensureAssessmentCriteria($pdo);

// 3. Lấy thông tin giảng viên đang đăng nhập
$teacherProfile = null;
$teacherSchoolId = '';
$teacherSchoolName = '';
$teacherId = '';
try {
    $tStmt = $pdo->prepare("
        SELECT tp.id, tp.userId, tp.schoolId, s.name as schoolName
        FROM teacher_profiles tp
        LEFT JOIN schools s ON s.id = tp.schoolId
        WHERE tp.userId = :uid
        LIMIT 1
    ");
    $tStmt->execute(['uid' => (string) $user['id']]);
    $teacherProfile = $tStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($teacherProfile) {
        $teacherId = (string) ($teacherProfile['id'] ?? '');
        $teacherSchoolId = (string) ($teacherProfile['schoolId'] ?? '');
        $teacherSchoolName = (string) ($teacherProfile['schoolName'] ?? '');
    }
} catch (\Throwable $e) {}

// Helper định dạng thời gian tương đối theo slide ("Hôm nay", "Hôm qua", ngày tháng)
if (!function_exists('formatRelativeDate')) {
    function formatRelativeDate(?string $datetime): string {
        if (!$datetime) {
            return 'Hôm nay';
        }
        $time = strtotime($datetime);
        if (!$time) {
            return 'Hôm nay';
        }
        $todayMidnight = strtotime('today midnight');
        $yesterdayMidnight = strtotime('yesterday midnight');
        if ($time >= $todayMidnight) {
            return 'Hôm nay';
        } elseif ($time >= $yesterdayMidnight) {
            return 'Hôm qua';
        }
        return date('d/m/Y', $time);
    }
}

// Helper xác định trạng thái đánh giá chính xác dựa trên dữ liệu thực tế:
// - 'published': Đã gửi đánh giá chính thức
// - 'draft': Bản nháp thực sự (phải có điểm hoặc nhận xét đã lưu)
// - 'unassessed': Chưa đánh giá (chưa có bản ghi hoặc bản ghi rỗng)
if (!function_exists('resolveEvaluationStatus')) {
    function resolveEvaluationStatus(?string $rawStatus, ?float $overallScore, int $scoreCount, ?string $comment): string {
        if ($rawStatus === 'published') {
            return 'published';
        }
        if ($rawStatus === 'draft') {
            $hasScore = ($overallScore !== null) || ($scoreCount > 0);
            $hasComment = trim((string) $comment) !== '';
            if ($hasScore || $hasComment) {
                return 'draft';
            }
        }
        return 'unassessed';
    }
}

// 4. Xử lý Lưu Đánh giá (AJAX hoặc Form POST)
$flash = $_SESSION['teacherGradingFlash'] ?? null;
unset($_SESSION['teacherGradingFlash']);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf($_POST['csrfToken'] ?? null);
    $action = $_POST['action'] ?? 'save_draft'; // 'save_draft' | 'submit_assessment'
    $status = ($action === 'submit_assessment') ? 'published' : 'draft';

    $studentId = trim((string) ($_POST['studentId'] ?? ''));
    $classId = trim((string) ($_POST['classId'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $scoresInput = $_POST['criteria'] ?? [];

    $criteriaScores = [];
    $validCount = 0;
    $sumScore = 0.0;

    foreach ($activeCriteria as $crit) {
        $cid = (string) $crit['id'];
        $rawVal = isset($scoresInput[$cid]) ? trim((string) $scoresInput[$cid]) : '';
        if ($rawVal !== '' && is_numeric($rawVal)) {
            $num = max(0.0, min(100.0, (float) $rawVal));
            $criteriaScores[$cid] = $num;
            $sumScore += $num;
            $validCount++;
        }
    }

    if (!empty($studentId) && !empty($classId)) {
        $overallScore = $validCount > 0 ? round($sumScore / $validCount, 2) : null;
        if (isset($_POST['overallScore']) && trim((string) $_POST['overallScore']) !== '') {
            $overrideScore = max(0.0, min(100.0, (float) $_POST['overallScore']));
            $overallScore = round($overrideScore, 2);
        }

        try {
            $pdo->beginTransaction();

            $chkStmt = $pdo->prepare("SELECT id, version, status FROM assessments WHERE teacherId = ? AND studentId = ? AND classId = ? FOR UPDATE");
            $chkStmt->execute([$teacherId, $studentId, $classId]);
            $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

            $assessmentId = '';
            $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

            if ($existing) {
                $assessmentId = (string) $existing['id'];
                $updStmt = $pdo->prepare("
                    UPDATE assessments
                    SET overallScore = ?, comment = ?, status = ?, publishedAt = ?, version = version + 1, updatedAt = NOW()
                    WHERE id = ?
                ");
                $updStmt->execute([$overallScore, $comment, $status, $publishedAt, $assessmentId]);
            } else {
                $assessmentId = Uuid::v4();
                $insStmt = $pdo->prepare("
                    INSERT INTO assessments
                        (id, teacherId, studentId, classId, activityId, projectId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt)
                    VALUES
                        (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, 1, NOW(), NOW())
                ");
                $insStmt->execute([$assessmentId, $teacherId, $studentId, $classId, $overallScore, $comment, $status, $publishedAt]);
            }

            // Lưu điểm từng tiêu chí vào assessment_scores
            $delScores = $pdo->prepare("DELETE FROM assessment_scores WHERE assessmentId = ?");
            $delScores->execute([$assessmentId]);

            $insScore = $pdo->prepare("INSERT INTO assessment_scores (id, assessmentId, criteriaId, score, createdAt, updatedAt) VALUES (?, ?, ?, ?, NOW(), NOW())");
            foreach ($criteriaScores as $cid => $scVal) {
                $insScore->execute([Uuid::v4(), $assessmentId, $cid, $scVal]);
            }

            // Nếu gửi đánh giá (published) thì đồng bộ điểm vào student_profiles
            if ($status === 'published' && $overallScore !== null) {
                $updStudent = $pdo->prepare("UPDATE student_profiles SET talentScore = ?, updatedAt = NOW() WHERE id = ?");
                $updStudent->execute([$overallScore, $studentId]);
            }

            $pdo->commit();

            $stName = 'Học viên';
            try {
                $stNameStmt = $pdo->prepare("SELECT u.fullName FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE sp.id = ?");
                $stNameStmt->execute([$studentId]);
                $stName = $stNameStmt->fetchColumn() ?: 'Học viên';
            } catch (\Throwable $e) {}

            $statusText = ($status === 'published') ? 'Đã gửi đánh giá chính thức' : 'Đã lưu bản nháp';
            $msg = "{$statusText} cho học viên {$stName} thành công" . ($overallScore !== null ? " (Điểm: {$overallScore})" : "") . ".";

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'status' => $status,
                    'overallScore' => $overallScore !== null ? number_format($overallScore, 1) : null,
                    'studentId' => $studentId,
                    'assessmentId' => $assessmentId,
                ]);
                exit;
            }

            $_SESSION['teacherGradingFlash'] = $msg;
            $classQuery = isset($_POST['className']) ? '&class=' . urlencode((string)$_POST['className']) : '';
            header('Location: ' . app_href('/app/teacher/grading.php') . '?student_id=' . urlencode($studentId) . $classQuery);
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Lỗi lưu dữ liệu: ' . $e->getMessage()]);
                exit;
            }
            $_SESSION['teacherGradingFlash'] = 'Lỗi lưu đánh giá: ' . $e->getMessage();
        }
    }
}

// 5. Đọc danh sách lớp học theo trường của giáo viên
$classList = [];
if ($teacherSchoolId !== '') {
    try {
        $classStmt = $pdo->prepare("
            SELECT c.id, c.name, COUNT(sp.id) AS studentCount
            FROM classes c
            LEFT JOIN student_profiles sp ON sp.classId = c.id AND sp.studyStatus = 'active'
            WHERE c.schoolId = :schoolId
              AND c.status = 'active'
            GROUP BY c.id, c.name
            ORDER BY c.name ASC
        ");
        $classStmt->execute(['schoolId' => $teacherSchoolId]);
        $classList = $classStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $classList = [];
    }
}

$requestedClass = trim((string) ($_GET['class'] ?? ($_GET['class_id'] ?? '')));
$selectedClass = null;

foreach ($classList as $c) {
    if ($requestedClass !== '' && ($c['id'] === $requestedClass || $c['name'] === $requestedClass)) {
        $selectedClass = $c;
        break;
    }
}
if ($selectedClass === null && !empty($classList)) {
    $selectedClass = $classList[0];
}

$activeClassId = (string) ($selectedClass['id'] ?? '');
$activeClassName = (string) ($selectedClass['name'] ?? '');

// 6. Đọc danh sách bài đánh giá trong HÀNG CHỜ từ dữ liệu thật
$students = [];
if ($activeClassId !== '') {
    try {
        $stStmt = $pdo->prepare("
            SELECT sp.id AS studentId, sp.userId, u.fullName, u.email, sp.phone, sp.createdAt AS studentCreatedAt,
                   COALESCE(c.name, :activeClassName) AS className,
                   c.id AS classId,
                   sp.talentScore,
                   a.id AS assessmentId, a.overallScore, a.comment, a.status AS assessmentStatus,
                   a.publishedAt, a.version AS assessmentVersion, a.updatedAt AS assessmentUpdatedAt,
                   a.createdAt AS assessmentCreatedAt,
                   (
                       SELECT COUNT(sc.id) 
                       FROM assessment_scores sc 
                       WHERE sc.assessmentId = a.id
                   ) AS scoreCount,
                   (
                       SELECT act.title
                       FROM activity_registrations ar
                       JOIN activities act ON act.id = ar.activityId
                       WHERE ar.studentId = sp.id
                       ORDER BY ar.registeredAt DESC
                       LIMIT 1
                   ) AS latestActivityTitle,
                   (
                       SELECT ar.registeredAt
                       FROM activity_registrations ar
                       WHERE ar.studentId = sp.id
                       ORDER BY ar.registeredAt DESC
                       LIMIT 1
                   ) AS latestActivityRegisteredAt
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN assessments a ON a.studentId = sp.id AND a.teacherId = :teacherId AND a.classId = :activeClassIdAssessment
            WHERE sp.studyStatus = 'active'
              AND sp.classId = :activeClassIdStudent
            ORDER BY u.fullName ASC
        ");
        $stStmt->execute([
            'activeClassIdAssessment' => $activeClassId,
            'activeClassIdStudent' => $activeClassId,
            'activeClassName' => $activeClassName,
            'teacherId' => $teacherId,
        ]);
        $students = $stStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $students = [];
    }
}

// Tính số lượng bài đang chờ từ dữ liệu thật (chưa hoàn tất đánh giá chính thức)
$waitingCount = 0;
foreach ($students as $st) {
    $itemStatus = resolveEvaluationStatus(
        $st['assessmentStatus'] ?? null,
        $st['overallScore'] !== null ? (float)$st['overallScore'] : null,
        (int)($st['scoreCount'] ?? 0),
        $st['comment'] ?? null
    );
    if ($itemStatus !== 'published') {
        $waitingCount++;
    }
}

// 7. Xác định bài đánh giá / học viên đang được chọn trong khu vực ĐANG CHẤM
$requestedStudentId = trim((string) ($_GET['student_id'] ?? ($_GET['student'] ?? '')));
$selectedStudent = null;

if (!empty($students)) {
    if ($requestedStudentId !== '') {
        foreach ($students as $st) {
            if ($st['studentId'] === $requestedStudentId) {
                $selectedStudent = $st;
                break;
            }
        }
    }
    if ($selectedStudent === null) {
        $selectedStudent = $students[0];
    }
}

// 8. Đọc thông tin chi tiết cho học viên ĐANG CHẤM (Điểm tiêu chí & Hoạt động thực tế)
$studentSavedScores = [];
$studentActivities = [];

if ($selectedStudent) {
    $stId = $selectedStudent['studentId'];
    $assId = $selectedStudent['assessmentId'] ?? '';

    // Lấy điểm các tiêu chí đã lưu trong database nếu có
    if (!empty($assId)) {
        try {
            $scStmt = $pdo->prepare("
                SELECT sc.criteriaId, sc.score, ac.code, ac.name
                FROM assessment_scores sc
                JOIN assessment_criteria ac ON ac.id = sc.criteriaId
                WHERE sc.assessmentId = ?
            ");
            $scStmt->execute([$assId]);
            $rawScores = $scStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawScores as $r) {
                $studentSavedScores[$r['criteriaId']] = (float) $r['score'];
            }
        } catch (\Throwable $e) {}
    }

    // Lấy dữ liệu hoạt động thực tế từ database
    try {
        $actStmt = $pdo->prepare("
            SELECT a.id, a.title, a.category, a.startAt, a.endAt, a.status AS activityStatus,
                   ar.status AS registrationStatus, ar.registeredAt
            FROM activity_registrations ar
            JOIN activities a ON a.id = ar.activityId
            WHERE ar.studentId = ?
            ORDER BY ar.registeredAt DESC
        ");
        $actStmt->execute([$stId]);
        $studentActivities = $actStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $studentActivities = [];
    }
}

$pageTitle = 'Chấm điểm - TalentHub';
$currentRoute = 'assessments';

$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'activities/', 'href' => '/app/teacher/activities/index.php', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'href' => '/app/teacher/grading.php', 'icon' => 'clipboard-check', 'active' => true],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => false],
];

$rawName = $_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? ''));
$teacherName = trim((string) ($rawName !== '' ? $rawName : ($user['fullName'] ?? 'Giáo viên')));
$teacherInfo = [
    'full_name' => $teacherName !== '' ? $teacherName : 'Giáo viên',
    'role_label' => 'Giáo viên / Hướng dẫn viên',
    'school_name' => $teacherSchoolName,
    'avatar_initials' => 'GV',
    'notification_count' => 0,
];

if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $words = preg_split('/\s+/', trim($name));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
        }
        return mb_strtoupper(mb_substr($name, 0, 2)) ?: 'HV';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chấm điểm | TalentHub</title>

    <link rel="stylesheet" href="<?= app_href('/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/brand-component.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/polish.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/teacher.css'); ?>">
    <style>
        /* 1. Header tinh giản theo Slide */
        .grading-header-section {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .grading-main-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.25rem 0;
            letter-spacing: -0.01em;
        }
        .grading-subtitle {
            font-size: 0.92rem;
            color: #64748B;
            margin: 0;
            font-weight: 500;
        }

        /* 2. Bố cục chính theo Slide: HÀNG CHỜ (trái) + ĐANG CHẤM (phải) */
        .grading-layout-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 992px) {
            .grading-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* KHU VỰC BÊN TRÁI — HÀNG CHỜ */
        .queue-box {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }
        .queue-box-header {
            padding: 0.9rem 1.15rem;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .queue-box-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .queue-box-badge {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748B;
            background: #E2E8F0;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
        }
        .queue-items-list {
            max-height: 620px;
            overflow-y: auto;
        }
        .queue-card-item {
            display: block;
            padding: 0.9rem 1.15rem;
            border-bottom: 1px solid #F1F5F9;
            text-decoration: none;
            color: inherit;
            transition: background-color 0.12s ease;
            border-left: 3px solid transparent;
        }
        .queue-card-item:hover {
            background: #FFF7ED;
        }
        .queue-card-item.is-selected {
            background: #FFF7ED;
            border-left-color: #F97316;
        }
        .queue-card-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.2rem;
        }
        .queue-card-activity {
            font-size: 0.82rem;
            color: #475569;
            margin-bottom: 0.35rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .queue-card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
        }
        .queue-card-time {
            color: #94A3B8;
            font-weight: 500;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-badge--unassessed {
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #E2E8F0;
        }
        .status-badge--draft {
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid #FDE68A;
        }
        .status-badge--published {
            background: #DCFCE7;
            color: #15803D;
            border: 1px solid #BBF7D0;
        }

        /* KHU VỰC BÊN PHẢI — ĐANG CHẤM */
        .eval-workspace-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            padding: 1.5rem;
        }

        /* Thông tin học viên đang chấm */
        .eval-student-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1.15rem;
            border-bottom: 1px solid #F1F5F9;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .eval-student-left {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .eval-student-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #FFF7ED;
            color: #EA580C;
            border: 1.5px solid #FED7AA;
            font-weight: 800;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .eval-student-name {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.15rem 0;
        }
        .eval-student-meta {
            font-size: 0.82rem;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .eval-student-meta strong {
            color: #334155;
            font-weight: 600;
        }
        .eval-meta-sep {
            color: #CBD5E1;
        }

        /* 4 TIÊU CHÍ (Thanh kéo nhỏ gọn, hiện đại 0–100) */
        .criteria-container {
            margin-bottom: 1.25rem;
        }
        .criteria-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .criteria-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .criteria-scale-note {
            font-size: 0.75rem;
            color: #64748B;
            font-weight: 600;
        }

        /* Lưới 2x2 cân đối, gọn nhẹ trên desktop, 1 cột trên mobile */
        .criteria-sliders-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        @media (max-width: 640px) {
            .criteria-sliders-grid {
                grid-template-columns: 1fr;
            }
        }

        .criterion-slider-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 0.7rem 0.95rem;
            transition: all 0.15s ease;
        }
        .criterion-slider-card:focus-within,
        .criterion-slider-card:hover {
            border-color: #FED7AA;
            background: #FFFFFF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .criterion-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.45rem;
        }
        .criterion-card-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1E293B;
        }
        .criterion-score-badge {
            user-select: none;
            white-space: nowrap;
            font-size: 0.82rem;
            color: #64748B;
        }
        .criterion-score-val {
            font-size: 1.05rem;
            font-weight: 800;
            color: #EA580C;
            transition: color 0.12s ease;
        }
        .criterion-score-val.is-unset {
            color: #94A3B8;
            font-weight: 600;
        }
        .criterion-score-max {
            font-size: 0.78rem;
            color: #94A3B8;
            font-weight: 600;
        }

        /* Range input styling: thanh kéo nhỏ gọn, mượt mà */
        .criterion-slider-track {
            display: flex;
            align-items: center;
        }
        .criterion-range-slider {
            width: 100%;
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            border-radius: 3px;
            background: #E2E8F0;
            outline: none;
            transition: background 0.12s ease;
            cursor: pointer;
            margin: 0;
        }
        .criterion-range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #F97316;
            cursor: pointer;
            border: 2px solid #FFFFFF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.22);
            transition: transform 0.12s ease, background 0.12s ease;
        }
        .criterion-range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            background: #EA580C;
        }
        .criterion-range-slider::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #F97316;
            cursor: pointer;
            border: 2px solid #FFFFFF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.22);
        }
        .criterion-range-slider[data-has-value="0"]::-webkit-slider-thumb {
            background: #94A3B8;
        }
        .criterion-range-slider[data-has-value="0"]::-moz-range-thumb {
            background: #94A3B8;
        }

        /* 5. ĐIỂM TỔNG KẾT */
        .overall-score-indicator {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFF7ED;
            border: 1px solid #FFEDD5;
            padding: 0.75rem 1.15rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }
        .overall-score-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #9A3412;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .overall-score-display {
            font-size: 1.35rem;
            font-weight: 800;
            color: #EA580C;
            white-space: nowrap;
        }
        .overall-score-unit {
            font-size: 0.85rem;
            font-weight: 600;
            color: #C2410C;
        }

        /* 6. NHẬN XÉT */
        .comment-section {
            margin-bottom: 1.25rem;
        }
        .comment-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.45rem;
        }
        .comment-textarea {
            width: 100%;
            min-height: 85px;
            padding: 0.65rem 0.8rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 8px;
            font-size: 0.88rem;
            color: #0F172A;
            line-height: 1.45;
            resize: vertical;
            background: #FFFFFF;
            box-sizing: border-box;
            font-family: inherit;
        }
        .comment-textarea:focus {
            outline: none;
            border-color: #F97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }

        /* 7. NÚT THAO TÁC (Lưu nháp | Gửi đánh giá) */
        .action-buttons-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            align-items: center;
            padding-top: 1.15rem;
            border-top: 1px solid #F1F5F9;
        }
        .btn-draft {
            padding: 0.6rem 1.3rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            background: #FFFFFF;
            color: #334155;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-draft:hover {
            background: #F1F5F9;
            border-color: #94A3B8;
        }
        .btn-submit {
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(249, 115, 22, 0.28);
            transition: all 0.15s ease;
        }
        .btn-submit:hover {
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.38);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="teacher-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">

                    <!-- 1. HEADER THEO SLIDE: Chấm điểm + {N} bài đang chờ... -->
                    <div class="grading-header-section">
                        <div>
                            <h1 class="grading-main-title">Chấm điểm</h1>
                            <p class="grading-subtitle">
                                <?= $waitingCount; ?> bài đang chờ - hãy hoàn tất trước thứ Hai.
                            </p>
                        </div>

                        <?php if (count($classList) > 1): ?>
                            <div style="display: flex; align-items: center; gap: 0.4rem; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.3rem 0.65rem;">
                                <label for="classFilterSelect" style="font-size: 0.8rem; font-weight: 700; color: #64748B; margin: 0;">Lớp:</label>
                                <select id="classFilterSelect" style="border: none; background: transparent; font-size: 0.85rem; font-weight: 700; color: #0F172A; outline: none; cursor: pointer;" onchange="location.href='grading.php?class=' + encodeURIComponent(this.value)">
                                    <?php foreach ($classList as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']); ?>" <?= $c['name'] === $activeClassName ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($c['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Flash Message -->
                    <?php if ($flash): ?>
                        <div style="background: #DCFCE7; border: 1px solid #BBF7D0; color: #15803D; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span><?= htmlspecialchars($flash); ?></span>
                        </div>
                    <?php endif; ?>

                    <div id="ajaxAlertNotification" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-weight: 600; align-items: center; gap: 0.5rem; font-size: 0.88rem;"></div>

                    <?php if (empty($students)): ?>
                        <div style="text-align: center; color: #64748B; padding: 3.5rem 1.5rem; background: #FFFFFF; border-radius: 10px; border: 1px solid #E2E8F0;">
                            <p style="margin: 0; font-size: 0.95rem;">Hiện tại chưa có bài đánh giá nào trong danh sách.</p>
                        </div>
                    <?php else: ?>

                    <!-- BỐ CỤC SLIDE: HÀNG CHỜ (TRÁI) & ĐANG CHẤM (PHẢI) -->
                    <div class="grading-layout-grid">

                        <!-- 2. KHU VỰC BÊN TRÁI — HÀNG CHỜ -->
                        <div class="queue-box">
                            <div class="queue-box-header">
                                <span class="queue-box-title">Hàng chờ</span>
                                <span class="queue-box-badge"><?= count($students); ?> bài</span>
                            </div>

                            <div class="queue-items-list" id="queueListWrapper">
                                <?php foreach ($students as $st): 
                                    $isSelected = ($selectedStudent && $selectedStudent['studentId'] === $st['studentId']);
                                    
                                    // Xác định trạng thái thật từ database
                                    $stStatus = resolveEvaluationStatus(
                                        $st['assessmentStatus'] ?? null,
                                        $st['overallScore'] !== null ? (float)$st['overallScore'] : null,
                                        (int)($st['scoreCount'] ?? 0),
                                        $st['comment'] ?? null
                                    );

                                    $actTitle = !empty($st['latestActivityTitle']) 
                                        ? $st['latestActivityTitle'] 
                                        : ('Đánh giá Năng lực - Lớp ' . $activeClassName);
                                    
                                    $evalTimestamp = $st['assessmentUpdatedAt'] ?? ($st['assessmentCreatedAt'] ?? ($st['latestActivityRegisteredAt'] ?? ($st['studentCreatedAt'] ?? null)));
                                    $relTime = formatRelativeDate($evalTimestamp);
                                ?>
                                    <a href="grading.php?class=<?= urlencode($activeClassName); ?>&student_id=<?= urlencode($st['studentId']); ?>"
                                       class="queue-card-item <?= $isSelected ? 'is-selected' : ''; ?>"
                                       id="queue-item-<?= htmlspecialchars($st['studentId']); ?>">
                                        <div class="queue-card-name">
                                            <?= htmlspecialchars($st['fullName']); ?>
                                        </div>
                                        <div class="queue-card-activity" title="<?= htmlspecialchars($actTitle); ?>">
                                            <?= htmlspecialchars($actTitle); ?>
                                        </div>
                                        <div class="queue-card-bottom">
                                            <span class="queue-card-time"><?= htmlspecialchars($relTime); ?></span>
                                            <div>
                                                <?php if ($stStatus === 'published'): ?>
                                                    <span class="status-badge status-badge--published">Đã đánh giá</span>
                                                <?php elseif ($stStatus === 'draft'): ?>
                                                    <span class="status-badge status-badge--draft">Bản nháp</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-badge--unassessed">Chưa đánh giá</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 3. KHU VỰC BÊN PHẢI — ĐANG CHẤM -->
                        <div class="eval-workspace-panel">
                            <?php if (!$selectedStudent): ?>
                                <div style="text-align: center; padding: 3rem 1rem; color: #64748B;">
                                    Vui lòng chọn một bài đánh giá từ hàng chờ để tiến hành chấm điểm.
                                </div>
                            <?php else: 
                                $curOverallScore = $selectedStudent['overallScore'] !== null ? (float)$selectedStudent['overallScore'] : null;
                                $curComment = $selectedStudent['comment'] ?? '';

                                // Xác định trạng thái thật của học viên đang chọn
                                $curStatus = resolveEvaluationStatus(
                                    $selectedStudent['assessmentStatus'] ?? null,
                                    $curOverallScore,
                                    count($studentSavedScores),
                                    $curComment
                                );
                                
                                $curActTitle = !empty($selectedStudent['latestActivityTitle']) 
                                    ? $selectedStudent['latestActivityTitle'] 
                                    : (!empty($studentActivities) ? $studentActivities[0]['title'] : ('Đánh giá Năng lực - Lớp ' . $activeClassName));
                                
                                $curTimestamp = $selectedStudent['assessmentUpdatedAt'] ?? ($selectedStudent['assessmentCreatedAt'] ?? ($selectedStudent['latestActivityRegisteredAt'] ?? ($selectedStudent['studentCreatedAt'] ?? null)));
                                $curRelTime = formatRelativeDate($curTimestamp);
                            ?>
                                <form id="studentGradingForm" method="post" action="grading.php">
                                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken()); ?>">
                                    <input type="hidden" name="studentId" value="<?= htmlspecialchars($selectedStudent['studentId']); ?>">
                                    <input type="hidden" name="classId" value="<?= htmlspecialchars($activeClassId); ?>">
                                    <input type="hidden" name="className" value="<?= htmlspecialchars($activeClassName); ?>">
                                    <input type="hidden" name="action" id="gradingActionInput" value="save_draft">

                                    <!-- Thông tin học viên đang chấm: Avatar, Tên, Hoạt động, Thời gian, Trạng thái -->
                                    <div class="eval-student-card">
                                        <div class="eval-student-left">
                                            <div class="eval-student-avatar">
                                                <?= htmlspecialchars(getInitials($selectedStudent['fullName'])); ?>
                                            </div>
                                            <div>
                                                <h2 class="eval-student-name"><?= htmlspecialchars($selectedStudent['fullName']); ?></h2>
                                                <div class="eval-student-meta">
                                                    <strong><?= htmlspecialchars($curActTitle); ?></strong>
                                                    <span class="eval-meta-sep">•</span>
                                                    <span><?= htmlspecialchars($curRelTime); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="evalStatusTagContainer">
                                            <?php if ($curStatus === 'published'): ?>
                                                <span class="status-badge status-badge--published" style="font-size: 0.8rem; padding: 0.25rem 0.65rem;">Đã đánh giá</span>
                                            <?php elseif ($curStatus === 'draft'): ?>
                                                <span class="status-badge status-badge--draft" style="font-size: 0.8rem; padding: 0.25rem 0.65rem;">Bản nháp</span>
                                            <?php else: ?>
                                                <span class="status-badge status-badge--unassessed" style="font-size: 0.8rem; padding: 0.25rem 0.65rem;">Chưa đánh giá</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- 4. 4 TIÊU CHÍ (Thanh kéo nhỏ gọn, hiện đại 0–100) -->
                                    <div class="criteria-container">
                                        <div class="criteria-header-row">
                                            <span class="criteria-title">Tiêu chí đánh giá</span>
                                            <span class="criteria-scale-note">Thang điểm: 0 – 100</span>
                                        </div>

                                        <div class="criteria-sliders-grid">
                                            <?php foreach ($activeCriteria as $crit): 
                                                $cid = (string) $crit['id'];
                                                $hasSaved = isset($studentSavedScores[$cid]);
                                                $savedScoreVal = $hasSaved ? (float) $studentSavedScores[$cid] : null;
                                            ?>
                                                <div class="criterion-slider-card">
                                                    <div class="criterion-card-top">
                                                        <label class="criterion-card-label" for="slider_<?= htmlspecialchars($cid); ?>">
                                                            <?= htmlspecialchars($crit['name']); ?>
                                                        </label>
                                                        <div class="criterion-score-badge">
                                                            <strong id="val_display_<?= htmlspecialchars($cid); ?>" 
                                                                    class="criterion-score-val <?= $savedScoreVal === null ? 'is-unset' : ''; ?>">
                                                                <?= $savedScoreVal !== null ? (int)$savedScoreVal : '—'; ?>
                                                            </strong>
                                                            <span class="criterion-score-max"> / 100</span>
                                                        </div>
                                                    </div>
                                                    <div class="criterion-slider-track">
                                                        <input type="range" 
                                                               class="criterion-range-slider"
                                                               id="slider_<?= htmlspecialchars($cid); ?>"
                                                               min="0" 
                                                               max="100" 
                                                               step="1"
                                                               value="<?= $savedScoreVal !== null ? (int)$savedScoreVal : 0; ?>"
                                                               data-has-value="<?= $savedScoreVal !== null ? '1' : '0'; ?>"
                                                               data-cid="<?= htmlspecialchars($cid); ?>"
                                                               oninput="handleSliderInput(this)">
                                                        
                                                        <input type="hidden" 
                                                               id="crit_hidden_<?= htmlspecialchars($cid); ?>"
                                                               name="criteria[<?= htmlspecialchars($cid); ?>]"
                                                               value="<?= $savedScoreVal !== null ? (int)$savedScoreVal : ''; ?>"
                                                               <?= $savedScoreVal !== null ? '' : 'disabled'; ?>>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- 5. ĐIỂM TỔNG KẾT (Tính từ 4 tiêu chí thật) -->
                                    <div class="overall-score-indicator">
                                        <span class="overall-score-title">Điểm tổng kết</span>
                                        <div class="overall-score-display">
                                            <span id="liveOverallScoreVal">
                                                <?= $curOverallScore !== null ? number_format($curOverallScore, 1) : '—'; ?>
                                            </span>
                                            <span class="overall-score-unit" id="liveOverallScoreUnit" style="<?= $curOverallScore !== null ? '' : 'display:none;'; ?>">
                                                / 100
                                            </span>
                                        </div>
                                        <input type="hidden" name="overallScore" id="overallScoreHidden" value="<?= $curOverallScore !== null ? $curOverallScore : ''; ?>">
                                    </div>

                                    <!-- 6. NHẬN XÉT -->
                                    <div class="comment-section">
                                        <label class="comment-label" for="teacherCommentField">Nhận xét</label>
                                        <textarea class="comment-textarea" 
                                                  id="teacherCommentField" 
                                                  name="comment" 
                                                  placeholder="Nhập nhận xét của giáo viên..."><?= htmlspecialchars($curComment); ?></textarea>
                                    </div>

                                    <!-- 7. NÚT THAO TÁC (Lưu nháp | Gửi đánh giá) -->
                                    <div class="action-buttons-footer">
                                        <button type="button" class="btn-draft" onclick="executeGradingAction('save_draft')">
                                            Lưu nháp
                                        </button>
                                        <button type="button" class="btn-submit" onclick="executeGradingAction('submit_assessment')">
                                            Gửi đánh giá
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <script>
        // Cập nhật gradient màu fill của thanh trượt slider
        function updateSliderFill(slider) {
            const hasVal = slider.getAttribute('data-has-value') === '1';
            if (!hasVal) {
                slider.style.background = '#E2E8F0';
                return;
            }
            const min = parseFloat(slider.min) || 0;
            const max = parseFloat(slider.max) || 100;
            const val = parseFloat(slider.value) || 0;
            const pct = ((val - min) / (max - min)) * 100;
            slider.style.background = `linear-gradient(to right, #F97316 0%, #F97316 ${pct}%, #E2E8F0 ${pct}%, #E2E8F0 100%)`;
        }

        // Khi người dùng tương tác với slider
        function handleSliderInput(slider) {
            const cid = slider.getAttribute('data-cid');
            const hidden = document.getElementById('crit_hidden_' + cid);
            const display = document.getElementById('val_display_' + cid);

            slider.setAttribute('data-has-value', '1');
            updateSliderFill(slider);

            if (hidden) {
                hidden.disabled = false;
                hidden.value = slider.value;
            }
            if (display) {
                display.textContent = slider.value;
                display.classList.remove('is-unset');
            }

            calcOverallLiveScore();
        }

        // Tự động tính điểm tổng kết trung bình cộng của 4 tiêu chí
        function calcOverallLiveScore() {
            const sliders = document.querySelectorAll('.criterion-range-slider');
            let sum = 0;
            let count = 0;
            const totalRequired = sliders.length;

            sliders.forEach(sl => {
                if (sl.getAttribute('data-has-value') === '1') {
                    const v = parseFloat(sl.value);
                    if (!isNaN(v)) {
                        sum += Math.max(0, Math.min(100, v));
                        count++;
                    }
                }
            });

            const display = document.getElementById('liveOverallScoreVal');
            const unit = document.getElementById('liveOverallScoreUnit');
            const hidden = document.getElementById('overallScoreHidden');

            // Nếu đã nhập đủ 4 tiêu chí: hiển thị điểm tổng kết
            if (count > 0 && count === totalRequired) {
                const avg = (sum / count).toFixed(1);
                if (display) display.textContent = avg;
                if (unit) unit.style.display = 'inline';
                if (hidden) hidden.value = avg;
            } else {
                // Chưa nhập đủ 4 tiêu chí: hiển thị trạng thái '—'
                if (display) display.textContent = '—';
                if (unit) unit.style.display = 'none';
                if (hidden) hidden.value = '';
            }
        }

        // Xử lý gửi hành động Lưu nháp hoặc Gửi đánh giá
        async function executeGradingAction(actionType) {
            const form = document.getElementById('studentGradingForm');
            if (!form) return;

            // Kiểm tra nếu gửi đánh giá chính thức nhưng chưa chấm đủ 4 tiêu chí
            if (actionType === 'submit_assessment') {
                const sliders = document.querySelectorAll('.criterion-range-slider');
                let count = 0;
                sliders.forEach(sl => {
                    if (sl.getAttribute('data-has-value') === '1') count++;
                });
                if (count < sliders.length) {
                    alert('Vui lòng điều chỉnh đầy đủ cả 4 tiêu chí (0–100 điểm) trước khi gửi đánh giá.');
                    return;
                }
            }

            const actionInput = document.getElementById('gradingActionInput');
            if (actionInput) actionInput.value = actionType;

            const formData = new FormData(form);
            formData.append('is_ajax', '1');

            const alertBox = document.getElementById('ajaxAlertNotification');

            try {
                const res = await fetch('grading.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    if (alertBox) {
                        alertBox.style.display = 'flex';
                        alertBox.style.background = '#DCFCE7';
                        alertBox.style.border = '1px solid #BBF7D0';
                        alertBox.style.color = '#15803D';
                        alertBox.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + data.message + '</span>';
                        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }

                    // Cập nhật huy hiệu trạng thái bên Đang chấm
                    const statusTagContainer = document.getElementById('evalStatusTagContainer');
                    if (statusTagContainer) {
                        if (data.status === 'published') {
                            statusTagContainer.innerHTML = '<span class="status-badge status-badge--published" style="font-size: 0.8rem; padding: 0.25rem 0.65rem;">Đã đánh giá</span>';
                        } else {
                            statusTagContainer.innerHTML = '<span class="status-badge status-badge--draft" style="font-size: 0.8rem; padding: 0.25rem 0.65rem;">Bản nháp</span>';
                        }
                    }

                    // Cập nhật trạng thái bên Hàng chờ
                    const activeQueueItem = document.querySelector('#queueListWrapper .queue-card-item.is-selected');
                    if (activeQueueItem) {
                        const tagEl = activeQueueItem.querySelector('.status-badge');
                        if (tagEl) {
                            if (data.status === 'published') {
                                tagEl.className = 'status-badge status-badge--published';
                                tagEl.textContent = 'Đã đánh giá';
                            } else {
                                tagEl.className = 'status-badge status-badge--draft';
                                tagEl.textContent = 'Bản nháp';
                            }
                        }
                    }
                } else {
                    throw new Error(data.message || 'Lưu đánh giá thất bại.');
                }
            } catch (err) {
                // Fallback submit thường nếu fetch gặp lỗi mạng
                form.submit();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.criterion-range-slider').forEach(sl => {
                updateSliderFill(sl);
            });
            calcOverallLiveScore();
        });
    </script>
</body>
</html>
