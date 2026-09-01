<?php
/**
 * TalentHub - School Dashboard Reports Page
 * Tạo & tải các báo cáo CSV/Excel của trường (dữ liệu thật từ DB).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Service\SchoolDashboardService;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$userId  = $context['user']['id'];
$session = $context['session'];
$pdo     = $context['pdo'] ?? null;

$flash = null;
$error = null;

// Direct Download Handler
if (isset($_GET['download']) && !empty($_GET['id'])) {
    $reportId = (string) $_GET['id'];
    try {
        $content = $service->readReportFile($userId, $reportId);
        $filename = 'report-' . date('Ymd-His') . '.csv';
        if ($pdo !== null) {
            $stmt = $pdo->prepare('SELECT reportType, fileUrl FROM reports WHERE id = ? LIMIT 1');
            $stmt->execute([$reportId]);
            $rep = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($rep && !empty($rep['fileUrl'])) {
                $filename = basename((string) $rep['fileUrl']);
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    } catch (\Throwable $e) {
        $error = 'Không thể tải xuống tệp báo cáo: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $type = $_POST['reportType'] ?? '';
        $start = $_POST['periodStart'] ?? null;
        $end   = $_POST['periodEnd'] ?? null;
        $result = $service->generateReport($userId, (string) $type, $start ? (string) $start : null, $end ? (string) $end : null);
        $downloadLink = '?download=1&id=' . urlencode((string) $result['id']);
        $flash = 'Đã tạo báo cáo thành công! <a href="' . htmlspecialchars($downloadLink) . '" target="_blank" rel="noopener" style="font-weight: 700;">Tải về ngay (.CSV / Excel)</a>.';
    } catch (ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

$reports = $service->listReports($userId);

$perPage = 6;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$totalReports = count($reports);
$pagedReports = array_slice($reports, $offset, $perPage);

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/reports.php';
$pageTitle    = 'Báo cáo & Xuất dữ liệu';

$reportTypes = [
    SchoolDashboardService::REPORT_TYPE_INTERNSHIPS => ['Tiến độ thực tập & Doanh nghiệp', 'Tổng hợp tiếp nhận thực tập, phân công Mentor & doanh nghiệp liên kết'],
    SchoolDashboardService::REPORT_TYPE_COMPETENCY  => ['Đánh giá năng lực sinh viên', 'Bảng điểm ĐGNL (0-100), xếp loại học thuật & nhận xét của Giảng viên'],
    SchoolDashboardService::REPORT_TYPE_STUDENTS    => ['Hồ sơ & Danh sách sinh viên', 'Toàn bộ sinh viên kèm phân lớp chuyên ngành và kỹ năng đã xác thực'],
    SchoolDashboardService::REPORT_TYPE_MONTHLY     => ['Báo cáo hoạt động theo tháng', 'Tổng hợp lượt sinh viên tham gia rèn luyện và đánh giá theo từng tháng'],
    SchoolDashboardService::REPORT_TYPE_CLASS       => ['Báo cáo theo lớp chuyên ngành', 'Sĩ số, phân bố và tỷ lệ hoàn thiện hồ sơ sinh viên từng lớp'],
    SchoolDashboardService::REPORT_TYPE_AWARDS      => ['Danh sách giải thưởng & Huy hiệu', 'Thống kê giải thưởng Hackathon, chứng chỉ và danh hiệu đạt được'],
];

ob_start();
?>
<?php
$pageDescription = 'Tạo và tải xuống các báo cáo CSV/Excel tổng hợp tiến độ thực tập, điểm năng lực và hồ sơ sinh viên.';
include __DIR__ . '/includes/page-banner.php';
?>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success" style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem 1.25rem; background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 8px; color: #166534; margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span><?= $flash; ?></span>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="school-flash school-flash--error" style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem 1.25rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; color: #991B1B; margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?= htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<div class="school-grid-2col school-grid-2col--reports" style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1.75rem; align-items: start;">
    
    <!-- Form Tạo báo cáo mới -->
    <div class="school-section-box" style="margin-bottom: 0;">
        <div class="school-section-box__header" style="border-bottom: 1px solid #F1F5F9; padding-bottom: 1rem; margin-bottom: 1.25rem;">
            <h3 class="school-section-box__title" style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.15rem; font-weight: 700; color: #0F172A;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Tạo Báo Cáo Mới
            </h3>
            <p class="school-section-box__subtitle" style="font-size: 0.8125rem; color: #64748B; margin-top: 0.25rem;">
                Xuất dữ liệu theo mẫu chuẩn hỗ trợ Excel & phần mềm quản lý đào tạo
            </p>
        </div>

        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="school-form__grid" style="grid-template-columns: 1fr; gap: 1.15rem;">
                <label class="school-form__field">
                    <span style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        Loại báo cáo cần xuất <em>*</em>
                    </span>
                    <select name="reportType" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.875rem; background: #FFFFFF;">
                        <?php foreach ($reportTypes as $code => [$label, $desc]): ?>
                            <option value="<?= htmlspecialchars($code); ?>">
                                <?= htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <label class="school-form__field">
                        <span style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.35rem; display: block;">Từ ngày</span>
                        <input type="date" name="periodStart" value="<?= date('Y-m-01'); ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.875rem;">
                    </label>
                    <label class="school-form__field">
                        <span style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.35rem; display: block;">Đến ngày</span>
                        <input type="date" name="periodEnd" value="<?= date('Y-m-d'); ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.875rem;">
                    </label>
                </div>
            </div>

            <div class="school-form__actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 700; font-size: 0.875rem; border-radius: 6px; background: #2563EB; color: #FFFFFF; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Tạo báo cáo
                </button>
            </div>
        </form>
    </div>

    <!-- Danh sách Báo cáo gần đây -->
    <div class="school-section-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="school-section-box__header" style="border-bottom: 1px solid #F1F5F9; padding-bottom: 1rem; margin-bottom: 1.25rem;">
            <h3 class="school-section-box__title" style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.15rem; font-weight: 700; color: #0F172A;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Báo Cáo Gần Đây
            </h3>
            <p class="school-section-box__subtitle" style="font-size: 0.8125rem; color: #64748B; margin-top: 0.25rem;">
                Lịch sử các tệp dữ liệu đã kết xuất và sẵn sàng tải về
            </p>
        </div>

        <?php if ($reports === []): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" style="margin: 0 auto 0.75rem auto; display: block;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <p style="font-size: 0.875rem; margin: 0;">Chưa có báo cáo nào được tạo. Hãy chọn loại báo cáo và nhấn <strong>Tạo báo cáo</strong>.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                <table class="school-class-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #E2E8F0; text-align: left;">
                            <th style="padding: 0.75rem 0.5rem; font-weight: 700; color: #475569;">Loại báo cáo</th>
                            <th style="padding: 0.75rem 0.5rem; font-weight: 700; color: #475569;">Kỳ dữ liệu</th>
                            <th style="padding: 0.75rem 0.5rem; font-weight: 700; color: #475569;">Thời gian</th>
                            <th style="padding: 0.75rem 0.5rem; font-weight: 700; color: #475569; text-align: right;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagedReports as $r): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 0.85rem 0.5rem;">
                                    <?php
                                    $label = $r['reportType'];
                                    foreach ($reportTypes as $code => [$lbl, $_]) {
                                        if ($code === $r['reportType']) { $label = $lbl; break; }
                                    }
                                    ?>
                                    <strong style="color: #0F172A; display: block; font-size: 0.875rem;"><?= htmlspecialchars($label); ?></strong>
                                    <span style="font-size: 0.75rem; color: #64748B;">Định dạng CSV / Excel (UTF-8)</span>
                                </td>
                                <td style="padding: 0.85rem 0.5rem;">
                                    <span style="font-size: 0.8125rem; color: #334155; font-weight: 500;">
                                        <?= htmlspecialchars($r['periodStart']); ?> → <?= htmlspecialchars($r['periodEnd']); ?>
                                    </span>
                                </td>
                                <td style="padding: 0.85rem 0.5rem;">
                                    <span style="font-size: 0.75rem; color: #64748B;">
                                        <?= htmlspecialchars(substr((string) $r['createdAt'], 0, 16)); ?>
                                    </span>
                                </td>
                                <td style="padding: 0.85rem 0.5rem; text-align: right;">
                                    <a class="btn btn-sm" href="?download=1&id=<?= urlencode((string) $r['id']); ?>" target="_blank" rel="noopener" style="padding: 0.4rem 0.85rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                        Tải về
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <nav class="school-pagination" aria-label="Phân trang" style="margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 1rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-outline" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid #CBD5E1; text-decoration: none; color: #475569;">‹ Trước</a>
                    <?php endif; ?>
                    <span class="school-pagination__info" style="font-size: 0.875rem; color: #64748B;">Trang <?= $page; ?> · <?= $perPage; ?> / trang</span>
                    <?php if (($offset + $perPage) < $totalReports): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-outline" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid #CBD5E1; text-decoration: none; color: #475569;">Sau ›</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
@media (max-width: 850px) {
    .school-grid-2col--reports { grid-template-columns: 1fr !important; }
}
</style>
HTML;

require __DIR__ . '/includes/layout.php';
