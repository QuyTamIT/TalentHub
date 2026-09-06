<?php
/** TalentHub Learner - Authorized school project detail */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/project-data.php';

$project = null;
$projectLoadFailed = false;
try {
    $project = learner_project((string) ($_GET['id'] ?? ''));
} catch (InvalidArgumentException) {
    $project = null;
} catch (Throwable) {
    $project = null;
    $projectLoadFailed = true;
}

$learnerProjectMembership = null;
if ($project !== null) {
    try {
        $learnerProjectMembership = learner_project_repository()->findActiveMembershipForStudent(
            learner_current_student_id(),
            (string) $project['id']
        );
    } catch (Throwable) {
        $learnerProjectMembership = null;
    }
}
$learnerProjectJoined = is_array($learnerProjectMembership)
    && (string) ($learnerProjectMembership['status'] ?? '') === 'active';
$learnerProjectRegistered = ($_GET['registered'] ?? '') === '1';
$learnerProjectRegisterFailed = ($_GET['register'] ?? '') === 'failed';
$learnerProjectCsrfToken = (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? '');

$pageTitle = $project ? 'Chi tiết dự án' : 'Không tìm thấy dự án';
$currentRoute = '/app/learner/ecosystem.php';
$headerSearchLabel = 'Tìm trong dự án';
$headerSearchPlaceholder = 'Tìm nội dung dự án...';

if (!function_exists('learner_project_money')) {
    function learner_project_money(mixed $amount, string $currency = 'VND'): string
    {
        $normalizedCurrency = strtoupper(trim($currency)) ?: 'VND';
        return number_format((float) $amount, 0, ',', '.') . ' ' . $normalizedCurrency;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Thông tin đầy đủ của dự án trường trên TalentHub.">
    <title><?= learner_escape($project['title'] ?? $pageTitle); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/learner.css'); ?>">
</head>
<body class="learner-app learner-page-project-detail">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn">
                    <a href="ecosystem.php">Hệ sinh thái &amp; Dự án</a>
                    <span aria-hidden="true">/</span>
                    <a href="ecosystem.php?tab=opportunities">Dự án</a>
                    <span aria-hidden="true">/</span>
                    <span><?= learner_escape($project['title'] ?? 'Không tìm thấy'); ?></span>
                </nav>

                <?php if ($projectLoadFailed): ?>
                    <section class="learner-card learner-not-found" aria-labelledby="project-error-title">
                        <span><?= learner_icon('info', 34); ?></span>
                        <h1 id="project-error-title">Không thể tải chi tiết dự án</h1>
                        <p>Dữ liệu dự án đang tạm thời gián đoạn. Vui lòng thử lại sau.</p>
                        <button class="learner-btn learner-btn--outline" type="button" onclick="location.reload()">Thử lại</button>
                    </section>
                <?php elseif (!$project): ?>
                    <section class="learner-card learner-not-found" aria-labelledby="project-not-found-title">
                        <span><?= learner_icon('briefcase', 34); ?></span>
                        <h1 id="project-not-found-title">Không tìm thấy dự án</h1>
                        <p>Dự án không tồn tại, không còn triển khai hoặc không thuộc phạm vi trường của bạn.</p>
                        <a class="learner-btn learner-btn--primary" href="ecosystem.php?tab=opportunities">Quay lại danh sách dự án</a>
                    </section>
                <?php else: ?>
                    <?php if ($learnerProjectRegistered && $learnerProjectJoined): ?>
                        <div class="learner-project-detail__notice learner-project-detail__notice--success" role="status">
                            Đăng ký dự án thành công. Bạn đã trở thành thành viên đang hoạt động của dự án này.
                        </div>
                    <?php elseif ($learnerProjectRegisterFailed): ?>
                        <div class="learner-project-detail__notice learner-project-detail__notice--error" role="alert">
                            Không thể đăng ký dự án lúc này. Vui lòng thử lại.
                        </div>
                    <?php endif; ?>
                    <section class="learner-project-detail__hero learner-card" aria-labelledby="project-title">
                        <div class="learner-project-detail__hero-main">
                            <div class="learner-project-detail__badges">
                                <span class="learner-badge learner-badge--secondary">Dự án</span>
                                <span class="learner-status-dot learner-status-dot--active"><?= learner_escape($project['status_label']); ?></span>
                            </div>
                            <p class="learner-card-kicker"><?= learner_escape($project['category_label']); ?></p>
                            <h1 id="project-title"><?= learner_escape($project['title']); ?></h1>
                            <p class="learner-project-detail__school"><?= learner_icon('building', 18); ?> <?= learner_escape($project['school_name']); ?></p>
                        </div>
                        <div class="learner-project-detail__actions">
                            <?php if ($learnerProjectJoined): ?>
                                <button class="learner-btn learner-btn--primary" type="button" disabled>Đã tham gia dự án</button>
                            <?php else: ?>
                                <form class="learner-project-detail__register" method="post" action="actions/register-project.php">
                                    <input type="hidden" name="projectId" value="<?= learner_escape($project['id']); ?>">
                                    <input type="hidden" name="csrfToken" value="<?= learner_escape($learnerProjectCsrfToken); ?>">
                                    <button class="learner-btn learner-btn--primary" type="submit"><?= learner_icon('users', 18); ?> Đăng ký dự án</button>
                                </form>
                            <?php endif; ?>
                            <a class="learner-btn learner-btn--outline" href="ecosystem.php?tab=opportunities"><?= learner_icon('arrow-left', 17); ?> Danh sách dự án</a>
                        </div>

                        <div class="learner-project-detail__facts">
                            <div><?= learner_icon('briefcase', 19); ?><span>Lĩnh vực<strong><?= learner_escape($project['category_label']); ?></strong></span></div>
                            <div><?= learner_icon('user', 19); ?><span>Người hướng dẫn<strong><?= learner_escape(trim((string) ($project['mentor_name'] ?? '')) !== '' ? $project['mentor_name'] : 'Chưa cập nhật'); ?></strong></span></div>
                            <div><?= learner_icon('users', 19); ?><span>Nhóm dự án<strong><?= learner_escape($project['members_count']); ?> thành viên</strong></span></div>
                            <div><?= learner_icon('calendar', 19); ?><span>Thời gian<strong><?= learner_escape(($project['start_at_label'] ?: 'Chưa cập nhật') . ' – ' . ($project['end_at_label'] ?: 'Chưa cập nhật')); ?></strong></span></div>
                        </div>
                    </section>

                    <div class="learner-project-detail__body">
                        <div class="learner-project-detail__main">
                            <section class="learner-card learner-content-section" aria-labelledby="project-description-title">
                                <h2 id="project-description-title">Mô tả dự án</h2>
                                <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                                    <p class="learner-project-detail__description"><?= nl2br(learner_escape($project['description'])); ?></p>
                                <?php else: ?>
                                    <p class="learner-project-detail__muted">Thông tin mô tả đang được nhà trường cập nhật.</p>
                                <?php endif; ?>
                            </section>

                            <?php if ($project['sponsorships'] !== []): ?>
                                <section class="learner-card learner-project-detail__sponsors" aria-labelledby="project-sponsors-title">
                                    <div class="learner-project-detail__section-heading">
                                        <span><?= learner_icon('building', 20); ?></span>
                                        <div>
                                            <h2 id="project-sponsors-title">Doanh nghiệp đồng hành &amp; Tài trợ</h2>
                                            <p>Các khoản tài trợ đã được ghi nhận cho dự án.</p>
                                        </div>
                                    </div>
                                    <div class="learner-project-detail__sponsor-list">
                                        <?php foreach ($project['sponsorships'] as $sponsorship): ?>
                                            <article>
                                                <div>
                                                    <strong><?= learner_escape($sponsorship['enterprise_name']); ?></strong>
                                                    <span>Doanh nghiệp đồng hành</span>
                                                </div>
                                                <strong><?= learner_escape(learner_project_money($sponsorship['amount'], $sponsorship['currency'])); ?></strong>
                                                <?php if (trim((string) ($sponsorship['note'] ?? '')) !== ''): ?>
                                                    <p><?= learner_escape($sponsorship['note']); ?></p>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </div>

                        <aside class="learner-card learner-project-detail__funding" aria-labelledby="project-funding-title">
                            <span class="learner-project-detail__funding-icon"><?= learner_icon('sparkles', 23); ?></span>
                            <h2 id="project-funding-title">Mục tiêu tài trợ</h2>
                            <?php if ((float) ($project['funding_goal'] ?? 0) > 0): ?>
                                <strong><?= learner_escape(learner_project_money($project['funding_goal'])); ?></strong>
                                <div class="learner-project-detail__funding-track" aria-hidden="true">
                                    <?php $fundingProgress = min(100, ((float) $project['raised_amount'] / (float) $project['funding_goal']) * 100); ?>
                                    <span style="width: <?= learner_escape(number_format($fundingProgress, 2, '.', '')); ?>%"></span>
                                </div>
                                <p>Đã ghi nhận <b><?= learner_escape(learner_project_money($project['raised_amount'])); ?></b> từ các doanh nghiệp đồng hành.</p>
                            <?php else: ?>
                                <strong><?= learner_escape(learner_project_money($project['raised_amount'])); ?></strong>
                                <p>Tổng tài trợ đã ghi nhận. Nhà trường chưa công bố mục tiêu tài trợ.</p>
                            <?php endif; ?>
                            <dl>
                                <div><dt>Ngày bắt đầu</dt><dd><?= learner_escape($project['start_at_label'] ?: 'Chưa cập nhật'); ?></dd></div>
                                <div><dt>Ngày kết thúc</dt><dd><?= learner_escape($project['end_at_label'] ?: 'Chưa cập nhật'); ?></dd></div>
                                <div><dt>Trạng thái</dt><dd><?= learner_escape($project['status_label']); ?></dd></div>
                            </dl>
                        </aside>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/learner.js'); ?>"></script>
</body>
</html>
