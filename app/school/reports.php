<?php
/**
 * TalentHub - School Dashboard Reports Page
 * Tạo & tải các báo cáo CSV của trường (dữ liệu thật từ DB).
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Service\SchoolDashboardService;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$userId  = $context['user']['id'];

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $type = $_POST['reportType'] ?? '';
        $start = $_POST['periodStart'] ?? null;
        $end   = $_POST['periodEnd'] ?? null;
        $result = $service->generateReport($userId, (string) $type, $start ? (string) $start : null, $end ? (string) $end : null);
        $flash = 'Đã tạo báo cáo. <a href="' . htmlspecialchars($result['fileUrl']) . '" target="_blank" rel="noopener">Tải xuống</a>.';
    } catch (ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

$reports = $service->listReports($userId);

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Trung học',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/reports.php';
$pageTitle    = 'Báo cáo';

$reportTypes = [
    SchoolDashboardService::REPORT_TYPE_MONTHLY  => ['Báo cáo tháng', 'Tổng hợp hoạt động theo tháng'],
    SchoolDashboardService::REPORT_TYPE_STUDENTS => ['Danh sách học sinh', 'Toàn bộ hồ sơ học sinh'],
    SchoolDashboardService::REPORT_TYPE_CLASS    => ['Báo cáo lớp', 'Theo từng lớp học'],
    SchoolDashboardService::REPORT_TYPE_AWARDS   => ['Giải thưởng', 'Danh sách & thống kê giải'],
];

ob_start();
?>
<?php
$pageDescription = 'Tạo và tải xuống các báo cáo CSV theo kỳ cho nhà trường.';
include __DIR__ . '/includes/page-banner.php';
?>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success"><?= $flash; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="school-grid-2col school-grid-2col--reports">
    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Tạo báo cáo mới</h3>
        </div>
        <form method="post" class="school-form" novalidate>
            <div class="school-form__grid" style="grid-template-columns: 1fr;">
                <label class="school-form__field">
                    <span>Loại báo cáo <em>*</em></span>
                    <select name="reportType" required>
                        <?php foreach ($reportTypes as $code => [$label, $desc]): ?>
                            <option value="<?= htmlspecialchars($code); ?>">
                                <?= htmlspecialchars($label); ?> — <?= htmlspecialchars($desc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <label class="school-form__field">
                        <span>Từ ngày</span>
                        <input type="date" name="periodStart" value="<?= date('Y-m-01'); ?>">
                    </label>
                    <label class="school-form__field">
                        <span>Đến ngày</span>
                        <input type="date" name="periodEnd" value="<?= date('Y-m-d'); ?>">
                    </label>
                </div>
            </div>
            <div class="school-form__actions">
                <button type="submit" class="btn btn-primary">Tạo báo cáo</button>
            </div>
        </form>
    </div>

    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Báo cáo gần đây</h3>
        </div>
        <?php if ($reports === []): ?>
            <p style="color: var(--text-muted);">Chưa có báo cáo nào được tạo.</p>
        <?php else: ?>
            <table class="school-class-table">
                <thead>
                    <tr>
                        <th>Loại</th>
                        <th>Kỳ</th>
                        <th>Ngày tạo</th>
                        <th style="text-align: right;">Tải</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>
                                <?php
                                $label = $r['reportType'];
                                foreach ($reportTypes as $code => [$lbl, $_]) {
                                    if ($code === $r['reportType']) { $label = $lbl; break; }
                                }
                                ?>
                                <strong><?= htmlspecialchars($label); ?></strong>
                            </td>
                            <td>
                                <span style="font-size: 0.8125rem; color: var(--text-secondary);">
                                    <?= htmlspecialchars($r['periodStart']); ?> → <?= htmlspecialchars($r['periodEnd']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.8125rem; color: var(--text-muted);">
                                    <?= htmlspecialchars(substr((string) $r['createdAt'], 0, 16)); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a class="btn btn-sm btn-outline" href="<?= htmlspecialchars($r['fileUrl']); ?>" target="_blank" rel="noopener">CSV</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.school-grid-2col { display: grid; grid-template-columns: minmax(0, 360px) minmax(0, 1fr); gap: 1.5rem; }
.school-form__grid { display: grid; gap: 1rem; margin-top: 1rem; }
.school-form__field { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; color: var(--text-secondary); }
.school-form__field input,
.school-form__field select { width: 100%; padding: 0.625rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size: 0.9375rem; }
.school-form__field input:focus,
.school-form__field select:focus { outline: 2px solid #2563EB; outline-offset: 1px; }
.school-form__field em { color: #DC2626; font-style: normal; margin-left: 2px; }
.school-form__actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.school-flash { padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.875rem; }
.school-flash--success { background: #ECFDF5; color: #047857; border: 1px solid #6EE7B7; }
.school-flash--success a { color: #047857; text-decoration: underline; margin-left: 0.25rem; }
.school-flash--error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FCA5A5; }
@media (max-width: 900px) { .school-grid-2col { grid-template-columns: 1fr; } }
</style>
HTML;

require __DIR__ . '/includes/layout.php';
