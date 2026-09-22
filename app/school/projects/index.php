<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';
require dirname(__DIR__, 3) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$service = $context['projects'];
$userId = (string) $context['user']['id'];

$flash = isset($_SESSION['school_project_flash']) && is_string($_SESSION['school_project_flash'])
    ? $_SESSION['school_project_flash']
    : null;
unset($_SESSION['school_project_flash']);

$projects = $service->listProjects($userId)['items'];

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/projects/';
$pageTitle = 'Quản lý Dự án';
$pageDescription = 'Theo dõi dự án khởi nghiệp/nghiên cứu, tiến độ tài trợ và thành viên tham gia.';
$pageActions = '<a class="btn btn-primary" href="' . htmlspecialchars(app_href('/app/school/projects/create.php'), ENT_QUOTES, 'UTF-8') . '">+ Tạo dự án</a>';

$statusLabels = [
    'draft' => 'Bản nháp',
    'in_progress' => 'Đang thực hiện',
    'completed' => 'Đã hoàn thành',
    'archived' => 'Lưu trữ',
];

ob_start();
include dirname(__DIR__) . '/includes/page-banner.php';
?>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <?= htmlspecialchars($flash); ?>
    </div>
<?php endif; ?>

<section class="school-section-box list-section">
    <div class="school-section-box__header school-section-box__header--bordered">
        <h2 class="school-section-box__title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Danh sách Dự án
        </h2>
        <span class="school-badge school-badge--info"><?= count($projects) ?> dự án</span>
    </div>

    <?php if ($projects === []): ?>
        <div class="school-empty-state">
            <div class="school-empty-state__icon">📁</div>
            <p>Nhà trường chưa khởi tạo dự án nào.</p>
            <small>Tạo dự án mới để theo dõi tiến độ và kêu gọi tài trợ.</small>
            <div style="margin-top:1rem;">
                <a class="btn btn-primary" href="<?= htmlspecialchars(app_href('/app/school/projects/create.php'), ENT_QUOTES, 'UTF-8'); ?>">Tạo dự án đầu tiên</a>
            </div>
        </div>
    <?php else: ?>
        <div class="school-project-cards">
            <?php foreach ($projects as $project): ?>
                <?php
                $detailUrl = app_href('/app/school/projects/detail.php?id=' . urlencode((string) $project['id']));
                $topicLabel = trim((string) ($project['topic'] ?? ''));
                $categoryLabel = trim((string) ($project['category'] ?? ''));
                $subtitle = $topicLabel !== '' ? $topicLabel : ($categoryLabel !== '' ? $categoryLabel : 'Chưa phân loại');
                ?>
                <a class="school-project-card" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration:none;color:inherit;display:block;">
                    <div class="school-project-card__header">
                        <div class="school-project-title-area">
                            <h3><?= htmlspecialchars((string) $project['title']); ?></h3>
                            <span class="school-project-topic"><?= htmlspecialchars($subtitle); ?></span>
                        </div>
                        <div class="school-project-status-badge school-project-status-badge--<?= htmlspecialchars((string) $project['status']); ?>">
                            <?= htmlspecialchars($statusLabels[$project['status']] ?? (string) $project['status']); ?>
                        </div>
                    </div>

                    <div class="school-project-card__stats">
                        <div class="school-project-card__stat">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span><?= (int) ($project['membersCount'] ?? 0); ?> thành viên</span>
                            <?php if (!empty($project['pendingMembersCount']) && (int) $project['pendingMembersCount'] > 0): ?>
                                <span class="school-badge" style="background:#FEF3C7;color:#B45309;border:1px solid #FCD34D;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:12px;flex-shrink:0;">
                                    <?= (int) $project['pendingMembersCount']; ?> chờ duyệt
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($project['fundingGoal']) && (float) $project['fundingGoal'] > 0): ?>
                            <div class="school-project-card__stat school-project-card__stat--funding">
                                <span style="font-weight:700;font-size:0.7rem;padding:0.15rem 0.4rem;background:rgba(16,185,129,0.12);color:#059669;border-radius:4px;flex-shrink:0;">VNĐ</span>
                                <span style="min-width:0;">
                                    <strong><?= number_format((float) ($project['raisedAmount'] ?? 0), 0, ',', '.'); ?></strong>
                                    /
                                    <?= number_format((float) ($project['fundingGoal'] ?? 0), 0, ',', '.'); ?>
                                    <small>(<?= (int) ($project['sponsorsCount'] ?? 0); ?> nhà tài trợ)</small>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="school-project-card__actions">
                        <span class="btn btn-outline btn-sm" style="pointer-events:none;">Xem chi tiết</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php
$pageBody = ob_get_clean();
$extraStyles = '';
$extraScripts = '';
require dirname(__DIR__) . '/includes/layout.php';
