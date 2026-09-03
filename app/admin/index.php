<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Rbac\RoleCodes;

$adminUser = PortalGuard::requireRole(RoleCodes::PLATFORM_ADMIN, '/app/admin/index.php');
$adminName = trim((string) ($adminUser['fullName'] ?? 'Quản trị viên'));
$adminFirstName = preg_split('/\s+/u', $adminName)[0] ?? 'Quản trị viên';
$adminInitials = implode('', array_map(
    static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
    array_slice(preg_split('/\s+/u', $adminName) ?: ['A'], 0, 2)
));

$health = [
    ['label' => 'API success', 'value' => '99.94%', 'detail' => '24 giờ qua', 'tone' => 'positive', 'icon' => 'pulse'],
    ['label' => 'P95 latency', 'value' => '184 ms', 'detail' => '−12 ms so với hôm qua', 'tone' => 'positive', 'icon' => 'clock'],
    ['label' => 'Lỗi 5xx', 'value' => '7', 'detail' => '2 lỗi chưa xử lý', 'tone' => 'warning', 'icon' => 'alert'],
    ['label' => 'Database', 'value' => 'Ổn định', 'detail' => '15 migrations · no drift', 'tone' => 'positive', 'icon' => 'database'],
];

$metrics = [
    ['key'=>'activeUsers','label' => 'Người dùng hoạt động', 'value' => '—', 'change' => 'Live', 'detail' => 'Tài khoản active', 'tone' => 'blue'],
    ['key'=>'organizations','label' => 'Tổ chức', 'value' => '—', 'change' => 'Live', 'detail' => 'School & Enterprise', 'tone' => 'amber'],
    ['key'=>'applications','label' => 'Hồ sơ ứng tuyển', 'value' => '—', 'change' => 'Live', 'detail' => 'Toàn bá»™ pipeline', 'tone' => 'violet'],
    ['key'=>'pendingPayments','label' => 'Thanh toán đang xử lý', 'value' => '—', 'change' => 'Live', 'detail' => 'Payment order pending', 'tone' => 'emerald'],
];
$metrics[2]['detail'] = 'Toàn bộ quy trình';

$queue = [
    ['severity' => 'critical', 'title' => 'Payment order chờ quá 30 phút', 'meta' => '4 giao dịch · ₫86.000.000', 'owner' => 'Finance Ops', 'sla' => 'Còn 18 phút'],
    ['severity' => 'high', 'title' => 'Doanh nghiệp chờ xác minh quá SLA', 'meta' => '8 hồ sơ · hồ sơ cũ nhất 31 giờ', 'owner' => 'Verification', 'sla' => 'Quá hạn'],
    ['severity' => 'medium', 'title' => 'Ứng tuyển chưa được review', 'meta' => '23 hồ sơ · 5 doanh nghiệp', 'owner' => 'Partner Ops', 'sla' => 'Còn 4 giờ'],
    ['severity' => 'medium', 'title' => 'Check-in cần đối soát', 'meta' => '12 bản ghi · 3 hoạt động', 'owner' => 'Academic Ops', 'sla' => 'Còn 7 giờ'],
];

$organizations = [
    ['name' => 'FPT Software', 'type' => 'Enterprise', 'status' => 'Chờ xác minh', 'members' => '14', 'activity' => '18 phút trước', 'risk' => 'Cần xem xét'],
    ['name' => 'THPT Nguyễn Huệ', 'type' => 'School', 'status' => 'Đang hoạt động', 'members' => '1.248', 'activity' => '4 phút trước', 'risk' => 'Bình thường'],
    ['name' => 'GreenTech Labs', 'type' => 'Enterprise', 'status' => 'Đang hoạt động', 'members' => '8', 'activity' => '1 giờ trước', 'risk' => 'Bình thường'],
    ['name' => 'Đại học Công nghệ', 'type' => 'School', 'status' => 'Thiếu thông tin', 'members' => '2.806', 'activity' => '2 giờ trước', 'risk' => 'Cần xem xét'],
];

$audit = [
    ['time' => '10:42', 'title' => 'Enterprise được xác minh', 'detail' => 'admin.ops · GreenTech Labs', 'tone' => 'positive'],
    ['time' => '10:31', 'title' => 'Tài khoản bị tạm khóa', 'detail' => 'security.policy · login anomaly', 'tone' => 'danger'],
    ['time' => '10:18', 'title' => 'Migration smoke hoàn tất', 'detail' => 'system · 15 migrations', 'tone' => 'info'],
    ['time' => '09:56', 'title' => 'Quyền được cập nhật', 'detail' => 'admin.security · payment.read', 'tone' => 'warning'],
];

// Các khu vực này được render từ API Admin; không hiển thị fixture dễ gây nhầm lẫn với dữ liệu thật.
$queue = [];
$organizations = [];
$audit = [];

function icon(string $name, string $class = ''): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'tasks' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'building' => '<path d="M3 21h18M6 21V5l6-3 6 3v16M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M12 12v.01M3 12a18 18 0 0 0 18 0"/>',
        'wallet' => '<path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v12H5a3 3 0 0 1-3-3V6"/><path d="M16 13h2"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'server' => '<rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.1H9.6V21a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4h-.1V9.6H3A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1v-.1h4V3a1.7 1.7 0 0 0 1.1 1.6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.36.3.57.75.6 1.2v.1h.1v4h-.1a1.7 1.7 0 0 0-1.6.7z"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/>',
        'pulse' => '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'alert' => '<path d="M10.3 3.5L2.4 18a2 2 0 0 0 1.75 3h15.7a2 2 0 0 0 1.75-3L13.7 3.5a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.66 4.03 3 9 3s9-1.34 9-3V5M3 11v6c0 1.66 4.03 3 9 3s9-1.34 9-3v-6"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron' => '<path d="M9 18l6-6-6-6"/>',
        'close' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'command' => '<path d="M18 9a3 3 0 1 0 0-6 3 3 0 0 0-3 3v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12z"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
    ];
    return '<svg class="icon ' . htmlspecialchars($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['grid']) . '</svg>';
}

$nav = [
    ['label' => 'Tổng quan', 'icon' => 'grid', 'section' => 'dashboard', 'active' => true],
    ['label' => 'Việc cần xử lý', 'icon' => 'tasks', 'section' => 'dashboard', 'count' => 8],
    ['label' => 'Người dùng', 'icon' => 'users', 'section' => 'users'],
    ['label' => 'Tổ chức', 'icon' => 'building', 'section' => 'organizations'],
    ['label' => 'Học tập & hoạt động', 'icon' => 'book', 'section' => 'activities'],
    ['label' => 'Cơ hội & ứng tuyển', 'icon' => 'briefcase', 'section' => 'applications'],
    ['label' => 'Tài trợ & thanh toán', 'icon' => 'wallet', 'section' => 'payments'],
    ['label' => 'Thông báo', 'icon' => 'bell', 'section' => 'notifications'],
    ['label' => 'Audit & bảo mật', 'icon' => 'shield', 'section' => 'audit'],
    ['label' => 'RBAC & quyền', 'icon' => 'shield', 'section' => 'rbac'],
    ['label' => 'Hệ thống', 'icon' => 'server', 'section' => 'system'],
];
?>
<!doctype html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Trung tâm vận hành | TalentHub Admin</title>
    <link rel="icon" href="/assets/images/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/brand-component.css">
    <link rel="stylesheet" href="/assets/css/polish.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/typeui-selects.css">
    <script src="/assets/js/admin.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Bỏ qua điều hướng</a>
<div class="admin-shell">
    <div class="sidebar-scrim" data-sidebar-close hidden></div>
    <aside class="sidebar" id="admin-sidebar" aria-label="Điều hướng quản trị">
        <a class="brand learner-brand" href="/app/admin/index.php" aria-label="TalentHub Admin - Tổng quan">
            <span class="brand-mark learner-brand__mark" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
                </svg>
            </span>
            <span class="learner-brand__text">
                <span class="learner-brand__name">Talent<span>Hub</span></span>
                <span class="learner-brand__subtitle">Bảng quản trị</span>
            </span>
        </a>
        <nav class="side-nav">
            <p class="nav-label">Điều hành</p>
            <?php foreach ($nav as $item): ?>
                <a class="nav-item <?= !empty($item['active']) ? 'is-active' : '' ?>" href="#<?= htmlspecialchars($item['section']) ?>" data-admin-section="<?= htmlspecialchars($item['section']) ?>" <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>>
                    <?= icon($item['icon']) ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <?php if (isset($item['count'])): ?><span class="nav-count" data-nav-count="<?= htmlspecialchars($item['section']) ?>" aria-label="<?= $item['count'] ?> mục"><?= $item['count'] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="environment-card">
                <span class="status-dot is-ok"></span>
                <div><strong>Production healthy</strong><small>MySQL 8.4 · PHP target 8.5</small></div>
            </div>
            <div class="admin-profile">
                <span class="avatar"><?= htmlspecialchars($adminInitials) ?></span>
                <div><strong><?= htmlspecialchars($adminName) ?></strong><small>Platform administrator</small></div>
            </div>
            <button class="logout-button" type="button" data-admin-logout><?= icon('logout') ?><span>Đăng xuất</span></button>
        </div>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <button class="icon-button mobile-menu" type="button" aria-label="Mở điều hướng" aria-controls="admin-sidebar" aria-expanded="false" data-sidebar-toggle><?= icon('menu') ?></button>
            <button class="command-trigger" type="button" data-command-open aria-haspopup="dialog">
                <?= icon('search') ?><span>Tìm người dùng, tổ chức, mã yêu cầu...</span><kbd>⌘ K</kbd>
            </button>
            <div class="topbar-actions">
                <span class="prototype-chip"><span class="status-dot is-ok"></span>Connected · dữ liệu thật</span>
                <button class="icon-button has-indicator" type="button" data-alert-count aria-label="Việc cần xử lý" hidden><?= icon('bell') ?><span></span></button>
            </div>
        </header>

        <main id="main-content" tabindex="-1">
            <section class="page-heading" aria-labelledby="page-title">
                <div>
                    <p class="eyebrow">Trung tâm vận hành</p>
                    <h1 id="page-title">Chào buổi sáng, <?= htmlspecialchars($adminFirstName) ?>.</h1>
                    <p>Mọi tín hiệu quan trọng của TalentHub tại một nơi. Ưu tiên ngoại lệ trước, số liệu sau.</p>
                </div>
                <div class="heading-actions">
                    <span class="last-updated"><span class="status-dot is-ok"></span>Cập nhật 20 giây trước</span>
                    <button class="button secondary" type="button" data-refresh>Đồng bộ dữ liệu</button>
                    <button class="button primary" type="button" data-command-open><?= icon('command') ?>Mở trung tâm lệnh</button>
                </div>
            </section>

            <div data-dashboard-view>
            <section aria-labelledby="metrics-title">
                <div class="section-heading"><div><p class="eyebrow">Sức khỏe sản phẩm</p><h2 id="metrics-title">Chỉ số quan trọng</h2></div><a href="#analytics">Xem phân tích <?= icon('arrow') ?></a></div>
                <div class="metric-grid">
                    <?php foreach ($metrics as $metric): ?>
                    <article class="metric-card <?= htmlspecialchars($metric['tone']) ?>">
                        <div class="metric-top"><span><?= htmlspecialchars($metric['label']) ?></span><button class="ghost-menu" aria-label="Tùy chọn cho <?= htmlspecialchars($metric['label']) ?>">•••</button></div>
                        <strong data-dashboard-metric="<?= htmlspecialchars($metric['key']) ?>"><?= htmlspecialchars($metric['value']) ?></strong>
                        <div><span class="metric-change"><?= htmlspecialchars($metric['change']) ?></span><small><?= htmlspecialchars($metric['detail']) ?></small></div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="dashboard-grid">
                <section class="panel chart-panel" id="analytics" aria-labelledby="activity-title">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Cơ cấu tài khoản</p><h2 id="activity-title">Người dùng theo vai trò</h2><p>Số tài khoản hiện có trong từng nhóm quyền.</p></div>
                    </div>
                    <div class="chart-wrap" data-role-distribution>
                        <svg class="line-chart" viewBox="0 0 720 260" role="img" aria-labelledby="chart-title chart-desc">
                            <title id="chart-title">Xu hướng người dùng hoạt động 7 ngày</title>
                            <desc id="chart-desc">Học viên tăng từ khoảng 5.100 lên 6.800. Nhà trường giữ ổn định quanh 1.500. Doanh nghiệp tăng nhẹ lên khoảng 1.200.</desc>
                            <g class="grid-lines"><path d="M52 28H700M52 82H700M52 136H700M52 190H700M52 244H700"/></g>
                            <g class="axis-labels"><text x="18" y="32">8K</text><text x="18" y="86">6K</text><text x="18" y="140">4K</text><text x="18" y="194">2K</text><text x="31" y="248">0</text><text x="52" y="258">T2</text><text x="158" y="258">T3</text><text x="264" y="258">T4</text><text x="370" y="258">T5</text><text x="476" y="258">T6</text><text x="582" y="258">T7</text><text x="688" y="258">CN</text></g>
                            <path class="area student-area" d="M52 105L158 98L264 102L370 84L476 76L582 66L700 58L700 244L52 244Z"/>
                            <path class="series student-line" d="M52 105L158 98L264 102L370 84L476 76L582 66L700 58"/>
                            <path class="series school-line" d="M52 202L158 198L264 200L370 195L476 192L582 194L700 190"/>
                            <path class="series enterprise-line" d="M52 221L158 218L264 216L370 217L476 211L582 208L700 205"/>
                        </svg>
                    </div>
                </section>

                <section class="panel queue-panel" id="việc-cần-xử-lý" aria-labelledby="queue-title">
                    <div class="panel-heading compact"><div><p class="eyebrow">Operational queue</p><h2 id="queue-title">Cần xử lý ngay</h2></div><span class="count-badge" data-queue-count>0 mục</span></div>
                    <div class="queue-list">
                        <?php foreach ($queue as $index => $item): ?>
                        <button class="queue-item" type="button" data-queue-item data-title="<?= htmlspecialchars($item['title']) ?>">
                            <span class="severity <?= htmlspecialchars($item['severity']) ?>" aria-label="Mức độ <?= htmlspecialchars($item['severity']) ?>"></span>
                            <span class="queue-content"><strong><?= htmlspecialchars($item['title']) ?></strong><small><?= htmlspecialchars($item['meta']) ?></small><span><b><?= htmlspecialchars($item['owner']) ?></b> · <?= htmlspecialchars($item['sla']) ?></span></span>
                            <?= icon('chevron') ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <a class="panel-footer-link" href="#queue">Mở toàn bộ hàng đợi <?= icon('arrow') ?></a>
                </section>
            </div>

            <div class="operations-grid">
                <section class="panel table-panel" id="tổ-chức" aria-labelledby="org-title">
                    <div class="panel-heading">
                        <div><p class="eyebrow">Tổ chức</p><h2 id="org-title">Cần theo dõi</h2><p>School và Enterprise có hoạt động hoặc rủi ro gần đây.</p></div>
                        <div class="table-tools"><label><span class="sr-only">Lọc tổ chức</span><?= icon('search') ?><input type="search" placeholder="Lọc tổ chức..." data-org-filter></label><button class="button secondary small" type="button">Tất cả tổ chức</button></div>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <caption class="sr-only">Danh sách tổ chức cần theo dõi</caption>
                            <thead><tr><th scope="col">Tổ chức</th><th scope="col">Trạng thái</th><th scope="col">Thành viên</th><th scope="col">Hoạt động cuối</th><th scope="col">Rủi ro</th><th scope="col"><span class="sr-only">Hành động</span></th></tr></thead>
                            <tbody data-dashboard-organizations>
                                <?php foreach ($organizations as $org): ?>
                                <tr data-org-row>
                                    <td><div class="org-cell"><span class="org-logo"><?= htmlspecialchars(substr($org['name'], 0, 1)) ?></span><div><strong><?= htmlspecialchars($org['name']) ?></strong><small><?= htmlspecialchars($org['type']) ?></small></div></div></td>
                                    <td><span class="status-badge <?= $org['status'] === 'Đang hoạt động' ? 'success' : ($org['status'] === 'Chờ xác minh' ? 'warning' : 'neutral') ?>"><?= htmlspecialchars($org['status']) ?></span></td>
                                    <td><?= htmlspecialchars($org['members']) ?></td><td><?= htmlspecialchars($org['activity']) ?></td>
                                    <td><span class="risk <?= $org['risk'] === 'Bình thường' ? 'normal' : 'review' ?>"><i></i><?= htmlspecialchars($org['risk']) ?></span></td>
                                    <td><button class="icon-button" type="button" aria-label="Mở <?= htmlspecialchars($org['name']) ?>"><?= icon('chevron') ?></button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="panel audit-panel" id="audit--bảo-mật" aria-labelledby="audit-title">
                    <div class="panel-heading compact"><div><p class="eyebrow">Audit stream</p><h2 id="audit-title">Hoạt động gần đây</h2></div><button class="icon-button" aria-label="Lọc audit"><?= icon('settings') ?></button></div>
                    <ol class="audit-list" data-dashboard-audit>
                        <?php foreach ($audit as $event): ?>
                        <li><time><?= htmlspecialchars($event['time']) ?></time><span class="audit-dot <?= htmlspecialchars($event['tone']) ?>"></span><div><strong><?= htmlspecialchars($event['title']) ?></strong><small><?= htmlspecialchars($event['detail']) ?></small></div></li>
                        <?php endforeach; ?>
                    </ol>
                    <a class="panel-footer-link" href="#audit">Mở Audit Explorer <?= icon('arrow') ?></a>
                </aside>
            </div>
            </div>
            <section class="admin-module" data-module-view hidden aria-live="polite">
                <div class="module-toolbar">
                    <div><p class="eyebrow" data-module-kicker>Quản trị</p><h2 data-module-title>Module</h2><p data-module-description></p></div>
                    <div class="table-tools"><label><?= icon('search') ?><span class="sr-only">Tìm trong module</span><input type="search" placeholder="Tìm kiếm..." data-module-search></label><button class="button secondary small" type="button" data-module-refresh>Làm mới</button></div>
                </div>
                <div class="module-state" data-module-state>Chọn module từ thanh điều hướng.</div>
                <div class="panel module-content" data-module-content hidden></div>
            </section>
        </main>
    </div>
</div>

<dialog class="command-dialog" data-command-dialog aria-labelledby="command-title">
    <div class="command-box">
        <div class="command-search"><?= icon('search') ?><label class="sr-only" for="command-input">Tìm kiếm toàn cục</label><input id="command-input" type="search" placeholder="Tìm người dùng, tổ chức, mã yêu cầu hoặc lệnh..." autocomplete="off" data-command-input><button class="icon-button" type="button" data-command-close aria-label="Đóng trung tâm lệnh"><?= icon('close') ?></button></div>
        <div class="command-results" data-command-results>
            <p class="command-group-label">Đi tới nhanh</p>
            <button type="button" data-command-item><span class="command-item-icon"><?= icon('users') ?></span><span><strong>Quản lý người dùng</strong><small>Tìm, kiểm tra trạng thái và quyền truy cập</small></span><kbd>G U</kbd></button>
            <button type="button" data-command-item><span class="command-item-icon"><?= icon('building') ?></span><span><strong>Hàng đợi xác minh tổ chức</strong><small>Mở dữ liệu xác minh hiện tại</small></span><kbd>G O</kbd></button>
            <button type="button" data-command-item><span class="command-item-icon"><?= icon('shield') ?></span><span><strong>Tìm theo Request ID</strong><small>Mở audit timeline liên kết</small></span><kbd>G A</kbd></button>
        </div>
        <div class="command-footer"><span><kbd>↑</kbd><kbd>↓</kbd> di chuyển</span><span><kbd>Enter</kbd> mở</span><span><kbd>Esc</kbd> đóng</span></div>
    </div>
</dialog>

<dialog class="action-dialog" data-action-dialog aria-labelledby="action-title">
    <form method="dialog" data-action-form>
        <div class="action-dialog-header"><div><p class="eyebrow">Xác nhận thao tác</p><h2 id="action-title" data-action-title>Thay đổi trạng thái</h2></div><button class="icon-button" value="cancel" aria-label="Đóng"><?= icon('close') ?></button></div>
        <p data-action-description></p>
        <label class="field-label" for="organization-decision" data-decision-field hidden>Quyết định</label>
        <select id="organization-decision" class="typeui-select" data-organization-decision hidden><option value="verified">Phê duyệt</option><option value="rejected">Từ chối</option><option value="pending">Chuyển về chờ duyệt</option></select>
        <label class="field-label" for="action-reason">Lý do <span aria-hidden="true">*</span></label>
        <textarea id="action-reason" rows="4" minlength="5" required placeholder="Nhập lý do để ghi vào audit log..."></textarea>
        <div class="dialog-actions"><button class="button secondary" value="cancel">Hủy</button><button class="button primary" type="submit" value="confirm" data-action-submit>Xác nhận</button></div>
    </form>
</dialog>

<dialog class="action-dialog account-dialog" data-account-dialog aria-labelledby="account-title">
    <form data-account-form>
        <div class="action-dialog-header"><div><p class="eyebrow">Quản lý tài khoản</p><h2 id="account-title" data-account-title>Thêm tài khoản</h2></div><button class="icon-button" type="button" data-account-close aria-label="Đóng"><?= icon('close') ?></button></div>
        <input type="hidden" name="id">
        <div class="account-form-grid">
            <label>Họ và tên<input name="fullName" minlength="2" maxlength="150" required></label>
            <label>Email<input name="email" type="email" maxlength="255" required></label>
            <label>Vai trò<select name="role" class="typeui-select" required><option value="student">Học viên</option><option value="teacher">Giáo viên</option><option value="school">Nhà trường</option><option value="enterprise">Doanh nghiệp</option><option value="platform_admin">Quản trị hệ thống</option></select></label>
            <label data-password-field>Mật khẩu tạm thời<input name="password" type="password" minlength="12" autocomplete="new-password"><small>Tối thiểu 12 ký tự; chỉ bắt buộc khi tạo mới.</small></label>
        </div>
        <div class="dialog-actions"><button class="button secondary" type="button" data-account-close>Hủy</button><button class="button primary" type="submit">Lưu tài khoản</button></div>
    </form>
</dialog>

<div class="toast" role="status" aria-live="polite" aria-atomic="true" data-toast hidden></div>
</body>
</html>
