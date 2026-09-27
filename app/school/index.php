<?php
/**
 * TalentHub - School Dashboard Main Entry Point
 * Dashboard cho Nhà trường (data from DB via SchoolDashboardService).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$school     = $context['school'];
$dashboard  = $context['dashboard'];

function schoolInitials(string $name): string {
    $words = explode(' ', $name);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }
    return $initials ?: 'NA';
}

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => mb_substr($school['name'], 0, 2),
    'level'         => $school['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];

$currentRoute = '/app/school/';
$pageTitle    = 'Tổng quan';

$kpis            = $dashboard['kpis'];
$topTalents      = $dashboard['topTalents'];
$classes         = $dashboard['classes'];
$recentActivities= $dashboard['recentActivity'];
$overview        = $dashboard['overview'] ?? \TalentHub\Modules\School\Service\SchoolDashboardService::emptyOverview();

$statIcons = [
    'students'      => ['tone' => 'purple', 'svg' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
    'activities'    => ['tone' => 'blue', 'svg' => '<line x1="6" y1="20" x2="6" y2="12"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="18" y1="20" x2="18" y2="9"></line>'],
    'participation' => ['tone' => 'orange', 'svg' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>'],
    'completion'    => ['tone' => 'pink', 'svg' => '<path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v5a5 5 0 0 1-10 0z"></path><path d="M17 5h3v2a3 3 0 0 1-3 3"></path><path d="M7 5H4v2a3 3 0 0 0 3 3"></path>'],
];
$talentColors = [
    'technical' => '#F97316',
    'academic'  => '#FBBF24',
    'business'  => '#EC4899',
    'arts'      => '#7C3AED',
    'sports'    => '#14B8A6',
];
$groupIcons = [
    ['tone' => 'orange', 'svg' => '<polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline><line x1="14" y1="4" x2="10" y2="20"></line>'],
    ['tone' => 'purple', 'svg' => '<rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"></path><line x1="2" y1="13" x2="22" y2="13"></line>'],
    ['tone' => 'pink', 'svg' => '<rect x="4" y="8" width="16" height="12" rx="2"></rect><path d="M12 8V4"></path><circle cx="12" cy="3" r="1"></circle><circle cx="9" cy="13" r="1.2"></circle><circle cx="15" cy="13" r="1.2"></circle><path d="M9 17h6"></path><path d="M2 13v3M22 13v3"></path>'],
];

$talentTotal = array_sum(array_column($overview['talents'], 'count'));
$donutRadius = 70;
$donutCircumference = 2 * M_PI * $donutRadius;

$monthlyMax = 0;
foreach ($overview['monthly'] as $m) {
    $monthlyMax = max($monthlyMax, $m['registered'], $m['completed']);
}
$tickStep = 1;
if ($monthlyMax > 0) {
    $raw = $monthlyMax / 4;
    $magnitude = 10 ** floor(log10($raw));
    foreach ([1, 2, 2.5, 5, 10] as $factor) {
        if ($factor * $magnitude >= $raw) {
            $tickStep = $factor * $magnitude;
            break;
        }
    }
}
$chartMax = max(4, $tickStep * 4);
$ticks = [];
for ($i = 4; $i >= 0; $i--) {
    $ticks[] = $i * $chartMax / 4;
}

ob_start();
?>
<section class="sd-hero">
    <div class="sd-hero__decor" aria-hidden="true"></div>
    <h2 class="sd-hero__title">
        <span class="sd-hero__line">Khu vực học sinh</span>
        <span class="sd-hero__line sd-hero__line--accent">Thống kê</span>
    </h2>
    <p class="sd-hero__subtitle">Tổng quan năng lực toàn trường – dành cho ban giám hiệu.</p>
    <?php if (empty($school['id'])): ?>
        <div class="sd-hero__notice">Chưa có trường học nào được thiết lập trong cơ sở dữ liệu. Bắt đầu bằng cách tạo mới trường học hoặc liên hệ Quản trị viên hệ thống.</div>
    <?php endif; ?>
</section>

<section class="sd-stats">
    <?php foreach ($overview['stats'] as $stat): ?>
        <?php
        $icon  = $statIcons[$stat['key']] ?? $statIcons['students'];
        $delta = (float) $stat['delta'];
        $trend = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        ?>
        <article class="sd-stat">
            <span class="sd-stat__icon sd-tone--<?= $icon['tone']; ?>">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon['svg']; ?></svg>
            </span>
            <div class="sd-stat__body">
                <span class="sd-stat__label"><?= htmlspecialchars($stat['label']); ?></span>
                <strong class="sd-stat__value"><?= htmlspecialchars($stat['value']); ?></strong>
                <span class="sd-stat__delta sd-stat__delta--<?= $trend; ?>">
                    <?= ($delta > 0 ? '+' : '') . htmlspecialchars(rtrim(rtrim(number_format($delta, 1, '.', ''), '0'), '.')) . $stat['unit']; ?> so với tháng trước
                    <?php if ($trend !== 'flat'): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="<?= $trend === 'up' ? 'M12 4l7 8h-4v8h-6v-8H5z' : 'M12 20l-7-8h4V4h6v8h4z'; ?>"></path></svg>
                    <?php endif; ?>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="sd-charts">
    <article class="sd-card">
        <header class="sd-card__header">
            <h3 class="sd-card__title">Phân bổ năng khiếu</h3>
            <p class="sd-card__subtitle">Theo định hướng toàn trường</p>
        </header>
        <div class="sd-donut">
            <svg class="sd-donut__chart" viewBox="0 0 200 200" role="img" aria-label="Biểu đồ phân bổ năng khiếu">
                <circle cx="100" cy="100" r="<?= $donutRadius; ?>" fill="none" stroke="#F1F5F9" stroke-width="36"></circle>
                <?php if ($talentTotal > 0): ?>
                    <?php $offset = 0.0; ?>
                    <?php foreach ($overview['talents'] as $talent): ?>
                        <?php if ($talent['count'] <= 0) { continue; } ?>
                        <?php $length = $talent['count'] / $talentTotal * $donutCircumference; ?>
                        <circle cx="100" cy="100" r="<?= $donutRadius; ?>" fill="none"
                                stroke="<?= $talentColors[$talent['key']] ?? '#94A3B8'; ?>" stroke-width="36"
                                stroke-dasharray="<?= round($length, 3); ?> <?= round($donutCircumference - $length, 3); ?>"
                                stroke-dashoffset="<?= round(-$offset, 3); ?>"
                                transform="rotate(-90 100 100)">
                            <title><?= htmlspecialchars($talent['label']); ?>: <?= $talent['percentage']; ?>%</title>
                        </circle>
                        <?php $offset += $length; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </svg>
            <ul class="sd-donut__legend">
                <?php foreach ($overview['talents'] as $talent): ?>
                    <li class="sd-donut__item">
                        <span class="sd-donut__dot" style="background: <?= $talentColors[$talent['key']] ?? '#94A3B8'; ?>"></span>
                        <span class="sd-donut__label"><?= htmlspecialchars($talent['label']); ?></span>
                        <strong class="sd-donut__value"><?= $talent['percentage']; ?>%</strong>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($overview['talents'])): ?>
                    <li class="sd-empty">Chưa có dữ liệu năng khiếu.</li>
                <?php endif; ?>
            </ul>
        </div>
    </article>

    <article class="sd-card">
        <header class="sd-card__header">
            <h3 class="sd-card__title">Tham gia &amp; hoàn thành</h3>
            <p class="sd-card__subtitle"><?= count($overview['monthly']); ?> tháng gần nhất</p>
        </header>
        <div class="sd-bars">
            <div class="sd-bars__plot">
                <div class="sd-bars__grid" aria-hidden="true">
                    <?php foreach ($ticks as $tick): ?>
                        <div class="sd-bars__tick"><span><?= number_format($tick); ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="sd-bars__groups">
                    <?php foreach ($overview['monthly'] as $month): ?>
                        <div class="sd-bars__group">
                            <div class="sd-bars__pair">
                                <span class="sd-bars__bar sd-bars__bar--registered" style="height: <?= round($month['registered'] * 100 / $chartMax, 2); ?>%" title="Đăng ký: <?= (int) $month['registered']; ?>"></span>
                                <span class="sd-bars__bar sd-bars__bar--completed" style="height: <?= round($month['completed'] * 100 / $chartMax, 2); ?>%" title="Hoàn thành: <?= (int) $month['completed']; ?>"></span>
                            </div>
                            <span class="sd-bars__label"><?= htmlspecialchars($month['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sd-bars__legend">
                <span><i class="sd-bars__swatch sd-bars__swatch--registered"></i>Đăng ký</span>
                <span><i class="sd-bars__swatch sd-bars__swatch--completed"></i>Hoàn thành</span>
            </div>
        </div>
    </article>
</section>

<section class="sd-card sd-top">
    <header class="sd-card__header">
        <h3 class="sd-card__title">Top khoa / khối nổi bật</h3>
    </header>
    <div class="sd-top__list">
        <?php foreach ($overview['topGroups'] as $index => $group): ?>
            <?php $icon = $groupIcons[$index % count($groupIcons)]; ?>
            <article class="sd-top__item">
                <span class="sd-top__icon sd-tone--<?= $icon['tone']; ?>">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon['svg']; ?></svg>
                </span>
                <div class="sd-top__body">
                    <span class="sd-top__progress sd-top__progress--<?= $icon['tone']; ?>"><i style="width: <?= (int) $group['progress']; ?>%"></i></span>
                    <strong class="sd-top__name"><?= htmlspecialchars($group['name']); ?><?php if (!empty($group['grade'])): ?> <small><?= htmlspecialchars($group['grade']); ?></small><?php endif; ?></strong>
                    <span class="sd-top__meta"><?= htmlspecialchars($group['subtitle']); ?></span>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (empty($overview['topGroups'])): ?>
            <p class="sd-empty">Chưa có lớp / khối nào hoàn thành hoạt động.</p>
        <?php endif; ?>
    </div>
</section>

<div class="school-grid-layout">
    <div class="school-grid-layout__main">
        <section class="school-section-box">
            <div class="school-section-box__header">
                <div>
                    <h3 class="school-section-box__title">Lớp học</h3>
                    <p class="school-section-box__subtitle"><?= count($classes); ?> lớp trong trường</p>
                </div>
                <a href="./classes.php" class="school-section-box__link">Quản lý lớp</a>
            </div>
            <div class="table-scroll school-class-table-wrap">
            <table class="school-class-table">
                <thead>
                    <tr>
                        <th>Lớp</th>
                        <th>Khối</th>
                        <th>Sĩ số</th>
                        <th>Niên khóa</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td data-label="Lớp"><strong><?= htmlspecialchars($class['name']); ?></strong></td>
                                <td data-label="Khối"><?= htmlspecialchars($class['grade']); ?></td>
                                <td data-label="Sĩ số"><?= htmlspecialchars((string) $class['students']); ?> HS</td>
                                <td data-label="Niên khóa"><?= htmlspecialchars($class['academicYear']); ?></td>
                                <td data-label="Trạng thái">
                                    <span class="school-class-badge school-class-badge--<?= htmlspecialchars($class['status']); ?>">
                                        <?= htmlspecialchars($class['statusText']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 24px; color: var(--school-text-secondary, #64748b);">
                                Chưa có lớp học nào trong hệ thống.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </section>
    </div>

    <div class="school-grid-layout__sidebar">
        <section class="school-section-box">
            <div class="school-section-box__header">
                <div>
                    <h3 class="school-section-box__title">Hoạt động gần đây</h3>
                    <p class="school-section-box__subtitle">Cập nhật mới nhất từ trường</p>
                </div>
            </div>
            <div class="school-activity-timeline">
                <?php if (!empty($recentActivities)): ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="school-activity-item">
                            <span class="school-activity-item__indicator"></span>
                            <span class="school-activity-item__text"><?= htmlspecialchars(is_array($activity) ? ($activity['text'] ?? $activity['title'] ?? '') : (string)$activity); ?></span>
                            <span class="school-activity-item__time"><?= htmlspecialchars(is_array($activity) ? ($activity['time'] ?? $activity['created_at'] ?? '') : ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 24px; text-align: center; color: var(--school-text-secondary, #64748b);">
                        Chưa có hoạt động nào được ghi nhận gần đây.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '<link rel="stylesheet" href="' . app_href('/assets/css/school-dashboard.css') . '?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/school-dashboard.css') . '">';

require __DIR__ . '/includes/layout.php';
