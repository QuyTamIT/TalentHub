<?php
/**
 * TalentHub - School Dashboard Students Page
 * Danh sách sinh viên / học sinh của nhà trường + lọc theo lớp / chuyên ngành + import Excel/CSV.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Support\Uuid;

// 1. Handle Template Download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_danh_sach_sinh_vien.csv');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Họ và tên', 'Email', 'Mã lớp', 'Chuyên ngành', 'Số điện thoại', 'Ngày sinh']);
    fputcsv($out, ['Nguyễn Hoàng Long', 'long.nh@student.btec.fpt.edu.vn', 'BTEC-AI-2026A', 'Kỹ thuật Phần mềm & Trí tuệ nhân tạo AI', '0981234567', '2005-03-15']);
    fputcsv($out, ['Phan Thị Thanh Hằng', 'hang.ptt@student.btec.fpt.edu.vn', 'BTEC-SE-2026A', 'Lập trình Fullstack Web & Mobile', '0972345678', '2005-07-20']);
    fputcsv($out, ['Đặng Quốc Huy', 'huy.dq@student.ctu.edu.vn', 'K47 Quản trị Kinh doanh', 'Quản trị Kinh doanh & Chuỗi cung ứng', '0913456789', '2004-11-05']);
    fclose($out);
    exit;
}

$appContext = new SchoolAppContext();
$context = $appContext->boot();
$service = $context['service'];
$userId  = $context['user']['id'];
$session = $context['session'];
$pdo     = $context['pdo'];
$schoolId = $context['school']['id'];

// 2. Handle Student Import POST
$importFlash = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'import_students') {
    $session->assertCsrf(is_string($_POST['csrfToken'] ?? null) ? $_POST['csrfToken'] : null);
    
    $rows = [];
    if (isset($_FILES['student_file']) && is_uploaded_file($_FILES['student_file']['tmp_name'])) {
        $tmp = $_FILES['student_file']['tmp_name'];
        $content = file_get_contents($tmp);
        if ($content !== false) {
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // remove UTF-8 BOM
            $lines = preg_split('/\r\n|\r|\n/', trim($content));
            $header = null;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $cols = str_getcsv($line);
                if ($header === null) {
                    $header = array_map('trim', $cols);
                    continue;
                }
                if (count($cols) >= 2) {
                    $rows[] = $cols;
                }
            }
        }
    } elseif (!empty($_POST['csv_raw'])) {
        $content = trim((string)$_POST['csv_raw']);
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $header = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $cols = str_getcsv($line);
            if ($header === null && (stripos($line, 'email') !== false || stripos($line, 'họ') !== false)) {
                $header = array_map('trim', $cols);
                continue;
            }
            if (count($cols) >= 2) {
                $rows[] = $cols;
            }
        }
    }

    if (!empty($rows)) {
        $importCount = 0;
        $errorCount = 0;
        $defaultPasswordHash = password_hash('123456', PASSWORD_BCRYPT);
        $studentRoleIdStmt = $pdo->query("SELECT id FROM roles WHERE code = 'student' LIMIT 1");
        $studentRoleId = (string) ($studentRoleIdStmt->fetchColumn() ?: '10000000-0000-4000-8000-000000000010');

        foreach ($rows as $row) {
            $fullName = trim((string)($row[0] ?? ''));
            $email = trim(strtolower((string)($row[1] ?? '')));
            $className = trim((string)($row[2] ?? 'BTEC-AI-2026A'));
            $major = trim((string)($row[3] ?? 'Công nghệ thông tin'));
            $phone = trim((string)($row[4] ?? ''));
            $dob = trim((string)($row[5] ?? '2005-01-01'));

            if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorCount++;
                continue;
            }

            // Find or create class in this school
            $classStmt = $pdo->prepare("SELECT id FROM classes WHERE schoolId = ? AND name = ? LIMIT 1");
            $classStmt->execute([$schoolId, $className]);
            $targetClassId = $classStmt->fetchColumn();
            if (!$targetClassId) {
                $targetClassId = Uuid::v4();
                $pdo->prepare("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt) VALUES (?, ?, ?, 1, '2025-2026', 'active', NOW(), NOW())")
                    ->execute([$targetClassId, $schoolId, $className]);
            }

            // Find or create user
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $userStmt->execute([$email]);
            $targetUserId = $userStmt->fetchColumn();
            if (!$targetUserId) {
                $targetUserId = Uuid::v4();
                $pdo->prepare("INSERT INTO users (id, email, fullName, passwordHash, roleId, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())")
                    ->execute([$targetUserId, $email, $fullName, $defaultPasswordHash, $studentRoleId]);
            } else {
                $pdo->prepare("UPDATE users SET fullName = ?, status = 'active' WHERE id = ?")
                    ->execute([$fullName, $targetUserId]);
            }

            // Find or create student_profiles
            $spStmt = $pdo->prepare("SELECT id FROM student_profiles WHERE userId = ? LIMIT 1");
            $spStmt->execute([$targetUserId]);
            $targetSpId = $spStmt->fetchColumn();
            if (!$targetSpId) {
                $targetSpId = Uuid::v4();
                $pdo->prepare("INSERT INTO student_profiles (id, userId, classId, phone, dateOfBirth, studyStatus, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())")
                    ->execute([$targetSpId, $targetUserId, $targetClassId, $phone, $dob]);
            } else {
                $pdo->prepare("UPDATE student_profiles SET classId = ?, phone = ?, studyStatus = 'active' WHERE id = ?")
                    ->execute([$targetClassId, $phone, $targetSpId]);
            }

            // Find or create student_profile_details
            $spdStmt = $pdo->prepare("SELECT id FROM student_profile_details WHERE studentId = ? LIMIT 1");
            $spdStmt->execute([$targetSpId]);
            if ($spdStmt->fetchColumn()) {
                $pdo->prepare("UPDATE student_profile_details SET headline = ? WHERE studentId = ?")
                    ->execute([$major, $targetSpId]);
            } else {
                $pdo->prepare("INSERT INTO student_profile_details (id, studentId, headline, bio, createdAt, updatedAt) VALUES (?, ?, ?, ?, NOW(), NOW())")
                    ->execute([Uuid::v4(), $targetSpId, $major, "Sinh viên chuyên ngành {$major} - {$context['school']['name']}"]);
            }

            $importCount++;
        }

        $flashMsg = "Đã import thành công {$importCount} sinh viên vào nhà trường!";
        if ($errorCount > 0) {
            $flashMsg .= " (Bỏ qua {$errorCount} dòng sai email/họ tên)";
        }
        header('Location: ' . app_href('/app/school/students.php?msg=imported&msg_text=' . urlencode($flashMsg)));
        exit;
    }
}

// 3. Query Students List
$classFilter = $_GET['classId'] ?? null;
if ($classFilter !== null && !Uuid::isValid($classFilter)) {
    $classFilter = null;
}

$perPage = max(10, min(100, (int) ($_GET['perPage'] ?? 25)));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$students = $service->students($userId, $perPage, $offset, $classFilter);
$totalStudents = $service->studentCount($userId, $classFilter);

// 3.1. Enrich student records with profile details (headline, bio) and skills
$studentIds = array_column($students, 'id');
$detailsMap = [];
$skillsMap  = [];
if (!empty($studentIds)) {
    $inClause = implode(',', array_fill(0, count($studentIds), '?'));

    $spdStmt = $pdo->prepare("SELECT studentId, headline, bio, location, avatarUrl FROM student_profile_details WHERE studentId IN ($inClause)");
    $spdStmt->execute($studentIds);
    foreach ($spdStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $detailsMap[$row['studentId']] = $row;
    }

    $skStmt = $pdo->prepare("
        SELECT ss.studentId, s.name as skillName, ss.levelScore, ss.verificationStatus
        FROM student_skills ss
        JOIN skills s ON s.id = ss.skillId
        WHERE ss.studentId IN ($inClause)
        ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC
    ");
    $skStmt->execute($studentIds);
    foreach ($skStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $skillsMap[$row['studentId']][] = [
            'name'     => $row['skillName'],
            'score'    => (int) ($row['levelScore'] ?? 85),
            'verified' => ($row['verificationStatus'] === 'verified'),
        ];
    }
}
$schoolPrefix = stripos($context['school']['name'], 'Cần Thơ') !== false ? 'CTU-' : 'BTEC-';

$classes = $service->classesWithArchived($userId);

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/students.php';
$pageTitle    = 'Học sinh / Sinh viên';

$baseQuery = http_build_query(array_filter([
    'classId' => $classFilter,
    'perPage' => $perPage !== 25 ? $perPage : null,
]));

ob_start();
?>
<?php
$pageDescription = 'Quản lý danh sách sinh viên toàn trường, phân lớp chuyên ngành và import dữ liệu Excel / CSV.';
$pageActions = '
<div style="display: flex; gap: 0.75rem; align-items: center;">
    <button type="button" onclick="openImportModal()" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span>Import Excel / CSV</span>
    </button>
    <a href="./student-edit.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:0.45rem;font-weight:600;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>
        <span>Thêm sinh viên</span>
    </a>
</div>';
include __DIR__ . '/includes/page-banner.php';
?>

<div class="school-section-box">
    <div class="school-section-box__header">
        <p class="school-section-box__subtitle">
            <strong><?= $totalStudents; ?> sinh viên</strong> <?= $classFilter ? '(trong lớp / chuyên ngành đã chọn)' : '(toàn trường)'; ?>
        </p>
        <form method="get">
            <select name="classId" onchange="this.form.submit()" class="school-inline-select" aria-label="Lọc theo lớp">
                <option value="">Tất cả lớp & chuyên ngành</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= htmlspecialchars($c['id']); ?>" <?= $classFilter === $c['id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($c['name']); ?> (<?= htmlspecialchars($c['grade']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="perPage" value="<?= $perPage; ?>">
        </form>
    </div>
    <?php if ($students === []): ?>
        <div style="text-align: center; color: var(--text-muted); padding: 3.5rem 1rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" style="margin-bottom: 0.75rem;">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <p style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem;">Chưa có sinh viên nào trong danh sách. Vui lòng thêm mới hoặc Import file Excel.</p>
            <p style="font-size: 0.875rem; color: #64748B;">Sử dụng nút <strong></strong> ở trên hoặc tính năng <strong>Import Excel / CSV</strong> để bắt đầu quản lý hồ sơ sinh viên.</p>
        </div>Thêm sinh viên
    <?php else: ?>
        <table class="school-class-table">
            <thead>
                <tr>
                    <th>Họ và tên</th>
                    <th>Email</th>
                    <th>Lớp / Chuyên ngành</th>
                    <th>SĐT</th>
                    <th>Trạng thái</th>
                    <th style="text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): 
                    $sid = $s['id'];
                    $d   = $detailsMap[$sid] ?? [];
                    $sk  = $skillsMap[$sid] ?? [];

                    $studentCode = !empty($s['studentCode']) ? $s['studentCode'] : '';

                    // Headline / Chuyên ngành
                    $headline = !empty($d['headline']) ? $d['headline'] : '';
                    if ($headline === '') {
                        if (stripos($s['className'], 'AI') !== false) {
                            $headline = 'Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)';
                        } elseif (stripos($s['className'], 'SE') !== false) {
                            $headline = 'Kỹ thuật phần mềm & Lập trình Web Frontend/Backend';
                        } elseif (stripos($s['className'], 'Kinh doanh') !== false || stripos($s['className'], 'QTKD') !== false) {
                            $headline = 'Quản trị Kinh doanh & Chuỗi cung ứng FMCG';
                        } elseif (stripos($s['className'], 'KDQT') !== false || stripos($s['className'], 'Quốc tế') !== false) {
                            $headline = 'Kinh doanh Quốc tế & Xuất nhập khẩu';
                        } elseif (stripos($s['className'], 'CNTT') !== false) {
                            $headline = 'Công nghệ Thông tin & Kỹ thuật Phần mềm';
                        } else {
                            $headline = 'Chuyên ngành ' . $s['className'];
                        }
                    }

                    // Skills badges
                    $skillBadges = [];
                    if (!empty($sk)) {
                        foreach ($sk as $item) {
                            $skillBadges[] = $item['name'];
                        }
                    }

                    // Talent score (%)
                    $talentScore = null;
                    if (!empty($sk)) {
                        $scores = array_column($sk, 'score');
                        $talentScore = (int) round(array_sum($scores) / count($scores));
                    }

                    $bio = !empty($d['bio']) ? $d['bio'] : "Sinh viên năng động, có năng lực tự học và tư duy giải quyết vấn đề tốt. Luôn tích cực tham gia các dự án thực hành và sẵn sàng thử sức tại các kỳ thực tập doanh nghiệp.";

                    $studentPayload = [
                        'id'               => $s['id'],
                        'fullName'         => $s['fullName'],
                        'email'            => $s['email'],
                        'phone'            => $s['phone'] ?: 'Chưa cập nhật',
                        'studentCode'      => $studentCode,
                        'className'        => $s['className'],
                        'schoolName'       => $context['school']['name'],
                        'academicYear'     => $context['school']['academicYear'] ?? '2025 - 2026',
                        'headline'         => $headline,
                        'bio'              => $bio,
                        'skills'           => $skillBadges,
                        'talentScore'      => $talentScore,
                        'studyStatus'      => ($s['studyStatus'] === 'active' ? 'Đang theo học' : $s['studyStatus']),
                        'internshipStatus' => 'Sẵn sàng thực tập',
                        'editUrl'          => './student-edit.php?id=' . urlencode($s['id']),
                    ];
                    $jsonPayload = json_encode($studentPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                ?>
                    <tr>
                        <td>
                            <a href="javascript:void(0)" 
                               onclick='openStudentDetail(<?= $jsonPayload; ?>)'
                               class="school-student-name-link"
                               title="Xem chi tiết hồ sơ sinh viên">
                                <strong><?= htmlspecialchars($s['fullName']); ?></strong>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="school-link-icon" aria-hidden="true">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </td>
                        <td><span style="font-size: 0.875rem; color: var(--text-secondary);"><?= htmlspecialchars($s['email']); ?></span></td>
                        <td>
                            <span class="school-class-name-badge">
                                <?= htmlspecialchars($s['className']); ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($s['phone'] ?: '—'); ?></td>
                        <td>
                            <span class="school-class-badge school-class-badge--<?= $s['studyStatus'] === 'active' ? 'success' : 'warning'; ?>">
                                <?= htmlspecialchars($s['studyStatus'] === 'active' ? 'Đang theo học' : $s['studyStatus']); ?>
                            </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button type="button" 
                                    onclick='openStudentDetail(<?= $jsonPayload; ?>)'
                                    class="btn btn-sm btn-primary" 
                                    style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; cursor: pointer; padding: 0.35rem 0.75rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <span>Chi tiết</span>
                            </button>
                            <a href="./student-edit.php?id=<?= urlencode($s['id']); ?>" class="btn btn-sm btn-outline" style="text-decoration:none; margin-left: 0.4rem; padding: 0.35rem 0.6rem;">Sửa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalStudents > $perPage): ?>
            <nav class="school-pagination" aria-label="Phân trang">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-sm btn-outline">‹ Trước</a>
                <?php endif; ?>
                <span class="school-pagination__info">Trang <?= $page; ?> · <?= $perPage; ?> / trang</span>
                <?php if (($offset + count($students)) < $totalStudents): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-sm btn-outline">Sau ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal Import Excel / CSV -->
<div id="importModal" class="school-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="school-modal">
        <div class="school-modal__header">
            <div>
                <h3 class="school-modal__title" id="modalTitle">Import Danh Sách Sinh Viên (Excel / CSV)</h3>
                <p class="school-modal__desc">Nhập danh sách sinh viên nhanh chóng từ file Excel (.xlsx, .csv) vào hệ thống trường.</p>
            </div>
            <button type="button" onclick="closeImportModal()" class="school-modal__close" aria-label="Đóng modal">×</button>
        </div>

        <form method="post" enctype="multipart/form-data" class="school-modal__form">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="import_students">

            <!-- Step 1: Download Template -->
            <div class="school-modal__template-box">
                <div class="school-modal__template-info">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <div>
                        <strong>File mẫu chuẩn TalentHub (.CSV / Excel)</strong>
                        <p>Bao gồm các cột: Họ và tên, Email, Mã lớp, Chuyên ngành, Số điện thoại, Ngày sinh.</p>
                    </div>
                </div>
                <a href="?download_template=1" class="btn btn-sm btn-outline" style="white-space:nowrap;display:inline-flex;align-items:center;gap:0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Tải file mẫu
                </a>
            </div>

            <!-- Step 2: Upload File Area -->
            <div class="school-modal__dropzone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:0.5rem;">
                    <polyline points="16 16 12 12 8 16"></polyline>
                    <line x1="12" y1="12" x2="12" y2="21"></line>
                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                    <polyline points="16 16 12 12 8 16"></polyline>
                </svg>
                <div id="dropZoneText">
                    <strong>Chọn file Excel (.xlsx, .csv) từ máy tính</strong>
                    <p style="font-size:0.8125rem;color:var(--text-muted);margin-top:0.25rem;">hoặc kéo thả file vào đây (Dung lượng tối đa 5MB)</p>
                </div>
                <input type="file" id="fileInput" name="student_file" accept=".csv, .xlsx, .xls, text/csv" style="display:none;" onchange="handleFileSelected(this)">
            </div>

            <!-- Optional: CSV Text Area (Alternative) -->
            <details style="margin-top:1rem;font-size:0.875rem;color:var(--text-secondary);">
                <summary style="cursor:pointer;font-weight:600;color:#2563EB;">Hoặc dán trực tiếp dữ liệu CSV</summary>
                <div style="margin-top:0.5rem;">
                    <textarea name="csv_raw" rows="4" class="school-inline-textarea" placeholder="Họ và tên,Email,Mã lớp,Chuyên ngành,Số điện thoại,Ngày sinh
Nguyễn Văn A,nguyenvana@student.edu.vn,BTEC-AI-2026A,AI & Robotics,0901234567,2005-01-01"></textarea>
                </div>
            </details>

            <div class="school-modal__footer">
                <button type="button" onclick="closeImportModal()" class="btn btn-outline">Hủy bỏ</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitImport" style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Bắt đầu Import
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Chi Tiết Hồ Sơ Sinh Viên -->
<div id="studentDetailModal" class="school-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="sdModalTitle">
    <div class="school-modal school-modal--detail" style="max-width: 700px; border-radius: 16px; overflow: hidden; padding: 0; background: #FFFFFF;">
        <!-- Header with identity banner -->
        <div style="background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%); padding: 1.35rem 1.65rem; color: #FFFFFF; position: relative;">
            <button type="button" onclick="closeStudentDetailModal()" class="school-modal__close" style="position: absolute; top: 0.85rem; right: 1rem; color: #FFFFFF; font-size: 1.75rem; background: none; border: none; cursor: pointer; opacity: 0.9;" aria-label="Đóng modal">×</button>
            <div style="display: flex; align-items: center; gap: 1.15rem;">
                <div id="sd_avatar" style="width: 58px; height: 58px; border-radius: 14px; background: rgba(255, 255, 255, 0.22); border: 2px solid rgba(255, 255, 255, 0.45); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; color: #FFFFFF; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); flex-shrink: 0;">
                    SV
                </div>
                <div style="min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <h3 id="sdModalTitle" style="font-size: 1.25rem; font-weight: 700; color: #FFFFFF; margin: 0;"></h3>
                        <span id="sd_code" style="font-size: 0.75rem; font-weight: 700; background: rgba(255,255,255,0.2); color: #FFFFFF; padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3);"></span>
                        <span style="font-size: 0.75rem; font-weight: 600; background: #ECFDF5; color: #047857; padding: 0.2rem 0.5rem; border-radius: 6px;">✓ Đã xác thực</span>
                    </div>
                    <div id="sd_headline" style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.92); margin-top: 0.25rem; font-weight: 500;"></div>
                </div>
            </div>
        </div>

        <!-- Body content -->
        <div style="padding: 1.5rem 1.65rem; max-height: calc(85vh - 150px); overflow-y: auto;">
            
            <!-- Quick Status Badges -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1.15rem;">
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.75rem 0.875rem; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.03em;">Trạng thái học tập</div>
                        <div id="sd_studyStatus" style="font-size: 0.9rem; font-weight: 700; color: #047857; margin-top: 0.15rem;"></div>
                    </div>
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10B981;"></span>
                </div>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.75rem 0.875rem; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.03em;">Hồ sơ tuyển dụng</div>
                        <div id="sd_internshipStatus" style="font-size: 0.9rem; font-weight: 700; color: #1D4ED8; margin-top: 0.15rem;"></div>
                    </div>
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #3B82F6;"></span>
                </div>
            </div>

            <!-- Section 1: Thông tin định danh & Đào tạo (2 cột) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.15rem;">
                <!-- Cột định danh -->
                <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem;">
                    <div style="font-size: 0.775rem; font-weight: 700; color: #1E293B; margin-bottom: 0.65rem; display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Thông tin định danh
                    </div>
                    <div style="font-size: 0.8125rem; line-height: 1.7; color: var(--text-secondary);">
                        <div><strong>Họ và tên:</strong> <span id="sd_name" style="color: var(--text-primary); font-weight: 600;"></span></div>
                        <div><strong>Mã SV:</strong> <span id="sd_code_text" style="color: var(--text-primary); font-family: monospace; font-weight: 600;"></span></div>
                        <div><strong>Email:</strong> <span id="sd_email" style="color: #2563EB;"></span></div>
                        <div><strong>Số điện thoại:</strong> <span id="sd_phone" style="color: var(--text-primary);"></span></div>
                    </div>
                </div>

                <!-- Cột đào tạo -->
                <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem;">
                    <div style="font-size: 0.775rem; font-weight: 700; color: #1E293B; margin-bottom: 0.65rem; display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path></svg>
                        Thông tin đào tạo
                    </div>
                    <div style="font-size: 0.8125rem; line-height: 1.7; color: var(--text-secondary);">
                        <div><strong>Trường đào tạo:</strong> <span id="sd_school" style="color: var(--text-primary); font-weight: 600;"></span></div>
                        <div><strong>Khóa / Niên khóa:</strong> <span id="sd_academicYear" style="color: var(--text-primary);"></span></div>
                        <div><strong>Lớp chuyên ngành:</strong> <span id="sd_class" class="school-class-name-badge" style="display:inline-block; margin-top:2px;"></span></div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Năng lực & Đánh giá -->
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem; margin-bottom: 1.15rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                    <div style="font-size: 0.775rem; font-weight: 700; color: #1E293B; display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        Năng lực & Đánh giá
                    </div>
                    <div style="font-size: 0.8125rem; font-weight: 700; color: #1D4ED8;">
                        Điểm đánh giá năng lực: <span id="sd_score" style="font-size: 1.05rem; color: #047857; font-weight: 800;"></span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="background: #E2E8F0; border-radius: 999px; height: 8px; overflow: hidden; margin-bottom: 0.85rem;">
                    <div id="sd_score_bar" style="background: linear-gradient(90deg, #3B82F6 0%, #10B981 100%); height: 100%; border-radius: 999px; transition: width 0.4s ease; width: 85%;"></div>
                </div>

                <!-- Skills tags -->
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;">KỸ NĂNG CHÍNH (SKILLS BADGES):</div>
                <div id="sd_skills_container" style="display: flex; flex-wrap: wrap; gap: 0.4rem;"></div>
            </div>

            <!-- Section 3: Giới thiệu bản thân (Bio) -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.9rem 1rem;">
                <div style="font-size: 0.775rem; font-weight: 700; color: #1E293B; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    Mục tiêu & Giới thiệu bản thân
                </div>
                <p id="sd_bio" style="font-size: 0.8125rem; line-height: 1.6; color: var(--text-secondary); margin: 0;"></p>
            </div>

        </div>

        <!-- Footer -->
        <div style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 0.85rem 1.65rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; color: var(--text-muted);">
                TalentHub Academic Portal • Dữ liệu hồ sơ sinh viên
            </span>
            <div style="display: flex; gap: 0.65rem;">
                <button type="button" onclick="closeStudentDetailModal()" class="btn btn-secondary" style="padding: 0.4rem 1rem; font-weight: 600; cursor: pointer;">
                    Đóng
                </button>
                <a id="sd_edit_btn" href="#" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 1rem; font-weight: 600; text-decoration: none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Chỉnh sửa hồ sơ
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.school-inline-select { padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); font-size: 0.875rem; font-weight: 500; }
.school-class-name-badge { display: inline-block; font-weight: 600; color: #1E293B; background: #F1F5F9; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.875rem; }

/* Clickable Student Name Link */
.school-student-name-link {
    color: #2563EB;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: color 0.15s ease-in-out;
}
.school-student-name-link:hover {
    text-decoration: underline;
    color: #1D4ED8;
}
.school-student-name-link .school-link-icon {
    opacity: 0.55;
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.school-student-name-link:hover .school-link-icon {
    opacity: 1;
    transform: translate(1px, -1px);
}

/* Skill Badge in Modal */
.school-skill-badge {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

/* Modal Styles */
.school-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
}
.school-modal {
    background: #FFFFFF;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 580px;
    overflow: hidden;
    animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.school-modal--detail {
    max-width: 700px;
}
.school-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #F1F5F9;
}
.school-modal__title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 0.25rem 0;
}
.school-modal__desc {
    font-size: 0.85rem;
    color: #64748B;
    margin: 0;
}
.school-modal__close {
    background: none;
    border: none;
    font-size: 1.75rem;
    line-height: 1;
    color: #94A3B8;
    cursor: pointer;
    padding: 0.25rem;
}
.school-modal__close:hover { color: #0F172A; }
.school-modal__form { padding: 1.5rem; }
.school-modal__template-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 0.875rem 1rem;
    margin-bottom: 1.25rem;
}
.school-modal__template-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.school-modal__template-info strong { font-size: 0.875rem; color: #1E293B; display: block; }
.school-modal__template-info p { font-size: 0.75rem; color: #64748B; margin: 0.15rem 0 0 0; }
.school-modal__dropzone {
    border: 2px dashed #93C5FD;
    background: #EFF6FF;
    border-radius: 12px;
    padding: 2rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.school-modal__dropzone:hover {
    border-color: #3B82F6;
    background: #DBEAFE;
}
.school-inline-textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-family: monospace;
    font-size: 0.8125rem;
    resize: vertical;
}
.school-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #F1F5F9;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
HTML;

$extraScripts = <<<'HTML'
<script>
// Modal Chi Tiết Sinh Viên Handlers
function openStudentDetail(student) {
    const modal = document.getElementById('studentDetailModal');
    if (!modal || !student) return;

    document.getElementById('sdModalTitle').textContent = student.fullName || 'Hồ sơ sinh viên';
    document.getElementById('sd_name').textContent = student.fullName || '';
    document.getElementById('sd_email').textContent = student.email || '';
    document.getElementById('sd_phone').textContent = student.phone || 'Chưa cập nhật';
    document.getElementById('sd_code').textContent = 'Mã SV: ' + (student.studentCode || '');
    document.getElementById('sd_code_text').textContent = student.studentCode || '';
    document.getElementById('sd_school').textContent = student.schoolName || '';
    document.getElementById('sd_academicYear').textContent = student.academicYear || '';
    document.getElementById('sd_class').textContent = student.className || '';
    document.getElementById('sd_headline').textContent = student.headline || '';
    document.getElementById('sd_bio').textContent = student.bio || '';
    document.getElementById('sd_studyStatus').textContent = student.studyStatus || 'Đang theo học';
    document.getElementById('sd_internshipStatus').textContent = student.internshipStatus || 'Sẵn sàng thực tập';

    const words = (student.fullName || 'SV').trim().split(/\s+/);
    let initials = 'SV';
    if (words.length > 1) {
        initials = (words[0][0] + words[words.length - 1][0]).toUpperCase();
    } else if (words.length === 1 && words[0].length > 0) {
        initials = words[0].substring(0, 2).toUpperCase();
    }
    document.getElementById('sd_avatar').textContent = initials;

    if (student.talentScore !== null && student.talentScore !== undefined) {
        document.getElementById('sd_score').textContent = student.talentScore + '%';
        document.getElementById('sd_score_bar').style.width = student.talentScore + '%';
    } else {
        document.getElementById('sd_score').textContent = 'Chưa đánh giá';
        document.getElementById('sd_score_bar').style.width = '0%';
    }

    const skillsCont = document.getElementById('sd_skills_container');
    skillsCont.innerHTML = '';
    const skills = student.skills || [];
    if (skills.length === 0) {
        skillsCont.innerHTML = '<span style="color:var(--text-muted);font-size:0.8125rem;">Chưa có dữ liệu kỹ năng</span>';
    } else {
        skills.forEach(skill => {
            const badge = document.createElement('span');
            badge.className = 'school-skill-badge';
            badge.textContent = '✓ ' + skill;
            skillsCont.appendChild(badge);
        });
    }

    const editBtn = document.getElementById('sd_edit_btn');
    if (editBtn) {
        editBtn.href = student.editUrl || '#';
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeStudentDetailModal() {
    const modal = document.getElementById('studentDetailModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Modal Import Handlers
function openImportModal() {
    document.getElementById('importModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
    document.body.style.overflow = '';
}
function handleFileSelected(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('dropZoneText').innerHTML = `
            <strong style="color:#0284C7;">✓ Đã chọn file: ${file.name}</strong>
            <p style="font-size:0.8125rem;color:#64748B;margin-top:0.25rem;">Kích thước: ${(file.size / 1024).toFixed(1)} KB. Bấm "Bắt đầu Import" để nạp dữ liệu.</p>
        `;
    }
}

// Global Event Listeners (Backdrop click, Escape key, Drag & drop)
document.addEventListener('DOMContentLoaded', function() {
    // Backdrop clicks
    document.addEventListener('click', function(e) {
        const sdModal = document.getElementById('studentDetailModal');
        if (e.target === sdModal) {
            closeStudentDetailModal();
        }
        const impModal = document.getElementById('importModal');
        if (e.target === impModal) {
            closeImportModal();
        }
    });

    // Escape key
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStudentDetailModal();
            closeImportModal();
        }
    });

    // Drag & drop handlers
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    if (dropZone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#2563EB';
                dropZone.style.background = '#DBEAFE';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#93C5FD';
                dropZone.style.background = '#EFF6FF';
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelected(fileInput);
            }
        });
    }

    // Auto-open student detail modal if requested in URL
    const params = new URLSearchParams(window.location.search);
    if (params.has('view_id')) {
        const targetId = params.get('view_id');
        const targetBtn = document.querySelector(`button[onclick*="${targetId}"]`);
        if (targetBtn) {
            targetBtn.click();
        }
    }

    // Flash notifications
    if (params.has('msg')) {
        const key = params.get('msg');
        const customText = params.get('msg_text');
        const map = { 
            created: 'Đã thêm sinh viên.', 
            updated: 'Đã cập nhật sinh viên.', 
            imported: customText || 'Đã import danh sách sinh viên thành công.' 
        };
        if (map[key] && typeof showSchoolToast === 'function') {
            showSchoolToast(map[key]);
        } else if (map[key]) {
            alert(map[key]);
        }
    }
});
</script>
HTML;

require __DIR__ . '/includes/layout.php';
