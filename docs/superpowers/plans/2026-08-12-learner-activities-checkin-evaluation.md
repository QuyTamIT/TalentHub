# Learner Activities, Check-in, and Evaluation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Activities, QR Check-in, and Competency Evaluation pages to the existing `app/learner` portal without changing any other TalentHub role area.

**Architecture:** Extend the existing Learner shell and mock-data provider. Each new PHP page includes the shared sidebar and header, all styles remain in `assets/css/learner.css`, and all behavior remains in `assets/js/learner.js`; pure JavaScript helpers are exported through `LearnerUI` for Node tests before page-specific DOM initialization.

**Tech Stack:** PHP 8.3, semantic HTML5, CSS custom properties and responsive media queries, vanilla JavaScript, zero-dependency PHP assertions, Node test runner, Playwright browser smoke tests.

## Global Constraints

- Use the confirmed `app/learner` routes, not `app/student`.
- Do not install a frontend framework or package.
- Do not modify `app/enterprise/**`, `assets/css/enterprise.css`, `assets/js/enterprise.js`, Home, Teacher, School, or database code.
- Reuse `app/learner/includes/header.php`, `sidebar.php`, `student-data.php`, `assets/css/learner.css`, and `assets/js/learner.js`.
- Keep every new selector, ID, data attribute, and JavaScript behavior scoped to Learner.
- Use only the established orange, blue, green, neutral design tokens; no pink-purple gradient.
- Escape dynamic PHP output with `learner_escape()` and serialize JSON with safe hex flags.
- Preserve all existing overview, profile, discovery, mobile drawer, modal, toast, and accessibility behavior.
- Follow red-green-refactor for each behavior and commit only exact task files; never stage the untracked `design/` directory.

## File Map

- Create `app/learner/activities.php`: activity catalog, filters, results, empty state, confirmation modal.
- Create `app/learner/checkin.php`: QR sample, demo scanner modal, check-in history.
- Create `app/learner/evaluation.php`: semester selector, criteria, total score, feedback, empty state.
- Modify `app/learner/includes/student-data.php`: routes and the three mock datasets.
- Modify `app/learner/includes/header.php`: page-configurable search label and placeholder without duplicating the header.
- Modify `app/learner/index.php`: make Activities links real routes.
- Modify `assets/js/learner.js`: pure filter/evaluation helpers and page-safe initializers.
- Modify `assets/css/learner.css`: page-scoped layouts and responsive rules.
- Modify `tests/learner_frontend_test.php`: PHP data, route, markup, isolation, and regression assertions.
- Modify `tests/learner_js_test.js`: pure activity matching and evaluation resolution tests.
- Modify `tests/learner_browser_smoke.js`: render all six pages and exercise the new interactions.

---

### Task 1: Register Routes and Mock Data

**Files:**
- Modify: `tests/learner_frontend_test.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/index.php`

**Interfaces:**
- Produces `$activityCatalog`, `$activityCategories`, `$checkinHistory`, `$evaluationTerms`, and `$defaultEvaluationTerm`.
- Produces implemented navigation routes `/app/learner/activities.php`, `/app/learner/checkin.php`, and `/app/learner/evaluation.php`.
- Existing overview consumes the first three records of `$activityCatalog` as `$activities`.

- [ ] **Step 1: Add failing PHP assertions for routes and datasets**

Append assertions that require six activity records, four check-ins, multiple evaluation terms, and exact implemented routes:

```php
$assert(count($activityCatalog ?? []) === 6, 'Activity catalog contains six records');
$assert(count($checkinHistory ?? []) === 4, 'Check-in history contains four records');
$assert(isset($evaluationTerms['2025-2026-2']), 'Default evaluation term exists');
$assert(isset($evaluationTerms['2024-2025-2']) && $evaluationTerms['2024-2025-2']['evaluation'] === null, 'Evaluation data exposes an empty term');

$navByLabel = array_column($learnerNav, null, 'label');
$assert($navByLabel['Hoạt động']['route'] === '/app/learner/activities.php' && $navByLabel['Hoạt động']['implemented'], 'Activities route is implemented');
$assert($navByLabel['Check-in QR']['route'] === '/app/learner/checkin.php' && $navByLabel['Check-in QR']['implemented'], 'Check-in route is implemented');
$assert($navByLabel['Đánh giá']['route'] === '/app/learner/evaluation.php' && $navByLabel['Đánh giá']['implemented'], 'Evaluation route is implemented');
```

- [ ] **Step 2: Run the PHP suite and verify RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_frontend_test.php
```

Expected: FAIL because the new datasets and routes do not exist.

- [ ] **Step 3: Add exact mock-data shapes and route updates**

Update navigation and define records with this schema:

```php
$learnerNav[3] = ['label' => 'Hoạt động', 'route' => '/app/learner/activities.php', 'icon' => 'calendar', 'implemented' => true];
$learnerNav[4] = ['label' => 'Check-in QR', 'route' => '/app/learner/checkin.php', 'icon' => 'qr', 'implemented' => true];
$learnerNav[5] = ['label' => 'Đánh giá', 'route' => '/app/learner/evaluation.php', 'icon' => 'clipboard', 'implemented' => true];

$activityCategories = ['Tất cả', 'Kỹ thuật', 'Kinh doanh', 'Sáng tạo', 'Cộng đồng'];
$activityCatalog = [
    ['id' => 'iot-lab', 'category' => 'Kỹ thuật', 'filter_category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'IoT Lab — Cảm biến thông minh', 'time' => 'Th 6, 14:00', 'location' => 'Phòng B305', 'participants' => 38, 'capacity' => 50],
    ['id' => 'drone-workshop', 'category' => 'Sáng tạo', 'filter_category' => 'Sáng tạo', 'tone' => 'secondary', 'title' => 'Drone Workshop', 'time' => 'CN, 09:00', 'location' => 'Sân vận động', 'participants' => 18, 'capacity' => 20],
    ['id' => 'startup-pitch', 'category' => 'Kinh doanh', 'filter_category' => 'Kinh doanh', 'tone' => 'success', 'title' => 'Startup Club — Pitch Night', 'time' => 'Th 7, 18:30', 'location' => 'Hall A', 'participants' => 12, 'capacity' => 30],
    ['id' => 'ai-bootcamp', 'category' => 'Công nghệ', 'filter_category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'AI Bootcamp', 'time' => 'T2, 09:00', 'location' => 'Phòng IT', 'participants' => 25, 'capacity' => 40],
    ['id' => 'design-thinking', 'category' => 'Sáng tạo', 'filter_category' => 'Sáng tạo', 'tone' => 'secondary', 'title' => 'Design Thinking Lab', 'time' => 'T4, 15:00', 'location' => 'Studio C', 'participants' => 9, 'capacity' => 25],
    ['id' => 'charity-marathon', 'category' => 'Cộng đồng', 'filter_category' => 'Cộng đồng', 'tone' => 'success', 'title' => 'Marathon từ thiện', 'time' => 'CN, 06:00', 'location' => 'Hồ Tây', 'participants' => 67, 'capacity' => 100],
];
$activities = array_slice($activityCatalog, 0, 3);

$checkinHistory = [
    ['activity' => 'IoT Lab', 'time' => 'Hôm nay, 14:02', 'location' => 'Phòng B305', 'hours' => 2, 'confirmed' => true],
    ['activity' => 'Startup Club', 'time' => 'Hôm qua, 18:35', 'location' => 'Hall A', 'hours' => 1.5, 'confirmed' => true],
    ['activity' => 'Drone Workshop', 'time' => '12/06, 09:10', 'location' => 'Sân vận động', 'hours' => 3, 'confirmed' => true],
    ['activity' => 'AI Bootcamp', 'time' => '10/06, 09:05', 'location' => 'Phòng IT', 'hours' => 2, 'confirmed' => true],
];

$defaultEvaluationTerm = '2025-2026-2';
$evaluationTerms = [
    '2025-2026-2' => [
        'label' => 'Học kỳ II · 2025–2026',
        'status' => 'Đã công bố',
        'evaluation' => [
            'criteria' => [
                ['name' => 'Chuyên môn', 'score' => 36, 'max' => 40, 'tone' => 'primary'],
                ['name' => 'Sáng tạo', 'score' => 17, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Kỷ luật', 'score' => 19, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Làm việc nhóm', 'score' => 18, 'max' => 20, 'tone' => 'primary'],
            ],
            'total' => 90,
            'classification' => 'Xuất sắc',
            'ranking' => 'Top 12% học sinh khối 11',
            'comment' => 'A thể hiện khả năng tư duy hệ thống tốt, chủ động dẫn dắt nhóm trong dự án Smart Garden. Cần luyện thêm kỹ năng thuyết trình trước đám đông.',
            'reviewer' => 'Cô Lê Thị Hương, IoT Lab',
        ],
    ],
    '2025-2026-1' => [
        'label' => 'Học kỳ I · 2025–2026',
        'status' => 'Đã công bố',
        'evaluation' => [
            'criteria' => [
                ['name' => 'Chuyên môn', 'score' => 33, 'max' => 40, 'tone' => 'primary'],
                ['name' => 'Sáng tạo', 'score' => 16, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Kỷ luật', 'score' => 18, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Làm việc nhóm', 'score' => 17, 'max' => 20, 'tone' => 'primary'],
            ],
            'total' => 84,
            'classification' => 'Tốt',
            'ranking' => 'Top 20% học sinh khối 11',
            'comment' => 'A có nền tảng chuyên môn tốt và phối hợp nhóm tích cực. Hãy tiếp tục tăng tính chủ động trong phần trình bày.',
            'reviewer' => 'Thầy Trần Minh Anh, CLB Công nghệ',
        ],
    ],
    '2024-2025-2' => ['label' => 'Học kỳ II · 2024–2025', 'status' => 'Chưa có dữ liệu', 'evaluation' => null],
];
```

Remove `data-pending-route` from both Activities links in `app/learner/index.php`.

- [ ] **Step 4: Run the PHP suite and verify GREEN**

Run the same PHP command. Expected: all existing and new assertions pass.

- [ ] **Step 5: Commit the data foundation**

```powershell
git add -- app/learner/includes/student-data.php app/learner/index.php tests/learner_frontend_test.php
git commit -m "feat: add learner activity and evaluation data"
```

---

### Task 2: Build the Activities Page and Pure Filtering Logic

**Files:**
- Create: `app/learner/activities.php`
- Modify: `app/learner/includes/header.php`
- Modify: `assets/js/learner.js`
- Modify: `tests/learner_frontend_test.php`
- Modify: `tests/learner_js_test.js`

**Interfaces:**
- Consumes `$activityCatalog`, `$activityCategories`, shared shell includes, and shared modal controller.
- Produces `LearnerUI.normalizeSearchText(value)` and `LearnerUI.activityMatches(activity, query, category)`.
- Produces DOM markers `[data-activity-card]`, `[data-activity-filter]`, `[data-activity-empty]`, `[data-activity-register]`, and `#learner-registration-modal`.

- [ ] **Step 1: Add failing PHP page assertions**

Add checks for file existence, heading, six cards, five filters, progress semantics, registration modal, and no inline handlers:

```php
$activitiesHtml = $renderLearnerPage('activities.php');
$assert(str_contains($activitiesHtml, 'Khám phá hoạt động'), 'Activities renders heading');
$assert(substr_count($activitiesHtml, 'data-activity-card=') === 6, 'Activities renders six cards');
$assert(substr_count($activitiesHtml, 'data-activity-filter=') === 5, 'Activities renders five filters');
$assert(str_contains($activitiesHtml, 'id="learner-registration-modal"'), 'Activities provides registration modal');
$assert(str_contains($activitiesHtml, 'data-activity-empty'), 'Activities provides empty state');
$assert(!str_contains($activitiesHtml, 'onclick='), 'Activities uses unobtrusive JavaScript');
```

- [ ] **Step 2: Add failing Node tests for accent-insensitive matching**

```javascript
test('activity matching combines accent-insensitive query and category', () => {
    const activity = {
        title: 'IoT Lab — Cảm biến thông minh',
        category: 'Công nghệ',
        filterCategory: 'Kỹ thuật',
        location: 'Phòng B305',
    };

    assert.equal(LearnerUI.activityMatches(activity, 'cam bien', 'Kỹ thuật'), true);
    assert.equal(LearnerUI.activityMatches(activity, 'ho tay', 'Kỹ thuật'), false);
    assert.equal(LearnerUI.activityMatches(activity, '', 'Cộng đồng'), false);
});
```

- [ ] **Step 3: Run both suites and verify RED**

Expected: PHP fails because `activities.php` is absent; Node fails because `activityMatches` is undefined.

- [ ] **Step 4: Implement the pure functions before DOM behavior**

Add before `global.LearnerUI` assignment:

```javascript
function normalizeSearchText(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLocaleLowerCase('vi')
        .trim();
}

function activityMatches(activity, query, category) {
    const normalizedCategory = category || 'Tất cả';
    const categoryMatches = normalizedCategory === 'Tất cả'
        || activity.filterCategory === normalizedCategory;
    const haystack = normalizeSearchText([
        activity.title,
        activity.category,
        activity.filterCategory,
        activity.location,
    ].join(' '));
    return categoryMatches && haystack.includes(normalizeSearchText(query));
}
```

Expose both functions through `LearnerUI`.

- [ ] **Step 5: Make the shared header search configurable**

At the top of `header.php`, set defaults and render escaped variables:

```php
$headerSearchLabel = $headerSearchLabel ?? 'Tìm hoạt động hoặc kỹ năng';
$headerSearchPlaceholder = $headerSearchPlaceholder ?? 'Tìm hoạt động, kỹ năng...';
```

`activities.php` sets both values to `Tìm hoạt động` before including the header.

- [ ] **Step 6: Create semantic Activities markup**

Render each card with escaped data and derived values:

```php
<?php foreach ($activityCatalog as $activity): ?>
    <?php
    $remaining = max(0, $activity['capacity'] - $activity['participants']);
    $occupancy = min(100, (int) round($activity['participants'] / $activity['capacity'] * 100));
    ?>
    <article class="learner-card learner-catalog-card"
        data-activity-card
        data-title="<?= learner_escape($activity['title']); ?>"
        data-category="<?= learner_escape($activity['category']); ?>"
        data-filter-category="<?= learner_escape($activity['filter_category']); ?>"
        data-location="<?= learner_escape($activity['location']); ?>">
        <span class="learner-badge learner-badge--<?= learner_escape($activity['tone']); ?>"><?= learner_escape($activity['category']); ?></span>
        <h2><?= learner_escape($activity['title']); ?></h2>
        <div class="learner-catalog-card__meta">
            <span><?= learner_icon('clock', 18); ?> <?= learner_escape($activity['time']); ?></span>
            <span><?= learner_icon('map-pin', 18); ?> <?= learner_escape($activity['location']); ?></span>
        </div>
        <div class="learner-catalog-card__capacity">
            <span><?= learner_icon('users', 18); ?> <?= learner_escape($activity['participants']); ?>/<?= learner_escape($activity['capacity']); ?></span>
            <strong>Còn <?= learner_escape($remaining); ?> chỗ</strong>
        </div>
        <div class="learner-progress" role="progressbar" aria-label="Sức chứa <?= learner_escape($activity['title']); ?>" aria-valuemin="0" aria-valuemax="<?= learner_escape($activity['capacity']); ?>" aria-valuenow="<?= learner_escape($activity['participants']); ?>">
            <span class="learner-progress--<?= learner_escape($activity['tone']); ?>" style="--learner-progress: <?= learner_escape($occupancy); ?>%;"></span>
        </div>
        <button class="learner-btn learner-btn--primary learner-btn--block" type="button" data-activity-register data-activity-name="<?= learner_escape($activity['title']); ?>">Đăng ký ngay</button>
    </article>
<?php endforeach; ?>
```

Include the shared accessible modal structure with cancel and `[data-confirm-registration]` controls.

- [ ] **Step 7: Wire search, filters, empty state, and confirmation**

Within DOMContentLoaded, add the page-safe initializer:

```javascript
const activityCards = Array.from(document.querySelectorAll('[data-activity-card]'));
const activityFilters = Array.from(document.querySelectorAll('[data-activity-filter]'));
const activityEmpty = document.querySelector('[data-activity-empty]');
const activityResultStatus = document.querySelector('[data-activity-result-status]');
const activitySearch = document.getElementById('learner-search-input');
const registrationModal = document.getElementById('learner-registration-modal');
const registrationName = registrationModal?.querySelector('[data-registration-name]');
const registrationConfirm = registrationModal?.querySelector('[data-confirm-registration]');
let activeActivityCategory = 'Tất cả';
let pendingRegistrationButton = null;

const updateActivityResults = () => {
    let visibleCount = 0;
    activityCards.forEach((card) => {
        const matches = activityMatches({
            title: card.dataset.title,
            category: card.dataset.category,
            filterCategory: card.dataset.filterCategory,
            location: card.dataset.location,
        }, activitySearch?.value || '', activeActivityCategory);
        card.hidden = !matches;
        if (matches) visibleCount += 1;
    });
    if (activityEmpty) activityEmpty.hidden = visibleCount !== 0;
    if (activityResultStatus) activityResultStatus.textContent = `${visibleCount} hoạt động phù hợp`;
};

if (activityCards.length > 0) {
    activitySearch?.addEventListener('input', updateActivityResults);
    activityFilters.forEach((filter) => {
        filter.addEventListener('click', () => {
            activeActivityCategory = filter.dataset.activityFilter || 'Tất cả';
            activityFilters.forEach((item) => item.setAttribute('aria-pressed', String(item === filter)));
            updateActivityResults();
        });
    });
    document.querySelectorAll('[data-activity-register]').forEach((button) => {
        button.addEventListener('click', () => {
            pendingRegistrationButton = button;
            if (registrationName) registrationName.textContent = button.dataset.activityName || 'hoạt động này';
            openModal(registrationModal, button);
        });
    });
    registrationConfirm?.addEventListener('click', () => {
        if (!pendingRegistrationButton) return;
        pendingRegistrationButton.textContent = 'Đã đăng ký';
        pendingRegistrationButton.disabled = true;
        pendingRegistrationButton.classList.add('is-complete');
        pendingRegistrationButton = null;
        closeModal(registrationModal);
        showToast('Đăng ký hoạt động thành công.');
    });
    updateActivityResults();
}
```

- [ ] **Step 8: Run PHP and Node tests and verify GREEN**

Expected: all assertions and Node tests pass, including existing profile and discovery tests.

- [ ] **Step 9: Commit Activities functionality**

```powershell
git add -- app/learner/activities.php app/learner/includes/header.php assets/js/learner.js tests/learner_frontend_test.php tests/learner_js_test.js
git commit -m "feat: build learner activities experience"
```

---

### Task 3: Build QR Check-in and Demo Scanner

**Files:**
- Create: `app/learner/checkin.php`
- Modify: `tests/learner_frontend_test.php`

**Interfaces:**
- Consumes `$checkinHistory`, shared shell, icons, modal controller, and design tokens.
- Produces `#learner-scanner-modal` opened by `data-open-modal`; no new JavaScript API is needed.

- [ ] **Step 1: Add failing Check-in assertions**

```php
$checkinHtml = $renderLearnerPage('checkin.php');
$assert(str_contains($checkinHtml, 'Check-in trải nghiệm'), 'Check-in renders heading');
$assert(str_contains($checkinHtml, 'aria-label="Mã QR mẫu của Nguyễn Văn A"'), 'Check-in QR has an accessible label');
$assert(substr_count($checkinHtml, 'data-checkin-record') === 4, 'Check-in renders four history records');
$assert(str_contains($checkinHtml, 'Đây là giao diện demo'), 'Scanner identifies demo behavior');
$assert(!str_contains($checkinHtml, 'getUserMedia'), 'Check-in never requests camera permission');
```

- [ ] **Step 2: Run PHP test and verify RED**

Expected: FAIL because `checkin.php` does not exist.

- [ ] **Step 3: Create Check-in markup**

Use the standard shell, set `$currentRoute = '/app/learner/checkin.php'`, and render:

```php
<div class="learner-checkin-grid">
    <section class="learner-card learner-qr-card" aria-labelledby="learner-qr-title">
        <svg class="learner-qr-code" viewBox="0 0 210 210" role="img" aria-label="Mã QR mẫu của Nguyễn Văn A">
            <rect width="210" height="210" rx="12" fill="var(--surface)"/>
            <g fill="var(--text-primary)">
                <path d="M20 20h55v55H20zm10 10v35h35V30zm10 10h15v15H40zM135 20h55v55h-55zm10 10v35h35V30zm10 10h15v15h-15zM20 135h55v55H20zm10 10v35h35v-35zm10 10h15v15H40z"/>
                <path d="M90 20h10v10H90zM110 20h10v20h-10zM85 45h20v10H85zM115 50h10v20h-10zM85 70h15v15H85zM105 85h20v10h-20zM130 85h10v20h-10zM150 85h20v10h-20zM180 85h10v15h-10zM80 105h20v10H80zM110 105h10v20h-10zM130 115h20v10h-20zM160 105h10v20h-10zM180 115h10v15h-10zM85 130h15v20H85zM105 135h20v10h-20zM135 140h10v20h-10zM155 135h20v10h-20zM180 150h10v20h-10zM85 165h20v10H85zM115 155h10v25h-10zM140 175h20v15h-20zM170 180h20v10h-20z"/>
            </g>
        </svg>
        <h2 id="learner-qr-title">Mã QR của bạn</h2>
        <p>Đưa cho ban tổ chức quét mã tại địa điểm hoạt động để ghi nhận giờ trải nghiệm.</p>
        <button class="learner-btn learner-btn--primary" type="button" data-open-modal="learner-scanner-modal">Mở camera quét</button>
    </section>
    <section class="learner-card learner-checkin-history" aria-labelledby="learner-checkin-history-title">
        <h2 id="learner-checkin-history-title">Lịch sử check-in</h2>
        <?php foreach ($checkinHistory as $record): ?>
            <article class="learner-checkin-record" data-checkin-record>
                <span class="learner-checkin-record__icon"><?= learner_icon('check', 20); ?></span>
                <div>
                    <h3><?= learner_escape($record['activity']); ?></h3>
                    <p><?= learner_icon('clock', 16); ?> <?= learner_escape($record['time']); ?> <?= learner_icon('map-pin', 16); ?> <?= learner_escape($record['location']); ?></p>
                </div>
                <div class="learner-checkin-record__status">
                    <span class="learner-verified-badge">Đã xác nhận</span>
                    <strong>+<?= learner_escape($record['hours']); ?>h</strong>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
```

Add the scanner modal with explicit text “Đây là giao diện demo, hệ thống không yêu cầu quyền truy cập camera.” and a decorative scan frame marked `aria-hidden="true"`.

- [ ] **Step 4: Run the PHP suite and verify GREEN**

Expected: Check-in assertions and all earlier page assertions pass.

- [ ] **Step 5: Commit Check-in markup**

```powershell
git add -- app/learner/checkin.php tests/learner_frontend_test.php
git commit -m "feat: build learner qr checkin page"
```

---

### Task 4: Build Evaluation Page and Semester Switching

**Files:**
- Create: `app/learner/evaluation.php`
- Modify: `assets/js/learner.js`
- Modify: `tests/learner_frontend_test.php`
- Modify: `tests/learner_js_test.js`

**Interfaces:**
- Consumes `$evaluationTerms` and `$defaultEvaluationTerm`.
- Produces `LearnerUI.getEvaluationTerm(terms, termId)`.
- Produces `#learner-evaluation-term`, `[data-evaluation-content]`, `[data-evaluation-empty]`, `[data-evaluation-status]`, and field-level update markers.

- [ ] **Step 1: Add failing evaluation markup assertions**

```php
$evaluationHtml = $renderLearnerPage('evaluation.php');
$assert(str_contains($evaluationHtml, 'Đánh giá năng lực'), 'Evaluation renders heading');
$assert(str_contains($evaluationHtml, 'id="learner-evaluation-term"'), 'Evaluation renders semester selector');
$assert(substr_count($evaluationHtml, 'data-evaluation-criterion') === 4, 'Default evaluation renders four criteria');
$assert(str_contains($evaluationHtml, 'data-evaluation-total>90<'), 'Evaluation renders total 90');
$assert(str_contains($evaluationHtml, 'data-evaluation-empty'), 'Evaluation provides empty state');
$evaluationSource = file_get_contents($root . '/app/learner/evaluation.php');
$assert(str_contains($evaluationSource, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'), 'Evaluation JSON is serialized safely');
```

- [ ] **Step 2: Add failing Node tests for term resolution**

```javascript
test('evaluation term resolver returns published and empty terms safely', () => {
    const terms = {
        published: { status: 'Đã công bố', evaluation: { total: 90 } },
        empty: { status: 'Chưa có dữ liệu', evaluation: null },
    };
    assert.equal(LearnerUI.getEvaluationTerm(terms, 'published').evaluation.total, 90);
    assert.equal(LearnerUI.getEvaluationTerm(terms, 'empty').evaluation, null);
    assert.equal(LearnerUI.getEvaluationTerm(terms, 'missing'), null);
});
```

- [ ] **Step 3: Run PHP and Node tests and verify RED**

Expected: missing page and missing helper failures.

- [ ] **Step 4: Implement the pure term resolver**

```javascript
function getEvaluationTerm(terms, termId) {
    if (!terms || typeof terms !== 'object') return null;
    return Object.prototype.hasOwnProperty.call(terms, termId) ? terms[termId] : null;
}
```

Expose it through `LearnerUI`.

- [ ] **Step 5: Create evaluation markup and safe JSON payload**

Set the active route, render the default term in PHP, and provide the complete payload:

```php
<script type="application/json" id="learner-evaluation-data"><?=
    json_encode(
        $evaluationTerms,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
?></script>
```

Use an actual `<select>` with every term, render four criterion rows with progress semantics, the score `90/100`, `Xuất sắc`, ranking, feedback, and a hidden live empty panel.

- [ ] **Step 6: Wire semester changes**

Parse only the dedicated JSON script and update with DOM APIs:

```javascript
const evaluationSelect = document.getElementById('learner-evaluation-term');
const evaluationPayload = document.getElementById('learner-evaluation-data');
const evaluationContent = document.querySelector('[data-evaluation-content]');
const evaluationSummary = document.querySelector('[data-evaluation-summary]');
const evaluationEmpty = document.querySelector('[data-evaluation-empty]');
const evaluationCriteria = document.querySelector('[data-evaluation-criteria]');
const evaluationStatus = document.querySelector('[data-evaluation-status]');

if (evaluationSelect && evaluationPayload) {
    const evaluationTerms = JSON.parse(evaluationPayload.textContent || '{}');
    const setText = (selector, value) => {
        const target = document.querySelector(selector);
        if (target) target.textContent = String(value ?? '');
    };
    const renderEvaluation = (termId) => {
        const term = getEvaluationTerm(evaluationTerms, termId);
        const evaluation = term?.evaluation || null;
        if (evaluationStatus) evaluationStatus.textContent = term?.status || 'Chưa có dữ liệu';
        if (evaluationContent) evaluationContent.hidden = !evaluation;
        if (evaluationSummary) evaluationSummary.hidden = !evaluation;
        if (evaluationEmpty) evaluationEmpty.hidden = Boolean(evaluation);
        if (!evaluation || !evaluationCriteria) return;

        evaluationCriteria.replaceChildren(...evaluation.criteria.map((criterion) => {
            const row = document.createElement('article');
            row.className = 'learner-evaluation-criterion';
            row.dataset.evaluationCriterion = '';
            const heading = document.createElement('div');
            const name = document.createElement('span');
            const score = document.createElement('strong');
            name.textContent = criterion.name;
            score.textContent = `${criterion.score}/${criterion.max}`;
            heading.append(name, score);
            const progress = document.createElement('div');
            progress.className = 'learner-progress';
            progress.setAttribute('role', 'progressbar');
            progress.setAttribute('aria-label', criterion.name);
            progress.setAttribute('aria-valuemin', '0');
            progress.setAttribute('aria-valuemax', String(criterion.max));
            progress.setAttribute('aria-valuenow', String(criterion.score));
            const bar = document.createElement('span');
            bar.className = `learner-progress--${criterion.tone}`;
            bar.style.setProperty('--learner-progress', `${criterion.score / criterion.max * 100}%`);
            progress.append(bar);
            row.append(heading, progress);
            return row;
        }));
        setText('[data-evaluation-total]', evaluation.total);
        setText('[data-evaluation-classification]', evaluation.classification);
        setText('[data-evaluation-ranking]', evaluation.ranking);
        setText('[data-evaluation-comment]', evaluation.comment);
        setText('[data-evaluation-reviewer]', evaluation.reviewer);
    };
    evaluationSelect.addEventListener('change', () => renderEvaluation(evaluationSelect.value));
}
```

- [ ] **Step 7: Run PHP and Node tests and verify GREEN**

Expected: all tests pass with the default and empty terms covered.

- [ ] **Step 8: Commit Evaluation functionality**

```powershell
git add -- app/learner/evaluation.php assets/js/learner.js tests/learner_frontend_test.php tests/learner_js_test.js
git commit -m "feat: build learner competency evaluation"
```

---

### Task 5: Add Mockup-Aligned Shared Styles

**Files:**
- Modify: `assets/css/learner.css`
- Modify: `tests/learner_frontend_test.php`

**Interfaces:**
- Consumes all new `learner-` classes from Tasks 2–4.
- Produces desktop, tablet, mobile, disabled, focus, empty, and reduced-motion presentation without global selectors.

- [ ] **Step 1: Add failing CSS scope and responsive assertions**

```php
$learnerCss = file_get_contents($root . '/assets/css/learner.css');
$assert(str_contains($learnerCss, '.learner-activity-catalog'), 'CSS styles activity catalog');
$assert(str_contains($learnerCss, '.learner-checkin-grid'), 'CSS styles check-in layout');
$assert(str_contains($learnerCss, '.learner-evaluation-grid'), 'CSS styles evaluation layout');
$assert(str_contains($learnerCss, '.learner-scanner-frame'), 'CSS styles demo scanner');
$assert(!str_contains($learnerCss, '.ent-'), 'Learner CSS remains isolated from Enterprise');
$assert(!preg_match('/linear-gradient|radial-gradient/i', $learnerCss), 'Learner CSS contains no gradient');
```

- [ ] **Step 2: Run PHP test and verify RED**

Expected: missing selector failures.

- [ ] **Step 3: Add page-scoped desktop styles**

Append sections for:

```css
.learner-activity-toolbar { display: flex; margin: 18px 2px 22px; align-items: flex-end; justify-content: space-between; gap: 20px; }
.learner-filter-list { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 10px; }
.learner-filter-button { min-height: 40px; padding: 8px 18px; color: var(--text-primary); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-full); cursor: pointer; }
.learner-filter-button[aria-pressed='true'] { color: var(--surface); background: var(--primary); }
.learner-activity-catalog { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
.learner-catalog-card { display: flex; min-height: 325px; padding: 20px; flex-direction: column; }
.learner-checkin-grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); gap: 20px; }
.learner-qr-card { padding: 32px; text-align: center; background: var(--primary-light); }
.learner-checkin-record { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; padding: 15px; align-items: center; gap: 14px; border: 1px solid var(--border); border-radius: var(--radius-md); }
.learner-scanner-frame { position: relative; min-height: 260px; border: 2px solid var(--secondary); border-radius: var(--radius-md); overflow: hidden; }
.learner-evaluation-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(300px, .7fr); gap: 20px; }
.learner-evaluation-score { display: grid; min-height: 100%; padding: 40px 28px; text-align: center; place-content: center; }
.learner-empty-state { padding: 48px 24px; color: var(--text-secondary); text-align: center; border: 1px dashed var(--border); border-radius: var(--radius-md); }
```

Every color declaration must use an existing token except already-established token-derived shadows.

- [ ] **Step 4: Add responsive and reduced-motion rules**

At `max-width: 1100px`, use two activity columns and stack layouts when their minimum widths no longer fit. At `max-width: 720px`, use one activity column, stacked toolbar controls, full-width filters where appropriate, stacked metadata, and full-width actions. At `max-width: 480px`, prevent QR/history/score overflow. Disable scanner animation under the existing reduced-motion block.

- [ ] **Step 5: Run PHP suite and verify GREEN**

Expected: all CSS assertions pass and no Enterprise selector or gradient is found.

- [ ] **Step 6: Commit shared styling**

```powershell
git add -- assets/css/learner.css tests/learner_frontend_test.php
git commit -m "feat: style learner activities checkin evaluation"
```

---

### Task 6: Extend Browser Regression Coverage

**Files:**
- Modify: `tests/learner_browser_smoke.js`

**Interfaces:**
- Consumes all six Learner pages through the PHP test server.
- Produces screenshots and interaction assertions at desktop, tablet, and mobile viewport sizes.

- [ ] **Step 1: Add the three pages to the render matrix and verify RED**

Extend the page array:

```javascript
const pages = [
    { name: 'overview', path: '/app/learner/index.php', marker: 'Chào mừng trở lại' },
    { name: 'profile', path: '/app/learner/profile.php', marker: 'Nguyễn Văn A' },
    { name: 'discover', path: '/app/learner/discover.php', marker: 'Khám phá năng khiếu' },
    { name: 'activities', path: '/app/learner/activities.php', marker: 'Khám phá hoạt động' },
    { name: 'checkin', path: '/app/learner/checkin.php', marker: 'Check-in trải nghiệm' },
    { name: 'evaluation', path: '/app/learner/evaluation.php', marker: 'Đánh giá năng lực' },
];
```

Run before all pages exist/styling is complete. Expected: new page or interaction assertions fail.

- [ ] **Step 2: Add exact Activities interaction checks**

Verify `Sáng tạo` shows two cards; accent-free search `cam bien` shows IoT; an impossible search shows the empty state; clearing search restores cards; registration opens the correct dialog; cancelling preserves the button; confirming disables it and changes copy; Escape restores focus.

- [ ] **Step 3: Add exact Check-in interaction checks**

Verify four history records, green confirmation copy, scanner modal opening, demo disclosure visibility, no browser permission prompt, Escape close, and focus restoration.

- [ ] **Step 4: Add exact Evaluation interaction checks**

Verify default `90` and `Xuất sắc`; switching to `2025-2026-1` updates total to `84`; switching to `2024-2025-2` shows the empty state; switching back restores four criterion rows.

- [ ] **Step 5: Run browser smoke at all viewports and verify GREEN**

Start PHP server if necessary:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -S 127.0.0.1:8765 -t 'D:\TalentHub'
```

Run Playwright with the bundled dependency path:

```powershell
$env:NODE_PATH='C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
$env:LEARNER_QA_DIR="$env:TEMP\talenthub-learner-qa"
node tests\learner_browser_smoke.js
```

Expected: all six pages return HTTP 200 at desktop, tablet, and mobile; zero horizontal overflow; zero console errors; all interactions pass; 18 screenshots are created.

- [ ] **Step 6: Commit browser coverage**

```powershell
git add -- tests/learner_browser_smoke.js
git commit -m "test: cover extended learner portal"
```

---

### Task 7: Final Verification and Isolation Audit

**Files:**
- Verify only; modify a Learner file only if a failing test identifies a regression.

**Interfaces:**
- Confirms the complete feature and branch boundaries.

- [ ] **Step 1: Lint every Learner PHP file**

```powershell
Get-ChildItem app\learner -Recurse -Filter *.php | ForEach-Object { & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l $_.FullName }
```

Expected: no syntax errors for all ten Learner PHP files.

- [ ] **Step 2: Run all static and unit checks**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_frontend_test.php
node tests\learner_js_test.js
node --check assets\js\learner.js
node --check tests\learner_browser_smoke.js
git diff --check
```

Expected: every command exits `0` with no warnings other than normal test-runner summaries.

- [ ] **Step 3: Run the complete browser suite again from a clean server process**

Expected: all render, responsive, console, navigation, modal, filter, search, registration, scanner, evaluation, mobile drawer, profile, clipboard, and assessment checks pass.

- [ ] **Step 4: Inspect representative screenshots against mockups**

Inspect Activities desktop/mobile, Check-in desktop/mobile, and Evaluation desktop/mobile. Confirm layout hierarchy, card proportions, orange active navigation, token colors, wrapping, and no clipped content.

- [ ] **Step 5: Prove role isolation**

```powershell
git diff --name-only develop...HEAD -- app/enterprise assets/css/enterprise.css assets/js/enterprise.js
git diff --name-only develop...HEAD -- app/learner assets/css/learner.css assets/js/learner.js tests docs/superpowers
git status --short --branch
```

Expected: the first command prints nothing; the second lists only approved Learner/docs/test files; status contains only the pre-existing untracked `design/` directory.

- [ ] **Step 6: Review commits and hand off the branch**

```powershell
git log --oneline --decorate develop..HEAD
```

Expected: focused design, data, page, style, interaction, and test commits on `feature/student`, ready for push or PR after user approval.
