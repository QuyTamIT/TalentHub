<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$audit = $context['audit'];
$error = null;

$filters = [
    'search' => is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '',
    'accessType' => is_string($_GET['accessType'] ?? null) ? trim($_GET['accessType']) : '',
    'from' => is_string($_GET['from'] ?? null) ? trim($_GET['from']) : '',
    'to' => is_string($_GET['to'] ?? null) ? trim($_GET['to']) : '',
    'limit' => 100,
    'offset' => 0,
];

try {
    $overview = $audit->profileAccessOverview((string) $context['user']['id'], $filters);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    $overview = [
        'items' => [],
        'summary' => ['totalAccesses' => 0, 'uniqueEnterprises' => 0, 'uniqueStudents' => 0, 'recentAccesses' => 0],
        'page' => ['total' => 0, 'limit' => 100, 'offset' => 0],
    ];
}

$typeLabels = [
    'talent_detail' => 'Xem Talent Passport',
    'application_cv' => 'Xem hồ sơ ứng tuyển',
    'shared_profile' => 'Xem hồ sơ được chia sẻ',
];
$summary = $overview['summary'];
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr((string) $context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/audit-logs.php';
$pageTitle = 'Nhật ký truy cập hồ sơ';

ob_start();
?>
<?php $pageDescription = 'Theo dõi doanh nghiệp đã truy cập hồ sơ sinh viên thuộc phạm vi nhà trường.'; include __DIR__ . '/includes/page-banner.php'; ?>

<?php if ($error !== null): ?>
    <div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="school-audit-kpis">
    <article class="school-audit-kpi"><span>Tổng lượt truy cập</span><strong><?= (int) $summary['totalAccesses']; ?></strong></article>
    <article class="school-audit-kpi"><span>30 ngày gần nhất</span><strong><?= (int) $summary['recentAccesses']; ?></strong></article>
    <article class="school-audit-kpi"><span>Doanh nghiệp</span><strong><?= (int) $summary['uniqueEnterprises']; ?></strong></article>
    <article class="school-audit-kpi"><span>Sinh viên được xem</span><strong><?= (int) $summary['uniqueStudents']; ?></strong></article>
</div>

<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h2 class="school-section-box__title">Bộ lọc nhật ký</h2>
            <p class="school-audit-muted">Chỉ hiển thị sinh viên thuộc lớp của trường hiện tại.</p>
        </div>
    </div>
    <form method="get" class="school-audit-filter">
        <label><span>Tìm kiếm</span><input name="search" value="<?= htmlspecialchars($filters['search']); ?>" placeholder="Sinh viên, doanh nghiệp, tài khoản truy cập"></label>
        <label><span>Loại truy cập</span><select name="accessType"><option value="">Tất cả</option><?php foreach ($typeLabels as $value => $label): ?><option value="<?= $value; ?>" <?= $filters['accessType'] === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option><?php endforeach; ?></select></label>
        <label><span>Từ ngày</span><input type="date" name="from" value="<?= htmlspecialchars($filters['from']); ?>"></label>
        <label><span>Đến ngày</span><input type="date" name="to" value="<?= htmlspecialchars($filters['to']); ?>"></label>
        <div class="school-audit-filter__actions"><button class="btn btn-primary" type="submit">Lọc dữ liệu</button><a class="btn btn-secondary" href="<?= app_href('/app/school/audit-logs.php'); ?>">Đặt lại</a></div>
    </form>
</section>

<section class="school-section-box school-audit-table-box">
    <div class="school-section-box__header">
        <h2 class="school-section-box__title">Lịch sử truy cập</h2>
        <span class="school-audit-muted"><?= (int) $overview['page']['total']; ?> bản ghi</span>
    </div>
    <?php if ($overview['items'] === []): ?>
        <div class="school-audit-empty">Chưa có lượt truy cập hồ sơ phù hợp với bộ lọc.</div>
    <?php else: ?>
        <div class="school-audit-table-wrap">
            <table class="school-class-table">
                <thead><tr><th>Thời gian</th><th>Sinh viên</th><th>Doanh nghiệp</th><th>Loại truy cập</th><th>Tài khoản thực hiện</th><th>Request ID</th></tr></thead>
                <tbody>
                <?php foreach ($overview['items'] as $item): ?>
                    <tr>
                        <td><time datetime="<?= htmlspecialchars((string) $item['accessedAt']); ?>"><?= htmlspecialchars((string) $item['accessedAt']); ?></time></td>
                        <td><strong><?= htmlspecialchars((string) $item['studentName']); ?></strong><br><small><?= htmlspecialchars((string) $item['className']); ?></small></td>
                        <td><?= htmlspecialchars((string) $item['enterpriseName']); ?></td>
                        <td><span class="school-audit-type"><?= htmlspecialchars($typeLabels[(string) $item['accessType']] ?? (string) $item['accessType']); ?></span></td>
                        <td><?= htmlspecialchars((string) ($item['actorEmail'] ?? 'Hệ thống')); ?></td>
                        <td><code><?= htmlspecialchars((string) ($item['requestId'] ?? '—')); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$pageBody = ob_get_clean();
$extraStyles = <<<'HTML'
<style>
.school-audit-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.25rem}.school-audit-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem 1.15rem}.school-audit-kpi span{display:block;color:#64748b;font-size:.84rem;margin-bottom:.4rem}.school-audit-kpi strong{color:#0f172a;font-size:1.65rem}.school-audit-filter{display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr;gap:1rem;align-items:end}.school-audit-filter label span{display:block;font-size:.82rem;font-weight:700;color:#475569;margin-bottom:.4rem}.school-audit-filter input,.school-audit-filter select{width:100%;min-height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:.55rem .7rem;background:#fff}.school-audit-filter__actions{display:flex;gap:.6rem;grid-column:1/-1}.school-audit-table-box{margin-top:1.25rem}.school-audit-table-wrap{overflow:auto}.school-audit-table-wrap table{min-width:980px}.school-audit-muted{color:#64748b;font-size:.86rem;margin:0}.school-audit-type{display:inline-flex;border-radius:999px;background:#eff6ff;color:#1d4ed8;padding:.28rem .62rem;font-size:.78rem;font-weight:700}.school-audit-empty{text-align:center;padding:2.5rem;color:#64748b}.school-audit-table-wrap code{font-size:.76rem;color:#475569}@media(max-width:1000px){.school-audit-kpis{grid-template-columns:repeat(2,1fr)}.school-audit-filter{grid-template-columns:1fr 1fr}}@media(max-width:640px){.school-audit-kpis,.school-audit-filter{grid-template-columns:1fr}}
</style>
HTML;
require __DIR__ . '/includes/layout.php';
