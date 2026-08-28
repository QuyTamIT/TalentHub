# Learner Progress Dashboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chỉnh phần nội dung bên dưới hero của dashboard học sinh theo mockup đã duyệt, dùng dữ liệu thật từ database chính cho điểm năng lực, kỹ năng, AI, huy hiệu, giờ trải nghiệm và hoạt động.

**Architecture:** Giữ nguyên header, sidebar và hero. `student-data.php` tiếp tục là lớp chuẩn bị view data từ Talent Passport/database; `index.php` chỉ chuẩn hóa dữ liệu trình bày và render ba khối mới; CSS mới được scope dưới `.learner-page-overview` để không ảnh hưởng trang khác. Không thêm API hay schema và không dùng dữ liệu mock khi ứng dụng chạy database mode.

**Tech Stack:** PHP 8+, MySQL/PDO, HTML5, CSS Grid/Flexbox, PowerShell contract tests.

## Global Constraints

- Giữ nguyên header và hero hiện tại cả markup, nội dung lẫn hành vi responsive.
- Font là `Be Vietnam Pro`, sans-serif.
- Màu: primary `#F97316`, primary hover `#EA580C`, primary light `#FFF7ED`, secondary `#2563EB`, secondary light `#EFF6FF`, accent `#16A34A`, background `#F8FAFC`, surface `#FFFFFF`, text primary `#0F172A`, text secondary `#64748B`, border `#E2E8F0`, warning `#F59E0B`, danger `#DC2626`.
- Radius control `8px`, radius card `12px`.
- Nguồn dữ liệu production là database chính qua `StudentAppContext`, `DatabaseTalentPassportRepository`, `BadgeReadService` và `DatabaseActivityRepository`.
- Schema `student_profiles` không có `talentScore`; điểm năng lực phải được tính từ `student_skills.levelScore`, ưu tiên kỹ năng `verified`.
- Không hardcode `92`, `64h`, `12`, nội dung AI hoặc hoạt động trong database mode.
- Không thay schema, không thêm dependency, không ghi đè thay đổi credential cards và các thay đổi không liên quan đang có trong worktree.
- Worktree hiện có thay đổi của người dùng trong `index.php` và `learner.css`; không `git add`/`git commit` các file triển khai dùng chung trong kế hoạch này để tránh cuốn thay đổi của người dùng vào commit.

---

## File Structure

- `app/learner/includes/student-data.php`: tạo view model ba KPI và danh sách kỹ năng từ Talent Passport/database.
- `app/learner/index.php`: render KPI, hồ sơ kỹ năng, AI tóm tắt và hoạt động sắp diễn ra; giữ nguyên header/hero.
- `assets/css/learner.css`: thêm CSS scoped cho phần dashboard mới và responsive; không sửa style hero/header.
- `tests/learner_journey_dashboard_ui_test.ps1`: cập nhật contract test theo giao diện và nguồn dữ liệu mới.
- `docs/superpowers/specs/2026-08-28-learner-progress-dashboard-redesign.md`: đặc tả đã duyệt, chỉ sửa khi phát hiện sai khác schema.

---

### Task 1: Khóa contract dữ liệu database và KPI

**Files:**
- Modify: `tests/learner_journey_dashboard_ui_test.ps1:1-70`
- Modify: `app/learner/includes/student-data.php:143-208`
- Modify: `app/learner/index.php:9-63`

**Interfaces:**
- Consumes: `$tp['skills']`, `$tp['experience']['confirmed_hours']`, `$badgeOverview['badges']` từ database repositories.
- Produces: `$dashboardKpis: list<array{id:string,label:string,value:string,icon:string,tone:string}>`, `$skills: list<array{name:string,score:int,level:string,tone:string,verified:bool}>`, `$dashboardKpiMap: array<string,array<string,mixed>>`.

- [ ] **Step 1: Viết contract test thất bại cho ba KPI và nguồn điểm thật**

Thay các assertion cũ cấm `Điểm năng lực` bằng các assertion sau:

```powershell
Assert-Contract ($studentData.Contains("'id' => 'competency'")) 'Dashboard data must expose the competency KPI.'
Assert-Contract ($studentData.Contains("'id' => 'experience'")) 'Dashboard data must expose the verified experience KPI.'
Assert-Contract ($studentData.Contains("'id' => 'badges'")) 'Dashboard data must expose the awarded badge KPI.'
Assert-Contract ($studentData.Contains("`$verifiedSkillScores")) 'Competency score must prefer verified database skill scores.'
Assert-Contract ($studentData.Contains("`$allSkillScores")) 'Competency score must have a database-skill fallback.'
Assert-Contract ($studentData.Contains("'Chưa có dữ liệu'")) 'Missing skills must not become a fabricated competency score.'
Assert-Contract (-not $dashboard.Contains("`$dashboardKpis[2]")) 'Dashboard must not depend on KPI array position.'
```

Xóa assertion cũ:

```powershell
Assert-Contract (-not $dashboard.Contains('Điểm năng lực')) 'Dashboard markup must not expose an unsupported competency KPI.'
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `FAIL: Dashboard data must expose the competency KPI.`

- [ ] **Step 3: Tạo ba KPI từ dữ liệu database trong `student-data.php`**

Trong database branch, thu thập điểm trong vòng lặp dựng `$skills`, sau đó tạo KPI sau `usort`:

```php
$verifiedSkillScores = [];
$allSkillScores = [];
$skills = [];
foreach ($tp['skills'] as $dbSkill) {
    $skillStatus = (string) ($dbSkill['skillStatus'] ?? $dbSkill['skill_status'] ?? 'active');
    $verificationStatus = (string) ($dbSkill['verificationStatus'] ?? $dbSkill['verification_status'] ?? '');
    if ($skillStatus !== 'active' || $verificationStatus === 'rejected') {
        continue;
    }

    $rawScore = (float) ($dbSkill['levelScore'] ?? $dbSkill['level_score'] ?? 0);
    $score = max(0, min(100, (int) round($rawScore)));
    $allSkillScores[] = $score;
    if ($verificationStatus === 'verified') {
        $verifiedSkillScores[] = $score;
    }

    $levelLabel = match (true) {
        $score >= 85 => 'Rất tốt',
        $score >= 70 => 'Tốt',
        $score >= 50 => 'Trung bình',
        default => 'Cơ bản',
    };
    $tone = match ((string) ($dbSkill['category'] ?? '')) {
        'technical' => 'primary',
        'soft' => 'secondary',
        'creative' => 'warning',
        default => 'success',
    };
    $skills[] = [
        'name' => (string) ($dbSkill['name'] ?? ''),
        'short_name' => (string) ($dbSkill['code'] ?? $dbSkill['name'] ?? ''),
        'score' => $score,
        'level' => $levelLabel,
        'tone' => $tone,
        'verified' => $verificationStatus === 'verified',
    ];
}
usort($skills, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

$competencyScores = $verifiedSkillScores !== [] ? $verifiedSkillScores : $allSkillScores;
$competencyScore = $competencyScores === []
    ? null
    : (int) round(array_sum($competencyScores) / count($competencyScores));
$competencyValue = $competencyScore === null ? 'Chưa có dữ liệu' : $competencyScore . '/100';

$dashboardKpis = [
    ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => $competencyValue, 'icon' => 'star', 'tone' => 'primary'],
    ['id' => 'experience', 'label' => 'Giờ trải nghiệm', 'value' => $hoursValue, 'icon' => 'clock', 'tone' => 'secondary'],
    ['id' => 'badges', 'label' => 'Huy hiệu đạt được', 'value' => (string) $awardedBadgeCount, 'icon' => 'trophy', 'tone' => 'success'],
];
```

Mock branch chỉ dùng cho `APP_ENV=test && TALENTHUB_LEARNER_SOURCE=mock` và cũng trả cùng shape `id/label/value/icon/tone`; production branch không tham chiếu giá trị mock.

- [ ] **Step 4: Loại bỏ phụ thuộc vị trí KPI trong `index.php`**

Thay truy cập `$dashboardKpis[2]` bằng map theo id:

```php
$dashboardKpiMap = [];
foreach ($dashboardKpis as $dashboardKpi) {
    $dashboardKpiId = (string) ($dashboardKpi['id'] ?? '');
    if ($dashboardKpiId !== '') {
        $dashboardKpiMap[$dashboardKpiId] = $dashboardKpi;
    }
}
$dashboardExperienceValue = trim((string) ($dashboardKpiMap['experience']['value'] ?? '0h'));
```

- [ ] **Step 5: Chạy contract test**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: test đi qua nhóm assertion KPI mới; các assertion markup cũ có thể tiếp tục fail cho tới Task 2.

- [ ] **Step 6: Kiểm tra diff task dữ liệu**

```powershell
git diff -- app/learner/includes/student-data.php app/learner/index.php tests/learner_journey_dashboard_ui_test.ps1
```

Expected: chỉ có contract KPI, công thức điểm từ kỹ năng database và map KPI; chưa stage hoặc commit file đang có thay đổi của người dùng.

---

### Task 2: Render hồ sơ kỹ năng và AI tóm tắt

**Files:**
- Modify: `tests/learner_journey_dashboard_ui_test.ps1:20-80`
- Modify: `app/learner/index.php:60-228`

**Interfaces:**
- Consumes: `$dashboardKpis`, `$dashboardSkills`, `$aiCapabilityProfile`, `$schoolCredentialData` từ Task 1 và database services.
- Produces: semantic hooks `data-dashboard-kpis`, `data-dashboard-skills`, `data-dashboard-ai-summary`, `data-ai-strengths`, `data-ai-improvements`, `data-ai-trend`.

- [ ] **Step 1: Viết contract test thất bại cho markup mới**

Thêm:

```powershell
foreach ($hook in @(
    'data-dashboard-kpis',
    'data-dashboard-skills',
    'data-dashboard-ai-summary',
    'data-ai-strengths',
    'data-ai-improvements'
)) {
    Assert-Contract ($dashboard.Contains($hook)) "Dashboard must expose semantic hook '$hook'."
}
Assert-Contract ($dashboard.Contains('/100')) 'Skill scores must be rendered on the 100-point scale.'
Assert-Contract ($dashboard.Contains("`$dashboardAiStrengths")) 'AI strengths must come from the capability profile.'
Assert-Contract ($dashboard.Contains("`$dashboardAiImprovements")) 'AI improvements must come from the capability profile.'
Assert-Contract (-not $dashboard.Contains('Năng khiếu nổi bật: IoT &amp; Drone')) 'Database dashboard must not contain the old fabricated AI copy.'
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `FAIL: Dashboard must expose semantic hook 'data-dashboard-kpis'.`

- [ ] **Step 3: Chuẩn hóa record AI từ database**

Ngay sau các biến `$dashboardAiStrengths` và `$dashboardAiImprovements`, thêm closure an toàn:

```php
$dashboardAiText = static function (mixed $item): string {
    if (is_string($item)) return trim($item);
    if (!is_array($item)) return '';
    foreach (['text', 'label', 'title'] as $field) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
};
$dashboardAiStrengthLabels = array_values(array_filter(array_map($dashboardAiText, array_slice($dashboardAiStrengths, 0, 2))));
$dashboardAiImprovementLabels = array_values(array_filter(array_map($dashboardAiText, array_slice($dashboardAiImprovements, 0, 2))));
$dashboardAiTrendSignals = is_array($aiCapabilityProfile['trend_signals'] ?? null) ? $aiCapabilityProfile['trend_signals'] : [];
$dashboardAiTrendLabel = $dashboardAiText($dashboardAiTrendSignals[0] ?? null);
```

- [ ] **Step 4: Thay KPI và grid kỹ năng/AI bên dưới hero**

Giữ nguyên toàn bộ `<section class="learner-welcome" ...>`; thay khối từ `<section class="learner-kpi-grid"` đến hết `.learner-dashboard-grid` bằng cấu trúc:

```php
<section class="learner-progress-kpis" aria-label="Chỉ số tiến bộ" data-dashboard-kpis>
    <?php foreach ($dashboardKpis as $kpi): ?>
        <article class="learner-card learner-progress-kpi learner-progress-kpi--<?= learner_escape($kpi['tone'] ?? 'primary'); ?>">
            <span class="learner-progress-kpi__icon"><?= learner_icon((string) $kpi['icon'], 22); ?></span>
            <div>
                <p><?= learner_escape($kpi['label']); ?></p>
                <strong><?= learner_escape($kpi['value']); ?></strong>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<div class="learner-progress-dashboard-grid">
    <section class="learner-card learner-progress-skills" aria-labelledby="skills-title" data-dashboard-skills>
        <div class="learner-section-heading learner-section-heading--stacked-copy">
            <div><h2 id="skills-title">Hồ sơ kỹ năng</h2><p>Theo dõi mức độ thành thạo của bạn</p></div>
            <a href="profile.php">Xem tất cả</a>
        </div>
        <?php if ($dashboardSkills === []): ?>
            <div class="learner-progress-empty">
                <?= learner_icon('sparkles', 22); ?>
                <div><strong>Chưa có dữ liệu kỹ năng</strong><p>Hoàn thành bài đánh giá để bắt đầu xây dựng hồ sơ.</p></div>
                <a href="discover.php">Bắt đầu đánh giá</a>
            </div>
        <?php else: ?>
            <div class="learner-progress-skill-list">
                <?php foreach ($dashboardSkills as $skill): ?>
                    <?php $score = max(0, min(100, (int) ($skill['score'] ?? 0))); ?>
                    <div class="learner-progress-skill">
                        <div class="learner-progress-skill__heading">
                            <strong><?= learner_escape($skill['short_name'] ?? $skill['name']); ?></strong>
                            <span><?= learner_escape($skill['level']); ?></span>
                            <b><?= $score; ?>/100</b>
                            <?php if ($skill['verified'] ?? false): ?><i title="Đã xác thực"><?= learner_icon('check', 12); ?></i><?php endif; ?>
                        </div>
                        <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($skill['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $score; ?>">
                            <span class="learner-progress--<?= learner_escape($skill['tone']); ?>" style="--learner-progress: <?= $score; ?>%;"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="learner-card learner-progress-ai" aria-labelledby="ai-title" data-dashboard-ai-summary>
        <div class="learner-progress-ai__title"><?= learner_icon('sparkles', 20); ?><h2 id="ai-title">AI tóm tắt tiến độ</h2></div>
        <?php if (is_array($aiCapabilityProfile)): ?>
            <p><?= learner_escape($dashboardAiTrendLabel !== '' ? $dashboardAiTrendLabel : 'Hồ sơ AI đã cập nhật từ dữ liệu năng lực mới nhất.'); ?></p>
            <section data-ai-strengths><strong>Điểm mạnh</strong><?php foreach ($dashboardAiStrengthLabels as $label): ?><span><?= learner_escape($label); ?></span><?php endforeach; ?></section>
            <section data-ai-improvements><strong>Nên cải thiện</strong><?php foreach ($dashboardAiImprovementLabels as $label): ?><span><?= learner_escape($label); ?></span><?php endforeach; ?></section>
            <?php if ($dashboardAiTrendLabel !== ''): ?><div class="learner-progress-ai__trend" data-ai-trend><?= learner_icon('chart', 16); ?><?= learner_escape($dashboardAiTrendLabel); ?></div><?php endif; ?>
            <a href="ai-recommendations.php">Xem phân tích đầy đủ <?= learner_icon('arrow-right', 15); ?></a>
        <?php elseif ($dashboardAssessmentUnavailable): ?>
            <p>Hệ thống chưa thể đọc trạng thái bài đánh giá. Tiến độ của bạn không bị thay đổi; vui lòng thử lại sau.</p>
            <a href="discover.php">Xem các bài đánh giá <?= learner_icon('arrow-right', 15); ?></a>
        <?php elseif ($dashboardAnalysisCompleted): ?>
            <p>Phân tích đã hoàn thành nhưng hồ sơ AI tạm thời chưa tải được.</p>
            <a href="ai-recommendations.php">Tải lại phân tích <?= learner_icon('arrow-right', 15); ?></a>
        <?php elseif ($dashboardAnalysisReady): ?>
            <p>Bốn bài đánh giá đã hoàn thành. Hãy tạo lộ trình để xem gợi ý cá nhân hóa.</p>
            <a href="ai-recommendations.php">Tạo lộ trình AI <?= learner_icon('arrow-right', 15); ?></a>
        <?php else: ?>
            <p>Đã hoàn thành <?= learner_escape($dashboardAssessmentCompleted); ?>/<?= learner_escape($dashboardAssessmentRequired); ?> bài đánh giá. Hoàn thành bộ bài để mở khóa gợi ý AI.</p>
            <a href="discover.php">Tiếp tục đánh giá <?= learner_icon('arrow-right', 15); ?></a>
        <?php endif; ?>
    </aside>
</div>
```

Trong nhánh không có AI profile, tái sử dụng nguyên điều kiện `$dashboardAssessmentUnavailable`, `analysis_completed`, `ready` và số bài đã hoàn thành; không thêm câu nhận định năng lực.

- [ ] **Step 5: Chạy test**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: nhóm assertion KPI/kỹ năng/AI PASS; assertion activity mới ở Task 3 chưa được thêm.

- [ ] **Step 6: Kiểm tra diff kỹ năng và AI**

```powershell
git diff -- app/learner/index.php tests/learner_journey_dashboard_ui_test.ps1
```

Expected: hero không đổi; chỉ markup dưới hero và contract test liên quan kỹ năng/AI thay đổi.

---

### Task 3: Gộp hoạt động sắp diễn ra từ database chính

**Files:**
- Modify: `tests/learner_journey_dashboard_ui_test.ps1:20-100`
- Modify: `app/learner/index.php:10-44,228-335`

**Interfaces:**
- Consumes: `learner_activity_catalog()` → `DatabaseActivityRepository::discoverForStudent()` trong database mode.
- Produces: `$dashboardUpcomingActivities: list<array<string,mixed>>`, semantic hook `data-dashboard-upcoming-activities`.

- [ ] **Step 1: Viết contract test thất bại cho một khối hoạt động**

```powershell
Assert-Contract ($dashboard.Contains('data-dashboard-upcoming-activities')) 'Dashboard must expose one upcoming-activities section.'
Assert-Contract ($dashboard.Contains('Hoạt động sắp diễn ra')) 'Dashboard must use the approved upcoming activity heading.'
Assert-Contract ($dashboard.Contains('activity-detail.php?id=')) 'Activity items must link to the real detail route.'
Assert-Contract ($dashboard.Contains('learner_activity_catalog()')) 'Upcoming activities must come from the learner activity repository.'
Assert-Contract (-not $dashboard.Contains('Hoạt động đã xác nhận')) 'Confirmed activity history must not occupy a second dashboard card.'
Assert-Contract (-not $dashboard.Contains('learner-activity-card__cover')) 'Compact dashboard activity items must not render large cover images.'
$activityPosition = $dashboard.IndexOf('data-dashboard-upcoming-activities')
$credentialPosition = $dashboard.IndexOf('id="dashboard-school-credential-title"')
Assert-Contract ($activityPosition -ge 0 -and $credentialPosition -gt $activityPosition) 'Upcoming activities must appear before the secondary credential section.'
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `FAIL: Dashboard must expose one upcoming-activities section.`

- [ ] **Step 3: Chuẩn hóa tối đa ba hoạt động database**

Ở đầu `index.php`, thay `$dashboardOpenActivities` và `$dashboardConfirmedActivities` bằng:

```php
$dashboardUpcomingActivities = array_slice(learner_activity_catalog(), 0, 3);
$dashboardActivityDateTime = static function (mixed $value): array {
    try {
        $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        return ['date' => $date->format('d/m'), 'time' => $date->format('H:i')];
    } catch (Throwable) {
        return ['date' => '--/--', 'time' => 'Chưa cập nhật'];
    }
};
```

Không sử dụng `$activities` cho khối dashboard này trong database mode.

- [ ] **Step 4: Thay hai section activity bằng một card gọn và chuyển credential xuống dưới**

Đặt card mới ngay sau `.learner-progress-dashboard-grid`. Di chuyển nguyên vẹn section `.learner-school-credential-section` hiện có xuống ngay sau card hoạt động; không sửa include, dữ liệu hoặc markup bên trong credential section.

```php
<section class="learner-card learner-progress-activities" aria-labelledby="activities-title" data-dashboard-upcoming-activities>
    <div class="learner-section-heading">
        <h2 id="activities-title">Hoạt động sắp diễn ra</h2>
        <a href="activities.php">Tất cả hoạt động</a>
    </div>
    <?php if ($dashboardUpcomingActivities === []): ?>
        <div class="learner-progress-empty">
            <?= learner_icon('calendar', 22); ?>
            <div><strong>Chưa có hoạt động sắp diễn ra</strong><p>Khám phá danh sách hoạt động phù hợp với bạn.</p></div>
            <a href="activities.php">Khám phá hoạt động</a>
        </div>
    <?php else: ?>
        <div class="learner-progress-activity-list">
            <?php foreach ($dashboardUpcomingActivities as $activity): ?>
                <?php
                $activityId = (string) ($activity['route_id'] ?? $activity['id'] ?? '');
                $activityWhen = $dashboardActivityDateTime($activity['start_at'] ?? null);
                $activityLocation = trim((string) ($activity['location'] ?? '')) ?: 'Chưa cập nhật';
                ?>
                <article class="learner-progress-activity">
                    <time datetime="<?= learner_escape($activity['start_at'] ?? ''); ?>"><strong><?= learner_escape($activityWhen['date']); ?></strong><span><?= learner_escape($activityWhen['time']); ?></span></time>
                    <div><h3><?= learner_escape($activity['title'] ?? 'Hoạt động TalentHub'); ?></h3><p><?= learner_icon('map-pin', 14); ?><?= learner_escape($activityLocation); ?></p><a href="activity-detail.php?id=<?= rawurlencode($activityId); ?>">Xem chi tiết <?= learner_icon('arrow-right', 14); ?></a></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
```

- [ ] **Step 5: Chạy contract test**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `PASS: learner journey dashboard UI contract`.

- [ ] **Step 6: Kiểm tra diff hoạt động**

```powershell
git diff -- app/learner/index.php tests/learner_journey_dashboard_ui_test.ps1
```

Expected: chỉ còn một khối hoạt động, hoạt động đứng trước credential section, phần credential được di chuyển nguyên vẹn.

---

### Task 4: Áp dụng CSS theo mockup mà không đổi hero/header

**Files:**
- Modify: `tests/learner_journey_dashboard_ui_test.ps1:70-110`
- Modify: `assets/css/learner.css:9606-end`

**Interfaces:**
- Consumes: class names từ Tasks 2–3.
- Produces: layout desktop/tablet/mobile cho `learner-progress-*` trong scope `.learner-page-overview`.

- [ ] **Step 1: Viết contract test CSS thất bại**

```powershell
foreach ($selector in @(
    '.learner-page-overview .learner-progress-kpis',
    '.learner-page-overview .learner-progress-dashboard-grid',
    '.learner-page-overview .learner-progress-skill',
    '.learner-page-overview .learner-progress-ai',
    '.learner-page-overview .learner-progress-activity-list',
    '.learner-page-overview .learner-progress-empty'
)) {
    Assert-Contract ($stylesheet.Contains($selector)) "Stylesheet must define '$selector'."
}
Assert-Contract ($stylesheet.Contains('@media (max-width: 1100px)')) 'Dashboard must keep its tablet breakpoint.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 720px)')) 'Dashboard must keep its mobile breakpoint.'
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `FAIL: Stylesheet must define '.learner-page-overview .learner-progress-kpis'.`

- [ ] **Step 3: Thêm CSS scoped ở cuối `learner.css`**

Thêm block mới, không sửa selector `.learner-welcome`, `.learner-header` hoặc `.learner-sidebar`:

```css
/* Learner progress dashboard: approved 2026-08-28 mockup ---------------- */
.learner-page-overview .learner-progress-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}

.learner-page-overview .learner-progress-kpi {
    display: flex;
    min-height: 104px;
    padding: 18px 20px;
    align-items: center;
    gap: 15px;
    box-shadow: var(--shadow-sm);
}

.learner-page-overview .learner-progress-kpi__icon {
    display: grid;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    color: var(--primary);
    background: var(--primary-light);
    border-radius: var(--radius-sm);
    place-items: center;
}

.learner-page-overview .learner-progress-kpi--secondary .learner-progress-kpi__icon { color: var(--secondary); background: var(--secondary-light); }
.learner-page-overview .learner-progress-kpi--success .learner-progress-kpi__icon { color: var(--accent); background: var(--accent-light); }
.learner-page-overview .learner-progress-kpi p { color: var(--text-secondary); font-size: .8rem; }
.learner-page-overview .learner-progress-kpi strong { display: block; margin-top: 2px; color: var(--text-primary); font-size: 1.65rem; line-height: 1.2; }

.learner-page-overview .learner-progress-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.62fr) minmax(340px, 1fr);
    gap: 16px;
    margin-top: 16px;
}

.learner-page-overview .learner-progress-skills,
.learner-page-overview .learner-progress-ai,
.learner-page-overview .learner-progress-activities {
    padding: 22px;
    box-shadow: var(--shadow-sm);
}

.learner-page-overview .learner-section-heading--stacked-copy > div p { margin-top: 3px; color: var(--text-secondary); font-size: .78rem; }
.learner-page-overview .learner-progress-skill-list { display: grid; gap: 14px; }
.learner-page-overview .learner-progress-skill__heading { display: grid; grid-template-columns: minmax(150px, 1fr) 88px 66px 18px; align-items: center; gap: 10px; font-size: .78rem; }
.learner-page-overview .learner-progress-skill__heading > strong { color: var(--text-primary); font-size: .84rem; }
.learner-page-overview .learner-progress-skill__heading > span { color: var(--text-secondary); }
.learner-page-overview .learner-progress-skill__heading > b { color: var(--text-primary); font-size: .9rem; text-align: right; }
.learner-page-overview .learner-progress-skill__heading > i { display: grid; width: 18px; height: 18px; color: var(--accent); border: 1px solid currentColor; border-radius: 50%; place-items: center; }
.learner-page-overview .learner-progress-skill .learner-progress { height: 8px; margin-top: 7px; background: var(--border); }
.learner-page-overview .learner-progress > .learner-progress--success { background: var(--success); }
.learner-page-overview .learner-progress > .learner-progress--warning { background: var(--warning); }

.learner-page-overview .learner-progress-ai { border-top: 2px solid color-mix(in srgb, var(--secondary) 55%, var(--border)); }
.learner-page-overview .learner-progress-ai__title { display: flex; align-items: center; gap: 8px; color: var(--secondary); }
.learner-page-overview .learner-progress-ai__title h2 { color: var(--text-primary); font-size: 1.12rem; }
.learner-page-overview .learner-progress-ai > p { margin-top: 9px; color: var(--text-secondary); font-size: .82rem; }
.learner-page-overview .learner-progress-ai section { margin-top: 18px; }
.learner-page-overview .learner-progress-ai section strong { display: block; margin-bottom: 8px; color: var(--text-primary); font-size: .8rem; }
.learner-page-overview .learner-progress-ai section span { display: inline-flex; margin: 0 6px 6px 0; padding: 6px 10px; color: var(--accent); background: var(--accent-light); border-radius: var(--radius-sm); font-size: .72rem; }
.learner-page-overview .learner-progress-ai [data-ai-improvements] span { color: var(--primary-hover); background: var(--primary-light); }
.learner-page-overview .learner-progress-ai__trend { display: flex; margin-top: 16px; padding: 10px 12px; align-items: center; gap: 7px; color: var(--secondary); background: var(--secondary-light); border-radius: var(--radius-sm); font-size: .74rem; }
.learner-page-overview .learner-progress-ai > a { display: inline-flex; margin-top: 14px; align-items: center; gap: 5px; color: var(--primary); font-size: .78rem; font-weight: 700; }

.learner-page-overview .learner-progress-activities { margin-top: 16px; }
.learner-page-overview .learner-progress-activity-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.learner-page-overview .learner-progress-activity { display: grid; min-width: 0; padding: 8px 18px; grid-template-columns: 56px minmax(0, 1fr); gap: 12px; border-right: 1px solid var(--border); }
.learner-page-overview .learner-progress-activity:last-child { border-right: 0; }
.learner-page-overview .learner-progress-activity time { display: flex; align-items: center; flex-direction: column; color: var(--primary); }
.learner-page-overview .learner-progress-activity time strong { font-size: 1.2rem; }
.learner-page-overview .learner-progress-activity time span { color: var(--text-secondary); font-size: .68rem; }
.learner-page-overview .learner-progress-activity h3 { overflow: hidden; font-size: .82rem; text-overflow: ellipsis; white-space: nowrap; }
.learner-page-overview .learner-progress-activity p,
.learner-page-overview .learner-progress-activity a { display: flex; margin-top: 5px; align-items: center; gap: 5px; font-size: .7rem; }
.learner-page-overview .learner-progress-activity p { color: var(--text-secondary); }
.learner-page-overview .learner-progress-activity a { color: var(--primary); font-weight: 700; }

.learner-page-overview .learner-progress-empty { display: flex; min-height: 92px; padding: 16px; align-items: center; gap: 12px; color: var(--secondary); background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-sm); }
.learner-page-overview .learner-progress-empty div { min-width: 0; flex: 1; }
.learner-page-overview .learner-progress-empty strong { color: var(--text-primary); font-size: .84rem; }
.learner-page-overview .learner-progress-empty p { margin-top: 2px; color: var(--text-secondary); font-size: .74rem; }
.learner-page-overview .learner-progress-empty a { color: var(--primary); font-size: .75rem; font-weight: 700; }
```

- [ ] **Step 4: Thêm responsive**

```css
@media (max-width: 1100px) {
    .learner-page-overview .learner-progress-dashboard-grid { grid-template-columns: 1fr; }
    .learner-page-overview .learner-progress-activity-list { grid-template-columns: 1fr; }
    .learner-page-overview .learner-progress-activity { border-right: 0; border-bottom: 1px solid var(--border); }
    .learner-page-overview .learner-progress-activity:last-child { border-bottom: 0; }
}

@media (max-width: 720px) {
    .learner-page-overview .learner-progress-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .learner-page-overview .learner-progress-kpi:last-child { grid-column: 1 / -1; }
    .learner-page-overview .learner-progress-skill__heading { grid-template-columns: minmax(0, 1fr) 64px 18px; }
    .learner-page-overview .learner-progress-skill__heading > span { grid-column: 1; grid-row: 2; }
    .learner-page-overview .learner-progress-skill__heading > b { grid-column: 2; grid-row: 1; }
    .learner-page-overview .learner-progress-skill__heading > i { grid-column: 3; grid-row: 1; }
}

@media (max-width: 480px) {
    .learner-page-overview .learner-progress-kpis { grid-template-columns: 1fr; }
    .learner-page-overview .learner-progress-kpi:last-child { grid-column: auto; }
    .learner-page-overview .learner-progress-skills,
    .learner-page-overview .learner-progress-ai,
    .learner-page-overview .learner-progress-activities { padding: 18px 16px; }
    .learner-page-overview .learner-progress-empty { align-items: flex-start; flex-direction: column; }
}
```

- [ ] **Step 5: Chạy contract test**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `PASS: learner journey dashboard UI contract`.

- [ ] **Step 6: Kiểm tra diff CSS**

```powershell
git diff -- assets/css/learner.css tests/learner_journey_dashboard_ui_test.ps1
```

Expected: CSS mới chỉ dùng selector scoped `.learner-page-overview .learner-progress-*`; không stage hoặc commit block credential cards có sẵn của người dùng.

---

### Task 5: Regression verification và visual QA

**Files:**
- Verify: `app/learner/index.php`
- Verify: `app/learner/includes/student-data.php`
- Verify: `assets/css/learner.css`
- Verify: `tests/learner_journey_dashboard_ui_test.ps1`

**Interfaces:**
- Consumes: toàn bộ deliverables Tasks 1–4.
- Produces: bằng chứng test và kiểm tra phạm vi thay đổi.

- [ ] **Step 1: Chạy dashboard contract test**

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_journey_dashboard_ui_test.ps1
```

Expected: `PASS: learner journey dashboard UI contract`.

- [ ] **Step 2: Chạy credential UI test để bảo đảm không phá thay đổi đang có**

```powershell
powershell -ExecutionPolicy Bypass -File tests/learner_credential_cards_ui_test.ps1
```

Expected: dòng `PASS` của credential cards test.

- [ ] **Step 3: Chạy PHP syntax check nếu PHP runtime khả dụng**

```powershell
$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    php -l app/learner/includes/student-data.php
    php -l app/learner/index.php
} else {
    Write-Output 'SKIP: PHP CLI is not installed on this host.'
}
```

Expected: `No syntax errors detected` cho hai file, hoặc skip rõ ràng nếu host không có PHP.

- [ ] **Step 4: Kiểm tra không chạm header/hero và không lẫn mock data**

```powershell
$dashboard = Get-Content -Raw 'app/learner/index.php'
$heroStart = $dashboard.IndexOf('<section class="learner-welcome"')
$heroEnd = $dashboard.IndexOf('</section>', $heroStart) + '</section>'.Length
$heroMarkup = $dashboard.Substring($heroStart, $heroEnd - $heroStart)
if (-not $heroMarkup.Contains('learner-journey-hero-v3.png')) { throw 'Hero asset changed or missing.' }
if (-not $heroMarkup.Contains('Chào mừng trở lại')) { throw 'Hero content changed or missing.' }
if ($dashboard.Contains('Năng khiếu nổi bật: IoT &amp; Drone')) { throw 'Fabricated demo AI copy remains in dashboard.' }
Write-Output 'PASS: hero contract and database-only AI copy'
```

Expected: `PASS: hero contract and database-only AI copy`. Việc giữ nguyên tuyệt đối markup hero được kiểm soát thêm bằng review diff thủ công vì file đã có thay đổi cache-busting từ trước.

- [ ] **Step 5: Kiểm tra phạm vi diff và whitespace**

```powershell
git diff --check
git status --short
git diff --stat
```

Expected: không có whitespace error; chỉ các file dashboard/test theo kế hoạch thay đổi ngoài các thay đổi người dùng đã có từ trước.

- [ ] **Step 6: Visual QA**

Khi local PHP web runtime khả dụng, kiểm tra dashboard bằng tài khoản học sinh database ở bốn viewport:

```text
1440 × 1000
1024 × 900
768 × 1024
390 × 844
```

Xác nhận: header/hero không đổi; ba KPI dùng dữ liệu thật; bốn kỹ năng tối đa; AI không bịa nội dung; ba hoạt động tối đa; không tràn ngang; empty state không cao quá 110px.

- [ ] **Step 7: Ghi nhận kết quả và bàn giao diff**

Không tự động stage/commit vì các file triển khai đang chứa thay đổi có trước của người dùng. Ghi rõ test nào pass, test nào skip và liệt kê các file dashboard đã sửa:

```powershell
git status --short
git diff --stat -- app/learner/includes/student-data.php app/learner/index.php assets/css/learner.css tests/learner_journey_dashboard_ui_test.ps1
```
