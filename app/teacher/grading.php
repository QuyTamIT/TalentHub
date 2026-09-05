<?php
/**
 * TalentHub - Teacher Portal: Chấm điểm Đồ án & Đánh giá Năng lực theo Lớp
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Support\Uuid;

date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Xác thực Giảng viên (Bỏ qua hoàn toàn kiểm tra hoạt động / sân chơi cũ)
$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/grading.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 2) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

$config = require dirname(__DIR__, 2) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$gradingService = new TeacherGradingService(new TeacherGradingRepository($pdo));

// Lấy teacher_profiles và trường học của giảng viên hiện tại
$teacherProfileStmt = $pdo->prepare("
    SELECT tp.id, tp.schoolId, s.name as schoolName, u.fullName
    FROM teacher_profiles tp
    JOIN users u ON u.id = tp.userId
    LEFT JOIN schools s ON s.id = tp.schoolId
    WHERE tp.userId = ?
    LIMIT 1
");
$teacherProfileStmt->execute([$user['id']]);
$teacherInfo = $teacherProfileStmt->fetch(PDO::FETCH_ASSOC);

$currentTeacherId = $teacherInfo['id'] ?? null;
$currentSchoolId = $teacherInfo['schoolId'] ?? null;
$currentSchoolName = $teacherInfo['schoolName'] ?? 'TalentHub';

// Lấy danh sách hoạt động / đồ án của trường
$activities = [];
if ($currentSchoolId) {
    $actStmt = $pdo->prepare("SELECT id, title, category FROM activities WHERE createdByTeacherId = ? ORDER BY createdAt DESC");
    $actStmt->execute([$currentTeacherId]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);
}
$selectedActivityId = trim((string)($_GET['activityId'] ?? ($_POST['activityId'] ?? ($activities[0]['id'] ?? ''))));

// Lấy danh sách Lớp học của trường
$classes = [];
if ($currentSchoolId) {
    $clStmt = $pdo->prepare("SELECT id, name FROM classes WHERE schoolId = ? ORDER BY name ASC");
    $clStmt->execute([$currentSchoolId]);
    $classes = $clStmt->fetchAll(PDO::FETCH_ASSOC);
}
$selectedClassId = trim((string)($_GET['classId'] ?? ''));

// Lấy danh sách tiêu chí chuẩn (Làm việc nhóm, Chủ động, Thực thi)
$criteriaStmt = $pdo->query("SELECT id, code, name, minScore, maxScore FROM assessment_criteria WHERE status = 'active' ORDER BY displayOrder ASC");
$criteriaList = $criteriaStmt->fetchAll(PDO::FETCH_ASSOC);

$saveEvaluation = static function (
    PDO $pdo,
    TeacherGradingService $service,
    string $teacherUserId,
    string $teacherId,
    string $studentId,
    string $activityId,
    float $score,
    string $comment,
    array $criteriaInput,
    string $requestId,
): void {
    $existing = $pdo->prepare(<<<'SQL'
        SELECT assessment.id, assessment.version
        FROM assessments assessment
        INNER JOIN activities act
          ON act.id = assessment.activityId
         AND act.createdByTeacherId = ?
        WHERE assessment.teacherId = ?
          AND assessment.studentId = ?
          AND assessment.activityId = ?
        LIMIT 1
        SQL);
    $existing->execute([$teacherId, $teacherId, $studentId, $activityId]);
    $assessment = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
    $service->save($teacherUserId, [
        'activityId' => $activityId,
        'studentId' => $studentId,
        'assessmentId' => (string) ($assessment['id'] ?? ''),
        'expectedVersion' => (string) ($assessment['version'] ?? 0),
        'assessmentStatus' => 'published',
        'overallScore' => number_format($score, 2, '.', ''),
        'comment' => $comment,
        'criteria' => $criteriaInput,
    ], $requestId);
};

// 2. Xử lý Lưu điểm (AJAX hoặc Form POST)
$flash = $_SESSION['teacherGradingFlash'] ?? null;
unset($_SESSION['teacherGradingFlash']);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

$respondError = static function (int $status, string $message) use ($isAjax): never {
    http_response_code($status);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    exit;
};

$publicSaveFailure = static function (Throwable $exception, string $requestId): array {
    if ($exception instanceof ApiException) {
        return [$exception->status, $exception->getMessage()];
    }
    if ($exception instanceof TeacherGradingConflictException) {
        return [409, 'Đánh giá đã thay đổi hoặc đã được công bố. Vui lòng tải lại trang.'];
    }

    error_log(sprintf(
        '[teacher-grading] request=%s exception=%s message=%s file=%s line=%d',
        $requestId,
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
    ));
    return [500, 'Không thể lưu đánh giá lúc này. Vui lòng thử lại sau.'];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $session->assertCsrf($_POST['csrfToken'] ?? null);
    } catch (\Throwable $e) {
        $respondError(403, 'CSRF token không hợp lệ.');
    }
    if ($currentTeacherId === null || $currentSchoolId === null) {
        $respondError(403, 'Tài khoản giáo viên chưa được gắn với trường học.');
    }
    $action = $_POST['action'] ?? 'save_single';
    $postActivityId = trim((string)($_POST['activityId'] ?? $selectedActivityId));
    $requestId = Uuid::v4();

    if ($action === 'save_single') {
        $studentId = trim((string) ($_POST['studentId'] ?? ''));
        $rawScore = trim((string) ($_POST['score'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $criteria = is_array($_POST['criteria'] ?? null) ? $_POST['criteria'] : [];
        if (!is_numeric($rawScore) || (float) $rawScore < 0 || (float) $rawScore > 100) {
            $respondError(422, 'Điểm phải là một số từ 0 đến 100.');
        }
        $score = (float) $rawScore;

        if (!empty($studentId)) {
            try {
                $saveEvaluation($pdo, $gradingService, (string) $user['id'], $currentTeacherId, $studentId, $postActivityId, $score, $comment, $criteria, $requestId);
            } catch (\Throwable $e) {
                [$status, $message] = $publicSaveFailure($e, $requestId);
                $respondError($status, $message);
            }

            $stName = 'Sinh viên';
            try {
                $stNameStmt = $pdo->prepare("SELECT u.fullName FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE sp.id = ?");
                $stNameStmt->execute([$studentId]);
                $stName = $stNameStmt->fetchColumn() ?: 'Sinh viên';
            } catch (\Throwable $e) {}

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => "Đã lưu và công bố đánh giá cho sinh viên {$stName} thành công.",
                    'score' => number_format($score, 0),
                    'studentId' => $studentId
                ]);
                exit;
            }

            $_SESSION['teacherGradingFlash'] = "Đã lưu và công bố đánh giá cho sinh viên {$stName} thành công.";
            header('Location: ' . app_href('/app/teacher/grading.php?activityId=' . urlencode($postActivityId) . '&classId=' . urlencode($selectedClassId)));
            exit;
        }
    } elseif ($action === 'save_batch') {
        $scores = $_POST['scores'] ?? [];
        $comments = $_POST['comments'] ?? [];
        $criteriaByStudent = $_POST['criteria'] ?? [];
        $count = 0;
        $failures = [];

        foreach ($scores as $sId => $rawScore) {
            if (!is_numeric($rawScore) || (float) $rawScore < 0 || (float) $rawScore > 100) {
                $failures[] = ['studentId' => (string) $sId, 'status' => 422, 'message' => 'Điểm phải nằm trong khoảng 0–100.'];
                continue;
            }
            $score = (float) $rawScore;
            $comment = trim((string) ($comments[$sId] ?? ''));
            $studentCriteria = is_array($criteriaByStudent[$sId] ?? null) ? $criteriaByStudent[$sId] : [];
            try {
                $saveEvaluation($pdo, $gradingService, (string) $user['id'], $currentTeacherId, (string) $sId, $postActivityId, $score, $comment, $studentCriteria, $requestId);
                $count++;
            } catch (\Throwable $e) {
                [$status, $message] = $publicSaveFailure($e, $requestId);
                $failures[] = ['studentId' => (string) $sId, 'status' => $status, 'message' => $message];
            }
        }

        if ($isAjax) {
            if ($failures !== []) {
                http_response_code($count > 0 ? 207 : (int) $failures[0]['status']);
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $failures === [],
                'partial' => $count > 0 && $failures !== [],
                'savedCount' => $count,
                'failures' => $failures,
                'message' => $failures === []
                    ? "Đã lưu và công bố đánh giá cho {$count} sinh viên thành công."
                    : "Đã lưu {$count} đánh giá; " . count($failures) . ' đánh giá không thể lưu.'
            ]);
            exit;
        }

        $_SESSION['teacherGradingFlash'] = $failures === []
            ? "Đã lưu và công bố đánh giá cho {$count} sinh viên thành công."
            : "Đã lưu {$count} đánh giá; " . count($failures) . ' đánh giá không thể lưu và cần kiểm tra lại.';
        header('Location: ' . app_href('/app/teacher/grading.php?activityId=' . urlencode($postActivityId) . '&classId=' . urlencode($selectedClassId)));
        exit;
    }
}

// 3. Đọc trực tiếp danh sách sinh viên theo Trường học của Giảng viên & Đợt đánh giá được chọn
$viewMode = $_GET['view'] ?? 'grading';
$students = [];
$allSchoolEvals = [];

try {
    if ($currentSchoolId === null) {
        throw new RuntimeException('Tài khoản giáo viên chưa được gắn với trường học.');
    }

    $whereClauses = ["sp.studyStatus = 'active'", "activity.id = ?", "ar.status IN ('approved', 'attended')"];
    $params = [$currentTeacherId, $currentTeacherId, $selectedActivityId];

    if (!empty($selectedClassId)) {
        $whereClauses[] = "sp.classId = ?";
        $params[] = $selectedClassId;
    }
    $whereSql = implode(' AND ', $whereClauses);

    $stStmt = $pdo->prepare("
        SELECT sp.id as studentId, u.fullName, u.email,
               COALESCE(c.name, 'Chưa phân lớp') as className,
               s.name as schoolName,
               a.overallScore as evalScore,
               a.comment as evalComment,
               a.status as evalStatus,
               COUNT(DISTINCT CASE WHEN ta.status = 'submitted' THEN ta.testId END) as completedTests,
               (SELECT COUNT(*) FROM assessments a_all WHERE a_all.studentId = sp.id AND a_all.status = 'published') as totalEvals
        FROM activity_registrations ar
        JOIN activities activity ON activity.id = ar.activityId AND activity.createdByTeacherId = ?
        JOIN student_profiles sp ON sp.id = ar.studentId
        JOIN users u ON u.id = sp.userId
        LEFT JOIN classes c ON c.id = sp.classId
        LEFT JOIN schools s ON s.id = c.schoolId
        LEFT JOIN assessments a ON a.studentId = sp.id AND a.activityId = activity.id AND a.teacherId = ?
        LEFT JOIN test_attempts ta ON ta.studentId = sp.id
        WHERE {$whereSql}
        GROUP BY sp.id, u.fullName, u.email, c.name, s.name, a.overallScore, a.comment, a.status
        ORDER BY (COUNT(DISTINCT CASE WHEN ta.status = 'submitted' THEN ta.testId END) >= 4) DESC, (u.fullName LIKE '%Võ Đức Anh%' OR u.fullName LIKE '%Vũ Đức Anh%') DESC, u.fullName ASC
    ");
    $stStmt->execute($params);
    $students = $stStmt->fetchAll(PDO::FETCH_ASSOC);

    $criteriaScores = [];
    if ($selectedActivityId !== '') {
        $scoreStmt = $pdo->prepare(<<<'SQL'
            SELECT assessment.studentId, score.criteriaId, score.score
            FROM assessments assessment
            INNER JOIN activities activity
              ON activity.id = assessment.activityId
             AND activity.createdByTeacherId = ?
            INNER JOIN assessment_scores score ON score.assessmentId = assessment.id
            WHERE assessment.teacherId = ? AND assessment.activityId = ?
            SQL);
        $scoreStmt->execute([$currentTeacherId, $currentTeacherId, $selectedActivityId]);
        foreach ($scoreStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $criteriaScores[(string) $row['studentId']][(string) $row['criteriaId']] = (string) $row['score'];
        }
    }
    foreach ($students as &$student) {
        $student['criteriaScores'] = $criteriaScores[(string) $student['studentId']] ?? [];
    }
    unset($student);

    // Lấy toàn bộ lịch sử feedback đã công bố trong trường của giảng viên
    $evalHistoryStmt = $pdo->prepare("
            SELECT a.id, a.overallScore, a.comment, a.publishedAt,
                   u_st.fullName as studentName, u_st.email as studentEmail,
                   COALESCE(c.name, 'Chưa phân lớp') as className,
                   act.title as activityTitle,
                   u_tc.fullName as teacherName
            FROM assessments a
            JOIN student_profiles sp ON sp.id = a.studentId
            JOIN users u_st ON u_st.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN activities act ON act.id = a.activityId
            JOIN teacher_profiles tp ON tp.id = a.teacherId
            JOIN users u_tc ON u_tc.id = tp.userId
            WHERE a.teacherId = ?
              AND act.createdByTeacherId = ?
              AND a.status = 'published'
            ORDER BY a.publishedAt DESC, u_st.fullName ASC
        ");
    $evalHistoryStmt->execute([$currentTeacherId, $currentTeacherId]);
    $allSchoolEvals = $evalHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $students = [];
    $allSchoolEvals = [];
}

$pageTitle = 'Chấm điểm & Feedback - TalentHub';
$currentRoute = 'assessments';

$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'href' => '/app/teacher/activities/index.php', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm & Feedback', 'route' => 'assessments', 'href' => '/app/teacher/grading.php', 'icon' => 'clipboard-check', 'active' => true],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chấm điểm theo Lớp - BTEC-AI-2026A | TalentHub Giảng viên</title>

    <link rel="stylesheet" href="<?= app_href('/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/teacher.css'); ?>">
    <style>
        :root {
            --font-primary: 'Be Vietnam Pro', sans-serif;
            --primary: #F97316;
            --primary-hover: #EA580C;
            --primary-light: #FFF7ED;
            --secondary: #2563EB;
            --secondary-light: #EFF6FF;
            --accent: #16A34A;
            --background: #F8FAFC;
            --surface: #FFFFFF;
            --text-primary: #0F172A;
            --text-secondary: #64748B;
            --border: #E2E8F0;
            --radius-sm: 8px;
            --radius-md: 12px;
        }
        body {
            font-family: var(--font-primary);
            background: var(--background);
            color: var(--text-primary);
        }
        .grading-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .grading-table th {
            background: #F8FAFC;
            padding: 0.95rem 1.15rem;
            text-align: left;
            font-weight: 700;
            color: #334155;
            border-bottom: 2px solid #E2E8F0;
            white-space: nowrap;
        }
        .grading-table td {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }
        .grading-table tbody tr:hover {
            background: #F8FAFC;
        }
        .score-input {
            width: 85px;
            padding: 0.45rem 0.6rem;
            border: 1.5px solid #CBD5E1;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--primary);
            text-align: center;
            transition: border-color 0.15s ease;
        }
        .score-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }
        .comment-input {
            width: 100%;
            min-width: 260px;
            padding: 0.5rem 0.75rem;
            border: 1.5px solid #CBD5E1;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--text-primary);
            transition: border-color 0.15s ease;
        }
        .comment-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.12);
        }
        .class-badge {
            display: inline-flex;
            align-items: center;
            background: #EFF6FF;
            color: #1D4ED8;
            border: 1px solid #BFDBFE;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.8125rem;
        }
        .talent-score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 50px;
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.875rem;
        }
        .btn-save-row {
            background: var(--primary);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.5rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .btn-save-row:hover {
            background: var(--primary-hover);
        }
    </style>
</head>
<body class="teacher-dashboard">
    <div class="teacher-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body">
                <div class="teacher-container">
                    
                    <!-- Header Section -->
                    <div style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">
                                Phân hệ Giảng viên • <?= htmlspecialchars($currentSchoolName); ?>
                            </div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: #0F172A; margin: 0 0 0.4rem;">
                                <?= $viewMode === 'history' ? 'Lịch sử Toàn bộ Feedback Đã Công Bố' : 'Chấm điểm & Nhận xét Năng lực theo Đợt'; ?>
                            </h2>
                            <p style="color: #64748B; margin: 0; font-size: 0.92rem;">
                                <?= $viewMode === 'history'
                                    ? 'Xem lại toàn bộ các bản ghi đánh giá và feedback của sinh viên thuộc trường đã được công bố trên hệ thống.'
                                    : 'Đánh giá kết quả đồ án, chấm điểm tiêu chí và đưa ra phản hồi (feedback) trực tiếp cho sinh viên thuộc trường.'; ?>
                            </p>
                        </div>

                        <?php if ($viewMode !== 'history'): ?>
                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                            <span class="class-badge" style="padding: 0.5rem 0.85rem; font-size: 0.9rem;">
                                Danh sách: <?= count($students); ?> sinh viên
                            </span>
                            <button type="button" onclick="document.getElementById('batchGradingForm').submit()" class="btn btn-primary" style="background: var(--primary); border: none; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1rem; border-radius: var(--radius-sm); color: #fff; cursor: pointer;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                <span>Lưu & Công bố toàn bộ</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Flash Message -->
                    <?php if ($flash): ?>
                        <div style="background: #ECFDF5; border: 1px solid #10B981; color: #047857; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span><?= htmlspecialchars($flash); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- View Navigation Tabs -->
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1.5px solid var(--border); padding-bottom: 0.5rem;">
                        <a href="grading.php?view=grading&activityId=<?= urlencode($selectedActivityId); ?>&classId=<?= urlencode($selectedClassId); ?>"
                           style="padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; <?= $viewMode !== 'history' ? 'background: var(--primary); color: #FFF;' : 'background: #F1F5F9; color: #475569;'; ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path><rect x="9" y="3" width="6" height="4" rx="2"></rect><polyline points="9 14 11 16 15 12"></polyline></svg>
                            <span>📝 Chấm điểm theo Đợt / Đồ án</span>
                        </a>

                        <a href="grading.php?view=history"
                           style="padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; <?= $viewMode === 'history' ? 'background: var(--primary); color: #FFF;' : 'background: #F1F5F9; color: #475569;'; ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span>📜 Lịch sử Feedback đã công bố (<?= count($allSchoolEvals); ?>)</span>
                        </a>
                    </div>

                    <?php if ($viewMode === 'history'): ?>
                        <!-- TAB 2: TOÀN BỘ LỊCH SỬ FEEDBACK ĐÃ CÔNG BỐ CỦA TRƯỜNG -->
                        <?php if (empty($allSchoolEvals)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 4rem 1.5rem; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0;">
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: #0F172A; margin-bottom: 0.5rem;">Chưa có bản ghi đánh giá nào</h3>
                                <p style="font-size: 0.875rem; color: #64748B; margin-bottom: 0;">Trường hiện chưa có dữ liệu feedback được công bố.</p>
                            </div>
                        <?php else: ?>
                            <section class="teacher-section-box" style="padding: 0; overflow: hidden; border: 1.5px solid var(--border); border-radius: var(--radius-md);">
                                <div style="overflow-x: auto;">
                                    <table class="grading-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 45px; text-align: center;">STT</th>
                                                <th style="width: 200px;">Sinh viên</th>
                                                <th style="width: 110px;">Lớp</th>
                                                <th style="width: 220px;">Hoạt động / Đồ án</th>
                                                <th style="width: 120px; text-align: center;">Điểm năng lực</th>
                                                <th>Lời nhận xét & Feedback</th>
                                                <th style="width: 150px;">Giảng viên đánh giá</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allSchoolEvals as $hIdx => $hRow):
                                                $hScore = (float) $hRow['overallScore'];
                                                $hBadgeClass = $hScore >= 90.0 ? 'badge-success' : ($hScore >= 80.0 ? 'badge-primary' : 'badge-warning');
                                                $hRatingText = $hScore >= 90.0 ? 'Xuất sắc' : ($hScore >= 80.0 ? 'Giỏi' : ($hScore >= 65.0 ? 'Khá' : 'Đạt'));
                                            ?>
                                                <tr>
                                                    <td style="font-weight: 600; color: #94A3B8; text-align: center;">
                                                        <?= $hIdx + 1; ?>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 700; color: #0F172A; font-size: 0.92rem;">
                                                            <?= htmlspecialchars($hRow['studentName']); ?>
                                                        </div>
                                                        <div style="font-size: 0.75rem; color: #64748B;">
                                                            <?= htmlspecialchars($hRow['studentEmail']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="class-badge">
                                                            <?= htmlspecialchars($hRow['className']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 600; color: #0F172A; font-size: 0.88rem;">
                                                            <?= htmlspecialchars($hRow['activityTitle'] ?? 'Đồ án chuyên ngành'); ?>
                                                        </div>
                                                        <div style="font-size: 0.72rem; color: #94A3B8;">
                                                            Ngày: <?= date('d/m/Y', strtotime($hRow['publishedAt'])); ?>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <span class="talent-score-badge" style="font-size: 0.85rem; padding: 0.25rem 0.6rem;">
                                                            <?= number_format($hScore, 1); ?>/100
                                                        </span>
                                                        <div style="font-size: 0.72rem; font-weight: 700; color: #047857; margin-top: 2px;">
                                                            ⭐ <?= $hRatingText; ?>
                                                        </div>
                                                    </td>
                                                    <td style="font-size: 0.88rem; color: #334155; line-height: 1.45;">
                                                        "<?= htmlspecialchars($hRow['comment']); ?>"
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 600; color: #0F172A; font-size: 0.85rem;">
                                                            <?= htmlspecialchars($hRow['teacherName'] ?? 'Giảng viên'); ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- TAB 1: CHẤM ĐIỂM & ĐÁNH GIÁ THEO ĐỢT -->
                        <!-- Activity & Class Filter Form -->
                        <form method="get" action="grading.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem; background: #FFFFFF; padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
                            <input type="hidden" name="view" value="grading">
                            <div style="display: flex; flex-direction: column; gap: 0.35rem; flex: 1; min-width: 260px;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">
                                    🎯 Chọn Hoạt động / Đồ án đánh giá:
                                </label>
                                <select name="activityId" onchange="this.form.submit()" style="padding: 0.55rem 0.85rem; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 700; color: #0F172A; background: #F8FAFC; cursor: pointer;">
                                    <?php foreach ($activities as $act): ?>
                                        <option value="<?= htmlspecialchars($act['id']); ?>" <?= $selectedActivityId === $act['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($act['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (!empty($classes)): ?>
                            <div style="display: flex; flex-direction: column; gap: 0.35rem; width: 220px;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">
                                    🏫 Lọc theo Lớp:
                                </label>
                                <select name="classId" onchange="this.form.submit()" style="padding: 0.55rem 0.85rem; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; color: #0F172A; background: #F8FAFC; cursor: pointer;">
                                    <option value="">-- Tất cả các lớp --</option>
                                    <?php foreach ($classes as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl['id']); ?>" <?= $selectedClassId === $cl['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($cl['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </form>

                        <!-- Direct Students Grading Table -->
                        <?php if (empty($students)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 4rem 1.5rem; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" style="margin-bottom: 0.75rem;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: #0F172A; margin-bottom: 0.5rem;">Chưa có sinh viên trong phạm vi</h3>
                                <p style="font-size: 0.875rem; color: #64748B; margin-bottom: 0;">Hiện tại chưa có sinh viên thuộc trường hoặc lớp học đã chọn.</p>
                            </div>
                        <?php else: ?>
                        <section class="teacher-section-box" style="padding: 0; overflow: hidden; border: 1.5px solid var(--border); border-radius: var(--radius-md);">
                            <form id="batchGradingForm" method="post" action="grading.php">
                                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken()); ?>">
                                <input type="hidden" name="action" value="save_batch">
                                <input type="hidden" name="activityId" value="<?= htmlspecialchars($selectedActivityId); ?>">

                                <div style="overflow-x: auto;">
                                    <table class="grading-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 45px; text-align: center;">STT</th>
                                                <th>Họ tên & Email</th>
                                                <th style="width: 120px;">Lớp</th>
                                                <th style="width: 120px; text-align: center;">Tiến độ Test</th>
                                                <th style="width: 120px; text-align: center;">Điểm hiện tại</th>
                                                <th style="width: 120px; text-align: center;">Điểm mới (0-100)</th>
                                                <th style="min-width: 210px;">Điểm tiêu chí</th>
                                                <th>Lời nhận xét & Feedback cho sinh viên</th>
                                                <th style="width: 125px; text-align: center;">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $idx => $st):
                                                $words = preg_split('/\s+/', trim($st['fullName']));
                                                $initials = count($words) > 1
                                                     ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
                                                     : 'SV';
                                                $hasScore = ($st['evalScore'] !== null);
                                                $currScore = $hasScore ? (float) $st['evalScore'] : null;
                                                $defaultComment = $st['evalComment'] ?? '';
                                                $isPublished = ($st['evalStatus'] ?? null) === 'published';
                                                $is4Tests = ((int)$st['completedTests'] >= 4);
                                                $totalEvals = (int)($st['totalEvals'] ?? 0);
                                            ?>
                                                <tr id="row-<?= htmlspecialchars($st['studentId']); ?>">
                                                    <td style="font-weight: 600; color: #94A3B8; text-align: center;">
                                                        <?= $idx + 1; ?>
                                                    </td>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                            <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--primary-light); color: var(--primary); font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; border: 1px solid rgba(249, 115, 22, 0.2);">
                                                                 <?= htmlspecialchars($initials); ?>
                                                            </div>
                                                            <div>
                                                                <div style="font-weight: 700; color: #0F172A; font-size: 0.95rem;">
                                                                    <?= htmlspecialchars($st['fullName']); ?>
                                                                </div>
                                                                <div style="font-size: 0.75rem; color: #64748B;">
                                                                    <?= htmlspecialchars($st['email'] ?? 'student@talenthub.vn'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="class-badge">
                                                            <?= htmlspecialchars($st['className'] ?? 'Chưa phân lớp'); ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if ($is4Tests): ?>
                                                            <span style="background: #ECFDF5; color: #047857; padding: 0.25rem 0.55rem; border-radius: 6px; font-weight: 700; font-size: 0.78rem; border: 1px solid #A7F3D0; display: inline-flex; align-items: center; gap: 0.2rem;">
                                                                ✓ 4/4 Test
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="background: #F1F5F9; color: #64748B; padding: 0.25rem 0.55rem; border-radius: 6px; font-weight: 600; font-size: 0.78rem; border: 1px solid #CBD5E1;">
                                                                <?= (int)$st['completedTests']; ?>/4 Test
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($totalEvals > 0): ?>
                                                            <div style="font-size: 0.72rem; color: #64748B; font-weight: 600; margin-top: 3px;">
                                                                (Đã có <?= $totalEvals; ?> feedback)
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if ($hasScore): ?>
                                                            <span id="current-score-<?= htmlspecialchars($st['studentId']); ?>" class="talent-score-badge">
                                                                <?= number_format((float) $currScore, 0); ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span id="current-score-<?= htmlspecialchars($st['studentId']); ?>" class="talent-score-badge" style="background: #F1F5F9; color: #64748B; border: 1px solid #CBD5E1;">
                                                                Chưa chấm
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <input type="number"
                                                               name="scores[<?= htmlspecialchars($st['studentId']); ?>]"
                                                               id="score-input-<?= htmlspecialchars($st['studentId']); ?>"
                                                               class="score-input"
                                                               min="0" max="100" step="1"
                                                               value="<?= $currScore !== null ? htmlspecialchars(number_format($currScore, 2, '.', '')) : ''; ?>"
                                                               placeholder="Điểm"
                                                               <?= $isPublished ? 'disabled' : 'required'; ?>>
                                                    </td>
                                                    <td>
                                                        <div style="display: grid; gap: 0.4rem;">
                                                            <?php foreach ($criteriaList as $criterion): ?>
                                                                <label style="display: grid; grid-template-columns: 1fr 72px; gap: 0.4rem; align-items: center; font-size: 0.72rem; color: #475569;">
                                                                    <span><?= htmlspecialchars((string) $criterion['name']); ?></span>
                                                                    <input type="number"
                                                                           class="criteria-input"
                                                                           data-student-id="<?= htmlspecialchars($st['studentId']); ?>"
                                                                           data-criteria-id="<?= htmlspecialchars((string) $criterion['id']); ?>"
                                                                           name="criteria[<?= htmlspecialchars($st['studentId']); ?>][<?= htmlspecialchars((string) $criterion['id']); ?>]"
                                                                           min="<?= htmlspecialchars((string) $criterion['minScore']); ?>"
                                                                           max="<?= htmlspecialchars((string) $criterion['maxScore']); ?>"
                                                                           step="0.01"
                                                                           value="<?= htmlspecialchars((string) ($st['criteriaScores'][$criterion['id']] ?? '')); ?>"
                                                                           <?= $isPublished ? 'disabled' : 'required'; ?>>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                               name="comments[<?= htmlspecialchars($st['studentId']); ?>]"
                                                               id="comment-input-<?= htmlspecialchars($st['studentId']); ?>"
                                                               class="comment-input"
                                                               value="<?= htmlspecialchars($defaultComment); ?>"
                                                               placeholder="Nhập nhận xét / feedback cho sinh viên..."
                                                               <?= $isPublished ? 'disabled' : ''; ?>>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <button type="button"
                                                                onclick="saveRowGrade('<?= htmlspecialchars($st['studentId']); ?>')"
                                                                class="btn-save-row"
                                                                id="btn-save-<?= htmlspecialchars($st['studentId']); ?>"
                                                                <?= $isPublished ? 'disabled' : ''; ?>>
                                                            <?= $isPublished ? 'Đã công bố' : 'Lưu & Công bố'; ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </section>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="gradingToast" style="display: none; position: fixed; bottom: 2rem; right: 2rem; background: #0F172A; color: #FFFFFF; padding: 0.85rem 1.35rem; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); z-index: 9999; font-weight: 600; font-size: 0.875rem;">
        <span id="gradingToastMsg"></span>
    </div>

    <script>
        const CSRF_TOKEN = <?= json_encode($session->csrfToken()); ?>;
        const SELECTED_ACTIVITY_ID = <?= json_encode($selectedActivityId); ?>;

        function showGradingToast(msg) {
            const toast = document.getElementById('gradingToast');
            const toastMsg = document.getElementById('gradingToastMsg');
            if (toast && toastMsg) {
                toastMsg.textContent = msg;
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, 3500);
            } else {
                alert(msg);
            }
        }

        async function saveRowGrade(studentId) {
            const scoreInput = document.getElementById('score-input-' + studentId);
            const commentInput = document.getElementById('comment-input-' + studentId);
            const btn = document.getElementById('btn-save-' + studentId);
            const currentBadge = document.getElementById('current-score-' + studentId);
            const criteriaInputs = Array.from(document.querySelectorAll('.criteria-input[data-student-id="' + studentId + '"]'));

            if (!scoreInput) return;
            const scoreVal = parseFloat(scoreInput.value);
            if (isNaN(scoreVal) || scoreVal < 0 || scoreVal > 100) {
                alert('Vui lòng nhập điểm số từ 0 đến 100.');
                scoreInput.focus();
                return;
            }
            for (const input of criteriaInputs) {
                const value = parseFloat(input.value);
                const min = parseFloat(input.min);
                const max = parseFloat(input.max);
                if (Number.isNaN(value) || value < min || value > max) {
                    alert('Vui lòng nhập đầy đủ điểm tiêu chí trong khoảng cho phép.');
                    input.focus();
                    return;
                }
            }

            const origText = btn.textContent;
            btn.textContent = 'Đang lưu...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('csrfToken', CSRF_TOKEN);
            formData.append('action', 'save_single');
            formData.append('activityId', SELECTED_ACTIVITY_ID);
            formData.append('studentId', studentId);
            formData.append('score', scoreVal.toString());
            formData.append('comment', commentInput ? commentInput.value.trim() : '');
            for (const input of criteriaInputs) {
                formData.append('criteria[' + input.dataset.criteriaId + ']', input.value);
            }
            formData.append('is_ajax', '1');

            try {
                const response = await fetch('grading.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showGradingToast(data.message || 'Đã lưu và công bố đánh giá thành công.');
                    if (currentBadge) {
                        currentBadge.textContent = Math.round(scoreVal) + '%';
                        currentBadge.style.background = '#ECFDF5';
                        currentBadge.style.color = '#047857';
                        currentBadge.style.border = '1px solid #A7F3D0';
                    }
                } else {
                    alert('Lỗi: ' + (data.message || 'Không thể lưu đánh giá.'));
                }
            } catch (err) {
                alert('Đã xảy ra lỗi kết nối khi lưu đánh giá.');
            } finally {
                btn.textContent = origText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
