<?php
/**
 * TalentHub - School Dashboard Analytics Page
 * Phân tích dữ liệu chi tiết & Biểu đồ Radar Năng khiếu Nhà trường
 */
declare(strict_types=1);

// Never cache this page: analytics must always reflect current DB (avoid stale
// radar "all 50" being served from browser/proxy cache after data is updated).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$school     = $context['school'];
$dashboard  = $context['dashboard'];
$service    = $context['service'];
$userId     = $context['user']['id'];
$pdo        = $context['pdo'] ?? null;

$metrics = $dashboard['metrics'];
$classes = $service->classes($userId);

$analytics = $service->analytics($userId);
$monthlyStudentRows = $analytics['monthlyStudents'] ?? [];

// Monthly Student Activity Stats (academic year, scoped to school).
// Build exactly the 12 months of the school's current academic year so the
// bars line up with the "Niên khóa" badge and the backend filter used by
// monthlyStudentActivity() (Sep 1 – Aug 31).
$currentAcademicYear = trim((string) ($school['academicYear'] ?? '2025 - 2026'));
if ($currentAcademicYear === '') {
    $currentAcademicYear = '2025 - 2026';
}
$monthlyByKey = [];
foreach ($monthlyStudentRows as $row) {
    $monthlyByKey[(string) $row['month']] = (int) $row['students'];
}
$monthlyStats = [];
if (preg_match('/(\d{4})\s*-\s*(\d{4})/', $currentAcademicYear, $m) === 1) {
    $startYear = (int) $m[1];
} else {
    $startYear = (int) date('Y');
}
for ($i = 0; $i < 12; $i++) {
    $dt = new DateTime(sprintf('%04d-09-01', $startYear));
    $dt->modify("+{$i} months");
    $monthKey = $dt->format('Y-m');
    $monthlyStats[] = [
        'month'    => 'T' . (int) $dt->format('n'),
        'yearMo'   => $monthKey,
        'students' => $monthlyByKey[$monthKey] ?? 0,
    ];
}

$maxStudents = max(1, max(array_column($monthlyStats, 'students')));

// Verified Skill & Aptitude Distribution
$talentDistribution = $service->verifiedSkillDistribution($userId);
if (!is_array($talentDistribution)) {
    $talentDistribution = [];
}

// 5 Core Aptitude Radar Dimensions - sourced from real data
$hasRadarData = !empty($school['id']) && !empty($classes);
$radarDimensions = $service->radarScores($userId);
if (empty($radarDimensions)) {
    $radarDimensions = [];
}

// Ensure consistency: details on the right use the SAME array as the radar
$hasRadarData = $hasRadarData && !empty($radarDimensions);

$gradeStats = [];
$grouped = [];
foreach ($classes as $class) {
    $grouped[$class['grade']][] = $class;
}
// Sort grade groups alphabetically (K1 < K2 < ... < Khối 12 / Năm 4)
ksort($grouped, SORT_NATURAL);
foreach ($grouped as $grade => $items) {
    $sum = array_sum(array_column($items, 'students'));
    // Weighted completion: classes with more students weigh more, so the
    // grade-level figure reflects the actual student body (not per-class avg).
    $weightedSum = 0.0;
    $weightedN   = 0;
    foreach ($items as $item) {
        $n = (int) $item['students'];
        $weightedSum += (int) $item['completion'] * $n;
        $weightedN   += $n;
    }
    $avg = $weightedN > 0
        ? (int) round($weightedSum / $weightedN)
        : (count($items) > 0
            ? (int) round(array_sum(array_column($items, 'completion')) / count($items))
            : 0);
    $gradeStats[] = [
        'grade'      => $grade,
        'students'   => $sum,
        'completion' => $avg,
    ];
}

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => mb_substr($school['name'], 0, 2),
    'level'         => $school['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '2025 - 2026',
];

$currentRoute = '/app/school/analytics.php';
$pageTitle = 'Phân tích dữ liệu & Năng khiếu';

ob_start();
?>
<?php
$pageDescription = 'Tổng quan số liệu sinh viên theo tháng, bản đồ Radar năng khiếu toàn trường và tiến độ hoàn thiện hồ sơ.';
include __DIR__ . '/includes/page-banner.php';
?>

<!-- PHẦN 1: BẢN ĐỒ RADAR NĂNG KHIẾU TOÀN TRƯỜNG & PHÂN BỔ NĂNG LỰC -->
<div class="school-grid-2col" style="margin-bottom: 1.75rem;">
    
    <!-- Radar Chart Container -->
    <div class="school-section-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="school-section-box__header school-section-box__header--bordered">
            <div class="school-flex-between" style="width: 100%;">
                <div>
                    <h3 class="school-section-box__title school-flex-center">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 19 21 12 17 5 21 12 2"></polygon>
                        </svg>
                        Bản đồ Radar Năng khiếu Toàn trường
                    </h3>
                    <p class="school-section-box__subtitle">
                        Tổng hợp điểm đánh giá trung bình 4 miền năng lực sinh viên <?= htmlspecialchars(!empty($school['name']) ? $school['name'] : 'Nhà trường'); ?>
                    </p>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; font-size: 0.75rem; font-weight: 600;">
                    <span style="display: flex; align-items: center; gap: 0.35rem; color: #1D4ED8;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #2563EB;"></span> <?= htmlspecialchars(!empty($school['name']) ? $school['name'] : 'Nhà trường'); ?> (Thực tế)
                    </span>
                    <span style="display: flex; align-items: center; gap: 0.35rem; color: #475569;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #CBD5E1;"></span> Benchmark chuẩn ngành
                    </span>
                </div>
            </div>
        </div>

        <div style="position: relative; flex: 1; min-height: 320px; display: flex; align-items: center; justify-content: center;">
            <canvas id="schoolRadarChart" style="max-height: 320px; width: 100%;"></canvas>
            <!-- SVG Fallback in case JS is disabled -->
            <noscript>
                <div style="text-align: center; padding: 1.5rem; color: #475569;">
                    <p><strong>Điểm năng khiếu trung bình:</strong></p>
                    <p>Kỹ thuật: <?= $radarDimensions[0]['score']; ?>đ | Logic - Toán học: <?= $radarDimensions[1]['score']; ?>đ | Kinh doanh: <?= $radarDimensions[2]['score']; ?>đ | Nghệ thuật: <?= $radarDimensions[3]['score']; ?>đ</p>
                </div>
            </noscript>
        </div>

        <!-- Radar Footer Insight -->
        <?php if ($hasRadarData): ?>
            <?php
            $_sorted = $radarDimensions;
            usort($_sorted, static fn($a,$b) => ($b['score']-$b['benchmark']) <=> ($a['score']-$a['benchmark']));
            $_top = array_slice($_sorted, 0, 2);
            $_label = $_top[0]['domain'] . ' (' . $_top[0]['score'] . '/100)';
            if (count($_top) > 1) $_label .= ' và ' . $_top[1]['domain'] . ' (' . $_top[1]['score'] . '/100)';
            ?>
            <div style="margin-top: 1rem; padding: 0.85rem 1rem; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span style="font-size: 0.8125rem; color: #15803D; font-weight: 600;">
                    Điểm nổi bật: <?= htmlspecialchars($_label); ?> trên chuẩn đào tạo khu vực.
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Phân bổ năng khiếu chi tiết (Aptitude Breakdown) -->
    <div class="school-section-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="school-section-box__header school-section-box__header--bordered">
            <h3 class="school-section-box__title">
                Chi tiết 4 Miền Năng lực
            </h3>
            <p class="school-section-box__subtitle">
                Phân tích điểm số và ứng dụng thực tiễn trong đào tạo
            </p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1.15rem; flex: 1; justify-content: space-between;">
            <?php foreach ($radarDimensions as $dim): ?>
                <div class="school-card-subtle">
                    <div class="school-flex-between" style="margin-bottom: 0.35rem;">
                        <span style="font-size: 0.875rem; font-weight: 700; color: #0F172A;">
                            <?= htmlspecialchars($dim['domain']); ?>
                        </span>
                        <div class="school-flex-center">
                            <span style="font-size: 0.875rem; font-weight: 800; color: <?= $dim['color']; ?>;">
                                <?= $dim['score']; ?> / 100
                            </span>
                            <?php $rawDiff = $dim['score'] - $dim['benchmark']; $diff = max(0, $rawDiff); ?>
                            <span class="school-badge school-badge--success">
                                +<?= $diff; ?>đ
                            </span>
                        </div>
                    </div>
                    <div class="school-progress-track" style="margin-bottom: 0.35rem;">
                        <div class="school-progress-fill" style="width: <?= $dim['score']; ?>%; background: <?= $dim['color']; ?>; transition: width 0.8s ease;"></div>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748B; line-height: 1.35;">
                        <?= htmlspecialchars($dim['description']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- PHẦN 2: HOẠT ĐỘNG THEO THÁNG & TIẾN ĐỘ THEO KHỐI -->
<div class="school-grid-2col" style="margin-bottom: 1.5rem;">
    
    <!-- Hoạt động theo tháng -->
    <div class="school-chart-container" style="margin-bottom: 0;">
        <div class="school-chart-header">
            <div>
                <h3 class="school-chart-title">Học sinh & Hoạt động theo tháng</h3>
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Số lượng sinh viên tham gia đánh giá và trải nghiệm theo 12 tháng của niên khóa <?= htmlspecialchars($currentAcademicYear); ?></p>
            </div>
            <span class="school-badge school-badge--info">
                Niên khóa <?= htmlspecialchars($currentAcademicYear); ?>
            </span>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 0.45rem; height: 190px; padding: 1.25rem 0 0.5rem 0;">
            <?php foreach ($monthlyStats as $stat):
                $height = $stat['students'] > 0 ? max(6, round(($stat['students'] / $maxStudents) * 145)) : 4; ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.3rem; min-width: 0; overflow: hidden;" title="<?= $stat['month']; ?>: <?= $stat['students']; ?> sinh viên tham gia">
                    <span style="font-size: 0.6875rem; font-weight: 600; color: var(--text-secondary);"><?= (int) $stat['students']; ?></span>
                    <div style="width: 100%; max-width: 42px; background: linear-gradient(180deg, #2563EB 0%, #93C5FD 100%); border-radius: 5px 5px 0 0; height: <?= $height; ?>px; opacity: <?= $stat['students'] > 0 ? '1' : '0.3'; ?>; transition: height 0.3s ease;"></div>
                    <span style="font-size: 0.625rem; font-weight: 500; color: var(--text-muted); white-space: nowrap;"><?= htmlspecialchars($stat['month']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tiến độ theo khối / chuyên ngành -->
    <div class="school-section-box" style="margin-bottom: 0;">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Tiến độ Hồ sơ theo Khối / Lớp</h3>
            <p class="school-section-box__subtitle">Tỷ lệ hoàn thiện hồ sơ năng lực và kỹ năng sinh viên</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1.15rem;">
            <?php if (!empty($gradeStats)): ?>
                <?php foreach ($gradeStats as $grade): ?>
                    <div class="school-card-subtle">
                        <div class="school-flex-between" style="margin-bottom: 0.4rem;">
                            <span style="font-size: 0.875rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($grade['grade']); ?></span>
                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <span style="font-size: 0.8125rem; color: #64748B;"><?= $grade['students']; ?> sinh viên</span>
                                <span style="font-size: 0.875rem; font-weight: 800; color: #2563EB;"><?= $grade['completion']; ?>%</span>
                            </div>
                        </div>
                        <div class="school-progress-track">
                            <div class="school-progress-fill" style="width: <?= $grade['completion']; ?>%; background: linear-gradient(90deg, #2563EB 0%, #38BDF8 100%);"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 24px; text-align: center; color: var(--school-text-secondary, #64748b);">
                    Chưa có dữ liệu tiến độ theo khối / lớp.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Load Chart.js for interactive Radar chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('schoolRadarChart');
    if (!ctx) return;

    var radarLabels = <?= json_encode(array_column($radarDimensions, 'domain'), JSON_UNESCAPED_UNICODE); ?>;
    var actualScores = <?= json_encode(array_column($radarDimensions, 'score')); ?>;
    var benchmarkScores = <?= json_encode(array_column($radarDimensions, 'benchmark')); ?>;

    if (typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: radarLabels,
                datasets: [
                    {
                        label: <?= json_encode(!empty($school['name']) ? $school['name'] : 'Nhà trường', JSON_UNESCAPED_UNICODE); ?>,
                        data: actualScores,
                        backgroundColor: 'rgba(37, 99, 235, 0.25)',
                        borderColor: '#2563EB',
                        pointBackgroundColor: '#1D4ED8',
                        pointBorderColor: '#FFFFFF',
                        pointHoverBackgroundColor: '#FFFFFF',
                        pointHoverBorderColor: '#1D4ED8',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Chuẩn ngành (Benchmark)',
                        data: benchmarkScores,
                        backgroundColor: 'rgba(148, 163, 184, 0.15)',
                        borderColor: '#94A3B8',
                        pointBackgroundColor: '#94A3B8',
                        pointBorderColor: '#FFFFFF',
                        pointRadius: 4,
                        borderWidth: 1.5,
                        borderDash: [4, 4]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: { color: '#E2E8F0' },
                        grid: { color: '#E2E8F0' },
                        pointLabels: {
                            font: { size: 12, weight: '700', family: 'Inter, system-ui, sans-serif' },
                            color: '#1E293B'
                        },
                        ticks: {
                            min: 40,
                            max: 100,
                            stepSize: 15,
                            backdropColor: 'transparent',
                            color: '#64748B',
                            font: { size: 10 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' / 100 điểm';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
$pageBody = ob_get_clean();

require __DIR__ . '/includes/layout.php';
