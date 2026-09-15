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
    'skillTags' => '',
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
        'skillTags' => implode("\n", $selectedActivity['skill_tags_list'] ?? []),
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
                $redirectUrl = 'index.php?saved=' . $savedKey;
                if ($action === 'view') {
                    $redirectUrl = 'index.php?action=view&id=' . rawurlencode($postedActivityId) . '&saved=' . $savedKey;
                }
                header('Location: ' . $redirectUrl);
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
                    header('Location: index.php?action=edit&id=' . rawurlencode($postedActivityId));
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
    } else {
        $ownedEditActivity = $formAction === 'edit' && $pdo && $teacherId !== '' && $postedActivityId !== ''
            ? teacherActivitiesFind($pdo, $teacherId, $postedActivityId)
            : null;
        $formValues = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'categoryChoice' => trim((string) ($_POST['categoryChoice'] ?? '')),
            'category' => '',
            'displayCategory' => '',
            'filterCategory' => '',
            'summary' => trim((string) ($_POST['summary'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'experienceHighlights' => (string) ($_POST['experienceHighlights'] ?? ''),
            'skillTags' => (string) ($_POST['skillTags'] ?? ''),
            'eligibilityRules' => (string) ($_POST['eligibilityRules'] ?? ''),
            'benefitItems' => (string) ($_POST['benefitItems'] ?? ''),
            'locationName' => trim((string) ($_POST['locationName'] ?? '')),
            'locationAddress' => trim((string) ($_POST['locationAddress'] ?? '')),
            'deliveryMode' => trim((string) ($_POST['deliveryMode'] ?? '')),
            'onlineMeetingUrl' => trim((string) ($_POST['onlineMeetingUrl'] ?? '')),
            'organizerName' => trim((string) ($_POST['organizerName'] ?? '')),
            'organizerContact' => trim((string) ($_POST['organizerContact'] ?? '')),
            'organizerEmail' => trim((string) ($_POST['organizerEmail'] ?? '')),
            'organizerPhone' => trim((string) ($_POST['organizerPhone'] ?? '')),
            'coverImageUrl' => trim((string) ($_POST['coverImageUrl'] ?? '')),
            'coverImageAlt' => trim((string) ($_POST['coverImageAlt'] ?? '')),
            'feeMode' => trim((string) ($_POST['feeMode'] ?? 'free')),
            'feeAmount' => trim((string) ($_POST['feeAmount'] ?? '0')),
            'currency' => trim((string) ($_POST['currency'] ?? 'VND')),
            'targetAudience' => trim((string) ($_POST['targetAudience'] ?? '')),
            'certificateLabel' => trim((string) ($_POST['certificateLabel'] ?? '')),
            'responsibleTeacherId' => trim((string) ($_POST['responsibleTeacherId'] ?? '')),
            'registrationOpensAt' => trim((string) ($_POST['registrationOpensAt'] ?? '')),
            'registrationClosesAt' => trim((string) ($_POST['registrationClosesAt'] ?? '')),
            'cancellationClosesAt' => trim((string) ($_POST['cancellationClosesAt'] ?? '')),
            'approvalMode' => trim((string) ($_POST['approvalMode'] ?? '')),
            'confirmedHours' => trim((string) ($_POST['confirmedHours'] ?? '')),
            'startAt' => trim((string) ($_POST['startAt'] ?? '')),
            'endAt' => trim((string) ($_POST['endAt'] ?? '')),
            'capacity' => trim((string) ($_POST['capacity'] ?? '')),
        ];

        if ($ownedEditActivity !== null) {
            $editFallbacks = [
                'title' => (string) ($ownedEditActivity['title'] ?? ''),
                'categoryChoice' => teacherActivitiesCategoryChoice($ownedEditActivity, $categoryCatalog),
                'summary' => (string) ($ownedEditActivity['summary'] ?? ''),
                'description' => (string) ($ownedEditActivity['description'] ?? ''),
                'experienceHighlights' => implode("\n", $ownedEditActivity['experience_highlights_list'] ?? []),
                'skillTags' => implode("\n", $ownedEditActivity['skill_tags_list'] ?? []),
                'eligibilityRules' => implode("\n", $ownedEditActivity['eligibility_rules_list'] ?? []),
                'benefitItems' => implode("\n", $ownedEditActivity['benefit_items_list'] ?? []),
                'locationName' => (string) ($ownedEditActivity['locationName'] ?? ''),
                'locationAddress' => (string) ($ownedEditActivity['locationAddress'] ?? ''),
                'deliveryMode' => (string) ($ownedEditActivity['deliveryMode'] ?? 'in_person'),
                'onlineMeetingUrl' => (string) ($ownedEditActivity['onlineMeetingUrl'] ?? ''),
                'organizerName' => (string) ($ownedEditActivity['organizerName'] ?? ''),
                'organizerContact' => (string) ($ownedEditActivity['organizerContact'] ?? ''),
                'organizerEmail' => (string) ($ownedEditActivity['organizerEmail'] ?? ''),
                'organizerPhone' => (string) ($ownedEditActivity['organizerPhone'] ?? ''),
                'coverImageUrl' => (string) ($ownedEditActivity['coverImageUrl'] ?? ''),
                'coverImageAlt' => (string) ($ownedEditActivity['coverImageAlt'] ?? ''),
                'feeMode' => (float) ($ownedEditActivity['feeAmount'] ?? 0) > 0 ? 'paid' : 'free',
                'feeAmount' => (string) ($ownedEditActivity['feeAmount'] ?? '0.00'),
                'currency' => (string) ($ownedEditActivity['currency'] ?? 'VND'),
                'targetAudience' => (string) ($ownedEditActivity['targetAudience'] ?? ''),
                'certificateLabel' => (string) ($ownedEditActivity['certificateLabel'] ?? ''),
                'responsibleTeacherId' => (string) ($ownedEditActivity['responsibleTeacherId'] ?? ''),
                'registrationOpensAt' => (string) ($ownedEditActivity['registration_opens_input'] ?? ''),
                'registrationClosesAt' => (string) ($ownedEditActivity['registration_closes_input'] ?? ''),
                'cancellationClosesAt' => (string) ($ownedEditActivity['cancellation_closes_input'] ?? ''),
                'approvalMode' => (string) ($ownedEditActivity['approvalMode'] ?? 'automatic'),
                'confirmedHours' => (string) ($ownedEditActivity['confirmedHours'] ?? '0.00'),
                'startAt' => (string) ($ownedEditActivity['start_input'] ?? ''),
                'endAt' => (string) ($ownedEditActivity['end_input'] ?? ''),
                'capacity' => (string) ($ownedEditActivity['capacity'] ?? ''),
            ];
            foreach ($editFallbacks as $field => $fallback) {
                if (!array_key_exists($field, $_POST)) $formValues[$field] = $fallback;
            }
        }

        $categoryMapping = teacherActivitiesResolveCategory($formValues['categoryChoice'], $categoryCatalog, $ownedEditActivity);
        if ($categoryMapping !== null) {
            $formValues = array_replace($formValues, $categoryMapping);
        }

        $deliveryWasChanged = (string) ($_POST['deliveryChanged'] ?? '') === '1'
            || ($ownedEditActivity !== null && array_key_exists('deliveryMode', $_POST) && $formValues['deliveryMode'] !== (string) ($ownedEditActivity['deliveryMode'] ?? 'in_person'))
            || ($formAction === 'create' && array_key_exists('deliveryMode', $_POST) && $formValues['deliveryMode'] !== 'in_person');
        if ($deliveryWasChanged) {
            if ($formValues['deliveryMode'] === 'in_person') $formValues['onlineMeetingUrl'] = '';
            if ($formValues['deliveryMode'] === 'online') {
                $formValues['locationName'] = '';
                $formValues['locationAddress'] = '';
            }
        }
        if ($formValues['feeMode'] === 'free') {
            $formValues['feeAmount'] = '0.00';
        }

        $addFieldError = static function (string $field, string $message) use (&$errors, &$fieldErrors): void {
            $errors[] = $message;
            $fieldErrors[$field] = $message;
        };

        if (isset($_FILES['coverFile']) && is_array($_FILES['coverFile']) && ($_FILES['coverFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($teacherId === '') {
                $addFieldError('coverFile', 'Chưa kết nối tài khoản giáo viên để lưu ảnh tải lên.');
            } else {
                try {
                    $uploadedCoverUrl = teacherActivitiesHandleCoverUpload($_FILES['coverFile'], $teacherId);
                    $formValues['coverImageUrl'] = $uploadedCoverUrl;
                } catch (\TalentHub\Http\ApiException $e) {
                    $addFieldError('coverFile', $e->getMessage());
                } catch (Throwable $e) {
                    $addFieldError('coverFile', 'Không thể tải ảnh bìa lên: ' . $e->getMessage());
                }
            }
        }

        if ($formValues['coverImageUrl'] !== '' && $formValues['coverImageAlt'] === '') {
            $formValues['coverImageAlt'] = 'Ảnh bìa hoạt động ' . ($formValues['title'] !== '' ? $formValues['title'] : 'ngoại khóa');
        }

        $startAt = teacherActivitiesFormDate($formValues['startAt']);
        $endAt = teacherActivitiesFormDate($formValues['endAt']);

        if ($formAction === 'create') {
            if ($formValues['registrationOpensAt'] === '' && $startAt) {
                $formValues['registrationOpensAt'] = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i');
            }
            if ($formValues['registrationClosesAt'] === '' && $startAt) {
                $formValues['registrationClosesAt'] = (new DateTimeImmutable($startAt))->modify('-2 hours')->format('Y-m-d\TH:i');
            }
            if ($formValues['cancellationClosesAt'] === '' && $startAt) {
                $formValues['cancellationClosesAt'] = (new DateTimeImmutable($startAt))->modify('-24 hours')->format('Y-m-d\TH:i');
            }
            if (($formValues['confirmedHours'] === '' || $formValues['confirmedHours'] === '0' || $formValues['confirmedHours'] === '0.00') && $startAt && $endAt) {
                $diffSec = max(0, (new DateTimeImmutable($endAt))->getTimestamp() - (new DateTimeImmutable($startAt))->getTimestamp());
                $calcHours = min(24.0, round($diffSec / 3600, 2));
                if ($calcHours > 0) {
                    $formValues['confirmedHours'] = number_format($calcHours, 2, '.', '');
                }
            }
        }

        if (!$pdo || $teacherId === '' || $schoolId === null) {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để lưu hoạt động.';
        }
        if ($formValues['title'] === '') {
            $addFieldError('title', 'Vui lòng nhập tên hoạt động.');
        }
        if ($categoryMapping === null || $formValues['category'] === '') {
            $addFieldError('categoryChoice', 'Vui lòng chọn nhóm hoạt động.');
        }
        if (trim((string) $formValues['summary']) === '') {
            $addFieldError('summary', 'Vui lòng nhập giới thiệu ngắn để gửi Nhà trường duyệt.');
        }
        if (trim((string) $formValues['description']) === '') {
            $addFieldError('description', 'Vui lòng nhập mô tả đầy đủ hoạt động.');
        }
        if (trim((string) $formValues['organizerName']) === '') {
            $addFieldError('organizerName', 'Vui lòng nhập đơn vị tổ chức hoạt động.');
        }
        if ($formValues['deliveryMode'] !== 'online' && trim((string) $formValues['locationName']) === '') {
            $addFieldError('locationName', 'Vui lòng nhập địa điểm tổ chức (hoặc chọn hình thức Trực tuyến).');
        }
        if (!$startAt) {
            $addFieldError('startAt', 'Thời gian bắt đầu không hợp lệ.');
        }
        if (!$endAt) {
            $addFieldError('endAt', 'Thời gian kết thúc không hợp lệ.');
        }
        if ($startAt && $endAt && $endAt <= $startAt) {
            $addFieldError('endAt', 'Thời gian kết thúc phải sau thời gian bắt đầu.');
        }
        if (filter_var($formValues['capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $addFieldError('capacity', 'Sức chứa phải là số nguyên lớn hơn 0.');
        }
        if (!in_array($formValues['deliveryMode'], ['in_person', 'online', 'hybrid'], true)) {
            $addFieldError('deliveryMode', 'Vui lòng chọn hình thức tổ chức hợp lệ.');
        }
        if ($formValues['feeMode'] === 'paid' && (!is_numeric($formValues['feeAmount']) || (float) $formValues['feeAmount'] <= 0)) {
            $addFieldError('feeAmount', 'Vui lòng nhập chi phí lớn hơn 0 cho hoạt động có thu phí.');
        }
        if (!in_array($formValues['approvalMode'], ['automatic', 'teacher_review'], true)) {
            $addFieldError('approvalMode', 'Vui lòng chọn cách duyệt đăng ký hợp lệ.');
        }
        if (preg_match('/\A(?:\d{1,2})(?:\.\d{1,2})?\z/', $formValues['confirmedHours']) !== 1 || (float) $formValues['confirmedHours'] > 24) {
            $addFieldError('confirmedHours', 'Số giờ trải nghiệm phải từ 0 đến 24, tối đa 2 chữ số thập phân.');
        }
        $policyDates = ['registrationOpensAt' => 'Thời gian mở đăng ký', 'registrationClosesAt' => 'Thời gian đóng đăng ký', 'cancellationClosesAt' => 'Thời gian đóng hủy đăng ký'];
        $parsedPolicyDates = [];
        foreach ($policyDates as $field => $label) {
            $parsedPolicyDates[$field] = teacherActivitiesFormDate($formValues[$field]);
            if (!$parsedPolicyDates[$field]) $addFieldError($field, $label . ' không hợp lệ.');
        }
        if ($parsedPolicyDates['registrationOpensAt'] && $parsedPolicyDates['registrationClosesAt'] && $parsedPolicyDates['registrationOpensAt'] > $parsedPolicyDates['registrationClosesAt']) {
            $addFieldError('registrationOpensAt', 'Thời gian mở đăng ký phải trước hoặc bằng thời gian đóng đăng ký.');
        }
        if ($parsedPolicyDates['registrationClosesAt'] && $startAt && $parsedPolicyDates['registrationClosesAt'] >= $startAt) {
            $addFieldError('registrationClosesAt', 'Thời gian đóng đăng ký phải trước thời gian bắt đầu hoạt động.');
        }
        if ($parsedPolicyDates['cancellationClosesAt'] && $startAt && $parsedPolicyDates['cancellationClosesAt'] > $startAt) {
            $addFieldError('cancellationClosesAt', 'Thời gian đóng hủy đăng ký không được sau thời gian bắt đầu hoạt động.');
        }
    if (!$errors) {
        $payload = [
            'title' => $formValues['title'],
            'category' => $formValues['category'],
            'startAt' => $startAt,
            'endAt' => $endAt,
            'capacity' => (int) $formValues['capacity'],
            'displayCategory' => $formValues['displayCategory'], 'filterCategory' => $formValues['filterCategory'],
            'summary' => $formValues['summary'], 'description' => $formValues['description'],
            'experienceHighlights' => $formValues['experienceHighlights'], 'skillTags' => $formValues['skillTags'],
            'eligibilityRules' => $formValues['eligibilityRules'], 'benefitItems' => $formValues['benefitItems'],
            'locationName' => $formValues['locationName'], 'locationAddress' => $formValues['locationAddress'],
            'deliveryMode' => $formValues['deliveryMode'], 'onlineMeetingUrl' => $formValues['onlineMeetingUrl'],
            'organizerName' => $formValues['organizerName'], 'organizerContact' => $formValues['organizerContact'],
            'organizerEmail' => $formValues['organizerEmail'], 'organizerPhone' => $formValues['organizerPhone'],
            'coverImageUrl' => $formValues['coverImageUrl'], 'coverImageAlt' => $formValues['coverImageAlt'],
            'feeAmount' => $formValues['feeAmount'], 'currency' => $formValues['currency'],
            'targetAudience' => $formValues['targetAudience'], 'certificateLabel' => $formValues['certificateLabel'],
            'responsibleTeacherId' => $formValues['responsibleTeacherId'],
            'registrationOpensAt' => $parsedPolicyDates['registrationOpensAt'],
            'registrationClosesAt' => $parsedPolicyDates['registrationClosesAt'],
            'cancellationClosesAt' => $parsedPolicyDates['cancellationClosesAt'],
            'approvalMode' => $formValues['approvalMode'], 'confirmedHours' => $formValues['confirmedHours'],
        ];

        try {
            if ($formAction === 'edit') {
                if ($postedActivityId === '') {
                    throw new \TalentHub\Http\ApiException(422, 'VALIDATION_FAILED', 'Thiếu mã hoạt động cần chỉnh sửa.');
                }
                $activityService->update($teacherId, $postedActivityId, $payload);
                header('Location: index.php?saved=updated');
                exit;
            }
            if ($formAction !== 'create') {
                throw new \TalentHub\Http\ApiException(422, 'VALIDATION_FAILED', 'Thao tác hoạt động không hợp lệ.');
            }
            $activityService->create($teacherId, $schoolId, $payload);
            header('Location: index.php?saved=created');
            exit;
        } catch (Throwable $exception) {
            $message = $exception->getMessage() ?: 'Không thể lưu hoạt động. Vui lòng kiểm tra lại kết nối dữ liệu.';
            $errors[] = $message;
            $errorField = teacherActivitiesErrorField($message);
            if ($errorField !== null) $fieldErrors[$errorField] = $message;
        }
    }

        $action = $formAction === 'edit' ? 'edit' : 'create';
        if ($action === 'edit' && $postedActivityId !== '') {
            $activityId = $postedActivityId;
            $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
        }
        }
    }
}

$firstErrorField = $fieldErrors ? (string) array_key_first($fieldErrors) : '';
$registrationFields = ['registrationOpensAt', 'registrationClosesAt', 'cancellationClosesAt', 'approvalMode', 'confirmedHours'];
$additionalFields = ['description', 'experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems', 'targetAudience', 'organizerName', 'organizerContact', 'organizerEmail', 'organizerPhone', 'responsibleTeacherId', 'coverImageUrl', 'coverImageAlt', 'feeAmount', 'currency', 'certificateLabel'];
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
$listCount = count(array_filter(['experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems'], static fn (string $field): bool => trim((string) $formValues[$field]) !== ''));
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

$showForm = in_array($action, ['create', 'edit'], true);
$formHeading = $action === 'edit' ? 'Chỉnh sửa hoạt động' : 'Tạo hoạt động mới';
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <section class="teacher-activities-heading">
                        <div>
                            <span class="teacher-welcome__tag">Quản lý giáo viên</span>
                            <h2 class="teacher-activities-heading__title">Hoạt động / Sân chơi của tôi</h2>
                            <p class="teacher-activities-heading__description">Theo dõi lịch hoạt động, số lượng đăng ký và vòng đời hoạt động.</p>
                        </div>
                        <a href="?action=create" class="btn btn-primary teacher-activities-create">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Tạo hoạt động mới</span>
                        </a>
                    </section>

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

                    <?php if ($showForm): ?>
                        <section class="teacher-section-box teacher-activities-form-panel">
                            <div class="teacher-section-box__header">
                                <div>
                                    <div class="teacher-activities-form__heading-row">
                                        <h2 class="teacher-section-box__title"><?= teacherActivitiesEscape($formHeading); ?></h2>
                                        <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($action === 'edit' ? ($selectedActivity['status_class'] ?? 'draft') : 'draft'); ?>"><?= teacherActivitiesEscape($action === 'edit' ? ($selectedActivity['status_label'] ?? 'Bản nháp') : 'Bản nháp'); ?></span>
                                        <?php if ($action === 'edit' && !empty($selectedActivity['approval_status_label']) && ($selectedActivity['approval_status'] ?? '') !== 'draft'): ?>
                                            <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['approval_status_class']); ?>"><?= teacherActivitiesEscape($selectedActivity['approval_status_label']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="teacher-section-box__subtitle">Nhập thông tin chính trước, sau đó bổ sung thiết lập khi cần.</p>
                                </div>
                                <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                            </div>

                            <form method="post" enctype="multipart/form-data" class="teacher-activities-form" data-activity-form aria-describedby="<?= $errors ? 'teacher-activities-errors teacher-activities-form-note' : 'teacher-activities-form-note'; ?>">
                                <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'create'; ?>">
                                <input type="hidden" name="deliveryChanged" value="0" data-delivery-changed>
                                <?php if ($action === 'edit' && $activityId !== ''): ?>
                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($activityId); ?>">
                                <?php endif; ?>

                                <!-- KHỐI 1: THÔNG TIN CƠ BẢN & ẢNH BÌA -->
                                <section class="teacher-form-block teacher-activities-form__section" aria-labelledby="activity-basic-heading">
                                    <div class="teacher-form-block__title-row">
                                        <div class="teacher-form-block__icon" aria-hidden="true">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        </div>
                                        <div>
                                            <h3 id="activity-basic-heading" class="teacher-form-block__title">1. Thông tin cơ bản & Ảnh bìa</h3>
                                            <p class="teacher-form-block__desc">Tiêu đề, nhóm phân loại, ảnh đại diện và mô tả nội dung hoạt động.</p>
                                        </div>
                                    </div>

                                    <!-- Cover Image Section (Balanced 2-column Layout) -->
                                    <div class="teacher-cover-field teacher-form-field--wide">
                                        <div class="teacher-cover-grid">
                                            <!-- Cột trái: Ảnh bìa xem trước & Bộ nút hành động -->
                                            <div class="teacher-cover-col-preview">
                                                <div class="teacher-cover-col-title">
                                                    <span class="teacher-form-field__label"><strong>Ảnh bìa hoạt động</strong> <small class="text-muted">(JPG, PNG, WebP tối đa 5MB)</small></span>
                                                </div>
                                                <div class="teacher-cover-preview <?= $formValues['coverImageUrl'] !== '' ? 'teacher-cover-preview--has-image' : ''; ?>" id="cover-preview-wrapper">
                                                    <?php 
                                                        $currentCover = $formValues['coverImageUrl'] !== '' ? $formValues['coverImageUrl'] : '';
                                                        $previewSrc = $currentCover !== '' ? teacherCoverWebUrl($currentCover) : teacherCoverWebUrl(null);
                                                    ?>
                                                    <img id="cover-preview-img" src="<?= teacherActivitiesEscape($previewSrc); ?>" alt="<?= teacherActivitiesEscape($formValues['coverImageAlt'] ?: 'Xem trước ảnh bìa hoạt động'); ?>" class="teacher-cover-preview__img" data-fallback-src="<?= teacherActivitiesEscape(teacherCoverWebUrl(null)); ?>" onerror="this.onerror=null;this.src=this.dataset.fallbackSrc;">
                                                    <span class="teacher-cover-preview__badge" id="cover-preview-badge"><?= $currentCover !== '' ? 'Ảnh đang chọn' : 'Ảnh mặc định hệ thống'; ?></span>
                                                </div>
                                                <div class="teacher-cover-actions">
                                                    <label class="teacher-btn-cover-action teacher-btn-cover-action--primary" for="activity-cover-file">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                                        <span>Tải ảnh từ máy</span>
                                                    </label>
                                                    <input type="file" id="activity-cover-file" name="coverFile" accept="image/jpeg,image/png,image/webp" class="sr-only" data-cover-file-input style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">
                                                    <button type="button" class="teacher-btn-cover-action teacher-btn-cover-action--outline" id="btn-open-preset-modal">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                                        <span>Chọn ảnh mẫu (15)</span>
                                                    </button>
                                                    <button type="button" class="teacher-btn-cover-action teacher-btn-cover-action--danger" id="btn-remove-cover" <?= $currentCover === '' ? 'style="display:none;"' : ''; ?>>
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                        <span>Gỡ ảnh</span>
                                                    </button>
                                                </div>
                                                <input type="hidden" id="activity-cover-url" name="coverImageUrl" value="<?= teacherActivitiesEscape($formValues['coverImageUrl']); ?>">
                                                <?php if (isset($fieldErrors['coverFile'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['coverFile']); ?></small><?php endif; ?>
                                                <?php if (isset($fieldErrors['coverImageUrl'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['coverImageUrl']); ?></small><?php endif; ?>
                                            </div>

                                            <!-- Cột phải: Mô tả Alt Text & Tiêu chuẩn ảnh bìa -->
                                            <div class="teacher-cover-col-meta">
                                                <div class="teacher-cover-alt-box">
                                                    <label class="teacher-form-field" for="activity-cover-alt" style="margin:0;">
                                                        <span class="teacher-cover-alt-label">
                                                            <span><strong>Mô tả ảnh bìa (Alt text)</strong></span>
                                                            <span class="teacher-cover-alt-tag">Khuyên dùng</span>
                                                        </span>
                                                        <input id="activity-cover-alt" type="text" name="coverImageAlt" maxlength="255" value="<?= teacherActivitiesEscape($formValues['coverImageAlt']); ?>" placeholder="Ví dụ: Sinh viên thực hành lắp ráp robot trong phòng Lab">
                                                        <small class="text-muted teacher-cover-alt-hint">Mô tả nội dung hình ảnh hỗ trợ học sinh khiếm thị dùng trình đọc màn hình và giúp tối ưu hóa kết quả tìm kiếm hoạt động.</small>
                                                    </label>
                                                </div>

                                                <div class="teacher-cover-guidelines">
                                                    <div class="teacher-cover-guidelines__title">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                                        <span>Quy chuẩn & Mẹo chọn ảnh bìa thu hút</span>
                                                    </div>
                                                    <div class="teacher-cover-guidelines__grid">
                                                        <div class="teacher-cover-tip-item">
                                                            <span class="teacher-cover-tip-icon">📐</span>
                                                            <div class="teacher-cover-tip-text">
                                                                <strong>Tỉ lệ chuẩn 16:9</strong>
                                                                <p>Khuyến nghị độ phân giải 1200 &times; 675 px để ảnh hiển thị trọn vẹn, không bị cắt góc trên thẻ hoạt động.</p>
                                                            </div>
                                                        </div>
                                                        <div class="teacher-cover-tip-item">
                                                            <span class="teacher-cover-tip-icon">✨</span>
                                                            <div class="teacher-cover-tip-text">
                                                                <strong>Thư viện ảnh mẫu có sẵn</strong>
                                                                <p>Nhấp <em>"Chọn ảnh mẫu (15)"</em> để sử dụng ngay các ảnh WebP sắc nét theo đúng 4 nhóm chủ đề.</p>
                                                            </div>
                                                        </div>
                                                        <div class="teacher-cover-tip-item">
                                                            <span class="teacher-cover-tip-icon">🎯</span>
                                                            <div class="teacher-cover-tip-text">
                                                                <strong>Định dạng & Dung lượng</strong>
                                                                <p>Hỗ trợ JPG, PNG, WebP tối đa 5MB. Ưu tiên hình ảnh thực tế, sáng rõ để thu hút sinh viên đăng ký.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="teacher-activities-form__grid">
                                        <label class="teacher-form-field teacher-form-field--wide" for="activity-title">
                                            <span>Tên hoạt động <strong class="text-danger">*</strong></span>
                                            <input id="activity-title" type="text" name="title" maxlength="255" value="<?= teacherActivitiesEscape($formValues['title']); ?>" required aria-invalid="<?= isset($fieldErrors['title']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'title' ? 'data-focus-on-load' : ''; ?> placeholder="Ví dụ: Hội thảo Ứng dụng Trí tuệ Nhân tạo trong Doanh nghiệp">
                                            <?php if (isset($fieldErrors['title'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['title']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-category">
                                            <span>Nhóm hoạt động <strong class="text-danger">*</strong></span>
                                            <select id="activity-category" name="categoryChoice" required aria-invalid="<?= isset($fieldErrors['categoryChoice']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'categoryChoice' ? 'data-focus-on-load' : ''; ?>>
                                                <option value="">Chọn nhóm hoạt động</option>
                                                <?php if ($formValues['categoryChoice'] === '__preserve__'): ?><option value="__preserve__" selected>Nhóm hiện có (giữ nguyên)</option><?php endif; ?>
                                                <?php foreach ($categoryCatalog as $choice => $mapping): ?>
                                                    <option value="<?= teacherActivitiesEscape($choice); ?>" <?= $formValues['categoryChoice'] === $choice ? 'selected' : ''; ?>><?= teacherActivitiesEscape($mapping['displayCategory']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($fieldErrors['categoryChoice'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['categoryChoice']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-capacity">
                                            <span>Sức chứa tối đa <strong class="text-danger">*</strong></span>
                                            <input id="activity-capacity" type="number" name="capacity" min="1" step="1" value="<?= teacherActivitiesEscape($formValues['capacity']); ?>" required aria-invalid="<?= isset($fieldErrors['capacity']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'capacity' ? 'data-focus-on-load' : ''; ?> placeholder="Ví dụ: 30">
                                            <?php if (isset($fieldErrors['capacity'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['capacity']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field teacher-form-field--wide" for="activity-summary">
                                            <span>Tóm tắt giới thiệu ngắn <strong class="text-danger">*</strong> <small>(tối đa 500 ký tự - hiển thị trên thẻ hoạt động)</small></span>
                                            <textarea id="activity-summary" name="summary" maxlength="500" rows="2" required placeholder="Tóm tắt nội dung chính và điểm đặc sắc để học sinh đăng ký tham gia..."><?= teacherActivitiesEscape($formValues['summary']); ?></textarea>
                                            <?php if (isset($fieldErrors['summary'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['summary']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field teacher-form-field--wide" for="activity-description">
                                            <span>Mô tả chi tiết nội dung <strong class="text-danger">*</strong> <small>(hiển thị đầy đủ ở trang chi tiết)</small></span>
                                            <textarea id="activity-description" name="description" rows="6" required placeholder="Nêu rõ mục tiêu, chương trình chi tiết, diễn giả hướng dẫn, tài liệu cần chuẩn bị..."><?= teacherActivitiesEscape($formValues['description']); ?></textarea>
                                            <?php if (isset($fieldErrors['description'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['description']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-delivery-mode">
                                            <span>Hình thức tổ chức <strong class="text-danger">*</strong></span>
                                            <select id="activity-delivery-mode" name="deliveryMode" data-delivery-mode aria-invalid="<?= isset($fieldErrors['deliveryMode']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'deliveryMode' ? 'data-focus-on-load' : ''; ?>>
                                                <option value="in_person" <?= $formValues['deliveryMode'] === 'in_person' ? 'selected' : ''; ?>>Trực tiếp</option>
                                                <option value="online" <?= $formValues['deliveryMode'] === 'online' ? 'selected' : ''; ?>>Trực tuyến</option>
                                                <option value="hybrid" <?= $formValues['deliveryMode'] === 'hybrid' ? 'selected' : ''; ?>>Kết hợp</option>
                                            </select>
                                            <?php if (isset($fieldErrors['deliveryMode'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['deliveryMode']); ?></small><?php endif; ?>
                                        </label>

                                        <div class="teacher-activities-form__subgrid teacher-form-field--wide" data-location-fields>
                                            <label class="teacher-form-field" for="activity-location-name">
                                                <span>Tên địa điểm / Phòng tổ chức</span>
                                                <input id="activity-location-name" type="text" name="locationName" maxlength="255" value="<?= teacherActivitiesEscape($formValues['locationName']); ?>" placeholder="Ví dụ: Phòng Hội thảo A, Phòng Lab 402">
                                                <?php if (isset($fieldErrors['locationName'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['locationName']); ?></small><?php endif; ?>
                                            </label>
                                            <label class="teacher-form-field" for="activity-location-address">
                                                <span>Địa chỉ</span>
                                                <input id="activity-location-address" type="text" name="locationAddress" maxlength="500" value="<?= teacherActivitiesEscape($formValues['locationAddress']); ?>" placeholder="Địa chỉ cơ sở trường học hoặc nơi diễn ra">
                                            </label>
                                        </div>

                                        <label class="teacher-form-field teacher-form-field--wide" for="activity-online-url" data-online-fields>
                                            <span>Đường dẫn phòng trực tuyến (Google Meet / Zoom / MS Teams)</span>
                                            <input id="activity-online-url" type="text" inputmode="url" name="onlineMeetingUrl" maxlength="500" value="<?= teacherActivitiesEscape($formValues['onlineMeetingUrl']); ?>" placeholder="https://..." aria-invalid="<?= isset($fieldErrors['onlineMeetingUrl']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'onlineMeetingUrl' ? 'data-focus-on-load' : ''; ?>>
                                            <?php if (isset($fieldErrors['onlineMeetingUrl'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['onlineMeetingUrl']); ?></small><?php endif; ?>
                                        </label>
                                    </div>
                                </section>

                                <!-- KHỐI 2: LỊCH TRÌNH & THỜI HẠN ĐĂNG KÝ -->
                                <section class="teacher-form-block teacher-activities-form__section" aria-labelledby="activity-schedule-heading">
                                    <div class="teacher-form-block__title-row">
                                        <div class="teacher-form-block__icon" aria-hidden="true">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        </div>
                                        <div>
                                            <h3 id="activity-schedule-heading" class="teacher-form-block__title">2. Lịch trình & Thời hạn đăng ký</h3>
                                            <p class="teacher-form-block__desc">Thời gian diễn ra, thời hạn mở/đóng cổng đăng ký và số giờ trải nghiệm được công nhận.</p>
                                        </div>
                                    </div>

                                    <div class="teacher-activities-form__grid">
                                        <label class="teacher-form-field" for="activity-start-at">
                                            <span>Bắt đầu hoạt động <strong class="text-danger">*</strong></span>
                                            <input id="activity-start-at" type="datetime-local" name="startAt" value="<?= teacherActivitiesEscape($formValues['startAt']); ?>" required aria-invalid="<?= isset($fieldErrors['startAt']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'startAt' ? 'data-focus-on-load' : ''; ?>>
                                            <?php if (isset($fieldErrors['startAt'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['startAt']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-end-at">
                                            <span>Kết thúc hoạt động <strong class="text-danger">*</strong></span>
                                            <input id="activity-end-at" type="datetime-local" name="endAt" value="<?= teacherActivitiesEscape($formValues['endAt']); ?>" required aria-invalid="<?= isset($fieldErrors['endAt']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'endAt' ? 'data-focus-on-load' : ''; ?>>
                                            <?php if (isset($fieldErrors['endAt'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['endAt']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-registration-opens">
                                            <span>Mở đăng ký <strong class="text-danger">*</strong></span>
                                            <input id="activity-registration-opens" type="datetime-local" name="registrationOpensAt" value="<?= teacherActivitiesEscape($formValues['registrationOpensAt']); ?>" required aria-invalid="<?= isset($fieldErrors['registrationOpensAt']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'registrationOpensAt' ? 'data-focus-on-load' : ''; ?>>
                                            <?php if (isset($fieldErrors['registrationOpensAt'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['registrationOpensAt']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-registration-closes">
                                            <span>Đóng đăng ký <strong class="text-danger">*</strong></span>
                                            <input id="activity-registration-closes" type="datetime-local" name="registrationClosesAt" value="<?= teacherActivitiesEscape($formValues['registrationClosesAt']); ?>" required aria-invalid="<?= isset($fieldErrors['registrationClosesAt']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'registrationClosesAt' ? 'data-focus-on-load' : ''; ?>>
                                            <?php if (isset($fieldErrors['registrationClosesAt'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['registrationClosesAt']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-cancellation-closes">
                                            <span>Cho phép hủy đến</span>
                                            <input id="activity-cancellation-closes" type="datetime-local" name="cancellationClosesAt" value="<?= teacherActivitiesEscape($formValues['cancellationClosesAt']); ?>" aria-invalid="<?= isset($fieldErrors['cancellationClosesAt']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'cancellationClosesAt' ? 'data-focus-on-load' : ''; ?>>
                                            <small class="text-muted">Mặc định: trước khi bắt đầu 24 giờ.</small>
                                            <?php if (isset($fieldErrors['cancellationClosesAt'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['cancellationClosesAt']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-approval-mode">
                                            <span>Cách duyệt đăng ký <strong class="text-danger">*</strong></span>
                                            <select id="activity-approval-mode" name="approvalMode" aria-invalid="<?= isset($fieldErrors['approvalMode']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'approvalMode' ? 'data-focus-on-load' : ''; ?>>
                                                <option value="automatic" <?= $formValues['approvalMode'] === 'automatic' ? 'selected' : ''; ?>>Duyệt tự động</option>
                                                <option value="teacher_review" <?= $formValues['approvalMode'] === 'teacher_review' ? 'selected' : ''; ?>>Giáo viên duyệt</option>
                                            </select>
                                            <?php if (isset($fieldErrors['approvalMode'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['approvalMode']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-confirmed-hours">
                                            <span>Giờ trải nghiệm công nhận <strong class="text-danger">*</strong></span>
                                            <input id="activity-confirmed-hours" type="number" name="confirmedHours" min="0" max="24" step="0.01" value="<?= teacherActivitiesEscape($formValues['confirmedHours']); ?>" required aria-invalid="<?= isset($fieldErrors['confirmedHours']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'confirmedHours' ? 'data-focus-on-load' : ''; ?>>
                                            <small class="text-muted">Cộng trực tiếp vào hồ sơ sinh viên sau khi hoàn thành.</small>
                                            <?php if (isset($fieldErrors['confirmedHours'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['confirmedHours']); ?></small><?php endif; ?>
                                        </label>
                                    </div>
                                </section>

                                <!-- KHỐI 3: TRẢI NGHIỆM, KỸ NĂNG & YÊU CẦU THAM GIA -->
                                <section class="teacher-form-block teacher-activities-form__section" aria-labelledby="activity-experience-heading">
                                    <div class="teacher-form-block__title-row">
                                        <div class="teacher-form-block__icon" aria-hidden="true">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                        </div>
                                        <div>
                                            <h3 id="activity-experience-heading" class="teacher-form-block__title">3. Trải nghiệm, Kỹ năng & Yêu cầu tham gia</h3>
                                            <p class="teacher-form-block__desc">Làm rõ đối tượng, kỹ năng tích lũy, điều kiện tham gia và quyền lợi để học sinh - sinh viên nắm rõ.</p>
                                        </div>
                                    </div>

                                    <div class="teacher-form-grid-2col">
                                        <div class="teacher-form-col">
                                            <label class="teacher-form-field teacher-form-field--wide" for="activity-audience">
                                                <span>Đối tượng tham gia mục tiêu</span>
                                                <input id="activity-audience" type="text" name="targetAudience" maxlength="255" value="<?= teacherActivitiesEscape($formValues['targetAudience']); ?>" placeholder="Ví dụ: Sinh viên ngành CNTT, Học sinh THPT yêu thích lập trình">
                                            </label>

                                            <label class="teacher-form-field teacher-form-field--wide" for="activity-skillTags">
                                                <span>Kỹ năng phát triển <small>(mỗi dòng 1 kỹ năng - hiển thị dạng nhãn tag)</small></span>
                                                <textarea id="activity-skillTags" name="skillTags" rows="4" placeholder="Lập trình Python&#10;Kỹ năng làm việc nhóm&#10;Thuyết trình trước đám đông"><?= teacherActivitiesEscape($formValues['skillTags']); ?></textarea>
                                            </label>

                                            <label class="teacher-form-field teacher-form-field--wide" for="activity-experienceHighlights">
                                                <span>Nội dung trải nghiệm nổi bật <small>(mỗi dòng một mục - danh sách điểm nhấn)</small></span>
                                                <textarea id="activity-experienceHighlights" name="experienceHighlights" rows="4" placeholder="Thực hành trực tiếp trên máy chủ AI&#10;Giao lưu cùng chuyên gia doanh nghiệp&#10;Nhận chứng chỉ hoàn thành khóa học"><?= teacherActivitiesEscape($formValues['experienceHighlights']); ?></textarea>
                                            </label>
                                        </div>

                                        <div class="teacher-form-col">
                                            <label class="teacher-form-field teacher-form-field--wide" for="activity-eligibilityRules">
                                                <span>Điều kiện & Yêu cầu khi tham gia <small>(mỗi dòng một điều kiện)</small></span>
                                                <textarea id="activity-eligibilityRules" name="eligibilityRules" rows="4" placeholder="Mang theo laptop cá nhân đã cài sẵn phần mềm&#10;Có kiến thức cơ bản về lập trình&#10;Có mặt đúng giờ"><?= teacherActivitiesEscape($formValues['eligibilityRules']); ?></textarea>
                                            </label>

                                            <label class="teacher-form-field teacher-form-field--wide" for="activity-benefitItems">
                                                <span>Quyền lợi sinh viên nhận được <small>(mỗi dòng một quyền lợi)</small></span>
                                                <textarea id="activity-benefitItems" name="benefitItems" rows="4" placeholder="Nhận tài liệu học tập độc quyền&#10;Cấp chứng nhận tham gia trên hệ thống&#10;Cộng điểm rèn luyện theo quy định"><?= teacherActivitiesEscape($formValues['benefitItems']); ?></textarea>
                                            </label>

                                            <label class="teacher-form-field teacher-form-field--wide" for="activity-certificate">
                                                <span>Nhãn chứng nhận sau hoạt động</span>
                                                <input id="activity-certificate" type="text" name="certificateLabel" maxlength="255" value="<?= teacherActivitiesEscape($formValues['certificateLabel']); ?>" placeholder="Ví dụ: Minh chứng tham gia trên TalentHub">
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                <!-- KHỐI 4: TỔ CHỨC, LIÊN HỆ & CHI PHÍ -->
                                <section class="teacher-form-block teacher-activities-form__section" aria-labelledby="activity-org-heading">
                                    <div class="teacher-form-block__title-row">
                                        <div class="teacher-form-block__icon" aria-hidden="true">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                        </div>
                                        <div>
                                            <h3 id="activity-org-heading" class="teacher-form-block__title">4. Đơn vị tổ chức, Liên hệ & Chi phí</h3>
                                            <p class="teacher-form-block__desc">Đơn vị chủ trì, giáo viên phụ trách, đầu mối hỗ trợ và chính sách lệ phí tham gia.</p>
                                        </div>
                                    </div>

                                    <div class="teacher-activities-form__grid">
                                        <label class="teacher-form-field" for="activity-organizer">
                                            <span>Đơn vị tổ chức <strong class="text-danger">*</strong></span>
                                            <input id="activity-organizer" type="text" name="organizerName" maxlength="255" value="<?= teacherActivitiesEscape($formValues['organizerName']); ?>" required aria-invalid="<?= isset($fieldErrors['organizerName']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'organizerName' ? 'data-focus-on-load' : ''; ?> placeholder="Ví dụ: Khoa Công nghệ Thông tin - BTEC FPT">
                                            <?php if (isset($fieldErrors['organizerName'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['organizerName']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-responsible-teacher">
                                            <span>Giáo viên phụ trách</span>
                                            <select id="activity-responsible-teacher" name="responsibleTeacherId" aria-invalid="<?= isset($fieldErrors['responsibleTeacherId']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'responsibleTeacherId' ? 'data-focus-on-load' : ''; ?>>
                                                <option value="">Chưa chọn</option>
                                                <?php foreach ($responsibleTeachers as $responsibleTeacher): ?>
                                                    <option value="<?= teacherActivitiesEscape($responsibleTeacher['id']); ?>" <?= $formValues['responsibleTeacherId'] === $responsibleTeacher['id'] ? 'selected' : ''; ?>><?= teacherActivitiesEscape($responsibleTeacher['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($fieldErrors['responsibleTeacherId'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['responsibleTeacherId']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field teacher-form-field--wide" for="activity-organizer-contact">
                                            <span>Đầu mối liên hệ hỗ trợ</span>
                                            <input id="activity-organizer-contact" type="text" name="organizerContact" maxlength="255" value="<?= teacherActivitiesEscape($formValues['organizerContact']); ?>" placeholder="Ví dụ: Ban tổ chức Sự kiện Sinh viên">
                                        </label>

                                        <label class="teacher-form-field" for="activity-organizer-email">
                                            <span>Email liên hệ</span>
                                            <input id="activity-organizer-email" type="email" inputmode="email" name="organizerEmail" maxlength="255" value="<?= teacherActivitiesEscape($formValues['organizerEmail']); ?>" aria-invalid="<?= isset($fieldErrors['organizerEmail']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'organizerEmail' ? 'data-focus-on-load' : ''; ?> placeholder="contact@school.edu.vn">
                                            <?php if (isset($fieldErrors['organizerEmail'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['organizerEmail']); ?></small><?php endif; ?>
                                        </label>

                                        <label class="teacher-form-field" for="activity-organizer-phone">
                                            <span>Số điện thoại liên hệ</span>
                                            <input id="activity-organizer-phone" type="tel" name="organizerPhone" maxlength="30" value="<?= teacherActivitiesEscape($formValues['organizerPhone']); ?>" aria-invalid="<?= isset($fieldErrors['organizerPhone']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'organizerPhone' ? 'data-focus-on-load' : ''; ?> placeholder="0987654321">
                                            <?php if (isset($fieldErrors['organizerPhone'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['organizerPhone']); ?></small><?php endif; ?>
                                        </label>

                                        <fieldset class="teacher-form-field teacher-form-field--wide teacher-fee-choice">
                                            <legend>Chi phí tham gia</legend>
                                            <label><input type="radio" name="feeMode" value="free" <?= $formValues['feeMode'] === 'free' ? 'checked' : ''; ?> data-fee-mode> Miễn phí</label>
                                            <label><input type="radio" name="feeMode" value="paid" <?= $formValues['feeMode'] === 'paid' ? 'checked' : ''; ?> data-fee-mode> Có phí</label>
                                        </fieldset>

                                        <div class="teacher-activities-form__subgrid teacher-form-field--wide" data-fee-amount>
                                            <label class="teacher-form-field" for="activity-fee">
                                                <span>Mức phí (VND)</span>
                                                <input id="activity-fee" type="number" name="feeAmount" min="0" step="1000" value="<?= teacherActivitiesEscape($formValues['feeAmount']); ?>" aria-invalid="<?= isset($fieldErrors['feeAmount']) ? 'true' : 'false'; ?>" <?= $firstErrorField === 'feeAmount' ? 'data-focus-on-load' : ''; ?>>
                                                <?php if (isset($fieldErrors['feeAmount'])): ?><small class="teacher-form-field__error"><?= teacherActivitiesEscape($fieldErrors['feeAmount']); ?></small><?php endif; ?>
                                            </label>
                                            <label class="teacher-form-field" for="activity-currency">
                                                <span>Đơn vị tiền</span>
                                                <input id="activity-currency" type="text" name="currency" maxlength="3" value="<?= teacherActivitiesEscape($formValues['currency']); ?>" readonly aria-invalid="<?= isset($fieldErrors['currency']) ? 'true' : 'false'; ?>">
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                <p class="teacher-activities-form__note" id="teacher-activities-form-note">Hoạt động mới được lưu dưới dạng bản nháp. Giáo viên có thể công bố sau khi đã kiểm tra đầy đủ thông tin.</p>
                                <div class="teacher-activities-form__actions">
                                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                                    <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Lưu thay đổi' : 'Lưu bản nháp'; ?></button>
                                </div>
                            </form>
                        </section>
                    <?php elseif ($action === 'view' && $selectedActivity): ?>
                        <section class="teacher-section-box teacher-activity-detail-panel">
                            <div class="teacher-section-box__header">
                                <div>
                                    <span class="teacher-welcome__tag">Chi tiết hoạt động</span>
                                    <h2 class="teacher-section-box__title"><?= teacherActivitiesEscape($selectedActivity['title']); ?></h2>
                                </div>
                                <div class="teacher-activities-header-actions">
                                    <?php $detailLifecycleAction = teacherActivitiesLifecycleAction($selectedActivity); ?>
                                    <?php if ($detailLifecycleAction !== null): ?>
                                        <form method="post" class="teacher-activities-inline-form">
                                            <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                            <input type="hidden" name="form_action" value="<?= teacherActivitiesEscape($detailLifecycleAction['form_action']); ?>">
                                            <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm"><?= teacherActivitiesEscape($detailLifecycleAction['label']); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                                </div>
                            </div>
                            <div class="teacher-activity-detail-grid">
                                <div><span>Trạng thái</span><strong>
                                    <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['status_class']); ?>"><?= teacherActivitiesEscape($selectedActivity['status_label']); ?></span>
                                    <?php if (!empty($selectedActivity['approval_status_label']) && ($selectedActivity['approval_status'] ?? '') !== 'draft'): ?>
                                        <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['approval_status_class']); ?>"><?= teacherActivitiesEscape($selectedActivity['approval_status_label']); ?></span>
                                    <?php endif; ?>
                                </strong></div>
                                <div><span>Thời gian</span><strong><?= teacherActivitiesEscape($selectedActivity['start_label']); ?> – <?= teacherActivitiesEscape($selectedActivity['end_label']); ?></strong></div>
                                <div><span>Địa điểm</span><strong><?= teacherActivitiesEscape($selectedActivity['locationName'] ?? 'Chưa xác định địa điểm'); ?></strong></div>
                                <div><span>Đăng ký</span><strong><?= teacherActivitiesEscape((string) $selectedActivity['registered_count']); ?> / <?= teacherActivitiesEscape((string) $selectedActivity['capacity']); ?></strong></div>
                                <div><span>Khả năng đăng ký</span><strong><span class="teacher-registration-pill teacher-registration-pill--<?= $selectedActivity['registration_available'] ? 'available' : 'unavailable'; ?>"><?= teacherActivitiesEscape($selectedActivity['registration_label']); ?></span></strong></div>
                                <div><span>Nhóm</span><strong><?= teacherActivitiesEscape(!empty($selectedActivity['category_label']) ? $selectedActivity['category_label'] : (!empty($selectedActivity['displayCategory']) ? $selectedActivity['displayCategory'] : 'Chưa phân loại')); ?></strong></div>
                            </div>
                        </section>
                    <?php elseif ($action === 'registrations' && $selectedActivity): ?>
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

                        <!-- ── Breadcrumb / back link ──────────────────────────── -->
                        <nav class="teacher-reg-breadcrumb" aria-label="Điều hướng">
                            <a href="index.php" class="teacher-reg-breadcrumb__back">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                                Quay lại danh sách hoạt động
                            </a>
                        </nav>

                        <!-- ── Page heading ───────────────────────────────────── -->
                        <div class="teacher-reg-heading">
                            <div class="teacher-reg-heading__main">
                                <span class="teacher-welcome__tag">Danh sách sinh viên</span>
                                <h2 class="teacher-reg-heading__title"><?= teacherActivitiesEscape($selectedActivity['title']); ?></h2>
                                <p class="teacher-reg-heading__meta">
                                    <strong><?= teacherActivitiesEscape((string) $totalReg); ?></strong> sinh viên đăng ký
                                    <?php if ($capacity > 0): ?>
                                        &nbsp;·&nbsp; Sức chứa <strong><?= teacherActivitiesEscape((string) $capacity); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedActivity['start_label'])): ?>
                                        &nbsp;·&nbsp; <?= teacherActivitiesEscape($selectedActivity['start_label']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if (in_array($selectedActivity['status'] ?? '', ['published', 'ongoing'], true)): ?>
                                <a href="../checkins/index.php?activity_id=<?= teacherActivitiesEscape($selectedActivity['id']); ?>"
                                   class="btn btn-primary btn-sm teacher-reg-qr-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3"/><path d="M17 14h3"/><path d="M17 17v3"/></svg>
                                    Tạo QR điểm danh
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- ── Summary cards ──────────────────────────────────── -->
                        <?php if ($totalReg > 0): ?>
                        <div class="teacher-reg-summary">
                            <div class="teacher-reg-summary__card">
                                <span class="teacher-reg-summary__value"><?= $totalReg; ?></span>
                                <span class="teacher-reg-summary__label">Đã đăng ký</span>
                            </div>
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--success">
                                <span class="teacher-reg-summary__value"><?= $totalCheckedIn; ?></span>
                                <span class="teacher-reg-summary__label">Đã điểm danh</span>
                            </div>
                            <?php if ($totalPending > 0): ?>
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--warn">
                                <span class="teacher-reg-summary__value"><?= $totalPending; ?></span>
                                <span class="teacher-reg-summary__label">Chờ xác nhận</span>
                            </div>
                            <?php endif; ?>
                            <div class="teacher-reg-summary__card teacher-reg-summary__card--muted">
                                <span class="teacher-reg-summary__value"><?= $totalNotCheckedIn; ?></span>
                                <span class="teacher-reg-summary__label">Chưa điểm danh</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ── Student list ───────────────────────────────────── -->
                        <section class="teacher-section-box teacher-reg-panel" aria-labelledby="teacher-reg-panel-title">
                            <div class="teacher-section-box__header">
                                <h3 class="teacher-section-box__title" id="teacher-reg-panel-title">
                                    Danh sách sinh viên
                                </h3>
                            </div>

                            <?php if (!$registrationRows): ?>
                                <div class="teacher-empty-state">
                                    <div class="teacher-empty-state__icon" aria-hidden="true">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <circle cx="9" cy="7" r="4"/><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <path d="M16 11a4 4 0 0 1 0 8"/>
                                        </svg>
                                    </div>
                                    <h4 class="teacher-empty-state__title">Chưa có sinh viên đăng ký</h4>
                                    <p class="teacher-empty-state__desc">Danh sách sẽ hiển thị ngay khi có học viên đăng ký hoạt động này.</p>
                                </div>
                            <?php else: ?>
                                <div class="teacher-activities-table-wrap">
                                    <table class="teacher-activities-table teacher-reg-table">
                                        <thead>
                                            <tr>
                                                <th>Sinh viên</th>
                                                <th>Ngày đăng ký</th>
                                                <th>Trạng thái đăng ký</th>
                                                <th>Điểm danh</th>
                                                <th class="teacher-activities-table__actions-heading">Thao tác</th>
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
                                                ?>
                                                <tr>
                                                    <td data-label="Sinh viên">
                                                        <span class="teacher-reg-student-name"><?= teacherActivitiesEscape($registration['student_name'] ?: 'Học viên'); ?></span>
                                                        <?php if (!empty($registration['student_email'])): ?>
                                                            <span class="teacher-reg-student-email"><?= teacherActivitiesEscape($registration['student_email']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Ngày đăng ký">
                                                        <span class="teacher-text-muted"><?= teacherActivitiesEscape($regDate ?: '—'); ?></span>
                                                    </td>
                                                    <td data-label="Trạng thái đăng ký">
                                                        <span class="teacher-registration-pill teacher-registration-pill--reg-<?= teacherActivitiesEscape($regStatusClass($rSt)); ?>">
                                                            <?= teacherActivitiesEscape($regStatusLabel($rSt)); ?>
                                                        </span>
                                                    </td>
                                                    <td data-label="Điểm danh">
                                                        <span class="teacher-registration-pill teacher-registration-pill--checkin-<?= teacherActivitiesEscape($ciClass); ?>">
                                                            <?= teacherActivitiesEscape($ciLabel); ?>
                                                        </span>
                                                        <?php if ($ciTime !== ''): ?>
                                                            <span class="teacher-reg-checkin-time"><?= teacherActivitiesEscape($ciTime); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Thao tác">
                                                        <?php if ($rSt === 'pending'): ?>
                                                            <div class="teacher-activities-row-actions">
                                                                <form method="post" class="teacher-activities-inline-form">
                                                                    <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                    <input type="hidden" name="form_action" value="registration_transition">
                                                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                                                    <input type="hidden" name="registration_id" value="<?= teacherActivitiesEscape($registration['id']); ?>">
                                                                    <input type="hidden" name="expected_status" value="pending">
                                                                    <button type="submit" name="registration_action" value="approve" class="teacher-activity-action teacher-activity-action--button">Duyệt</button>
                                                                </form>
                                                                <form method="post" class="teacher-activities-inline-form">
                                                                    <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                    <input type="hidden" name="form_action" value="registration_transition">
                                                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                                                    <input type="hidden" name="registration_id" value="<?= teacherActivitiesEscape($registration['id']); ?>">
                                                                    <input type="hidden" name="expected_status" value="pending">
                                                                    <button type="submit" name="registration_action" value="reject" class="teacher-activity-action teacher-activity-action--button">Từ chối</button>
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
                                <a href="?action=create" class="btn btn-secondary btn-sm teacher-empty-state__action">Tạo hoạt động mới</a>
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
                                                    <a href="?action=view&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-title"><?= teacherActivitiesEscape($activity['title']); ?></a>
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
                                                        <a href="?action=view&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Chi tiết</a>
                                                        <a href="?action=edit&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Chỉnh sửa</a>
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

    <!-- Preset Cover Gallery Modal (Top-level overlay) -->
    <div id="teacher-preset-modal" class="teacher-preset-modal" role="dialog" aria-modal="true" aria-labelledby="preset-modal-title" hidden>
        <div class="teacher-preset-modal__dialog">
            <div class="teacher-preset-modal__header">
                <div>
                    <h3 id="preset-modal-title" class="teacher-preset-modal__title">Thư viện ảnh bìa mẫu</h3>
                    <small class="text-muted">Chọn nhanh ảnh minh họa sắc nét chuẩn WebP phù hợp với chủ đề hoạt động.</small>
                </div>
                <button type="button" class="teacher-preset-modal__close" aria-label="Đóng thư viện ảnh" data-close-modal>&times;</button>
            </div>
            <div class="teacher-preset-modal__tabs" role="tablist">
                <button type="button" class="teacher-preset-tab teacher-preset-tab--active" data-preset-tab="all" role="tab" aria-selected="true">Tất cả (15)</button>
                <button type="button" class="teacher-preset-tab" data-preset-tab="tech" role="tab" aria-selected="false">Kỹ thuật & Công nghệ</button>
                <button type="button" class="teacher-preset-tab" data-preset-tab="business" role="tab" aria-selected="false">Kinh doanh & Khởi nghiệp</button>
                <button type="button" class="teacher-preset-tab" data-preset-tab="creative" role="tab" aria-selected="false">Sáng tạo & Nghệ thuật</button>
                <button type="button" class="teacher-preset-tab" data-preset-tab="community" role="tab" aria-selected="false">Cộng đồng & Tình nguyện</button>
            </div>
            <div class="teacher-preset-modal__body">
                <div class="teacher-preset-grid">
                    <?php foreach ($presetCovers as $categoryGroup): ?>
                        <?php foreach ($categoryGroup['items'] as $presetItem): ?>
                            <button type="button" class="teacher-preset-card" data-preset-category="<?= teacherActivitiesEscape($categoryGroup['category']); ?>" data-preset-url="<?= teacherActivitiesEscape($presetItem['url']); ?>" data-preset-web-url="<?= teacherActivitiesEscape(teacherCoverWebUrl($presetItem['url'])); ?>" data-preset-alt="<?= teacherActivitiesEscape($presetItem['alt']); ?>">
                                <img src="<?= teacherActivitiesEscape(teacherCoverWebUrl($presetItem['url'])); ?>" alt="<?= teacherActivitiesEscape($presetItem['alt']); ?>" class="teacher-preset-card__thumb" loading="lazy">
                                <div class="teacher-preset-card__meta">
                                    <h4 class="teacher-preset-card__title"><?= teacherActivitiesEscape($presetItem['name']); ?></h4>
                                    <span class="teacher-preset-card__tag"><?= teacherActivitiesEscape($categoryGroup['category_name']); ?></span>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
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
    <script src="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/js/teacher-activity-form.js')); ?>"></script>
</body>
</html>
