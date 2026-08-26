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
$session = $context['session'];

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
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
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
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
.school-flash--success a { color: #047857; text-decoration: underline; margin-left: 0.25rem; }
</style>
HTML;

require __DIR__ . '/includes/layout.php';
