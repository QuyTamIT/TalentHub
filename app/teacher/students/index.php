<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherStudentRepository;
use TalentHub\Modules\Teacher\Service\TeacherStudentService;
use TalentHub\Rbac\Service\PermissionService;

date_default_timezone_set('Asia/Ho_Chi_Minh');

function teacher_students_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function teacher_students_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'cancelled' => 'Đã huỷ',
        'attended' => 'Đã tham gia',
        'draft' => 'Nháp',
        'published' => 'Đã công bố',
        'none' => 'Chưa chấm',
        default => $status,
    };
}

function teacher_students_status_tone(string $status): string
{
    return match ($status) {
        'approved', 'attended', 'published' => 'success',
        'pending', 'draft' => 'warning',
        'rejected', 'cancelled' => 'danger',
        default => 'info',
    };
}

function teacher_students_url(array $filters, array $pagination, int $page): string
{
    $params = [];

    if ($filters['search'] !== '') {
        $params['search'] = $filters['search'];
    }
    if ($filters['activityId'] !== '') {
        $params['activityId'] = $filters['activityId'];
    }
    if ($filters['status'] !== '') {
        $params['status'] = $filters['status'];
    }
    if ((int) $pagination['perPage'] !== 20) {
        $params['perPage'] = (int) $pagination['perPage'];
    }
    if ($page > 1) {
        $params['page'] = $page;
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return '/app/teacher/students/index.php' . ($query !== '' ? '?' . $query : '');
}

use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Rbac\RoleCodes;

$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/students/index.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 3) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

$error = null;
$pageData = [
    'teacher' => [
        'full_name' => $user['fullName'] ?? 'Giáo viên TalentHub',
        'role_label' => 'Giáo viên / Hướng dẫn viên',
        'school_name' => '',
        'avatar_initials' => 'GV',
        'notification_count' => 0,
    ],
    'filters' => ['search' => '', 'activityId' => '', 'status' => ''],
    'activities' => [],
    'statuses' => ['pending', 'approved', 'rejected', 'cancelled', 'attended'],
    'summary' => ['uniqueStudents' => 0, 'totalRegistrations' => 0, 'assessedRegistrations' => 0, 'pendingRegistrations' => 0],
    'rows' => [],
    'pagination' => ['page' => 1, 'perPage' => 20, 'total' => 0, 'lastPage' => 1],
];

try {
    $config = require dirname(__DIR__, 3) . '/config/database.php';
    $pdo = (new Connection($config))->connect();
    $service = new TeacherStudentService(
        new TeacherStudentRepository($pdo),
        new PermissionService($pdo),
    );
    $pageData = $service->page((string) $user['id'], $_GET);

    // If the teacher has no activity registrations yet, show active students from
    // the teacher's school. The canonical classes schema is school-scoped and does
    // not contain the legacy `homeroomTeacherId` column.
    if (
        empty($pageData['rows'])
        && $pageData['filters']['activityId'] === ''
        && $pageData['filters']['status'] === ''
    ) {
        $searchQ = trim((string) ($_GET['search'] ?? ''));
        $sql = "
            SELECT sp.id as studentId, u.fullName, u.email, sp.phone, 
                   sp.talentScore,
                   c.name as className, spd.headline, sp.createdAt,
                   s.name as schoolName
            FROM teacher_profiles tp
            LEFT JOIN schools s ON s.id = tp.schoolId
            JOIN classes c ON c.schoolId = tp.schoolId
            JOIN student_profiles sp ON sp.classId = c.id
            JOIN users u ON u.id = sp.userId
            LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
            WHERE tp.userId = :teacherUserId
              AND c.status = 'active'
              AND sp.studyStatus = 'active'
              AND u.status = 'active'
        ";
        $params = ['teacherUserId' => (string) $user['id']];
        if ($searchQ !== '') {
            $sql .= " AND (u.fullName LIKE :q OR u.email LIKE :qEmail)";
            $params['q'] = '%' . $searchQ . '%';
            $params['qEmail'] = $params['q'];
        }
        $sql .= " ORDER BY u.fullName ASC";
        $cStmt = $pdo->prepare($sql);
        $cStmt->execute($params);
        $classStudents = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($classStudents)) {
            $classRows = [];
            $assessedCount = 0;
            foreach ($classStudents as $cs) {
                $hasScore = $cs['talentScore'] !== null;
                if ($hasScore) {
                    $assessedCount++;
                }
                $classRows[] = [
                    'studentId' => $cs['studentId'],
                    'fullName' => $cs['fullName'],
                    'email' => $cs['email'],
                    'activityTitle' => 'Lớp ' . $cs['className'],
                    'activityCategory' => ($cs['schoolName'] ?: 'Lớp học'),
                    'activityStartAt' => '2025-2026',
                    'registrationStatus' => 'approved',
                    'registeredAt' => date('d/m/Y', strtotime($cs['createdAt'] ?? 'now')),
                    'teacherActivityCount' => 1,
                    'assessmentStatus' => $hasScore ? 'published' : 'none',
                    'overallScore' => $hasScore ? number_format((float)$cs['talentScore'], 0) . '%' : 'Chưa có',
                ];
            }
            $pageData['rows'] = $classRows;
            $pageData['summary']['uniqueStudents'] = count($classRows);
            $pageData['summary']['totalRegistrations'] = count($classRows);
            $pageData['summary']['assessedRegistrations'] = $assessedCount;
            $pageData['summary']['pendingRegistrations'] = 0;
            $pageData['pagination']['total'] = count($classRows);
            $pageData['pagination']['lastPage'] = 1;
        }
    }
} catch (ApiException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log(sprintf(
        'Teacher students page failed for user %s: %s',
        (string) ($user['id'] ?? 'unknown'),
        $exception->getMessage(),
    ));
    $error = 'Không thể tải danh sách học viên lúc này.';
}

$teacherInfo = $pageData['teacher'];
$filters = $pageData['filters'];
$summary = $pageData['summary'];
$rows = $pageData['rows'];
require_once dirname(__DIR__) . '/includes/student-slide-data.php';
$studentFacts = isset($pdo) && $pdo instanceof PDO ? teacherStudentSlideFacts($pdo, $rows) : [];
$pagination = $pageData['pagination'];
$studentGroups = [];
foreach ($rows as $registrationRow) {
    $studentGroups[$registrationRow['studentId']][] = $registrationRow;
}
$teacherSlideUi = true;
$pageTitle = 'Học viên của tôi';
$currentRoute = 'students';
$todayLabel = date('d/m/Y');

$teacherSidebarHomeHref = '/index.php';
$teacherSidebarRoleHref = '/role-selection.php';
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'icon' => 'clipboard-check', 'active' => false],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => true],
];

$kpis = [
    ['label' => 'Học viên liên quan', 'value' => number_format((int) $summary['uniqueStudents']), 'change' => 'Có đăng ký trong hoạt động của tôi', 'change_type' => 'positive', 'icon' => 'users', 'status' => 'Theo phạm vi'],
    ['label' => 'Lượt đăng ký', 'value' => number_format((int) $summary['totalRegistrations']), 'change' => 'Tổng registration managed', 'change_type' => 'info', 'icon' => 'trophy', 'status' => 'Read-only'],
    ['label' => 'Đã có assessment', 'value' => number_format((int) $summary['assessedRegistrations']), 'change' => 'Do giáo viên hiện tại chấm', 'change_type' => 'positive', 'icon' => 'clipboard-check', 'status' => 'Đúng scope'],
    ['label' => 'Chờ duyệt', 'value' => number_format((int) $summary['pendingRegistrations']), 'change' => 'Registration pending', 'change_type' => ((int) $summary['pendingRegistrations'] > 0 ? 'warning' : 'positive'), 'icon' => 'users', 'status' => ((int) $summary['pendingRegistrations'] > 0 ? 'Cần theo dõi' : 'Ổn định')],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Danh sách học viên đã đăng ký hoạt động do giáo viên hiện tại phụ trách trên TalentHub.">
    <title>Học viên của tôi | TalentHub</title>

    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
    <link rel="stylesheet" href="../../../assets/css/typeui-selects.css">
    <link rel="stylesheet" href="<?= app_href('/assets/css/teacher-slide.css'); ?>">
</head>
<body class="teacher-dashboard teacher-slide-ui">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="slide-layout">
        <?php require dirname(__DIR__) . '/includes/slide-sidebar.php'; ?>

        <div class="slide-main-wrapper">
            <main class="slide-main-content" id="main-content">
                <section class="teacher-slide-page-header">
                    <div class="teacher-slide-page-header__content">
                        <span class="teacher-slide-page-header__eyebrow">KHU VỰC GIÁO VIÊN</span>
                        <h1 class="teacher-slide-page-header__title">Học viên của tôi</h1>
                    </div>
                    <span class="slide-pill slide-pill--students">HỌC VIÊN CỦA TÔI</span>
                </section>

                <section class="teacher-section-box teacher-students-panel">
                        <div class="teacher-section-box__header teacher-students-panel__header">
                            <div>
                                <h2 class="teacher-section-box__title"><?= number_format((int) $summary['uniqueStudents']); ?> học viên</h2>
                                <p class="teacher-section-box__subtitle">
                                    <?= number_format((int) $pagination['total']); ?> lượt tham gia phù hợp bộ lọc
                                </p>
                            </div>
                            <span class="teacher-section-box__count">Trang <?= (int) $pagination['page']; ?> / <?= (int) $pagination['lastPage']; ?></span>
                        </div>

                        <form class="teacher-students-filters" method="get" action="./index.php">
                            <label class="teacher-students-field teacher-students-field--search">
                                <span>Tìm kiếm</span>
                                <input type="search" name="search" maxlength="100" value="<?= teacher_students_escape($filters['search']); ?>" placeholder="Họ tên hoặc email">
                            </label>
                            <label class="teacher-students-field">
                                <span>Hoạt động</span>
                                <select name="activityId" class="typeui-select typeui-select--compact">
                                    <option value="">Tất cả hoạt động</option>
                                    <?php foreach ($pageData['activities'] as $activity): ?>
                                        <option value="<?= teacher_students_escape($activity['id']); ?>" <?= $filters['activityId'] === $activity['id'] ? 'selected' : ''; ?>>
                                            <?= teacher_students_escape($activity['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="teacher-students-field">
                                <span>Trạng thái đăng ký</span>
                                <select name="status" class="typeui-select typeui-select--compact">
                                    <option value="">Tất cả trạng thái</option>
                                    <?php foreach ($pageData['statuses'] as $status): ?>
                                        <option value="<?= teacher_students_escape($status); ?>" <?= $filters['status'] === $status ? 'selected' : ''; ?>>
                                            <?= teacher_students_escape(teacher_students_status_label($status)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <input type="hidden" name="perPage" value="<?= (int) $pagination['perPage']; ?>">
                            <div class="teacher-students-filter-actions">
                                <a class="btn btn-sm btn-outline" href="./index.php">Xoá lọc</a>
                                <button class="btn btn-sm btn-primary" type="submit">Lọc</button>
                            </div>
                        </form>

                        <?php if ($error !== null): ?>
                            <div class="teacher-students-alert" role="alert"><?= teacher_students_escape($error); ?></div>
                        <?php elseif ($rows === []): ?>
                            <div class="teacher-empty-state">
                                <div class="teacher-empty-state__icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <h4 class="teacher-empty-state__title">Chưa có học viên trong phạm vi này</h4>
                                <p class="teacher-empty-state__desc">Dữ liệu sẽ xuất hiện khi học viên đăng ký hoạt động do giáo viên hiện tại tạo.</p>
                            </div>
                        <?php else: ?>
                            <div class="teacher-students-table-wrap">
                                <table class="teacher-students-table">
                                    <thead>
                                        <tr><th>Học viên</th><th>Lớp</th><th>Điểm năng lực</th><th>Giờ trải nghiệm</th><th>Huy hiệu</th><th>Năng khiếu / kỹ năng</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($studentGroups as $studentRegistrations): $row = $studentRegistrations[0]; $facts = $studentFacts[$row['studentId']] ?? []; ?>
                                        <tr>
                                            <td data-label="Học viên">
                                                <div class="teacher-student-identity">
                                                    <span class="teacher-student-avatar" aria-hidden="true"><?= teacher_students_escape(mb_strtoupper(mb_substr(trim($row['fullName']), 0, 1))); ?></span>
                                                    <div><strong><?= teacher_students_escape($row['fullName']); ?></strong>
                                                        <details class="teacher-student-context"><summary>Thông tin tham gia</summary>
                                                            <span><?= teacher_students_escape($row['email']); ?></span>
                                                            <?php foreach ($studentRegistrations as $registration): ?>
                                                            <span><?= teacher_students_escape($registration['activityTitle']); ?> · <?= teacher_students_escape(teacher_students_status_label($registration['registrationStatus'])); ?></span>
                                                            <?php endforeach; ?>
                                                            <span><?= teacher_students_escape(teacher_students_status_label($row['registrationStatus'])); ?> · <?= (int) $row['teacherActivityCount']; ?> hoạt động</span>
                                                            <span>Đánh giá: <?= teacher_students_escape(teacher_students_status_label($row['assessmentStatus'])); ?> · <?= teacher_students_escape($row['overallScore'] ?? 'Chưa có điểm'); ?></span>
                                                        </details>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Lớp"><?= teacher_students_escape($facts['className'] ?? '—'); ?></td>
                                            <td data-label="Điểm năng lực"><strong class="teacher-student-score"><?= isset($facts['talentScore']) ? number_format((float) $facts['talentScore'], 0) : '—'; ?></strong></td>
                                            <td data-label="Giờ trải nghiệm"><?= isset($facts['experienceHours']) ? number_format((float) $facts['experienceHours'], 1) . 'h' : '—'; ?></td>
                                            <td data-label="Huy hiệu"><span class="teacher-student-badges"><?= isset($facts['badgeCount']) ? '★ ' . (int) $facts['badgeCount'] : '—'; ?></span></td>
                                            <td data-label="Năng khiếu / kỹ năng">
                                                <div class="teacher-student-skills">
                                                <?php foreach (array_slice($facts['skills'] ?? [], 0, 3) as $skill): ?>
                                                    <span class="teacher-slide-tag"><?= teacher_students_escape($skill); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (empty($facts['skills'])): ?><span class="teacher-students-muted">Chưa cập nhật</span><?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <nav class="teacher-students-pagination" aria-label="Phân trang học viên">
                                <div class="teacher-students-pagination__meta">
                                    <?= number_format((int) $pagination['total']); ?> kết quả · <?= (int) $pagination['perPage']; ?> / trang
                                </div>
                                <div class="teacher-students-pagination__actions">
                                    <?php if ((int) $pagination['page'] > 1): ?>
                                        <a class="btn btn-sm btn-outline" href="<?= teacher_students_escape(teacher_students_url($filters, $pagination, (int) $pagination['page'] - 1)); ?>">Trước</a>
                                    <?php endif; ?>
                                    <?php if ((int) $pagination['page'] < (int) $pagination['lastPage']): ?>
                                        <a class="btn btn-sm btn-outline" href="<?= teacher_students_escape(teacher_students_url($filters, $pagination, (int) $pagination['page'] + 1)); ?>">Sau</a>
                                    <?php endif; ?>
                                </div>
                            </nav>
                        <?php endif; ?>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <div class="slide-footer-gradient"></div>

    <div class="teacher-toast" id="teacher-toast" aria-live="polite" aria-atomic="true">
        <div class="teacher-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="teacher-toast__message">Tính năng đang được phát triển.</span>
        </div>
    </div>

    <script src="../../../assets/js/teacher.js"></script>
</body>
</html>
