<?php
/** TalentHub Learner - Assessment runner */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/assessment-data.php';

$assessmentCode = $_GET['code'] ?? $_GET['id'] ?? 'holland';
$validCodes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
if (!in_array($assessmentCode, $validCodes, true)) {
    $assessmentCode = 'holland';
}
$requestedBand = strtolower(trim((string) ($_GET['band'] ?? '')));
$educationBand = in_array($requestedBand, ['middle', 'high', 'college'], true) ? $requestedBand : '';
$historyResultUrl = 'assessment-result.php?code=' . urlencode($assessmentCode)
    . ($educationBand !== '' ? '&band=' . urlencode($educationBand) : '');

$assessmentNames = [
    'holland' => 'Holland — Sở thích nghề nghiệp',
    'mbti' => 'MBTI — Xu hướng tính cách',
    'disc' => 'DISC — Hành vi học tập',
    'multiple_intelligence' => 'Đa trí thông minh — Đa diện năng khiếu',
];
$assessmentName = $assessmentNames[$assessmentCode] ?? 'Bài đánh giá';
$pageTitle = $assessmentName;
$currentRoute = '/app/learner/discover.php';

$bootData = [
    'assessmentCode' => $assessmentCode,
    'endpoints' => [
        'catalog' => app_href('/app/learner/api/v1/assessments.php'),
        'attempts' => app_href('/app/learner/api/v1/assessment-attempts.php'),
        'answers' => app_href('/app/learner/api/v1/assessment-answers.php'),
        'submit' => app_href('/app/learner/api/v1/assessment-submit.php'),
    ],
    'appBase' => app_href(''),
    'apiBase' => app_href('/app/learner/api/v1'),
    'result_url' => app_href('/app/learner/assessment-result.php?code=' . urlencode($assessmentCode)),
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Thực hiện bài đánh giá năng khiếu trên TalentHub.">
    <title><?= learner_escape($assessmentName); ?> | TalentHub</title>
    <meta name="csrf-token" content="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
    <meta name="csrfToken" content="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css?v=<?= (int) @filemtime(__DIR__ . '/../../assets/css/learner.css'); ?>">
</head>
<body class="learner-app learner-page-assessment">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn">
                    <a href="discover.php">Khám phá năng khiếu</a><span aria-hidden="true">/</span><span><?= learner_escape($assessmentName); ?></span>
                </nav>

                <div class="learner-assessment-shell" data-assessment-runner data-assessment-code="<?= learner_escape($assessmentCode); ?>">
                    <!-- Loading state -->
                    <section class="learner-card learner-assessment-state" data-assessment-loading>
                        <span class="learner-assessment-spinner" aria-hidden="true"></span>
                        <h1>Đang tải bài đánh giá...</h1>
                        <p>TalentHub đang đồng bộ dữ liệu phiên bản và câu hỏi từ hệ thống.</p>
                    </section>

                    <!-- Source error state -->
                    <section class="learner-card learner-assessment-state learner-assessment-state--error" data-assessment-error hidden>
                        <?= learner_icon('info', 32); ?>
                        <h1>Không thể tải bài đánh giá</h1>
                        <p data-assessment-error-message>Đã xảy ra lỗi kết nối với máy chủ. Vui lòng thử lại.</p>
                        <button class="learner-btn learner-btn--primary" type="button" data-assessment-retry>Thử lại</button>
                    </section>

                    <!-- Save error state -->
                    <section class="learner-card learner-assessment-state learner-assessment-state--save-error" data-assessment-save-error hidden>
                        <?= learner_icon('alert-circle', 32); ?>
                        <h1>Lỗi lưu câu trả lời</h1>
                        <p data-assessment-save-error-message>Không thể lưu câu trả lời lên máy chủ. Vui lòng kiểm tra kết nối mạng và thử lại.</p>
                        <button class="learner-btn learner-btn--primary" type="button" data-assessment-retry-save>Thử lưu lại</button>
                    </section>

                    <!-- Validation error state -->
                    <section class="learner-card learner-assessment-state learner-assessment-state--validation-error" data-assessment-validation-error hidden>
                        <?= learner_icon('alert-triangle', 32); ?>
                        <h1>Yêu cầu chưa hợp lệ</h1>
                        <p data-assessment-validation-message>Vui lòng hoàn thành tất cả câu hỏi trước khi nộp bài.</p>
                        <button class="learner-btn learner-btn--secondary" type="button" data-assessment-back-to-questions>Quay lại câu hỏi</button>
                    </section>

                    <!-- Expired state -->
                    <section class="learner-card learner-assessment-state learner-assessment-state--expired" data-assessment-expired hidden>
                        <?= learner_icon('clock', 34); ?>
                        <h1>Phiên làm bài đã hết hạn</h1>
                        <p>Thời gian làm bài cho phiên này đã kết thúc. Bạn có thể bắt đầu một phiên làm bài mới.</p>
                        <button class="learner-btn learner-btn--primary" type="button" data-assessment-restart>Bắt đầu phiên mới</button>
                    </section>

                    <!-- Intro / Ready state -->
                    <section class="learner-card learner-assessment-intro" data-assessment-intro hidden>
                        <div class="learner-assessment-intro__visual">
                            <span><?= learner_icon('compass', 38); ?></span>
                        </div>
                        <div class="learner-assessment-intro__content">
                            <span class="learner-eyebrow">Bài đánh giá năng khiếu</span>
                            <h1 data-assessment-intro-name><?= learner_escape($assessmentName); ?></h1>
                            <p data-assessment-intro-desc>Khám phá năng khiếu và định hướng học tập qua các câu hỏi trắc nghiệm khách quan.</p>
                            <div class="learner-assessment-intro__facts">
                                <span><?= learner_icon('file-text', 18); ?><strong data-assessment-intro-count>Đang tải số câu</strong></span>
                                <span><?= learner_icon('check', 18); ?><strong>Tự động lưu câu trả lời</strong></span>
                                <span><?= learner_icon('compass', 18); ?><strong>Định hướng học tập</strong></span>
                            </div>
                            <div class="learner-data-note"><?= learner_icon('info', 17); ?><p>Kết quả chỉ phục vụ định hướng giáo dục và tham khảo học tập, không phải chẩn đoán tâm lý hay đánh giá tuyển sinh bắt buộc.</p></div>
                            <div class="learner-assessment-intro__actions">
                                <button class="learner-btn learner-btn--primary" type="button" data-assessment-start>Bắt đầu làm bài <?= learner_icon('arrow-right', 17); ?></button>
                                <a class="learner-btn learner-btn--secondary" href="<?= learner_escape($historyResultUrl); ?>">Xem lịch sử kết quả</a>
                            </div>
                            <button class="learner-text-button" type="button" data-assessment-resume hidden>Tiếp tục bản nháp hiện tại</button>
                        </div>
                    </section>

                    <!-- Active Runner (Continuous Scroll Stream) -->
                    <section class="learner-assessment-runner" data-assessment-active hidden>
                        <header class="learner-assessment-sticky-header" data-assessment-sticky-header>
                            <div class="learner-assessment-sticky-header__left">
                                <span class="learner-assessment-progress-text"><strong data-assessment-answered-counter>0</strong>/<span data-assessment-total-counter>0</span></span>
                            </div>
                            <div class="learner-assessment-progress-bar" role="progressbar" aria-label="Tiến độ bài đánh giá" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-assessment-progress>
                                <span class="learner-assessment-progress-fill" style="width: 0%;"></span>
                            </div>
                            <div class="learner-assessment-sticky-header__right">
                                <button type="button" class="learner-assessment-btn-detail" data-assessment-open-sheet>
                                    <?= learner_icon('list', 16); ?> Xem chi tiết
                                </button>
                                <button type="button" class="learner-btn learner-btn--primary btn-sm" data-assessment-open-submit data-assessment-header-submit hidden>
                                    Nộp bài
                                </button>
                            </div>
                        </header>

                        <!-- Continuous Question Stream Container -->
                        <div class="learner-assessment-stream" data-assessment-stream>
                            <!-- Dynamically populated question cards -->
                        </div>

                        <!-- Completion Card / Submit Actions at Bottom -->
                        <div class="learner-assessment-stream-footer" data-assessment-stream-footer hidden>
                            <div class="learner-assessment-complete-card">
                                <h2>Bạn đã hoàn thành tất cả câu hỏi!</h2>
                                <p>Hãy kiểm tra lại các lựa chọn nếu cần hoặc nhấn nút bên dưới để hoàn tất nộp bài đánh giá.</p>
                                <button type="button" class="learner-btn learner-btn--primary" data-assessment-open-submit>
                                    Kiểm tra &amp; nộp bài <?= learner_icon('arrow-right', 17); ?>
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <!-- Education band confirmation modal -->
    <div class="learner-modal" id="learner-assessment-band-modal" data-assessment-band-confirmation hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog learner-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="band-modal-title">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Xác nhận cấp học</span>
                    <h2 id="band-modal-title">Chọn cấp học của bạn</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
            </div>
            <p class="learner-modal__copy">Vui lòng xác nhận cấp học để hiển thị bộ câu hỏi phù hợp nhất với lứa tuổi của bạn.</p>
            <div class="learner-band-options">
                <label class="learner-band-option">
                    <input type="radio" name="education_band" value="middle">
                    <span><strong>Trung học cơ sở</strong> (Lớp 6 – 9)</span>
                </label>
                <label class="learner-band-option">
                    <input type="radio" name="education_band" value="high">
                    <span><strong>Trung học phổ thông</strong> (Lớp 10 – 12)</span>
                </label>
                <label class="learner-band-option">
                    <input type="radio" name="education_band" value="college">
                    <span><strong>Đại học / Cao đẳng</strong></span>
                </label>
            </div>
            <p class="learner-form-error" role="alert" data-assessment-band-error hidden>Vui lòng chọn một cấp học để tiếp tục.</p>
            <div class="learner-modal__actions">
                <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                <button class="learner-btn learner-btn--primary" type="button" data-confirm-band>Xác nhận và tiếp tục</button>
            </div>
        </div>
    </div>

    <!-- Submit confirmation modal -->
    <div class="learner-modal" id="learner-assessment-submit-modal" hidden data-assessment-submit-modal>
        <button class="learner-modal__backdrop" type="button" data-close-modal aria-label="Đóng xác nhận nộp bài"></button>
        <section class="learner-modal__dialog learner-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="assessment-submit-title">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Xác nhận hoàn thành</span>
                    <h2 id="assessment-submit-title">Nộp bài đánh giá?</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
            </div>
            <p class="learner-modal__copy" data-assessment-submit-copy>Hãy kiểm tra lại câu trả lời trước khi nộp.</p>
            <div class="learner-assessment-submit-summary">
                <span>Đã trả lời <strong data-submit-answered>0</strong></span>
                <span>Chưa trả lời <strong data-submit-unanswered>0</strong></span>
            </div>
            <p class="learner-form-error" role="alert" hidden data-assessment-submit-error>Bạn cần trả lời tất cả câu hỏi trước khi nộp.</p>
            <div class="learner-modal__actions">
                <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Kiểm tra lại</button>
                <button class="learner-btn learner-btn--primary" type="button" data-assessment-submit>Xác nhận nộp bài</button>
            </div>
        </section>
    </div>

    <!-- Assessment Retake Confirmation Modal -->
    <div class="learner-modal" id="learner-assessment-retake-modal" hidden data-assessment-retake-modal>
        <button class="learner-modal__backdrop" type="button" data-close-retake-modal aria-label="Đóng"></button>
        <section class="learner-modal__dialog learner-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="retake-modal-title">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Khuyến nghị chu kỳ đánh giá</span>
                    <h2 id="retake-modal-title">Xác nhận làm lại bài đánh giá</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-retake-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
            </div>
            <div class="learner-modal__copy" data-retake-modal-message>
                Bạn đã hoàn thành bài đánh giá này cách đây <strong data-retake-elapsed-days>0</strong> ngày. Kết quả xu hướng năng lực và tính cách thường ổn định và đạt độ tin cậy cao nhất sau chu kỳ <strong>90 ngày</strong> (còn <strong data-retake-remaining-days>0</strong> ngày nữa). Bạn có chắc chắn muốn làm lại ngay bây giờ không?
            </div>
            <div class="learner-modal__actions">
                <button class="learner-btn learner-btn--secondary" type="button" data-cancel-retake data-close-retake-modal>Giữ kết quả hiện tại</button>
                <button class="learner-btn learner-btn--primary" type="button" data-confirm-retake>Xác nhận làm lại</button>
            </div>
        </section>
    </div>

    <!-- Quick Jump Sheet Modal (Xem chi tiết) -->
    <div class="learner-modal" id="learner-assessment-sheet-modal" hidden data-assessment-sheet-modal>
        <button class="learner-modal__backdrop" type="button" data-close-sheet-modal aria-label="Đóng danh sách câu hỏi"></button>
        <section class="learner-modal__dialog learner-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="assessment-sheet-title">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Danh sách câu hỏi</span>
                    <h2 id="assessment-sheet-title">Chi tiết tiến độ làm bài</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-sheet-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
            </div>
            <p class="learner-modal__copy">Nhấp vào ô số bất kỳ để nhảy nhanh tới câu hỏi tương ứng:</p>
            <div class="learner-question-navigator__grid" data-assessment-sheet-grid></div>
            <div class="learner-question-navigator__legend">
                <span><i class="is-answered"></i> Đã trả lời</span>
                <span><i class="is-unanswered"></i> Chưa trả lời</span>
            </div>
            <div class="learner-modal__actions" style="margin-top: 20px;">
                <button class="learner-btn learner-btn--secondary" type="button" data-close-sheet-modal>Đóng</button>
                <button class="learner-btn learner-btn--primary" type="button" data-assessment-open-submit>Nộp bài ngay</button>
            </div>
        </section>
    </div>

    <script id="learner-session-boot" type="application/json"><?= json_encode([
        'csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? ''),
        'apiBase' => app_href('/app/learner/api/v1'),
        'appBase' => app_href(''),
    ], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script id="learner-assessment-boot" type="application/json"><?= json_encode($bootData, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/learner-api.js'); ?>"></script>
    <script src="../../assets/js/learner.js?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/learner.js'); ?>"></script>
    <script src="../../assets/js/learner-assessment.js?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/learner-assessment.js'); ?>"></script>
</body>
</html>
