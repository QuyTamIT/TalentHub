<?php
/**
 * TalentHub - Teacher Portal: Chấm điểm Đồ án & Đánh giá Năng lực theo Lớp
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Rbac\RoleCodes;

date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Xác thực Giảng viên (Bỏ qua hoàn toàn kiểm tra hoạt động / sân chơi cũ)
$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/grading.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 2) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

$config = require dirname(__DIR__, 2) . '/config/database.php';
$pdo = (new Connection($config))->connect();

// 2. Xử lý Lưu điểm (AJAX hoặc Form POST)
$flash = $_SESSION['teacherGradingFlash'] ?? null;
unset($_SESSION['teacherGradingFlash']);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf($_POST['csrfToken'] ?? null);
    $action = $_POST['action'] ?? 'save_single';

    if ($action === 'save_single') {
        $studentId = trim((string) ($_POST['studentId'] ?? ''));
        $rawScore = trim((string) ($_POST['score'] ?? '85'));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $score = max(0.0, min(100.0, (float) $rawScore));

        if (!empty($studentId)) {
            // Cập nhật điểm tổng quan trong student_profiles
            try {
                $upd = $pdo->prepare("UPDATE student_profiles SET talentScore = ?, updatedAt = NOW() WHERE id = ?");
                $upd->execute([$score, $studentId]);

                // Nếu có kỹ năng cụ thể được chỉ định, chỉ cập nhật đúng kỹ năng đó
                $specificSkillId = trim((string) ($_POST['skillId'] ?? ''));
                if (!empty($specificSkillId)) {
                    $updSkills = $pdo->prepare("UPDATE student_skills SET levelScore = ?, updatedAt = NOW() WHERE studentId = ? AND skillId = ?");
                    $updSkills->execute([$score, $studentId, $specificSkillId]);
                }
            } catch (\Throwable $e) {}

            // Lấy tên sinh viên
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
                    'message' => "Đã lưu điểm {$score} cho sinh viên {$stName} thành công.",
                    'score' => number_format($score, 0),
                    'studentId' => $studentId
                ]);
                exit;
            }

            $_SESSION['teacherGradingFlash'] = "Đã lưu điểm đánh giá {$score} cho sinh viên {$stName} thành công.";
            header('Location: ' . app_href('/app/teacher/grading.php'));
            exit;
        }
    } elseif ($action === 'save_batch') {
        $scores = $_POST['scores'] ?? [];
        $comments = $_POST['comments'] ?? [];
        $count = 0;

        foreach ($scores as $sId => $rawScore) {
            $score = max(0.0, min(100.0, (float) $rawScore));
            try {
                $upd = $pdo->prepare("UPDATE student_profiles SET talentScore = ?, updatedAt = NOW() WHERE id = ?");
                $upd->execute([$score, $sId]);

                // Không cập nhật đè điểm chung lên toàn bộ student_skills
            } catch (\Throwable $e) {}
            $count++;
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => "Đã lưu điểm cho {$count} sinh viên thành công."
            ]);
            exit;
        }

        $targetClass = trim((string) ($_POST['class_name'] ?? ''));
        $classSuffix = $targetClass !== '' ? " lớp {$targetClass}" : '';
        $_SESSION['teacherGradingFlash'] = "Đã lưu điểm đánh giá năng lực cho {$count} sinh viên{$classSuffix} thành công.";
        $redir = app_href('/app/teacher/grading.php') . ($targetClass !== '' ? '?class=' . urlencode($targetClass) : '');
        header('Location: ' . $redir);
        exit;
    }
}

// 3. Đọc danh sách lớp học và danh sách sinh viên theo lớp được chọn
$classList = [];
try {
    $classStmt = $pdo->query("
        SELECT c.id, c.name, COUNT(sp.id) AS studentCount
        FROM classes c
        LEFT JOIN student_profiles sp ON sp.classId = c.id AND sp.studyStatus = 'active'
        WHERE c.schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'
           OR c.name LIKE '%BTEC%'
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    $classList = $classStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $classList = [];
}

$requestedClass = trim((string) ($_GET['class'] ?? ($_GET['class_id'] ?? '')));
$selectedClass = null;

foreach ($classList as $c) {
    if ($requestedClass !== '' && ($c['id'] === $requestedClass || $c['name'] === $requestedClass)) {
        $selectedClass = $c;
        break;
    }
}
if ($selectedClass === null) {
    foreach ($classList as $c) {
        if (str_contains($c['name'], 'BTEC-AI')) {
            $selectedClass = $c;
            break;
        }
    }
}
if ($selectedClass === null && !empty($classList)) {
    $selectedClass = $classList[0];
}

$activeClassId = $selectedClass['id'] ?? 'a1e2894b-2386-5404-9695-78a78f5a60d3';
$activeClassName = $selectedClass['name'] ?? 'BTEC-AI-2026A';

$students = [];
try {
    $stStmt = $pdo->prepare("
        SELECT sp.id as studentId, u.fullName, u.email,
               COALESCE(c.name, :activeClassName) as className,
               sp.talentScore
        FROM student_profiles sp
        JOIN users u ON u.id = sp.userId
        LEFT JOIN classes c ON c.id = sp.classId
        WHERE sp.studyStatus = 'active'
          AND sp.classId = :activeClassId
        ORDER BY (u.fullName LIKE '%Vũ Đức Anh%') DESC, (u.fullName LIKE '%Lê Quý Tam%') DESC, u.fullName ASC
    ");
    $stStmt->execute([
        'activeClassId' => $activeClassId,
        'activeClassName' => $activeClassName,
    ]);
    $students = $stStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $students = [];
}

$pageTitle = 'Chấm điểm theo Lớp - TalentHub';
$currentRoute = 'assessments';

$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'href' => '/app/teacher/activities/index.php', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm theo Lớp', 'route' => 'assessments', 'href' => '/app/teacher/grading.php', 'icon' => 'clipboard-check', 'active' => true],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chấm điểm theo Lớp - <?= htmlspecialchars($activeClassName); ?> | TalentHub Giảng viên</title>

    <link rel="stylesheet" href="<?= app_href('/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/brand-component.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/polish.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/teacher.css'); ?>">
    <style>
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
            width: 90px;
            padding: 0.45rem 0.6rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0F172A;
            text-align: center;
            transition: border-color 0.15s ease;
        }
        .score-input:focus {
            border-color: #2563EB;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .comment-input {
            width: 100%;
            min-width: 240px;
            padding: 0.45rem 0.75rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #1E293B;
            transition: border-color 0.15s ease;
        }
        .comment-input:focus {
            border-color: #2563EB;
            outline: none;
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
            letter-spacing: 0.02em;
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
            background: #2563EB;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            padding: 0.45rem 1rem;
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
            background: #1D4ED8;
        }
    </style>
    <link rel="stylesheet" href="<?= app_href('/assets/css/typeui-selects.css'); ?>">
</head>
<body class="teacher-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    
                    <!-- Header Section -->
                    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: #2563EB; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">
                                Phân hệ Giảng viên • Đánh giá Năng lực
                            </div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: #0F172A; margin: 0 0 0.4rem;">
                                Chấm điểm theo Lớp: <span style="color: #2563EB;"><?= htmlspecialchars($activeClassName); ?></span>
                            </h2>
                            <p style="color: #64748B; margin: 0; font-size: 0.92rem;">
                                Danh sách sinh viên thuộc lớp <strong><?= htmlspecialchars($activeClassName); ?></strong> - Cao đẳng Quốc tế BTEC FPT.
                            </p>
                        </div>

                        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                            <div class="typeui-select-shell" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.75rem;">
                                <label for="classFilterSelect" style="font-size: 0.85rem; font-weight: 700; color: #475569; margin: 0; white-space: nowrap;">Chọn Lớp:</label>
                                <select id="classFilterSelect" class="typeui-select typeui-select--bare" onchange="location.href='grading.php?class=' + encodeURIComponent(this.value)">
                                    <?php foreach ($classList as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']); ?>" <?= $c['name'] === $activeClassName ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($c['name']); ?> (<?= (int)$c['studentCount']; ?> SV)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <span class="class-badge" style="padding: 0.5rem 0.85rem; font-size: 0.9rem;">
                                Sĩ số: <?= count($students); ?> sinh viên
                            </span>
                            <button type="button" onclick="document.getElementById('batchGradingForm').submit()" class="btn btn-primary" style="font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                <span>Lưu toàn bộ lớp</span>
                            </button>
                        </div>
                    </div>

                    <!-- Flash Message -->
                    <?php if ($flash): ?>
                        <div style="background: #ECFDF5; border: 1px solid #10B981; color: #047857; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span><?= htmlspecialchars($flash); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Direct Students Grading Table -->
                    <?php if (empty($students)): ?>
                        <div style="text-align: center; color: var(--text-muted); padding: 4rem 1.5rem; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" style="margin-bottom: 0.75rem;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0F172A; margin-bottom: 0.5rem;">Chưa có sinh viên cần đánh giá</h3>
                            <p style="font-size: 0.875rem; color: #64748B; margin-bottom: 0;">Hiện tại danh sách lớp chưa có sinh viên hoặc sinh viên chưa được phân bổ vào lớp học.</p>
                        </div>
                    <?php else: ?>
                    <section class="teacher-section-box" style="padding: 0; overflow: hidden;">
                        <form id="batchGradingForm" method="post" action="grading.php">
                            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken()); ?>">
                            <input type="hidden" name="action" value="save_batch">
                            <input type="hidden" name="class_name" value="<?= htmlspecialchars($activeClassName); ?>">

                            <div style="overflow-x: auto;">
                                <table class="grading-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px; text-align: center;">STT</th>
                                            <th>Họ tên</th>
                                            <th style="width: 160px;">Lớp</th>
                                            <th style="width: 120px; text-align: center;">Điểm hiện tại</th>
                                            <th style="width: 130px; text-align: center;">Điểm mới (0-100)</th>
                                            <th>Nhận xét kỹ năng</th>
                                            <th style="width: 120px; text-align: center;">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $idx => $st): 
                                            $words = preg_split('/\s+/', trim($st['fullName']));
                                            $initials = count($words) > 1 
                                                ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
                                                : 'SV';
                                        ?>
                                            <tr id="row-<?= htmlspecialchars($st['studentId']); ?>">
                                                <td style="font-weight: 600; color: #94A3B8; text-align: center;">
                                                    <?= $idx + 1; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #DBEAFE; color: #1D4ED8; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                                            <?= htmlspecialchars($initials); ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-weight: 700; color: #0F172A; font-size: 0.95rem;">
                                                                <?= htmlspecialchars($st['fullName']); ?>
                                                            </div>
                                                            <div style="font-size: 0.75rem; color: #64748B;">
                                                                <?= htmlspecialchars($st['email'] ?? 'student@btec.fpt.edu.vn'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="class-badge">
                                                        <?= htmlspecialchars($st['className'] ?? 'BTEC-AI-2026A'); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <?php if ($st['talentScore'] !== null): ?>
                                                        <span id="current-score-<?= htmlspecialchars($st['studentId']); ?>" class="talent-score-badge">
                                                            <?= number_format((float) $st['talentScore'], 0); ?>%
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
                                                           value="<?= $st['talentScore'] !== null ? number_format((float) $st['talentScore'], 0) : '90'; ?>"
                                                           placeholder="Điểm"
                                                           required>
                                                </td>
                                                <td>
                                                    <input type="text" 
                                                           name="comments[<?= htmlspecialchars($st['studentId']); ?>]" 
                                                           id="comment-input-<?= htmlspecialchars($st['studentId']); ?>"
                                                           class="comment-input" 
                                                           placeholder="Nhập nhận xét kỹ năng...">
                                                </td>
                                                <td style="text-align: center;">
                                                    <button type="button" 
                                                            onclick="saveRowGrade('<?= htmlspecialchars($st['studentId']); ?>')" 
                                                            class="btn-save-row" 
                                                            id="btn-save-<?= htmlspecialchars($st['studentId']); ?>">
                                                        Lưu điểm
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

            if (!scoreInput) return;
            const scoreVal = parseFloat(scoreInput.value);
            if (isNaN(scoreVal) || scoreVal < 0 || scoreVal > 100) {
                alert('Vui lòng nhập điểm số từ 0 đến 100.');
                scoreInput.focus();
                return;
            }

            const origText = btn.textContent;
            btn.textContent = 'Đang lưu...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('csrfToken', CSRF_TOKEN);
            formData.append('action', 'save_single');
            formData.append('studentId', studentId);
            formData.append('score', scoreVal.toString());
            formData.append('comment', commentInput ? commentInput.value.trim() : '');
            formData.append('is_ajax', '1');

            try {
                const res = await fetch('grading.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    if (currentBadge) {
                        currentBadge.textContent = Math.round(scoreVal) + '%';
                        currentBadge.style.background = '#DBEAFE';
                        currentBadge.style.color = '#1E40AF';
                    }
                    showGradingToast(data.message || 'Đã lưu điểm thành công.');
                } else {
                    alert(data.message || 'Không thể lưu điểm.');
                }
            } catch (err) {
                console.error(err);
                document.getElementById('batchGradingForm').submit();
            } finally {
                btn.textContent = origText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
