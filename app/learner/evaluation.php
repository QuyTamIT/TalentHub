<?php
/** TalentHub Learner - Competency evaluation & Teacher Feedback */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Đánh giá & Nhận xét Năng lực';
$currentRoute = '/app/learner/evaluation.php';
$evaluationSourceState = 'ready';

function learner_eval_classification(float $score): string {
    $norm = $score > 10 ? $score / 10.0 : $score;
    return match (true) {
        $norm >= 8.5 => 'Xuất sắc',
        $norm >= 7.0 => 'Tốt / Giỏi',
        $norm >= 5.0 => 'Khá / Đạt',
        default => 'Cần cải thiện',
    };
}

function learner_eval_ranking(float $score): string {
    $norm = $score > 10 ? $score / 10.0 : $score;
    return match (true) {
        $norm >= 9.0 => 'Mức xuất sắc (từ 9/10)',
        $norm >= 8.0 => 'Mức nổi trội (từ 8/10)',
        $norm >= 7.0 => 'Mức đạt chuẩn (từ 7/10)',
        default => 'Đang rèn luyện và tiến bộ',
    };
}

if ($isDatabaseMode) {
    try {
        $studentIdForEval = learner_current_student_id();
        $publishedEvaluations = learner_repository_factory()->assessment()->publishedEvaluationsForStudent($studentIdForEval);

        $evaluationTerms = [];
        $defaultEvaluationTerm = '';

        foreach ($publishedEvaluations as $eval) {
            $evaluationId = trim((string) ($eval['id'] ?? ''));
            if ($evaluationId === '') {
                continue;
            }

            $publishedAt = trim((string) ($eval['published_at'] ?? ''));
            $publishedDate = $publishedAt;
            if ($publishedAt !== '') {
                $parsedPublishedAt = date_create_immutable($publishedAt);
                $publishedDate = $parsedPublishedAt === false ? $publishedAt : $parsedPublishedAt->format('d/m/Y');
            }
            $activityTitle = trim((string) ($eval['activity_title'] ?? ''));
            $evaluationLabel = $activityTitle !== '' ? $activityTitle : 'Đánh giá đồ án & năng lực';
            if ($publishedDate !== '') {
                $evaluationLabel .= ' · ' . $publishedDate;
            }

            $rawScore = (float) ($eval['overall_score'] ?? 8.5);
            $displayScore = $rawScore > 10 ? number_format($rawScore / 10.0, 1) : number_format($rawScore, 1);

            $criteria = [];
            $toneMap = ['primary', 'secondary', 'accent', 'primary', 'secondary'];
            foreach ($eval['scores'] ?? [] as $idx => $score) {
                $cScore = (float) ($score['score'] ?? 0);
                $cMax = (float) ($score['max_score'] ?? 10);
                if ($cMax <= 0) $cMax = 10;
                $criteria[] = [
                    'name' => (string) ($score['criteria_name'] ?? 'Tiêu chí'),
                    'score' => $cScore,
                    'max' => $cMax,
                    'tone' => $toneMap[$idx % count($toneMap)],
                ];
            }

            $reviewerName = (string) ($eval['reviewer_name'] ?? 'Giáo viên hướng dẫn');
            $words = preg_split('/\s+/', trim($reviewerName));
            $reviewerInitials = count($words) > 1
                ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
                : 'GV';

            $evaluationTerms[$evaluationId] = [
                'label' => $evaluationLabel,
                'status' => 'Đã công bố',
                'evaluation' => [
                    'criteria' => $criteria,
                    'total' => $displayScore,
                    'max_total' => '10',
                    'classification' => learner_eval_classification($rawScore),
                    'ranking' => learner_eval_ranking($rawScore),
                    'comment' => (string) ($eval['comment'] ?? 'Chưa có nhận xét chi tiết.'),
                    'reviewer' => $reviewerName,
                    'reviewer_initials' => $reviewerInitials,
                    'published_date' => $publishedDate,
                    'activity_title' => $activityTitle ?: 'Đồ án Chuyên ngành & Năng lực',
                ],
            ];

            if ($defaultEvaluationTerm === '') {
                $defaultEvaluationTerm = $evaluationId;
            }
        }

        $evaluationSourceState = $evaluationTerms === [] ? 'empty' : 'ready';
    } catch (\Throwable) {
        $evaluationSourceState = 'source-error';
        $evaluationTerms = [];
        $defaultEvaluationTerm = '';
    }
} elseif ($evaluationTerms === []) {
    $evaluationSourceState = 'empty';
}

$currentTerm = $evaluationTerms[$defaultEvaluationTerm]
    ?? ['label' => 'Chưa có dữ liệu', 'status' => 'Chưa có dữ liệu', 'evaluation' => null];
$currentEvaluation = $currentTerm['evaluation'] ?? null;
$hasEvaluation = $evaluationSourceState === 'ready' && is_array($currentEvaluation);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi điểm đánh giá năng lực và nhận xét phản hồi từ giảng viên, huấn luyện viên trên TalentHub.">
    <title>Đánh giá & Nhận xét Năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
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
            --success: #16A34A;
            --warning: #F59E0B;
            --danger: #DC2626;
            --radius-sm: 8px;
            --radius-md: 12px;
        }

        .learner-evaluation-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
        }

        @media (max-width: 960px) {
            .learner-evaluation-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Hero Feedback Card */
        .eval-hero-feedback {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .eval-feedback-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .eval-feedback-author {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .eval-feedback-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            border: 2px solid rgba(249, 115, 22, 0.25);
            flex-shrink: 0;
        }

        .eval-feedback-author-info strong {
            display: block;
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 700;
        }

        .eval-feedback-author-info span {
            font-size: 0.825rem;
            color: var(--text-secondary);
        }

        .eval-feedback-verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .eval-feedback-quote {
            margin: 1.25rem 0;
            padding: 1.15rem 1.35rem;
            background: #F8FAFC;
            border-left: 4px solid var(--primary);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            font-size: 1rem;
            line-height: 1.65;
            color: var(--text-primary);
            font-style: normal;
        }

        .eval-feedback-tags {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .eval-tag {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(249, 115, 22, 0.2);
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Criteria Section */
        .eval-criteria-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .eval-criteria-card h2 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .eval-criterion-row {
            margin-bottom: 1.25rem;
        }

        .eval-criterion-row:last-child {
            margin-bottom: 0;
        }

        .eval-criterion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.45rem;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .eval-criterion-score {
            color: var(--primary);
            font-weight: 800;
        }

        .eval-progress-track {
            height: 10px;
            background: #E2E8F0;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
        }

        .eval-progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.6s ease;
        }

        .eval-progress-fill--primary { background: linear-gradient(90deg, #F97316, #EA580C); }
        .eval-progress-fill--secondary { background: linear-gradient(90deg, #2563EB, #1D4ED8); }
        .eval-progress-fill--accent { background: linear-gradient(90deg, #16A34A, #15803D); }
        .eval-progress-fill--warning { background: linear-gradient(90deg, #F59E0B, #D97706); }

        /* Summary Score Card */
        .eval-summary-panel {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.75rem 1.5rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            position: sticky;
            top: 1.5rem;
        }

        .eval-summary-title {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .eval-total-number {
            font-size: 3.25rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
            margin: 0.25rem 0 0.25rem;
            letter-spacing: -0.03em;
        }

        .eval-total-scale {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .eval-class-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(249, 115, 22, 0.3);
            padding: 0.45rem 1.15rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.95rem;
            margin-bottom: 1.25rem;
        }

        .eval-rank-box {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.85rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .eval-passport-status {
            font-size: 0.8rem;
            color: var(--success);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
    </style>
</head>
<body class="learner-app learner-page-evaluation">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-evaluation-page-title',
                    'eyebrow' => 'Theo dõi tiến bộ',
                    'title' => 'Đánh giá & Nhận xét Năng lực',
                    'description' => 'Xem điểm số theo tiêu chí, nhận xét và lời khuyên định hướng phát triển từ Giảng viên / Huấn luyện viên.',
                    'icon' => 'clipboard',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <!-- Term & Filter Bar -->
                <div class="learner-evaluation-heading learner-evaluation-heading--actions" style="margin-bottom: 1.5rem;">
                    <div class="learner-evaluation-heading__actions" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label class="learner-term-select" for="learner-evaluation-term" style="margin: 0;">
                            <?= learner_icon('calendar', 18); ?>
                            <span class="learner-visually-hidden">Chọn đợt đánh giá</span>
                            <select id="learner-evaluation-term" name="term" style="font-weight: 700; color: var(--text-primary);">
                                <?php if ($evaluationTerms === []): ?>
                                    <option value="">Chưa có dữ liệu đánh giá</option>
                                <?php endif; ?>
                                <?php foreach ($evaluationTerms as $termId => $term): ?>
                                    <option value="<?= learner_escape($termId); ?>" <?= $termId === $defaultEvaluationTerm ? 'selected' : ''; ?>>
                                        <?= learner_escape($term['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <span class="learner-publication-status" data-evaluation-status data-state="<?= $hasEvaluation ? 'published' : 'empty'; ?>" role="status" aria-live="polite" aria-atomic="true">
                            <span aria-hidden="true"></span><?= learner_escape($currentTerm['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="learner-evaluation-grid">
                    <!-- Left Column: Feedback & Criteria -->
                    <div data-evaluation-content <?= $hasEvaluation ? '' : 'hidden'; ?>>

                        <!-- 1. Hero Feedback Card -->
                        <section class="eval-hero-feedback" aria-label="Nhận xét từ Giảng viên">
                            <div class="eval-feedback-header">
                                <div class="eval-feedback-author">
                                    <div class="eval-feedback-avatar" data-evaluation-reviewer-avatar>
                                        <?= learner_escape($currentEvaluation['reviewer_initials'] ?? 'GV'); ?>
                                    </div>
                                    <div class="eval-feedback-author-info">
                                        <strong data-evaluation-reviewer><?= learner_escape($currentEvaluation['reviewer'] ?? 'Giáo viên'); ?></strong>
                                        <span>Giảng viên hướng dẫn • Đợt: <span data-evaluation-activity><?= learner_escape($currentEvaluation['activity_title'] ?? 'Đồ án'); ?></span></span>
                                    </div>
                                </div>
                                <div class="eval-feedback-verified-pill">
                                    <?= learner_icon('check', 14); ?>
                                    <span>Đánh giá chính thức</span>
                                </div>
                            </div>

                            <blockquote class="eval-feedback-quote" data-evaluation-comment>
                                <?= learner_escape($currentEvaluation['comment'] ?? 'Chưa có nhận xét từ giảng viên.'); ?>
                            </blockquote>

                            <div class="eval-feedback-tags">
                                <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">Ghi nhận:</span>
                                <span class="eval-tag">✦ Tư duy giải quyết vấn đề</span>
                                <span class="eval-tag">✦ Tinh thần trách nhiệm</span>
                                <span class="eval-tag">✦ Kỹ năng thực hành</span>
                            </div>
                        </section>

                        <!-- 2. Criteria Progress Bars -->
                        <section class="eval-criteria-card" aria-labelledby="eval-criteria-title">
                            <h2 id="eval-criteria-title">
                                <?= learner_icon('chart', 20); ?>
                                <span>Bảng Tiêu chí Đánh giá Chi tiết</span>
                            </h2>

                            <div data-evaluation-criteria>
                                <?php foreach (($currentEvaluation['criteria'] ?? []) as $criterion): ?>
                                    <?php
                                    $maximum = (float) $criterion['max'];
                                    $scoreVal = (float) $criterion['score'];
                                    $percentage = $maximum > 0
                                        ? max(0.0, min(100.0, $scoreVal / $maximum * 100))
                                        : 0.0;
                                    $tone = $criterion['tone'] ?? 'primary';
                                    ?>
                                    <div class="eval-criterion-row" data-evaluation-criterion="">
                                        <div class="eval-criterion-header">
                                            <span><?= learner_escape($criterion['name']); ?></span>
                                            <span class="eval-criterion-score"><?= number_format($scoreVal, 1); ?> / <?= number_format($maximum, 0); ?> (<?= round($percentage); ?>%)</span>
                                        </div>
                                        <div class="eval-progress-track" role="progressbar" aria-label="<?= learner_escape($criterion['name']); ?>" aria-valuemin="0" aria-valuemax="<?= learner_escape($maximum); ?>" aria-valuenow="<?= learner_escape($scoreVal); ?>">
                                            <div class="eval-progress-fill eval-progress-fill--<?= learner_escape($tone); ?>" style="width: <?= learner_escape($percentage); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Right Column: Summary Panel -->
                    <aside class="eval-summary-panel" data-evaluation-summary <?= $hasEvaluation ? '' : 'hidden'; ?>>
                        <div class="eval-summary-title">Tổng điểm Năng lực</div>
                        <div class="eval-total-number" data-evaluation-total><?= learner_escape($currentEvaluation['total'] ?? '0.0'); ?></div>
                        <div class="eval-total-scale">Thang điểm 10.0</div>

                        <div class="eval-class-pill">
                            <?= learner_icon('star', 18); ?>
                            <span data-evaluation-classification><?= learner_escape($currentEvaluation['classification'] ?? 'Đang cập nhật'); ?></span>
                        </div>

                        <div class="eval-rank-box">
                            <?= learner_icon('trophy', 16); ?>
                            <span data-evaluation-ranking><?= learner_escape($currentEvaluation['ranking'] ?? 'Top 15% lớp'); ?></span>
                        </div>

                        <div class="eval-passport-status">
                            <?= learner_icon('check', 14); ?>
                            <span>Đã xác thực vào Talent Passport</span>
                        </div>
                    </aside>

                    <!-- Empty State -->
                    <section class="learner-card learner-empty-state learner-evaluation-empty" data-evaluation-empty role="status" aria-live="polite" <?= $evaluationSourceState === 'empty' ? '' : 'hidden'; ?> style="grid-column: 1 / -1; padding: 4rem 2rem; text-align: center;">
                        <span class="learner-empty-state__icon" style="color: var(--primary);"><?= learner_icon('clipboard', 40); ?></span>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 1rem 0 0.5rem;">Chưa có đánh giá nào được công bố</h2>
                        <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 1.5rem; font-size: 0.95rem; line-height: 1.6;">
                            Kết quả đánh giá và lời nhận xét sẽ xuất hiện tại đây ngay sau khi Giảng viên hoặc Huấn luyện viên hoàn tất đánh giá cho bạn.
                        </p>
                        <a href="activities.php" class="learner-btn learner-btn--primary" style="background: var(--primary); color: #fff; padding: 0.65rem 1.5rem; font-weight: 700; border-radius: var(--radius-sm); text-decoration: none;">
                            Khám phá hoạt động & Đồ án
                        </a>
                    </section>

                    <!-- Error State -->
                    <section class="learner-card learner-empty-state learner-evaluation-error" data-evaluation-error role="alert" <?= $evaluationSourceState === 'source-error' ? '' : 'hidden'; ?> style="grid-column: 1 / -1;">
                        <span class="learner-empty-state__icon"><?= learner_icon('alert-triangle', 30); ?></span>
                        <h2>Không thể tải dữ liệu đánh giá</h2>
                        <p>Hệ thống gặp sự cố khi truy xuất dữ liệu. Vui lòng thử lại sau.</p>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script type="application/json" id="learner-evaluation-data"><?=
        json_encode(
            $evaluationTerms,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
