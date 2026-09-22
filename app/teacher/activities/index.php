<?php
/**
 * TalentHub - Teacher Activities / My Playgrounds
 */

require_once __DIR__ . '/../includes/dashboard-data.php';
require_once __DIR__ . '/../includes/activity-data.php';
require_once __DIR__ . '/../includes/cover-upload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('teacherActivitiesEscape')) {
    function teacherActivitiesEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('teacherActivitiesFormDate')) {
    function teacherActivitiesFormDate(?string $value): ?DateTimeImmutable
    {
        if (!$value || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
        $formats = [
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value, $tz);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format) === $value) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value, $tz);
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('teacherActivitiesCategoryCatalog')) {
    function teacherActivitiesCategoryCatalog(): array
    {
        return [
            'career_technical' => ['category' => 'career_technical', 'displayCategory' => 'Kỹ thuật', 'filterCategory' => 'Kỹ thuật'],
            'career_business' => ['category' => 'career_business', 'displayCategory' => 'Kinh doanh', 'filterCategory' => 'Kinh doanh'],
            'career_arts' => ['category' => 'career_arts', 'displayCategory' => 'Sáng tạo', 'filterCategory' => 'Sáng tạo'],
            'career_sports_academic' => ['category' => 'career_sports_academic', 'displayCategory' => 'Cộng đồng', 'filterCategory' => 'Cộng đồng'],
        ];
    }
}

if (!function_exists('teacherActivitiesCategoryChoice')) {
    function teacherActivitiesCategoryChoice(array $activity, array $catalog): string
    {
        foreach ($catalog as $choice => $mapping) {
            if (
                (string) ($activity['category'] ?? '') === $mapping['category']
                && (string) ($activity['displayCategory'] ?? '') === $mapping['displayCategory']
                && (string) ($activity['filterCategory'] ?? '') === $mapping['filterCategory']
            ) {
                return $choice;
            }
        }

        return '__preserve__';
    }
}

if (!function_exists('teacherActivitiesResolveCategory')) {
    function teacherActivitiesResolveCategory(string $choice, array $catalog, ?array $ownedActivity): ?array
    {
        if (isset($catalog[$choice])) {
            return $catalog[$choice];
        }

        if ($choice === '__preserve__' && $ownedActivity !== null) {
            return [
                'category' => (string) ($ownedActivity['category'] ?? ''),
                'displayCategory' => (string) ($ownedActivity['displayCategory'] ?? ''),
                'filterCategory' => (string) ($ownedActivity['filterCategory'] ?? ''),
            ];
        }

        return null;
    }
}

if (!function_exists('teacherActivitiesSummaryDate')) {
    function teacherActivitiesSummaryDate(string $value): string
    {
        $date = teacherActivitiesFormDate($value);
        return $date ? $date->format('d/m/Y H:i') : 'Chưa thiết lập';
    }
}

if (!function_exists('teacherActivitiesErrorField')) {
    function teacherActivitiesErrorField(string $message): ?string
    {
        $message = mb_strtolower($message, 'UTF-8');
        $patterns = [
            'mở đăng ký' => 'registrationOpensAt',
            'đóng đăng ký' => 'registrationClosesAt',
            'hủy đăng ký' => 'cancellationClosesAt',
            'title' => 'title',
            'tên hoạt động' => 'title',
            'nhóm hoạt động' => 'categoryChoice',
            'category' => 'categoryChoice',
            'tóm tắt' => 'summary',
            'mô tả' => 'description',
            'địa điểm' => 'locationName',
            'phòng tổ chức' => 'locationName',
            'đơn vị tổ chức' => 'organizerName',
            'bắt đầu' => 'startAt',
            'kết thúc' => 'endAt',
            'sức chứa' => 'capacity',
            'hình thức' => 'deliveryMode',
            'online' => 'onlineMeetingUrl',
            'trực tuyến' => 'onlineMeetingUrl',
            'công nhận' => 'confirmedHours',
            'giờ trải nghiệm' => 'confirmedHours',
            'cách duyệt' => 'approvalMode',
            'chi phí' => 'feeAmount',
            'tiền tệ' => 'currency',
            'email' => 'organizerEmail',
            'điện thoại' => 'organizerPhone',
            'alt' => 'coverImageAlt',
            'ảnh bìa' => 'coverFile',
            'ảnh' => 'coverFile',
            'tệp' => 'coverFile',
            'giáo viên phụ trách' => 'responsibleTeacherId',
            'kỹ năng' => 'skillIds',
            'catalog' => 'skillIds',
        ];
        foreach ($patterns as $needle => $field) {
            if (str_contains($message, $needle)) return $field;
        }
        return null;
    }
}

if (!function_exists('teacherCoverWebUrl')) {
    function teacherCoverWebUrl(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return function_exists('app_href') ? app_href('/app/learner/assets/activities/illustrations/hero-discover.svg') : '/app/learner/assets/activities/illustrations/hero-discover.svg';
        }
        $path = trim($path);
        return function_exists('app_href') ? app_href($path) : $path;
    }
}

$presetCovers = [
    [
        'category' => 'tech',
        'category_name' => 'Kỹ thuật & Công nghệ',
        'items' => [
            ['url' => '/app/learner/assets/activities/covers/fpt-ai-hacklab.webp', 'name' => 'FPT AI HackLab', 'alt' => 'FPT AI HackLab - Nghiên cứu và ứng dụng trí tuệ nhân tạo'],
            ['url' => '/app/learner/assets/activities/covers/nguyen-trai-python-robot.webp', 'name' => 'Python Robotics', 'alt' => 'Lập trình Python và điều khiển cánh tay robot'],
            ['url' => '/app/learner/assets/activities/covers/talenthub-python-workshop.webp', 'name' => 'Python Workshop', 'alt' => 'Hội thảo lập trình Python thực chiến'],
            ['url' => '/app/learner/assets/activities/covers/talenthub-stem-robotics.webp', 'name' => 'STEM Robotics', 'alt' => 'Sân chơi sáng tạo khoa học kỹ thuật STEM Robotics'],
        ],
    ],
    [
        'category' => 'business',
        'category_name' => 'Kinh doanh & Khởi nghiệp',
        'items' => [
            ['url' => '/app/learner/assets/activities/covers/fpt-product-sprint.webp', 'name' => 'Product Sprint', 'alt' => 'Product Sprint - Xây dựng sản phẩm số nhanh'],
            ['url' => '/app/learner/assets/activities/covers/fpt-startup-demo-day.webp', 'name' => 'Startup Demo Day', 'alt' => 'Ngày hội thuyết trình dự án khởi nghiệp'],
            ['url' => '/app/learner/assets/activities/covers/nguyen-trai-startup-debate.webp', 'name' => 'Startup Debate', 'alt' => 'Tranh biện kinh doanh và ý tưởng khởi nghiệp trẻ'],
            ['url' => '/app/learner/assets/activities/covers/nguyen-trai-young-business.webp', 'name' => 'Young Business Leaders', 'alt' => 'Ươm mầm tài năng lãnh đạo doanh nghiệp trẻ'],
            ['url' => '/app/learner/assets/activities/covers/talenthub-digital-marketing.webp', 'name' => 'Digital Marketing', 'alt' => 'Chiến dịch truyền thông và Marketing số'],
        ],
    ],
    [
        'category' => 'creative',
        'category_name' => 'Sáng tạo & Nghệ thuật',
        'items' => [
            ['url' => '/app/learner/assets/activities/covers/fpt-music-showcase.webp', 'name' => 'Music Showcase', 'alt' => 'Đêm nhạc acoustic và biểu diễn nghệ thuật sinh viên'],
            ['url' => '/app/learner/assets/activities/covers/nguyen-trai-poster-design.webp', 'name' => 'Poster Design & Branding', 'alt' => 'Thiết kế nhận diện thương hiệu và poster đồ họa'],
            ['url' => '/app/learner/assets/activities/covers/talenthub-creative-studio.webp', 'name' => 'Creative Design Studio', 'alt' => 'Xưởng sáng tạo thiết kế đa phương tiện'],
        ],
    ],
    [
        'category' => 'community',
        'category_name' => 'Cộng đồng & Tình nguyện',
        'items' => [
            ['url' => '/app/learner/assets/activities/covers/fpt-green-campus.webp', 'name' => 'FPT Green Campus', 'alt' => 'Chiến dịch đại học xanh và bảo vệ môi trường'],
            ['url' => '/app/learner/assets/activities/covers/nguyen-trai-green-campus.webp', 'name' => 'Trường học Xanh', 'alt' => 'Hoạt động trải nghiệm trường học xanh bền vững'],
            ['url' => '/app/learner/assets/activities/covers/talenthub-green-school.webp', 'name' => 'Green School Initiative', 'alt' => 'Sáng kiến học đường xanh và tái chế'],
        ],
    ],
];


if (!function_exists('teacherActivitiesLifecycleAction')) {
    function teacherActivitiesLifecycleAction(array $activity): ?array
    {
        $rawStatus = strtolower(trim((string) ($activity['raw_status'] ?? '')));

        if ($rawStatus === 'draft') {
            return match ((string) ($activity['approval_status'] ?? 'draft')) {
                'draft' => ['label' => 'Gửi Nhà trường duyệt', 'form_action' => 'submit_for_school_review'],
                'changes_requested' => ['label' => 'Gửi duyệt lại', 'form_action' => 'submit_for_school_review'],
                'approved' => ['label' => 'Công bố hoạt động', 'form_action' => 'advance_status'],
                default => null,
            };
        }

        if ($rawStatus === 'published') {
            return [
                'label' => 'Bắt đầu hoạt động',
                'form_action' => 'advance_status',
            ];
        }

        return match ($rawStatus) {
            'ongoing' => ['label' => 'Kết thúc hoạt động', 'form_action' => 'advance_status'],
            'completed' => ['label' => 'Lưu trữ hoạt động', 'form_action' => 'advance_status'],
            'archived' => null,
            default => null,
        };
    }
}

$dashboardData = teacherDashboardReadData();
$dashboardContext = teacherDashboardBackendContext();
$session = $dashboardContext['session'] ?? null;
$csrfToken = $session instanceof \TalentHub\Auth\Session\SessionManager ? $session->csrfToken() : '';
$teacherInfo = $dashboardData['teacherInfo'];
$teacherId = (string) ($teacherInfo['id'] ?? '');
$teacherUserId = (string) ($dashboardContext['user']['id'] ?? '');
$pdo = $teacherId !== '' ? teacherDashboardConnect() : null;
$schoolId = $pdo && $teacherId !== '' ? teacherActivitiesSchoolId($pdo, $teacherId) : null;
$activityService = $pdo ? teacherActivitiesService($pdo) : null;

$pageTitle = 'Hoạt động / Sân chơi của tôi';
$currentRoute = 'index.php';
$teacherSidebarHomeHref = '../index.php';
$teacherSidebarRoleHref = '../../../role-selection.php';
$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '../index.php',
        'href' => '../index.php',
        'icon' => 'grid',
        'active' => false,
    ],
    [
        'title' => 'Hoạt động',
        'route' => 'index.php',
        'icon' => 'trophy',
        'active' => true,
    ],
    [
        'title' => 'Chấm điểm',
        'route' => '../assessments',
        'icon' => 'clipboard-check',
        'active' => false,
    ],
    [
        'title' => 'Học viên',
        'route' => '../students',
        'icon' => 'users',
        'active' => false,
    ],
];

$action = strtolower(trim((string) ($_GET['action'] ?? '')));
$activityId = trim((string) ($_GET['id'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
$statusFilters = ['draft', 'pending_school_review', 'approved', 'published', 'ongoing', 'completed', 'archived'];
if (!in_array($statusFilter, $statusFilters, true)) {
    $statusFilter = '';
}

if ($action === 'create') {
    header('Location: create.php');
    exit;
}
if ($action === 'edit') {
    header('Location: create.php' . ($activityId !== '' ? ('?id=' . rawurlencode($activityId)) : ''));
    exit;
}
if ($action === 'view') {
    if ($activityId !== '') {
        $savedQuery = isset($_GET['saved']) ? '&saved=' . rawurlencode((string) $_GET['saved']) : '';
        header('Location: detail.php?id=' . rawurlencode($activityId) . $savedQuery);
        exit;
    }
    header('Location: index.php?error=not_found');
    exit;
}

$errors = [];
$fieldErrors = [];
$errorHeading = 'Chưa thể lưu hoạt động.';
if (isset($_SESSION['teacher_activity_review_error']) && is_array($_SESSION['teacher_activity_review_error'])) {
    $errorHeading = (string) ($_SESSION['teacher_activity_review_error']['heading'] ?? $errorHeading);
    $reviewMessages = (array) ($_SESSION['teacher_activity_review_error']['messages'] ?? []);
    foreach ($reviewMessages as $reviewMessage) {
        $reviewMessage = (string) $reviewMessage;
        if ($reviewMessage === '') continue;
        $errors[] = $reviewMessage;
        $reviewField = teacherActivitiesErrorField($reviewMessage);
        if ($reviewField !== null && !isset($fieldErrors[$reviewField])) {
            $fieldErrors[$reviewField] = $reviewMessage;
        }
    }
    unset($_SESSION['teacher_activity_review_error']);
}
$notice = '';
$noticeType = 'success';
$categoryCatalog = teacherActivitiesCategoryCatalog();

if (isset($_GET['saved'])) {
    $noticeMessages = [
        'created' => 'Đã lưu bản nháp. Chọn “Gửi Nhà trường duyệt” để xin phê duyệt trước khi công bố.',
        'updated' => 'Đã cập nhật hoạt động. Bạn có thể gửi Nhà trường duyệt khi thông tin đã hoàn tất.',
        'advanced' => 'Đã chuyển hoạt động sang trạng thái mới.',
        'started' => 'Đã bắt đầu hoạt động thành công. Hoạt động hiện đang diễn ra.',
        'registration' => 'Đã cập nhật trạng thái đăng ký.',
        'submitted' => 'Đã gửi hoạt động đến Nhà trường. Bạn có thể công bố sau khi được duyệt.',
    ];
    $notice = $noticeMessages[(string) $_GET['saved']] ?? 'Đã lưu thay đổi hoạt động.';
}

if (isset($_GET['error']) && (string) $_GET['error'] === 'not_found') {
    $notice = 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.';
    $noticeType = 'error';
}

$selectedActivity = null;
if ($pdo && $teacherId !== '' && $activityId !== '') {
    $selectedActivity = teacherActivitiesFind($pdo, $teacherId, $activityId);
}

$formValues = [
    'title' => '',
    'categoryChoice' => '',
    'category' => '',
    'displayCategory' => '',
    'filterCategory' => '',
    'summary' => '',
    'description' => '',
    'experienceHighlights' => '',
    'skillIds' => [],
    'eligibilityRules' => '',
    'benefitItems' => '',
    'locationName' => '',
    'locationAddress' => '',
    'deliveryMode' => 'in_person',
    'onlineMeetingUrl' => '',
    'organizerName' => !empty($teacherInfo['school_name']) && $teacherInfo['school_name'] !== 'Chưa kết nối trường' ? (string) $teacherInfo['school_name'] : '',
    'organizerContact' => '',
    'organizerEmail' => '',
    'organizerPhone' => '',
    'coverImageUrl' => '',
    'coverImageAlt' => '',
    'feeMode' => 'free',
    'feeAmount' => '0.00',
    'currency' => 'VND',
    'targetAudience' => 'Học sinh trong trường',
    'certificateLabel' => 'Minh chứng tham gia trên TalentHub',
    'responsibleTeacherId' => $teacherId,
    'registrationOpensAt' => (new DateTimeImmutable('now'))->format('Y-m-d\TH:i'),
    'registrationClosesAt' => (new DateTimeImmutable('+2 days -2 hours'))->format('Y-m-d\TH:i'),
    'cancellationClosesAt' => (new DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i'),
    'approvalMode' => 'automatic',
    'confirmedHours' => '2.00',
    'startAt' => (new DateTimeImmutable('+2 days 09:00'))->format('Y-m-d\TH:i'),
    'endAt' => (new DateTimeImmutable('+2 days 12:00'))->format('Y-m-d\TH:i'),
    'capacity' => '30',
];

$responsibleTeachers = $pdo && $teacherId !== '' ? $activityService?->responsibleTeachers($teacherId) ?? [] : [];
$skillCatalog = $pdo ? $activityService?->activeSkillCatalog() ?? [] : [];
$selectedSkillIds = [];

if ($action === 'edit' && $selectedActivity) {
    $formValues = [
        'title' => (string) ($selectedActivity['title'] ?? ''),
        'categoryChoice' => teacherActivitiesCategoryChoice($selectedActivity, $categoryCatalog),
        'category' => (string) ($selectedActivity['category'] ?? ''),
        'displayCategory' => (string) (!empty($selectedActivity['displayCategory']) ? $selectedActivity['displayCategory'] : ($selectedActivity['category_label'] ?? '')),
        'filterCategory' => (string) (!empty($selectedActivity['filterCategory']) ? $selectedActivity['filterCategory'] : ($selectedActivity['category_label'] ?? '')),
        'summary' => (string) ($selectedActivity['summary'] ?? ''),
        'description' => (string) ($selectedActivity['description'] ?? ''),
        'experienceHighlights' => implode("\n", $selectedActivity['experience_highlights_list'] ?? []),
        'skillIds' => $selectedActivity['assigned_skill_ids'] ?? [],
        'eligibilityRules' => implode("\n", $selectedActivity['eligibility_rules_list'] ?? []),
        'benefitItems' => implode("\n", $selectedActivity['benefit_items_list'] ?? []),
        'locationName' => (string) ($selectedActivity['locationName'] ?? ''),
        'locationAddress' => (string) ($selectedActivity['locationAddress'] ?? ''),
        'deliveryMode' => (string) ($selectedActivity['deliveryMode'] ?? 'in_person'),
        'onlineMeetingUrl' => (string) ($selectedActivity['onlineMeetingUrl'] ?? ''),
        'organizerName' => (string) ($selectedActivity['organizerName'] ?? ''),
        'organizerContact' => (string) ($selectedActivity['organizerContact'] ?? ''),
        'organizerEmail' => (string) ($selectedActivity['organizerEmail'] ?? ''),
        'organizerPhone' => (string) ($selectedActivity['organizerPhone'] ?? ''),
        'coverImageUrl' => (string) ($selectedActivity['coverImageUrl'] ?? ''),
        'coverImageAlt' => (string) ($selectedActivity['coverImageAlt'] ?? ''),
        'feeMode' => (float) ($selectedActivity['feeAmount'] ?? 0) > 0 ? 'paid' : 'free',
        'feeAmount' => (string) ($selectedActivity['feeAmount'] ?? '0.00'),
        'currency' => (string) ($selectedActivity['currency'] ?? 'VND'),
        'targetAudience' => (string) ($selectedActivity['targetAudience'] ?? ''),
        'certificateLabel' => (string) ($selectedActivity['certificateLabel'] ?? ''),
        'responsibleTeacherId' => (string) ($selectedActivity['responsibleTeacherId'] ?? ''),
        'registrationOpensAt' => (string) ($selectedActivity['registration_opens_input'] ?? ''),
        'registrationClosesAt' => (string) ($selectedActivity['registration_closes_input'] ?? ''),
        'cancellationClosesAt' => (string) ($selectedActivity['cancellation_closes_input'] ?? ''),
        'approvalMode' => (string) ($selectedActivity['approvalMode'] ?? 'automatic'),
        'confirmedHours' => (string) ($selectedActivity['confirmedHours'] ?? '0.00'),
        'startAt' => (string) ($selectedActivity['start_input'] ?? ''),
        'endAt' => (string) ($selectedActivity['end_input'] ?? ''),
        'capacity' => (string) ($selectedActivity['capacity'] ?? 0),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfValid = true;
    try {
        if (!$session instanceof \TalentHub\Auth\Session\SessionManager) {
            throw new RuntimeException('Teacher session is unavailable.');
        }
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    } catch (Throwable) {
        $csrfValid = false;
        $errors[] = 'Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
    }

    if (!$csrfValid) {
        $action = '';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? 'create');
        $postedActivityId = trim((string) ($_POST['activity_id'] ?? ''));

    if ($formAction === 'registration_transition') {
        $postedRegistrationId = trim((string) ($_POST['registration_id'] ?? ''));
        $registrationAction = trim((string) ($_POST['registration_action'] ?? ''));
        $expectedStatus = trim((string) ($_POST['expected_status'] ?? ''));
        if (!$pdo || !$activityService || $teacherId === '' || $teacherUserId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để xử lý đăng ký.';
        }
        if ($postedActivityId === '' || $postedRegistrationId === '') {
            $errors[] = 'Thiếu mã hoạt động hoặc mã đăng ký.';
        }
        if (!$errors) {
            try {
                (new \TalentHub\Rbac\Service\PermissionService($pdo))->require(
                    $teacherUserId,
                    'activity_registration.update_managed'
                );
                $activityService->transitionRegistration(
                    $teacherId,
                    $teacherUserId,
                    \TalentHub\Support\Id\RequestId::make(null),
                    $postedActivityId,
                    $postedRegistrationId,
                    ['expectedStatus' => $expectedStatus, 'action' => $registrationAction],
                );
                header('Location: index.php?action=registrations&id=' . rawurlencode($postedActivityId) . '&saved=registration');
                exit;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage() ?: 'Không thể xử lý đăng ký hoạt động.';
            }
        }
    }

    if ($formAction === 'advance_status') {
        if (!$pdo || $teacherId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để cập nhật trạng thái hoạt động.';
        }
        if ($postedActivityId === '') {
            $errors[] = 'Thiếu mã hoạt động cần cập nhật.';
        }

        if (!$errors) {
            try {
                $nextStatus = $activityService->advanceStatus($teacherId, $postedActivityId);
                $savedKey = $nextStatus === 'ongoing' ? 'started' : 'advanced';
                header('Location: index.php?saved=' . $savedKey);
                exit;
            } catch (Throwable $exception) {
                $msg = $exception->getMessage() ?: 'Không thể cập nhật trạng thái hoạt động. Vui lòng kiểm tra lại kết nối dữ liệu.';
                $errors[] = $msg;
                $lower = mb_strtolower($msg, 'UTF-8');
                if (str_contains($lower, 'phê duyệt') || str_contains($lower, 'duyệt') || str_contains($lower, 'approval')) {
                    $errorHeading = 'Chưa thể gửi duyệt hoạt động.';
                }
            }
        }
    }

    if ($formAction === 'submit_for_school_review') {
        if (!$pdo || $teacherId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để gửi duyệt hoạt động.';
        }
        if ($postedActivityId === '') {
            $errors[] = 'Thiếu mã hoạt động cần gửi duyệt.';
        }
        if (!$errors) {
            try {
                $activityService->submitForSchoolReview($teacherId, $postedActivityId, \TalentHub\Support\Id\RequestId::make(null));
                header('Location: index.php?saved=submitted');
                exit;
            } catch (Throwable $exception) {
                $message = $exception->getMessage() ?: 'Không thể gửi hoạt động đến Nhà trường. Vui lòng kiểm tra dữ liệu hoạt động.';
                if ($postedActivityId !== '' && isset($_SESSION) && is_array($_SESSION)) {
                    $_SESSION['teacher_activity_review_error'] = [
                        'heading' => 'Chưa thể gửi duyệt hoạt động.',
                        'messages' => [$message],
                    ];
                    header('Location: create.php?id=' . rawurlencode($postedActivityId));
                    exit;
                }
                $errorHeading = 'Chưa thể gửi duyệt hoạt động.';
                $errors[] = $message;
            }
        } else {
            $errorHeading = 'Chưa thể gửi duyệt hoạt động.';
        }
    }

    if ($formAction === 'registration_transition') {
        $action = 'registrations';
        $activityId = $postedActivityId;
        $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
    } elseif ($formAction === 'advance_status' || $formAction === 'submit_for_school_review') {
        $action = '';
        if ($postedActivityId !== '') {
            $activityId = $postedActivityId;
            $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
        }
    } elseif (in_array($formAction, ['create', 'edit'], true)) {
        header('Location: create.php' . ($formAction === 'edit' && $postedActivityId !== '' ? ('?id=' . rawurlencode($postedActivityId)) : ''));
        exit;
    }
    }
}

$firstErrorField = $fieldErrors ? (string) array_key_first($fieldErrors) : '';
$registrationFields = ['registrationOpensAt', 'registrationClosesAt', 'cancellationClosesAt', 'approvalMode', 'confirmedHours'];
$additionalFields = ['description', 'experienceHighlights', 'skillIds', 'eligibilityRules', 'benefitItems', 'targetAudience', 'organizerName', 'organizerContact', 'organizerEmail', 'organizerPhone', 'responsibleTeacherId', 'coverImageUrl', 'coverImageAlt', 'feeAmount', 'currency', 'certificateLabel'];
$registrationHasError = (bool) array_intersect(array_keys($fieldErrors), $registrationFields);
$additionalHasError = (bool) array_intersect(array_keys($fieldErrors), $additionalFields);
$registrationOpenDate = teacherActivitiesFormDate($formValues['registrationOpensAt']);
$registrationCloseDate = teacherActivitiesFormDate($formValues['registrationClosesAt']);
$cancellationCloseDate = teacherActivitiesFormDate($formValues['cancellationClosesAt']);
$activityStartDate = teacherActivitiesFormDate($formValues['startAt']);
$hoursValid = preg_match('/\A(?:\d{1,2})(?:\.\d{1,2})?\z/', $formValues['confirmedHours']) === 1 && (float) $formValues['confirmedHours'] <= 24;
$registrationValid = $registrationOpenDate && $registrationCloseDate && $cancellationCloseDate && $activityStartDate
    && $registrationOpenDate <= $registrationCloseDate
    && $registrationCloseDate < $activityStartDate
    && $cancellationCloseDate <= $activityStartDate
    && in_array($formValues['approvalMode'], ['automatic', 'teacher_review'], true)
    && $hoursValid;
$registrationOpen = !$registrationValid || $registrationHasError;
$additionalOpen = $additionalHasError;
$approvalSummary = $formValues['approvalMode'] === 'teacher_review' ? 'Giáo viên duyệt' : 'Tự động';
$registrationSummary = $registrationValid
    ? 'Mở ' . teacherActivitiesSummaryDate($formValues['registrationOpensAt'])
        . ' · Đóng ' . teacherActivitiesSummaryDate($formValues['registrationClosesAt'])
        . ' · Hủy đến ' . teacherActivitiesSummaryDate($formValues['cancellationClosesAt'])
        . ' · ' . $approvalSummary . ' · ' . $formValues['confirmedHours'] . ' giờ'
    : 'Chưa thiết lập';
$additionalLabels = [];
if ($formValues['description'] !== '') $additionalLabels[] = 'mô tả';
$listCount = count(array_filter(['experienceHighlights', 'eligibilityRules', 'benefitItems'], static fn (string $field): bool => trim((string) $formValues[$field]) !== ''));
if (!empty($formValues['skillIds'])) $listCount++;
if ($listCount > 0) $additionalLabels[] = $listCount . ' nhóm nội dung';
if ($formValues['targetAudience'] !== '') $additionalLabels[] = 'đối tượng';
if ($formValues['organizerName'] !== '' || $formValues['responsibleTeacherId'] !== '') $additionalLabels[] = 'đơn vị và người phụ trách';
if ($formValues['organizerContact'] !== '' || $formValues['organizerEmail'] !== '' || $formValues['organizerPhone'] !== '') $additionalLabels[] = 'liên hệ';
if ($formValues['coverImageUrl'] !== '') $additionalLabels[] = 'ảnh bìa';
if ($formValues['feeMode'] === 'paid') $additionalLabels[] = 'có thu phí';
if ($formValues['certificateLabel'] !== '') $additionalLabels[] = 'chứng nhận';
$additionalSummary = $additionalLabels ? 'Đã có ' . implode(', ', $additionalLabels) : 'Chưa có thông tin bổ sung';

$activities = $pdo && $teacherId !== '' ? teacherActivitiesRead($pdo, $teacherId, $search) : [];
if ($statusFilter !== '') {
    $activities = array_values(array_filter($activities, static function (array $activity) use ($statusFilter): bool {
        if ($statusFilter === 'approved') {
            return ($activity['approval_status'] ?? '') === 'approved' && ($activity['raw_status'] ?? '') === 'draft';
        }
        if ($statusFilter === 'pending_school_review') {
            return ($activity['approval_status'] ?? '') === 'pending_school_review';
        }
        return $activity['status_key'] === $statusFilter;
    }));
}

$registrationRows = [];
if ($action === 'registrations' && $selectedActivity && $pdo && $teacherId !== '') {
    $registrationRows = teacherActivitiesRegistrations($pdo, $teacherId, $activityId);
}

$selectedRespId = (string) ($formValues['responsibleTeacherId'] ?? '');
$dedupedTeachers = [];
foreach ($responsibleTeachers as $rt) {
    $tName = (string) $rt['name'];
    if (!isset($dedupedTeachers[$tName]) || $rt['id'] === $selectedRespId) {
        $dedupedTeachers[$tName] = $rt;
    }
}
$responsibleTeachers = array_values($dedupedTeachers);

?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Quản lý hoạt động và sân chơi do giáo viên phụ trách trên TalentHub.">
    <title><?= teacherActivitiesEscape($pageTitle); ?> | TalentHub</title>
<?php
$teacherAssetUrl = static function (string $relPath): string {
    $fsPath = dirname(__DIR__, 3) . $relPath;
    $ver = is_file($fsPath) ? (string) filemtime($fsPath) : '1.0';
    $href = function_exists('app_href') ? app_href($relPath) : $relPath;
    return $href . '?v=' . $ver;
};
?>
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/home.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/global.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/brand-component.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/polish.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/teacher.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/typeui-selects.css')); ?>">
</head>
<body class="teacher-dashboard teacher-activities-page">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once __DIR__ . '/../includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <?php if ($action !== 'registrations'): ?>
                    <section class="teacher-activities-heading">
                        <div>
                            <span class="teacher-welcome__tag">Quản lý giáo viên</span>
                            <h2 class="teacher-activities-heading__title">Hoạt động / Sân chơi của tôi</h2>
                            <p class="teacher-activities-heading__description">Theo dõi lịch hoạt động, số lượng đăng ký và vòng đời hoạt động.</p>
                        </div>
                        <a href="create.php" class="btn btn-primary teacher-activities-create">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Tạo hoạt động mới</span>
                        </a>
                    </section>
                    <?php endif; ?>

                    <?php if ($notice !== ''): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--<?= teacherActivitiesEscape($noticeType); ?>" role="status">
                            <?= teacherActivitiesEscape($notice); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--error" id="teacher-activities-errors" role="alert" tabindex="-1" data-focus-on-load>
                            <strong id="teacher-activities-errors-title"><?= teacherActivitiesEscape($errorHeading); ?></strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= teacherActivitiesEscape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'registrations' && $selectedActivity): ?>
                        <?php
                        // ── helpers (scoped to registrations view) ──────────────────────────────
                        $regStatusLabel = static function (string $status): string {
                            return match ($status) {
                                'pending'   => 'Chờ duyệt',
                                'approved'  => 'Đã duyệt',
                                'attended'  => 'Đã tham dự',
                                'rejected'  => 'Từ chối',
                                'cancelled' => 'Đã hủy',
                                'absent'    => 'Vắng mặt',
                                default     => $status ?: 'Đã đăng ký',
                            };
                        };
                        $regStatusClass = static function (string $status): string {
                            return match ($status) {
                                'pending'             => 'pending',
                                'approved', 'attended'=> 'attended',
                                'rejected', 'absent'  => 'absent',
                                'cancelled'           => 'cancelled',
                                default               => 'default',
                            };
                        };
                        $checkinLabel = static function (?string $status): array {
                            return match ($status) {
                                'confirmed' => ['Đã xác nhận',   'confirmed'],
                                'pending'   => ['Chờ xác nhận',  'pending'],
                                'rejected'  => ['Bị từ chối',    'rejected'],
                                null, ''    => ['Chưa điểm danh','none'],
                                default     => [$status,          'default'],
                            };
                        };

                        // ── summary counters ────────────────────────────────────────────────────
                        $totalReg      = count($registrationRows);
                        $totalCheckedIn = 0;
                        $totalPending   = 0;
                        foreach ($registrationRows as $_r) {
                            if (($_r['checkin_status'] ?? '') === 'confirmed') $totalCheckedIn++;
                            elseif (($_r['checkin_status'] ?? '') === 'pending')  $totalPending++;
                        }
                        $totalNotCheckedIn = $totalReg - $totalCheckedIn - $totalPending;
                        $capacity = (int) ($selectedActivity['capacity'] ?? 0);
                        ?>
                        <nav class="teacher-reg-breadcrumb" aria-label="Điều hướng">
                            <a href="index.php" class="teacher-reg-breadcrumb__back">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                                <span>Quay lại danh sách hoạt động</span>
                            </a>
                        </nav>

                        <!-- ── Activity Overview Header Card ───────────────────── -->
                        <div class="teacher-reg-header-card">
                            <div class="teacher-reg-header-card__main">
                                <div class="teacher-reg-header-card__tags">
                                    <span class="teacher-reg-tag">Chi tiết hoạt động</span>
                                    <?php if (!empty($selectedActivity['status_label'])): ?>
                                        <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['status_class'] ?? 'default'); ?>">
                                            <?= teacherActivitiesEscape($selectedActivity['status_label']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="teacher-reg-header-card__title"><?= teacherActivitiesEscape($selectedActivity['title']); ?></h2>
                                <div class="teacher-reg-meta-pills">
                                    <span class="teacher-reg-meta-pill">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <span><strong><?= teacherActivitiesEscape((string) $totalReg); ?></strong> sinh viên đăng ký</span>
                                    </span>
                                    <?php if ($capacity > 0): ?>
                                        <span class="teacher-reg-meta-pill">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <span>Sức chứa: <strong><?= teacherActivitiesEscape((string) $capacity); ?></strong></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedActivity['start_label'])): ?>
                                        <span class="teacher-reg-meta-pill">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            <span><?= teacherActivitiesEscape($selectedActivity['start_label']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedActivity['locationName'])): ?>
                                        <span class="teacher-reg-meta-pill">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <span><?= teacherActivitiesEscape($selectedActivity['locationName']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (in_array($selectedActivity['status'] ?? '', ['published', 'ongoing'], true)): ?>
                                <div class="teacher-reg-header-card__actions">
                                    <a href="../checkins/index.php?activity_id=<?= teacherActivitiesEscape($selectedActivity['id']); ?>"
                                       class="btn btn-primary btn-sm teacher-reg-qr-btn">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3"/><path d="M17 14h3"/><path d="M17 17v3"/></svg>
                                        <span>Tạo QR điểm danh</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ── Summary Statistics Cards ────────────────────────── -->
                        <div class="teacher-reg-summary">
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--total">
                                <div class="teacher-reg-summary__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <div class="teacher-reg-summary__content">
                                    <span class="teacher-reg-summary__value"><?= $totalReg; ?></span>
                                    <span class="teacher-reg-summary__label">Đã đăng ký</span>
                                </div>
                            </div>
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--success">
                                <div class="teacher-reg-summary__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                </div>
                                <div class="teacher-reg-summary__content">
                                    <span class="teacher-reg-summary__value"><?= $totalCheckedIn; ?></span>
                                    <span class="teacher-reg-summary__label">Đã điểm danh</span>
                                </div>
                            </div>
                            <?php if ($totalPending > 0): ?>
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--warn">
                                <div class="teacher-reg-summary__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div class="teacher-reg-summary__content">
                                    <span class="teacher-reg-summary__value"><?= $totalPending; ?></span>
                                    <span class="teacher-reg-summary__label">Chờ xác nhận</span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--muted">
                                <div class="teacher-reg-summary__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                </div>
                                <div class="teacher-reg-summary__content">
                                    <span class="teacher-reg-summary__value"><?= $totalNotCheckedIn; ?></span>
                                    <span class="teacher-reg-summary__label">Chưa điểm danh</span>
                                </div>
                            </div>
                        </div>

                        <!-- ── Student list ───────────────────────────────────── -->
                        <section class="teacher-section-box teacher-reg-panel" aria-labelledby="teacher-reg-panel-title">
                            <div class="teacher-section-box__header teacher-reg-panel__header">
                                <div class="teacher-reg-panel__title-group">
                                    <h3 class="teacher-section-box__title" id="teacher-reg-panel-title">
                                        Danh sách sinh viên
                                    </h3>
                                    <span class="teacher-reg-panel__count"><?= count($registrationRows); ?> sinh viên</span>
                                </div>
                            </div>

                            <?php if (!$registrationRows): ?>
                                <div class="teacher-empty-state">
                                    <div class="teacher-empty-state__icon" aria-hidden="true">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <circle cx="9" cy="7" r="4"/><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <path d="M16 11a4 4 0 0 1 0 8"/>
                                        </svg>
                                    </div>
                                    <h4 class="teacher-empty-state__title">Chưa có sinh viên đăng ký</h4>
                                    <p class="teacher-empty-state__desc">Danh sách sinh viên sẽ tự động hiển thị khi có học viên đăng ký tham gia hoạt động này.</p>
                                </div>
                            <?php else: ?>
                                <div class="teacher-activities-table-wrap teacher-reg-table-wrap">
                                    <table class="teacher-activities-table teacher-reg-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" style="min-width: 220px;">Sinh viên</th>
                                                <th scope="col" style="min-width: 140px;">Ngày đăng ký</th>
                                                <th scope="col" style="min-width: 150px;">Trạng thái đăng ký</th>
                                                <th scope="col" style="min-width: 160px;">Điểm danh</th>
                                                <th scope="col" class="teacher-activities-table__actions-heading" style="min-width: 110px;">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($registrationRows as $registration): ?>
                                                <?php
                                                    $regDate = '';
                                                    if (!empty($registration['registeredAt'])) {
                                                        $dt = teacherActivitiesDate((string) $registration['registeredAt']);
                                                        $regDate = $dt ? $dt->format('d/m/Y H:i') : substr((string) $registration['registeredAt'], 0, 16);
                                                    }
                                                    [$ciLabel, $ciClass] = $checkinLabel($registration['checkin_status'] ?? null);
                                                    $rSt = $registration['status'] ?? '';
                                                    $ciTime = '';
                                                    if (!empty($registration['checkin_confirmed_at'])) {
                                                        $ciDt = teacherActivitiesDate((string) $registration['checkin_confirmed_at']);
                                                        $ciTime = $ciDt ? $ciDt->format('d/m/Y H:i') : '';
                                                    }
                                                    $studentName = (string) ($registration['student_name'] ?: 'Học viên');
                                                    $nameParts = preg_split('/\s+/u', trim($studentName)) ?: [];
                                                    $initial = $nameParts !== [] ? mb_strtoupper(mb_substr(end($nameParts), 0, 1)) : 'H';
                                                ?>
                                                <tr>
                                                    <td data-label="Sinh viên" class="teacher-reg-cell-student">
                                                        <div class="teacher-reg-student-info">
                                                            <div class="teacher-reg-student-avatar" aria-hidden="true"><?= teacherActivitiesEscape($initial); ?></div>
                                                            <div class="teacher-reg-student-text">
                                                                <span class="teacher-reg-student-name"><?= teacherActivitiesEscape($studentName); ?></span>
                                                                <?php if (!empty($registration['student_email'])): ?>
                                                                    <span class="teacher-reg-student-email"><?= teacherActivitiesEscape($registration['student_email']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td data-label="Ngày đăng ký" class="teacher-reg-cell-date">
                                                        <span class="teacher-reg-date-val"><?= teacherActivitiesEscape($regDate ?: '—'); ?></span>
                                                    </td>
                                                    <td data-label="Trạng thái đăng ký" class="teacher-reg-cell-status">
                                                        <span class="teacher-registration-pill teacher-registration-pill--reg-<?= teacherActivitiesEscape($regStatusClass($rSt)); ?>">
                                                            <span class="teacher-reg-dot" aria-hidden="true"></span>
                                                            <span><?= teacherActivitiesEscape($regStatusLabel($rSt)); ?></span>
                                                        </span>
                                                    </td>
                                                    <td data-label="Điểm danh" class="teacher-reg-cell-checkin">
                                                        <div class="teacher-reg-checkin-block">
                                                            <span class="teacher-registration-pill teacher-registration-pill--checkin-<?= teacherActivitiesEscape($ciClass); ?>">
                                                                <span class="teacher-reg-dot" aria-hidden="true"></span>
                                                                <span><?= teacherActivitiesEscape($ciLabel); ?></span>
                                                            </span>
                                                            <?php if ($ciTime !== ''): ?>
                                                                <span class="teacher-reg-checkin-time">
                                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                                    <span><?= teacherActivitiesEscape($ciTime); ?></span>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td data-label="Thao tác" class="teacher-reg-cell-actions">
                                                        <?php if ($rSt === 'pending'): ?>
                                                            <div class="teacher-activities-row-actions teacher-reg-row-actions">
                                                                <form method="post" class="teacher-activities-inline-form">
                                                                    <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                    <input type="hidden" name="form_action" value="registration_transition">
                                                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                                                    <input type="hidden" name="registration_id" value="<?= teacherActivitiesEscape($registration['id']); ?>">
                                                                    <input type="hidden" name="expected_status" value="pending">
                                                                    <button type="submit" name="registration_action" value="approve" class="teacher-activity-action teacher-activity-action--approve">
                                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                                        <span>Duyệt</span>
                                                                    </button>
                                                                </form>
                                                                <form method="post" class="teacher-activities-inline-form">
                                                                    <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                    <input type="hidden" name="form_action" value="registration_transition">
                                                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                                                    <input type="hidden" name="registration_id" value="<?= teacherActivitiesEscape($registration['id']); ?>">
                                                                    <input type="hidden" name="expected_status" value="pending">
                                                                    <button type="submit" name="registration_action" value="reject" class="teacher-activity-action teacher-activity-action--reject">
                                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                                        <span>Từ chối</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="teacher-text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php elseif ($action !== '' && $activityId !== ''): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--error" role="alert">Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.</div>
                    <?php endif; ?>

                    <?php if ($action !== 'registrations'): ?>
                    <section class="teacher-section-box teacher-activities-list-panel">
                        <div class="teacher-section-box__header">
                            <div>
                                <h2 class="teacher-section-box__title">Danh sách hoạt động</h2>
                                <p class="teacher-section-box__subtitle">Hiển thị <?= teacherActivitiesEscape((string) count($activities)); ?> hoạt động theo bộ lọc hiện tại.</p>
                            </div>
                        </div>

                        <form method="get" class="teacher-activities-toolbar">
                            <label class="teacher-activities-search">
                                <span class="teacher-activities-search__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                </span>
                                <span class="sr-only">Tìm kiếm theo tên hoạt động</span>
                                <input type="search" name="q" value="<?= teacherActivitiesEscape($search); ?>" placeholder="Tìm theo tên hoạt động...">
                            </label>
                            <label class="teacher-activities-filter">
                                <span class="sr-only">Lọc theo trạng thái</span>
                                <select name="status" class="typeui-select typeui-select--compact">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
                                    <option value="pending_school_review" <?= $statusFilter === 'pending_school_review' ? 'selected' : ''; ?>>Chờ duyệt</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : ''; ?>>Đã duyệt (chờ công bố)</option>
                                    <option value="published" <?= $statusFilter === 'published' ? 'selected' : ''; ?>>Đã công bố</option>
                                    <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : ''; ?>>Đang diễn ra</option>
                                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : ''; ?>>Đã hoàn tất</option>
                                    <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : ''; ?>>Đã lưu trữ</option>
                                </select>
                            </label>
                            <button type="submit" class="btn btn-secondary btn-sm d-inline-flex align-items-center teacher-activities-filter-submit">Lọc</button>
                            <?php if ($search !== '' || $statusFilter !== ''): ?>
                                <a href="index.php" class="teacher-activities-reset">Xóa lọc</a>
                            <?php endif; ?>
                        </form>

                        <?php if (!$activities): ?>
                            <div class="teacher-empty-state teacher-activities-empty">
                                <div class="teacher-empty-state__icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M6 3h12v18H6z"></path>
                                        <path d="M9 7h6M9 11h6M9 15h4"></path>
                                    </svg>
                                </div>
                                <h3 class="teacher-empty-state__title">Chưa có hoạt động phù hợp</h3>
                                <p class="teacher-empty-state__desc">Hoạt động do giáo viên phụ trách sẽ xuất hiện ở đây khi có dữ liệu trong database.</p>
                                <a href="create.php" class="btn btn-secondary btn-sm teacher-empty-state__action">Tạo hoạt động mới</a>
                            </div>
                        <?php else: ?>
                            <div class="teacher-activities-table-wrap">
                                <table class="teacher-activities-table">
                                    <thead>
                                        <tr>
                                            <th>Hoạt động</th>
                                            <th>Thời gian</th>
                                            <th>Địa điểm</th>
                                            <th>Đăng ký / sức chứa</th>
                                            <th>Trạng thái</th>
                                            <th class="teacher-activities-table__actions-heading">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activities as $activity): ?>
                                            <?php $rowLifecycleAction = teacherActivitiesLifecycleAction($activity); ?>
                                            <tr>
                                                <td data-label="Hoạt động">
                                                    <a href="detail.php?id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-title"><?= teacherActivitiesEscape($activity['title']); ?></a>
                                                    <?php if (!empty($activity['category_label'])): ?>
                                                        <span class="teacher-activity-category"><?= teacherActivitiesEscape($activity['category_label']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Thời gian">
                                                    <span class="teacher-activity-time"><?= teacherActivitiesEscape($activity['start_label']); ?></span>
                                                    <span class="teacher-activity-time teacher-text-muted">đến <?= teacherActivitiesEscape($activity['end_label']); ?></span>
                                                </td>
                                                <td data-label="Địa điểm"><span><?= teacherActivitiesEscape($activity['locationName'] ?? 'Chưa xác định địa điểm'); ?></span></td>
                                                <td data-label="Đăng ký"><strong><?= teacherActivitiesEscape((string) $activity['registered_count']); ?> / <?= teacherActivitiesEscape((string) $activity['capacity']); ?></strong></td>
                                                <td data-label="Trạng thái">
                                                    <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($activity['status_class']); ?>"><?= teacherActivitiesEscape($activity['status_label']); ?></span>
                                                    <?php if (!empty($activity['approval_status_label']) && ($activity['approval_status'] ?? '') !== 'draft'): ?>
                                                        <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($activity['approval_status_class']); ?>"><?= teacherActivitiesEscape($activity['approval_status_label']); ?></span>
                                                    <?php endif; ?>
                                                    <span class="teacher-registration-pill teacher-registration-pill--<?= $activity['registration_available'] ? 'available' : 'unavailable'; ?>"><?= teacherActivitiesEscape($activity['registration_label']); ?></span>
                                                </td>
                                                <td data-label="Thao tác">
                                                    <div class="teacher-activities-row-actions">
                                                        <a href="detail.php?id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Chi tiết</a>
                                                        <?php if (!empty($activity['can_edit'])): ?>
                                                            <a href="create.php?id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Chỉnh sửa</a>
                                                        <?php endif; ?>
                                                        <a href="?action=registrations&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action teacher-activity-action--students" title="Xem danh sách sinh viên đã đăng ký hoạt động này">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" style="vertical-align:-2px;margin-right:2px"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.85"/></svg>Xem sinh viên
                                                        </a>
                                                        <?php if (in_array($activity['status'] ?? '', ['published', 'ongoing'], true)): ?>
                                                            <a href="../checkins/index.php?activity_id=<?= urlencode((string) $activity['id']); ?>" class="teacher-activity-action" style="color: var(--color-success, #15803D); font-weight: 600;">Tạo QR điểm danh</a>
                                                        <?php endif; ?>
                                                        <?php if ($rowLifecycleAction !== null): ?>
                                                            <form method="post" class="teacher-activities-inline-form">
                                                                <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                <input type="hidden" name="form_action" value="<?= teacherActivitiesEscape($rowLifecycleAction['form_action']); ?>">
                                                                <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($activity['id']); ?>">
                                                                <button
                                                                    type="submit"
                                                                    class="teacher-activity-action teacher-activity-action--button"
                                                                    <?= !empty($rowLifecycleAction['disabled']) ? 'disabled' : ''; ?>
                                                                    <?= !empty($rowLifecycleAction['title']) ? 'title="' . teacherActivitiesEscape($rowLifecycleAction['title']) . '"' : ''; ?>
                                                                ><?= teacherActivitiesEscape($rowLifecycleAction['label']); ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; /* $action !== 'registrations' */ ?>
                </div>
            </main>
        </div>
    </div>

    <div class="teacher-toast" id="teacher-toast" aria-live="polite" aria-atomic="true">
        <div class="teacher-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="teacher-toast__message">Tính năng đang được phát triển.</span>
        </div>
    </div>

    <script src="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/js/teacher.js')); ?>"></script>
</body>
</html>
