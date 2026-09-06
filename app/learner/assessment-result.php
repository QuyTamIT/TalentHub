<?php
/** TalentHub Learner - Assessment result and history */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/assessment-data.php';

$assessmentCode = $_GET['code'] ?? $_GET['id'] ?? 'holland';
$validCodes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
if (!in_array($assessmentCode, $validCodes, true)) {
    $assessmentCode = 'holland';
}

$assessmentNames = [
    'holland' => 'Holland — Sở thích nghề nghiệp',
    'mbti' => 'MBTI — Xu hướng tính cách',
    'disc' => 'DISC — Hành vi học tập',
    'multiple_intelligence' => 'Đa trí thông minh — Đa diện năng khiếu',
];
$assessmentName = $assessmentNames[$assessmentCode] ?? 'Bài đánh giá';
$pageTitle = 'Kết quả ' . $assessmentName;
$currentRoute = '/app/learner/discover.php';

$bootData = [
    'assessmentCode' => $assessmentCode,
    'endpoints' => [
        'detail' => '/app/learner/api/v1/assessments.php',
        'attempts' => '/app/learner/api/v1/assessment-attempts.php',
        'history' => '/app/learner/api/v1/assessments.php?view=history',
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kết quả và lịch sử bài đánh giá năng khiếu trên TalentHub.">
    <title>Kết quả <?= learner_escape($assessmentName); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-assessment-result">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content" data-assessment-result-page data-assessment-code="<?= learner_escape($assessmentCode); ?>">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn">
                    <a href="discover.php">Khám phá năng khiếu</a>
                    <span>/</span>
                    <a href="assessment.php?code=<?= learner_escape($assessmentCode); ?>"><?= learner_escape($assessmentName); ?></a>
                    <span>/</span>
                    <span>Kết quả</span>
                </nav>

                <section class="learner-card learner-assessment-state" data-assessment-result-loading>
                    <span class="learner-assessment-spinner" aria-hidden="true"></span>
                    <h1>Đang tải kết quả...</h1>
                    <p>TalentHub đang lấy kết quả và lịch sử bài đánh giá từ hệ thống.</p>
                </section>

                <section class="learner-card learner-assessment-state learner-assessment-state--error" data-assessment-result-error hidden>
                    <h1>Không thể tải kết quả</h1>
                    <p>Vui lòng thử lại sau.</p>
                </section>

                <section class="learner-card learner-not-found" data-assessment-result-empty hidden>
                    <h1>Chưa có kết quả</h1>
                    <p>Hãy hoàn thành bài đánh giá để xem phân tích chi tiết.</p>
                    <a class="learner-btn learner-btn--primary" href="assessment.php?code=<?= learner_escape($assessmentCode); ?>">Làm bài đánh giá</a>
                </section>

                <div data-assessment-result-content hidden>
                    <section class="learner-card learner-result-hero" data-assessment-current-result>
                        <div class="learner-result-hero__code" aria-label="Mã kết quả" data-result-code>---</div>
                        <div>
                            <span class="learner-eyebrow">Kết quả đánh giá gần nhất</span>
                            <h1>Đặc điểm nổi bật: <span data-result-primary-name>Đang tải...</span></h1>
                            <p data-result-primary-summary></p>
                            <span class="learner-demo-pill" data-result-source>Hệ thống TalentHub</span>
                        </div>
                        <div class="learner-result-hero__actions">
                            <a class="learner-btn learner-btn--primary" href="assessment.php?code=<?= learner_escape($assessmentCode); ?>">Làm lại bài đánh giá</a>
                            <a class="learner-btn learner-btn--outline" href="discover.php">Về trang khám phá</a>
                        </div>
                    </section>

                    <div class="learner-result-layout">
                        <section class="learner-card learner-result-scores" aria-labelledby="result-scores-title">
                            <div class="learner-section-heading">
                                <div>
                                    <h2 id="result-scores-title">Kết quả theo chiều đánh giá</h2>
                                    <p>Thang điểm chuẩn hóa 0–100</p>
                                </div>
                            </div>
                            <div class="learner-result-dimension-list" data-result-dimension-list>
                            </div>
                        </section>

                        <aside class="learner-card learner-result-guidance">
                            <h2>Gợi ý phát triển tiếp theo</h2>
                            <p>Ưu tiên trải nghiệm thực tế và rèn luyện kỹ năng qua các hoạt động phù hợp.</p>
                            <ul data-result-suggestions>
                            </ul>
                            <a class="learner-btn learner-btn--outline learner-btn--block" href="ecosystem.php?tab=opportunities">Khám phá cơ hội phù hợp</a>
                        </aside>
                    </div>

                    <div class="learner-data-note" data-advisory-disclaimer>
                        <?= learner_icon('info', 17); ?>
                        <p>Kết quả bài đánh giá chỉ phục vụ định hướng giáo dục và tham khảo học tập, không phải chẩn đoán tâm lý y khoa hay quyết định tuyển sinh bắt buộc.</p>
                    </div>
                </div>

                <section
                    class="learner-card learner-assessment-history"
                    data-assessment-complete-history
                    data-source="assessment_engine"
                    aria-labelledby="assessment-complete-history-title"
                >
                    <div class="learner-section-heading">
                        <div>
                            <h2 id="assessment-complete-history-title">Toàn bộ lịch sử đánh giá</h2>
                            <p>Mọi lần làm bài đã nộp và đã có kết quả, thuộc mọi bộ công cụ đánh giá.</p>
                        </div>
                        <span class="learner-demo-pill">Nguồn: hệ thống đánh giá tự động</span>
                    </div>
                    <p class="learner-empty-state__text" data-assessment-complete-history-loading>Đang tải lịch sử đánh giá...</p>
                    <p class="learner-empty-state__text" data-assessment-complete-history-empty hidden>Chưa có dữ liệu</p>
                    <p class="learner-empty-state__text" data-assessment-complete-history-error hidden>Không thể tải lịch sử đánh giá.</p>
                    <div class="learner-assessment-history__list" data-assessment-complete-history-list hidden></div>
                </section>

                <section
                    class="learner-card learner-assessment-history"
                    data-teacher-published-evaluations
                    data-source="teacher_published_evaluation"
                    aria-labelledby="teacher-published-evaluations-title"
                >
                    <div class="learner-section-heading">
                        <div>
                            <h2 id="teacher-published-evaluations-title">Đánh giá đã công bố từ giáo viên</h2>
                            <p>Chỉ hiển thị các đánh giá đã được giáo viên công bố.</p>
                        </div>
                        <span class="learner-demo-pill">Nguồn: giáo viên công bố</span>
                    </div>
                    <p class="learner-empty-state__text" data-teacher-published-evaluation-loading>Đang tải đánh giá đã công bố...</p>
                    <p class="learner-empty-state__text" data-teacher-published-evaluation-empty hidden>Chưa có dữ liệu</p>
                    <p class="learner-empty-state__text" data-teacher-published-evaluation-error hidden>Không thể tải đánh giá đã công bố.</p>
                    <div class="learner-assessment-history__list" data-teacher-published-evaluation-list hidden></div>
                </section>
            </main>
        </div>
    </div>

    <script id="learner-session-boot" type="application/json"><?= json_encode(['csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ''], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script id="learner-assessment-result-boot" type="application/json"><?= json_encode($bootData, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
