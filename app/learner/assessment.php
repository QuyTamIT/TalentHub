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
        'catalog' => '/app/learner/api/v1/assessments.php',
        'attempts' => '/app/learner/api/v1/assessment-attempts.php',
        'answers' => '/app/learner/api/v1/assessment-answers.php',
        'submit' => '/app/learner/api/v1/assessment-submit.php',
    ],
    'result_url' => 'assessment-result.php?code=' . urlencode($assessmentCode),
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Thực hiện bài đánh giá năng khiếu trên TalentHub.">
    <title><?= learner_escape($assessmentName); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
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
                                <span><?= learner_icon('file-text', 18); ?><strong data-assessment-intro-count>24 câu</strong></span>
                                <span><?= learner_icon('clock', 18); ?><strong data-assessment-intro-duration>12 phút</strong></span>
                                <span><?= learner_icon('check', 18); ?><strong>Tự động lưu câu trả lời</strong></span>
                            </div>
                            <div class="learner-data-note"><?= learner_icon('info', 17); ?><p>Kết quả chỉ phục vụ định hướng giáo dục và tham khảo học tập, không phải chẩn đoán tâm lý hay đánh giá tuyển sinh bắt buộc.</p></div>
                            <div class="learner-assessment-intro__actions">
                                <button class="learner-btn learner-btn--primary" type="button" data-assessment-start>Bắt đầu làm bài <?= learner_icon('arrow-right', 17); ?></button>
                                <a class="learner-btn learner-btn--secondary" href="<?= learner_escape($historyResultUrl); ?>">Xem lịch sử kết quả</a>
                            </div>
                            <button class="learner-text-button" type="button" data-assessment-resume hidden>Tiếp tục bản nháp hiện tại</button>
                        </div>
                    </section>

                    <!-- Active Runner -->
                    <section class="learner-assessment-runner" data-assessment-active hidden>
                        <header class="learner-card learner-assessment-runner__header">
                            <div>
                                <span class="learner-eyebrow" data-assessment-header-version><?= learner_escape($assessmentName); ?></span>
                                <h1 id="assessment-question-heading" tabindex="-1">Câu <span data-assessment-position>1</span></h1>
                            </div>
                            <div class="learner-assessment-timer" role="timer" aria-live="off">
                                <?= learner_icon('clock', 18); ?><span>Còn lại</span><strong data-assessment-timer>12:00</strong>
                            </div>
                            <div class="learner-progress learner-assessment-overall-progress" role="progressbar" aria-label="Tiến độ bài đánh giá" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-assessment-progress>
                                <span style="--learner-progress: 0%;"></span>
                            </div>
                            <p class="learner-assessment-save-status" role="status" aria-live="polite" data-assessment-save-status>Đã sẵn sàng.</p>
                        </header>

                        <div class="learner-assessment-runner__layout">
                            <section class="learner-card learner-question-card">
                                <p class="learner-question-card__hint">Mức độ phát biểu dưới đây phù hợp với bạn:</p>
                                <h2 data-assessment-question></h2>
                                <fieldset class="learner-likert-options" data-assessment-options>
                                    <legend class="learner-visually-hidden">Chọn mức độ phù hợp nhất</legend>
                                </fieldset>
                                <p class="learner-form-error" role="alert" hidden data-assessment-question-error>Hãy chọn một phương án trước khi tiếp tục.</p>
                                <div class="learner-question-card__actions">
                                    <button class="learner-btn learner-btn--secondary" type="button" data-assessment-previous><?= learner_icon('arrow-left', 17); ?> Câu trước</button>
                                    <button class="learner-btn learner-btn--primary" type="button" data-assessment-next>Câu tiếp <?= learner_icon('arrow-right', 17); ?></button>
                                    <button class="learner-btn learner-btn--primary" type="button" data-open-modal="learner-assessment-submit-modal" data-assessment-open-submit hidden>Kiểm tra &amp; nộp bài</button>
                                </div>
                            </section>

                            <aside class="learner-card learner-question-navigator" aria-labelledby="question-navigator-title">
                                <div class="learner-section-heading"><h2 id="question-navigator-title">Danh sách câu hỏi</h2><span><b data-assessment-answered-count>0</b> đã trả lời</span></div>
                                <div class="learner-question-navigator__grid" data-assessment-navigator>
                                </div>
                                <div class="learner-question-navigator__legend"><span><i class="is-current"></i> Đang xem</span><span><i class="is-answered"></i> Đã trả lời</span></div>
                            </aside>
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

    <script id="learner-session-boot" type="application/json"><?= json_encode(['csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ''], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script id="learner-assessment-boot" type="application/json"><?= json_encode($bootData, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
