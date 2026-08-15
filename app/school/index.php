<?php
/**
 * TalentHub - School Dashboard Main Entry Point
 * Dashboard cho Nhà trường (data from DB via SchoolDashboardService).
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

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
    'level'         => $school['level'] ?? 'Trung học',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];

$currentRoute = '/app/school/';
$pageTitle    = 'Tổng quan';

$kpis            = $dashboard['kpis'];
$topTalents      = $dashboard['topTalents'];
$classes         = $dashboard['classes'];
$recentActivities= $dashboard['recentActivity'];

ob_start();
?>
<section class="school-welcome">
    <div class="school-welcome__body">
        <div class="school-welcome__content">
            <span class="school-welcome__tag">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                Khu vực Nhà trường
            </span>
            <h2 class="school-welcome__title">Xin chào, Ban Giám hiệu <?= htmlspecialchars($school['name']); ?>!</h2>
            <p class="school-welcome__description">
                Theo dõi tổng quan hoạt động năng khiếu của trường, quản lý hồ sơ học sinh và xem báo cáo chi tiết về tiềm năng phát triển tài năng trong năm học <?= htmlspecialchars($school['academicYear']); ?>.
            </p>
            <div class="school-welcome__actions">
                <a href="/app/school/analytics.php" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    Xem phân tích
                </a>
                <a href="/app/school/reports.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    Tạo báo cáo
                </a>
                <a href="/app/school/settings.php" class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Cài đặt trường
                </a>
            </div>
        </div>
    </div>
</section>

<section class="school-kpis-grid">
    <?php foreach ($kpis as $kpi): ?>
        <article class="school-kpi-card">
            <div class="school-kpi-card__header">
                <span class="school-kpi-card__label"><?= htmlspecialchars($kpi['label']); ?></span>
            </div>
            <div class="school-kpi-card__value"><?= htmlspecialchars($kpi['value']); ?></div>
            <div class="school-kpi-card__footer">
                <span class="school-kpi-card__change school-kpi-card__change--<?= htmlspecialchars($kpi['changeType']); ?>">
                    <?= htmlspecialchars($kpi['change']); ?>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<div class="school-grid-layout">
    <div class="school-grid-layout__main">
        <section class="school-section-box">
            <div class="school-section-box__header">
                <div>
                    <h3 class="school-section-box__title">Lớp học</h3>
                    <p class="school-section-box__subtitle"><?= count($classes); ?> lớp trong trường</p>
                </div>
                <a href="/app/school/classes.php" class="school-section-box__link">Quản lý lớp</a>
            </div>
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
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($class['name']); ?></strong></td>
                            <td><?= htmlspecialchars($class['grade']); ?></td>
                            <td><?= htmlspecialchars((string) $class['students']); ?> HS</td>
                            <td><?= htmlspecialchars($class['academicYear']); ?></td>
                            <td>
                                <span class="school-class-badge school-class-badge--<?= htmlspecialchars($class['status']); ?>">
                                    <?= htmlspecialchars($class['statusText']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="school-activity-item">
                        <span class="school-activity-item__indicator"></span>
                        <span class="school-activity-item__text"><?= htmlspecialchars($activity['text']); ?></span>
                        <span class="school-activity-item__time"><?= htmlspecialchars($activity['time']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php
$pageBody = ob_get_clean();

require __DIR__ . '/includes/layout.php';