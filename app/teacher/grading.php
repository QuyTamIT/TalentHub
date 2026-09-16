<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Modules\Skills\Repository\SkillGroupRepository;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Support\Uuid;

date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Xác thực Giảng viên
$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/grading.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 2) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

// The rendered scores and optimistic-lock version must be a fresh snapshot.
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

$config = require dirname(__DIR__, 2) . '/config/database.php';
$pdo = (new Connection($config))->connect();

// 2. Read the configured rubric without changing its definitions.
if (!function_exists('ensureAssessmentCriteria')) {
    function ensureAssessmentCriteria(PDO $pdo): array
    {
        // Criteria are configuration, not request-time data. This endpoint must be read-only on GET.
        $stmt = $pdo->prepare("SELECT id, code, name, description, minScore, maxScore, displayOrder FROM assessment_criteria WHERE status = 'active' ORDER BY displayOrder ASC, name ASC");
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
    $activityId = trim((string) ($_POST['activityId'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $scoresInput = $_POST['criteria'] ?? [];

    // Pass raw values through; the shared service rejects unknown, malformed and out-of-range scores.
    $serviceCriteria = $scoresInput;

    if (!empty($studentId) && !empty($activityId)) {
        try {
            $assessmentId = $_POST['assessmentId'] ?? null;
            $expectedVersion = $_POST['expectedVersion'] ?? null;
            $isUpdate = is_string($assessmentId) && $assessmentId !== '';

            $gradingRepo = new TeacherGradingRepository($pdo);
            $gradingService = new TeacherGradingService($gradingRepo);

            $gradingService->save((string) $user['id'], [
                'mode' => 'activity',
                'contextId' => $activityId,
                'studentId' => $studentId,
                'assessmentId' => $assessmentId,
                'expectedVersion' => $expectedVersion,
                'assessmentStatus' => $status,
                'comment' => $comment,
                'criteria' => $serviceCriteria,
                // Preserve the existing skill API alongside the group-only form.
                // Mixed payloads reach service validation instead of silently dropping skills.
                ...(array_key_exists('skills', $_POST)
                    ? ['skills' => $_POST['skills']]
                    : ['skillGroups' => $_POST['skillGroups'] ?? []]),
                ...(array_key_exists('skills', $_POST) && array_key_exists('skillGroups', $_POST)
                    ? ['skillGroups' => $_POST['skillGroups']] : []),
            ]);

            // Query back the saved assessment to retrieve backend-calculated overallScore and assessmentId
            $fetchSaved = $pdo->prepare("SELECT id, version, overallScore FROM assessments WHERE teacherId = ? AND studentId = ? AND activityId = ? LIMIT 1");
            $fetchSaved->execute([$teacherId, $studentId, $activityId]);
            $savedRow = $fetchSaved->fetch(PDO::FETCH_ASSOC);
            $assessmentId = $savedRow ? (string) $savedRow['id'] : ($assessmentId ?? '');
            $overallScore = ($savedRow && is_numeric($savedRow['overallScore'])) ? (float) $savedRow['overallScore'] : null;

            $stName = 'Học viên';
            try {
                $stNameStmt = $pdo->prepare("SELECT u.fullName FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE sp.id = ?");
                $stNameStmt->execute([$studentId]);
                $stName = $stNameStmt->fetchColumn() ?: 'Học viên';
            } catch (\Throwable $e) {}

            $statusText = $isUpdate ? 'Đã cập nhật đánh giá' : ($status === 'published' ? 'Đã gửi đánh giá' : 'Đã lưu bản nháp');
            $msg = "{$statusText} thành công cho {$stName}.";

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'updated' => $isUpdate,
                    'message' => $msg,
                    'status' => $status,
                    'overallScore' => $overallScore !== null ? number_format($overallScore, 1) : null,
                    'studentId' => $studentId,
                    'assessmentId' => $assessmentId,
                    // Return the version THIS save committed, not a later concurrent save
                    // that could already be visible to the post-commit SELECT above.
                    'version' => (int) $expectedVersion + 1,
                    'activityId' => $activityId,
                ]);
                exit;
            }

            $_SESSION['teacherGradingFlash'] = $msg;
            $classQuery = isset($_POST['className']) ? '&class=' . urlencode((string)$_POST['className']) : '';
            header('Location: ' . app_href('/app/teacher/grading.php') . '?student_id=' . urlencode($studentId) . '&activity_id=' . urlencode($activityId) . $classQuery);
            exit;
        } catch (\Throwable $e) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                $isVersionConflict = $e instanceof \TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
                http_response_code($isVersionConflict
                    ? 409 : ($e instanceof \TalentHub\Http\ApiException ? $e->status : 500));
                echo json_encode([
                    'success' => false,
                    'code' => $isVersionConflict ? 'ASSESSMENT_VERSION_CONFLICT' : 'SAVE_FAILED',
                    'message' => $isVersionConflict
                        ? 'Đánh giá đã được cập nhật ở lần lưu khác. Hãy tải bản mới nhất, kiểm tra điểm rồi gửi lại.'
                        : 'Lỗi lưu dữ liệu: ' . $e->getMessage(),
                ]);
                exit;
            }
            $_SESSION['teacherGradingFlash'] = 'Lỗi lưu đánh giá: ' . $e->getMessage();
        }
    } else {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn học viên và Activity cần chấm.']);
        exit;
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
$requestedActivityId = trim((string) ($_GET['activity_id'] ?? $_GET['activity'] ?? ''));

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
                   act.id AS currentActivityId, act.title AS latestActivityTitle,
                   (
                       SELECT ar.registeredAt
                       FROM activity_registrations ar
                       WHERE ar.studentId = sp.id AND ar.activityId = act.id
                       ORDER BY ar.registeredAt DESC
                       LIMIT 1
                   ) AS latestActivityRegisteredAt
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN activities act ON act.id = (
                SELECT ar.activityId FROM activity_registrations ar
                JOIN activities eligible ON eligible.id = ar.activityId
                WHERE ar.studentId = sp.id AND ar.status IN ('approved', 'attended')
                  AND eligible.createdByTeacherId = :activityTeacherId AND eligible.schoolId = c.schoolId
                  AND (:activityFilter = '' OR ar.activityId = :activityId)
                ORDER BY ar.registeredAt DESC, ar.activityId ASC LIMIT 1
            )
            LEFT JOIN assessments a ON a.studentId = sp.id AND a.teacherId = :teacherId AND a.activityId = act.id
            WHERE sp.studyStatus = 'active'
              AND sp.classId = :activeClassIdStudent
            ORDER BY u.fullName ASC
        ");
        $stStmt->execute([
            'activityTeacherId' => $teacherId,
            'activityFilter' => $requestedActivityId,
            'activityId' => $requestedActivityId,
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
$skillGroups = [];
$savedGroupScores = [];
$skillLoadError = null;
$currentActivityId = (string) ($selectedStudent['currentActivityId'] ?? '');

try {
    $groupRepository = new SkillGroupRepository($pdo);
    $skillGroups = $groupRepository->forAssessment($selectedStudent['assessmentId'] ?? null);
    if ($selectedStudent && !empty($selectedStudent['assessmentId'])) {
        $savedGroupScores = $groupRepository->assessmentScores((string) $selectedStudent['assessmentId']);
    }
} catch (\Throwable $e) {
    $skillLoadError = 'Không tải được nhóm kỹ năng đã lưu. Vui lòng tải lại trang trước khi đánh giá.';
}

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

$pageTitle = 'Chấm điểm - FTalentHub';
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

if (!function_exists('getStudentSingleInitial')) {
    function getStudentSingleInitial(string $name): string {
        $words = preg_split('/\s+/', trim($name));
        if (!empty($words)) {
            $lastWord = end($words);
            return mb_strtoupper(mb_substr($lastWord, 0, 1));
        }
        return 'A';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Chấm điểm theo Lớp - <?= htmlspecialchars($activeClassName); ?> | TalentHub Giảng viên</title>
    <title>Chấm điểm | FTalentHub</title>

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
            font-size: 1.65rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.35rem 0;
            letter-spacing: -0.01em;
        }
        .grading-subtitle {
            font-size: 0.92rem;
            color: #64748B;
            margin: 0;
            font-weight: 500;
        }

        /* 2. Bố cục 2 Panel theo Slide: HÀNG CHỜ (trái) | ĐANG CHẤM (phải) */
        .grading-layout-grid {
            display: grid;
            grid-template-columns: 310px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 992px) {
            .grading-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-section-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #94A3B8;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.85rem;
            padding-left: 0.2rem;
        }

        /* KHU VỰC BÊN TRÁI — HÀNG CHỜ */
        .queue-box,
        .queue-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
            padding: 1.25rem;
        }
        .queue-items-list {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            max-height: 560px;
            overflow-y: auto;
        }
        .queue-items-list::-webkit-scrollbar {
            width: 4px;
        }
        .queue-items-list::-webkit-scrollbar-track {
            background: transparent;
        }
        .queue-items-list::-webkit-scrollbar-thumb {
            background: #E2E8F0;
            border-radius: 9999px;
        }
        .queue-card-item {
            display: block;
            padding: 0.75rem 0.95rem;
            border-radius: 10px;
            text-decoration: none;
            color: #0F172A;
            transition: all 0.15s ease;
            background: transparent;
            border: none;
        }
        .queue-card-item:hover {
            background: #FFF7ED;
        }
        .queue-card-item.is-selected {
            background: #FA6400;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(250, 100, 0, 0.22);
        }
        .queue-card-name {
            font-size: 0.94rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.2rem;
            line-height: 1.3;
        }
        .queue-card-item.is-selected .queue-card-name {
            color: #FFFFFF;
        }
        .queue-card-meta {
            font-size: 0.78rem;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }
        .queue-card-act {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .queue-card-item.is-selected .queue-card-meta {
            color: rgba(255, 255, 255, 0.88);
        }
        .queue-meta-sep {
            opacity: 0.65;
        }

        /* KHU VỰC BÊN PHẢI — ĐANG CHẤM */
        .eval-workspace-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
            padding: 1.5rem;
        }

        /* Header học viên: Tên + Meta (Trái) & Avatar tròn cam (Phải) */
        .eval-student-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1.35rem;
            margin-bottom: 1.25rem;
        }
        .eval-student-info {
            flex: 1;
        }
        .eval-student-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.25rem 0;
            letter-spacing: -0.01em;
        }
        .eval-student-meta {
            font-size: 0.84rem;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .eval-meta-sep {
            color: #CBD5E1;
        }
        .eval-student-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #FA6400;
            color: #FFFFFF;
            font-weight: 800;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(250, 100, 0, 0.25);
        }

        /* 4 TIÊU CHÍ (Horizontal sliders 0–100 theo chuẩn Slide) */
        .criteria-container {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
            margin-bottom: 1.35rem;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        .criterion-slider-row {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .criterion-row-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .criterion-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0F172A;
            cursor: pointer;
            margin: 0;
            padding: 0;
        }
        .criterion-score-badge {
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            user-select: none;
            white-space: nowrap;
        }
        .criterion-score-val {
            font-size: 0.92rem;
            font-weight: 800;
            color: #0F172A;
        }
        .criterion-score-val.is-unset {
            color: #94A3B8;
            font-weight: 600;
        }
        .criterion-score-max {
            font-size: 0.82rem;
            color: #64748B;
            font-weight: 600;
        }

        /* Range input styling theo chuẩn slide khách hàng:
           Đường ngang mảnh 4px + thumb nhỏ tròn 11px, tuyệt đối không khối hộp dày, không viền, không outline */
        .criterion-slider-track {
            display: flex;
            align-items: center;
            width: 100%;
            height: auto;
            min-height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .criterion-range-slider {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 100%;
            height: 4px !important;
            min-height: 0 !important;
            max-height: 4px !important;
            background: #EEF2F6;
            border-radius: 2px !important;
            outline: none !important;
            outline-offset: 0 !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            cursor: pointer;
            margin: 6px 0 !important;
            padding: 0 !important;
            border: none !important;
            box-sizing: border-box !important;
            display: block;
            -webkit-tap-highlight-color: transparent;
        }
        .criterion-range-slider:focus,
        .criterion-range-slider:focus-visible,
        .criterion-range-slider:active,
        .criterion-range-slider:focus-within {
            outline: none !important;
            outline-offset: 0 !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            border: none !important;
        }
        .criterion-range-slider::-webkit-slider-runnable-track {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 4px !important;
            min-height: 4px !important;
            max-height: 4px !important;
            border-radius: 2px !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
        }
        .criterion-range-slider::-moz-range-track {
            width: 100%;
            height: 4px !important;
            min-height: 4px !important;
            max-height: 4px !important;
            border-radius: 2px !important;
            background: #EEF2F6;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
        }
        .criterion-range-slider::-moz-range-progress {
            background: transparent;
            height: 4px !important;
            border-radius: 2px !important;
        }
        .criterion-range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            box-sizing: border-box;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #FA6400;
            cursor: pointer;
            border: 1.5px solid #FFFFFF;
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            margin-top: -3.5px;
            transition: background-color 0.15s ease;
        }
        .criterion-range-slider::-webkit-slider-thumb:hover {
            background: #E05300;
        }
        .criterion-range-slider::-moz-range-thumb {
            box-sizing: border-box;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #FA6400;
            cursor: pointer;
            border: 1.5px solid #FFFFFF;
            outline: none !important;
            box-shadow: none !important;
            transition: background-color 0.15s ease;
        }
        .criterion-range-slider::-moz-range-thumb:hover {
            background: #E05300;
        }
        .criterion-range-slider:active::-webkit-slider-thumb {
            cursor: grabbing;
        }
        .criterion-range-slider:active::-moz-range-thumb {
            cursor: grabbing;
        }
        .criterion-range-slider[data-has-value="0"]::-webkit-slider-thumb {
            background: #CBD5E1;
            border: 1.5px solid #FFFFFF;
        }
        .criterion-range-slider[data-has-value="0"]::-moz-range-thumb {
            background: #CBD5E1;
            border: 1.5px solid #FFFFFF;
        }
        .criterion-range-slider:focus::-webkit-slider-thumb,
        .criterion-range-slider:focus-visible::-webkit-slider-thumb,
        .criterion-range-slider:focus::-moz-range-thumb,
        .criterion-range-slider:focus-visible::-moz-range-thumb {
            outline: none !important;
            box-shadow: none !important;
        }

        /* 6. NHẬN XÉT COMPACT THEO SLIDE */
        .comment-section {
            margin-bottom: 1.25rem;
        }
        .comment-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 0.45rem;
        }
        .comment-textarea {
            width: 100%;
            min-height: 80px;
            height: 80px;
            padding: 0.75rem 0.9rem;
            border: 1px solid #FED7AA;
            border-radius: 8px;
            font-size: 0.88rem;
            color: #0F172A;
            line-height: 1.45;
            resize: vertical;
            background: #FFFBF7;
            box-sizing: border-box;
            font-family: inherit;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .comment-textarea:focus {
            outline: none;
            border-color: #FB923C;
            box-shadow: 0 0 0 2px rgba(251, 146, 60, 0.15);
        }
        .comment-textarea::placeholder {
            color: #94A3B8;
        }

        .verified-skills { min-width: 0; margin: 0 0 1.25rem; padding: 0; border: 0; }
        .verified-skills legend { padding: 0; }
        .verified-skills-help, .verified-skills-count, .verified-skills-empty {
            margin: 0 0 0.65rem; color: #64748B; font-size: 0.82rem; line-height: 1.5;
        }
        .verified-skills-list {
            display: grid; grid-template-columns: minmax(0, 1fr); gap: 0.35rem;
            max-height: 240px; overflow-y: auto; padding: 0.25rem;
        }
        .verified-skill-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; padding: 0.2rem 0.5rem; border-radius: 6px; }
        .verified-skill-row:has(input[type="checkbox"]:checked) { background: #FFF7ED; }
        .verified-skill-choice { display: flex; align-items: center; gap: 0.5rem; flex: 1 1 175px; min-height: 44px; cursor: pointer; color: #334155; font-size: 0.85rem; }
        .verified-skill-choice span { overflow-wrap: anywhere; }
        .verified-skill-choice input { width: 17px; height: 17px; flex-shrink: 0; accent-color: #EA580C; }
        .verified-skill-score { display: flex; align-items: center; gap: 0.5rem; margin-left: auto; color: #64748B; font-size: 0.8rem; }
        .verified-skill-score input { width: 88px; min-height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.4rem; font: inherit; color: #0F172A; background: #FFFFFF; }
        .verified-skill-score input:disabled { background: #F8FAFC; color: #64748B; cursor: not-allowed; }
        .verified-skills [hidden] { display: none; }
        .verified-skills input:focus-visible { outline: 2px solid #EA580C; outline-offset: 2px; }
        .verified-skills-count { margin: 0.5rem 0 0; }
        .verified-skills-error { color: #B91C1C; }
        .grading-toast {
            position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 1100;
            display: flex; align-items: center; gap: 0.75rem;
            width: max-content; max-width: min(440px, calc(100vw - 2.5rem));
            box-sizing: border-box; padding: 0.85rem 1rem; border-radius: 12px;
            background: #166534; color: #FFFFFF; font-size: 0.9rem; line-height: 1.5;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.16);
        }
        .grading-toast[hidden] { display: none; }
        .grading-toast svg { flex: 0 0 22px; }
        .grading-toast span { overflow-wrap: anywhere; }
        .grading-toast button { flex-shrink: 0; min-width: 44px; min-height: 44px; border: 0; border-radius: 6px; background: transparent; color: inherit; cursor: pointer; }
        .grading-toast button:focus-visible { outline: 2px solid #FFFFFF; outline-offset: 2px; }
        .action-buttons-footer button:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
        @media (max-width: 600px) {
            .verified-skills-list { grid-template-columns: minmax(0, 1fr); }
        }

        /* NÚT THAO TÁC Ở CUỐI PANEL */
        .action-buttons-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            align-items: center;
            padding-top: 0.5rem;
        }
        .btn-draft {
            padding: 0.6rem 1.4rem;
            border: 1px solid #CBD5E1;
            border-radius: 9999px;
            background: #FFFFFF;
            color: #334155;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-draft:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
        }
        .btn-submit {
            padding: 0.6rem 1.4rem;
            border: none;
            border-radius: 9999px;
            background: #FA6400;
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(250, 100, 0, 0.25);
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .btn-submit:hover {
            background: #EA580C;
            box-shadow: 0 4px 12px rgba(250, 100, 0, 0.35);
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

                    <div id="ajaxAlertNotification" role="alert" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-weight: 600; align-items: center; gap: 0.5rem; font-size: 0.88rem;"></div>

                    <?php if (empty($students)): ?>
                        <div style="text-align: center; color: #64748B; padding: 3.5rem 1.5rem; background: #FFFFFF; border-radius: 10px; border: 1px solid #E2E8F0;">
                            <p style="margin: 0; font-size: 0.95rem;">Hiện tại chưa có bài đánh giá nào trong danh sách.</p>
                        </div>
                    <?php else: ?>

                    <!-- BỐ CỤC SLIDE: HÀNG CHỜ (TRÁI) & ĐANG CHẤM (PHẢI) -->
                    <div class="grading-layout-grid">

                        <!-- 2. KHU VỰC BÊN TRÁI — HÀNG CHỜ -->
                        <div class="queue-panel">
                            <div class="panel-section-label">HÀNG CHỜ</div>

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
                                        : 'Chưa có Activity phù hợp để chấm';
                                    
                                    $evalTimestamp = $st['assessmentUpdatedAt'] ?? ($st['assessmentCreatedAt'] ?? ($st['latestActivityRegisteredAt'] ?? ($st['studentCreatedAt'] ?? null)));
                                    $relTime = formatRelativeDate($evalTimestamp);
                                ?>
                                    <a href="grading.php?class=<?= urlencode($activeClassName); ?>&student_id=<?= urlencode($st['studentId']); ?>&activity_id=<?= urlencode((string) ($st['currentActivityId'] ?? $requestedActivityId)); ?>"
                                       class="queue-card-item <?= $isSelected ? 'is-selected' : ''; ?>"
                                       id="queue-item-<?= htmlspecialchars($st['studentId']); ?>">
                                        <div class="queue-card-name">
                                            <?= htmlspecialchars($st['fullName']); ?>
                                        </div>
                                        <div class="queue-card-meta">
                                            <span class="queue-card-act" title="<?= htmlspecialchars($actTitle); ?>"><?= htmlspecialchars($actTitle); ?></span>
                                            <span class="queue-meta-sep">•</span>
                                            <span class="queue-card-time"><?= htmlspecialchars($relTime); ?></span>
                                        </div>
                                        <!-- Giữ badge ẩn để JS cập nhật không bị lỗi -->
                                        <span class="status-badge status-badge--<?= htmlspecialchars($stStatus); ?>" style="display: none;"><?= $stStatus === 'published' ? 'Đã đánh giá' : ($stStatus === 'draft' ? 'Bản nháp' : 'Chưa đánh giá'); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 3. KHU VỰC BÊN PHẢI — ĐANG CHẤM -->
                        <div class="eval-workspace-panel">
                            <div class="panel-section-label">ĐANG CHẤM</div>
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
                                    : 'Chưa có Activity phù hợp để chấm';
                                
                                $curTimestamp = $selectedStudent['assessmentUpdatedAt'] ?? ($selectedStudent['assessmentCreatedAt'] ?? ($selectedStudent['latestActivityRegisteredAt'] ?? ($selectedStudent['studentCreatedAt'] ?? null)));
                                $curRelTime = formatRelativeDate($curTimestamp);
                            ?>
                                <form id="studentGradingForm" method="post" action="grading.php">
                                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken()); ?>">
                                    <input type="hidden" name="studentId" value="<?= htmlspecialchars($selectedStudent['studentId']); ?>">
                                    <input type="hidden" name="activityId" value="<?= htmlspecialchars($currentActivityId); ?>">
                                    <input type="hidden" name="assessmentId" value="<?= htmlspecialchars((string) ($selectedStudent['assessmentId'] ?? '')); ?>">
                                    <input type="hidden" name="expectedVersion" value="<?= (int) ($selectedStudent['assessmentVersion'] ?? 0); ?>">
                                    <input type="hidden" name="className" value="<?= htmlspecialchars($activeClassName); ?>">
                                    <input type="hidden" name="action" id="gradingActionInput" value="save_draft">

                                    <!-- Thông tin học viên đang chấm: Tên + Meta bên trái, Avatar tròn chữ cái bên phải -->
                                    <div class="eval-student-card">
                                        <div class="eval-student-info">
                                            <h2 class="eval-student-name"><?= htmlspecialchars($selectedStudent['fullName']); ?></h2>
                                            <div class="eval-student-meta">
                                                <span><?= htmlspecialchars($curActTitle); ?></span>
                                                <span class="eval-meta-sep">•</span>
                                                <span><?= htmlspecialchars($curRelTime); ?></span>
                                            </div>
                                        </div>
                                        <div class="eval-student-avatar">
                                            <?= htmlspecialchars(getStudentSingleInitial($selectedStudent['fullName'])); ?>
                                        </div>

                                        <div id="evalStatusTagContainer" style="display: none;">
                                            <span class="status-badge status-badge--<?= htmlspecialchars($curStatus); ?>"><?= $curStatus === 'published' ? 'Đã đánh giá' : ($curStatus === 'draft' ? 'Bản nháp' : 'Chưa đánh giá'); ?></span>
                                        </div>
                                    </div>

                                    <!-- 4. 4 TIÊU CHÍ (Thanh kéo horizontal slider 0–100 gọn gàng theo slide) -->
                                    <div class="criteria-container">
                                        <?php 
                                        $criteriaWeights = [
                                            'chuyen_mon' => 40,
                                            'sang_tao' => 20,
                                            'ky_luat' => 20,
                                            'lam_viec_nhom' => 20,
                                        ];
                                        foreach ($activeCriteria as $crit): 
                                            $cid = (string) $crit['id'];
                                            $hasSaved = isset($studentSavedScores[$cid]);
                                            $savedScoreVal = $hasSaved ? (float) $studentSavedScores[$cid] : null;
                                            $cWeight = $criteriaWeights[$crit['code']] ?? null;
                                        ?>
                                            <div class="criterion-slider-row">
                                                <div class="criterion-row-top">
                                                    <label class="criterion-name" for="slider_<?= htmlspecialchars($cid); ?>">
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

                                    <!-- Giữ hidden overall score để logic tính toán và lưu DB không đổi -->
                                    <div style="display: none;">
                                        <span id="liveOverallScoreVal"><?= $curOverallScore !== null ? number_format($curOverallScore, 1) : '—'; ?></span>
                                        <span id="liveOverallScoreUnit">/ 100</span>
                                    </div>
                                    <input type="hidden" name="overallScore" id="overallScoreHidden" value="<?= $curOverallScore !== null ? $curOverallScore : ''; ?>">

                                    <!-- 6. NHẬN XÉT COMPACT -->
                                    <div class="comment-section">
                                        <label class="comment-label" for="teacherCommentField">Nhận xét</label>
                                        <textarea class="comment-textarea" 
                                                  id="teacherCommentField" 
                                                  name="comment" 
                                                  placeholder="Ghi nhận tiến bộ, góp ý cải thiện..."><?= htmlspecialchars($curComment); ?></textarea>
                                    </div>

                                    <fieldset class="verified-skills" <?= $currentActivityId === '' || $skillLoadError !== null ? 'disabled' : ''; ?>>
                                        <legend class="comment-label">Kỹ năng được xác thực</legend>
                                        <p class="verified-skills-help" id="verifiedSkillsHelp">Chọn nhóm năng lực học viên đã thể hiện. Nhập điểm riêng cho mỗi nhóm (0–100).</p>
                                        <?php if ($skillLoadError !== null || $currentActivityId === ''): ?>
                                            <p class="verified-skills-help verified-skills-error" role="alert"><?= htmlspecialchars($skillLoadError ?? 'Học viên chưa có Activity thuộc phạm vi chấm của bạn.'); ?></p>
                                        <?php elseif ($skillGroups === []): ?>
                                            <p class="verified-skills-empty">Chưa có nhóm kỹ năng đang hoạt động.</p>
                                        <?php else: ?>
                                            <div class="verified-skills-list">
                                                <?php foreach ($skillGroups as $groupIndex => $group):
                                                    $groupCode = (string) $group['code'];
                                                    $isGroupSelected = array_key_exists($groupCode, $savedGroupScores);
                                                ?>
                                                    <div class="verified-skill-row">
                                                        <label class="verified-skill-choice">
                                                            <input type="checkbox" name="skillGroups[<?= $groupIndex; ?>][groupCode]" value="<?= htmlspecialchars($groupCode); ?>" <?= $isGroupSelected ? 'checked' : ''; ?>>
                                                            <span><?= htmlspecialchars($group['name']); ?></span>
                                                        </label>
                                                        <label class="verified-skill-score">
                                                            <span>Điểm</span>
                                                            <input type="number" name="skillGroups[<?= $groupIndex; ?>][score]" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0–100" aria-label="Điểm <?= htmlspecialchars($group['name']); ?>" value="<?= $isGroupSelected ? htmlspecialchars($savedGroupScores[$groupCode]) : ''; ?>" <?= $isGroupSelected ? 'required' : 'disabled'; ?>>
                                                            <span>/ 100</span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="verified-skills-count" id="verifiedSkillsCount" role="status" aria-live="polite"></p>
                                        <?php endif; ?>
                                    </fieldset>

                                    <!-- 7. NÚT THAO TÁC Ở CUỐI PANEL -->
                                    <div class="action-buttons-footer">
                                        <button type="button" class="btn-draft" onclick="executeGradingAction('save_draft')" <?= $currentActivityId === '' || $skillLoadError !== null ? 'disabled' : ''; ?>>
                                            Lưu nháp
                                        </button>
                                        <button type="button" class="btn-submit" onclick="executeGradingAction('submit_assessment')" <?= $currentActivityId === '' || $skillLoadError !== null ? 'disabled' : ''; ?>>
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="16 9 10 15 7 12"></polyline>
                                            </svg>
                                            <span>Gửi đánh giá</span>
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

    <div id="gradingSuccessToast" class="grading-toast" hidden>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
        <span id="gradingSuccessMessage" role="status" aria-live="polite" aria-atomic="true"></span>
        <button type="button" aria-label="Đóng thông báo" onclick="dismissGradingToast()"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <script>
        let gradingToastTimer;
        function dismissGradingToast() {
            clearTimeout(gradingToastTimer);
            document.getElementById('gradingSuccessToast').hidden = true;
        }
        function showGradingSuccess(message) {
            const toast = document.getElementById('gradingSuccessToast');
            clearTimeout(gradingToastTimer);
            toast.hidden = false;
            document.getElementById('gradingSuccessMessage').textContent = message;
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && toast.animate) {
                toast.getAnimations().forEach(animation => animation.cancel());
                toast.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 200, easing: 'ease-out' });
            }
            gradingToastTimer = setTimeout(dismissGradingToast, 5500);
        }
        // Cập nhật gradient màu fill của thanh trượt slider (cam TalentHub dịu + xám nhạt)
        function updateSliderFill(slider) {
            const hasVal = slider.getAttribute('data-has-value') === '1';
            if (!hasVal) {
                slider.style.background = '#EEF2F6';
                return;
            }
            const min = parseFloat(slider.min) || 0;
            const max = parseFloat(slider.max) || 100;
            const val = parseFloat(slider.value) || 0;
            const pct = ((val - min) / (max - min)) * 100;
            slider.style.background = `linear-gradient(to right, #FA6400 0%, #FA6400 ${pct}%, #EEF2F6 ${pct}%, #EEF2F6 100%)`;
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
            if (!form || form.dataset.saving === '1' || form.dataset.versionConflict === '1' || form.querySelector('.verified-skills')?.disabled) return;
            if (!form.reportValidity()) return;

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
            const buttons = form.querySelectorAll('.action-buttons-footer button');
            form.dataset.saving = '1';
            form.setAttribute('aria-busy', 'true');
            buttons.forEach(button => { button.disabled = true; });
            dismissGradingToast();
            if (alertBox) alertBox.style.display = 'none';

            try {
                const res = await fetch('grading.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    form.elements.assessmentId.value = data.assessmentId;
                    form.elements.expectedVersion.value = String(data.version);
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('student_id', data.studentId);
                    currentUrl.searchParams.set('activity_id', data.activityId);
                    history.replaceState(null, '', currentUrl);
                    showGradingSuccess(data.message);

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
                    if (res.status === 409 && data.code === 'ASSESSMENT_VERSION_CONFLICT') {
                        form.dataset.versionConflict = '1';
                    }
                    throw new Error(data.message || 'Lưu đánh giá thất bại.');
                }
            } catch (err) {
                if (alertBox) {
                    alertBox.style.display = 'flex';
                    alertBox.style.background = '#FEF2F2';
                    alertBox.style.border = '1px solid #FECACA';
                    alertBox.style.color = '#B91C1C';
                    alertBox.setAttribute('role', 'alert');
                    alertBox.textContent = err instanceof SyntaxError || err instanceof TypeError
                        ? 'Chưa xác nhận được kết quả lưu. Vui lòng tải lại để kiểm tra trước khi thử lại.'
                        : (err.message || 'Không thể lưu đánh giá. Vui lòng kiểm tra kết nối.');
                    if (form.dataset.versionConflict === '1') {
                        const reload = document.createElement('a');
                        const url = new URL(window.location.href);
                        url.searchParams.set('student_id', form.elements.studentId.value);
                        url.searchParams.set('activity_id', form.elements.activityId.value);
                        reload.href = url.toString();
                        reload.textContent = 'Tải bản mới nhất';
                        // Reload the full form, including scores, before accepting its new version.
                        reload.addEventListener('click', event => { event.preventDefault(); window.location.replace(reload.href); });
                        alertBox.append(' ', reload);
                    }
                    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            } finally {
                form.dataset.saving = '0';
                form.removeAttribute('aria-busy');
                buttons.forEach(button => { button.disabled = form.dataset.versionConflict === '1'; });
            }
        }

        window.addEventListener('pageshow', event => {
            // Back/forward cache restores old hidden inputs without a new PHP request.
            if (event.persisted) window.location.reload();
        });

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('studentGradingForm');
            form?.addEventListener('submit', event => {
                event.preventDefault();
                executeGradingAction(document.getElementById('gradingActionInput').value);
            });
            const skillRows = Array.from(document.querySelectorAll('.verified-skill-row'));
            const updateSkills = () => {
                let selected = 0;
                skillRows.forEach(row => {
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    const score = row.querySelector('input[type="number"]');
                    score.disabled = !checkbox.checked;
                    score.required = checkbox.checked;
                    if (checkbox.checked) selected++;
                });
                const count = document.getElementById('verifiedSkillsCount');
                if (count) count.textContent = selected + ' nhóm kỹ năng được chọn';
            };
            skillRows.forEach(row => row.querySelector('input[type="checkbox"]').addEventListener('change', updateSkills));
            updateSkills();
            document.querySelectorAll('.criterion-range-slider').forEach(sl => {
                updateSliderFill(sl);
            });
            calcOverallLiveScore();
        });
    </script>
</body>
</html>
