# Learner Sidebar and Banner Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make learner navigation actions, page introductions, and sidebar branding consistent across the learner portal.

**Architecture:** A new PHP include renders a common page banner from a route-local `$learnerPageBanner` array. The existing learner sidebar owns the role-selection and logout actions, while the header retains only the responsive menu trigger. CSS in the existing learner stylesheet supplies all shared styling.

**Tech Stack:** PHP 8 templates, scoped CSS, Node.js built-in test runner.

## Global Constraints

- Change only learner UI templates, learner icons, learner stylesheet, and a focused static UI regression test.
- Keep `/logout.php` as the logout endpoint; do not alter authentication/session behavior.
- Preserve existing specialized heroes and task-focused detail routes.
- Do not stage or alter the existing learner-data worktree changes.

---

### Task 1: Establish the learner UI regression contract

**Files:**
- Create: `tests/learner_sidebar_banner_ui_test.js`

**Interfaces:**
- Consumes: learner template files as UTF-8 source text.
- Produces: a Node.js test suite run with `node --test tests/learner_sidebar_banner_ui_test.js`.

- [ ] **Step 1: Write the failing test**

```js
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('learner sidebar owns role selection and logout actions', () => {
    const sidebar = read('app/learner/includes/sidebar.php');
    const header = read('app/learner/includes/header.php');

    assert.match(sidebar, /class="learner-sidebar__footer"/);
    assert.match(sidebar, /href="\/role-selection\.php"/);
    assert.match(sidebar, /href="\/logout\.php"/);
    assert.doesNotMatch(header, /learner-role-switch/);
});

test('primary learner pages include the shared page banner', () => {
    [
        'profile.php', 'discover.php', 'activities.php', 'checkin.php',
        'evaluation.php', 'ai-recommendations.php', 'badges.php',
        'statistics.php', 'my-activities.php',
    ].forEach((page) => {
        assert.match(
            read(path.join('app/learner', page)),
            /includes\/page-banner\.php/,
            `${page} must render the shared learner page banner`,
        );
    });
});

test('sidebar wordmark and banner component use shared styling hooks', () => {
    const css = read('assets/css/learner.css');
    assert.match(css, /\.learner-brand__logo\s*\{/);
    assert.match(css, /\.learner-sidebar__footer\s*\{/);
    assert.match(css, /\.learner-page-banner\s*\{/);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/learner_sidebar_banner_ui_test.js`

Expected: FAIL because the sidebar lacks `learner-sidebar__footer` and the listed pages do not include `page-banner.php`.

- [ ] **Step 3: Keep this test unchanged until the production template changes are complete**

The assertions intentionally test rendered-template contracts instead of CSS implementation details. Do not weaken the route list or replace assertions with snapshots.

### Task 2: Create common learner presentation primitives

**Files:**
- Modify: `app/learner/includes/icons.php`
- Create: `app/learner/includes/page-banner.php`
- Modify: `assets/css/learner.css`

**Interfaces:**
- Consumes: `$learnerPageBanner = ['eyebrow' => string, 'title' => string, 'description' => string, 'icon' => string]`.
- Produces: `<section class="learner-page-banner">…</section>` with an ID based on the supplied title and learner SVG icon output.

- [ ] **Step 1: Write the minimal shared banner include**

```php
<?php
if (!isset($learnerPageBanner) || !is_array($learnerPageBanner)) {
    return;
}

$learnerBannerTitle = learner_escape((string) ($learnerPageBanner['title'] ?? 'TalentHub'));
$learnerBannerId = learner_escape((string) ($learnerPageBanner['id'] ?? 'learner-page-banner-title'));
?>
<section class="learner-page-banner" aria-labelledby="<?= $learnerBannerId; ?>">
    <span class="learner-page-banner__icon" aria-hidden="true">
        <?= learner_icon((string) ($learnerPageBanner['icon'] ?? 'sparkles'), 26); ?>
    </span>
    <div>
        <span class="learner-page-banner__eyebrow"><?= learner_escape((string) ($learnerPageBanner['eyebrow'] ?? 'TalentHub')); ?></span>
        <h1 id="<?= $learnerBannerId; ?>"><?= $learnerBannerTitle; ?></h1>
        <p><?= learner_escape((string) ($learnerPageBanner['description'] ?? '')); ?></p>
    </div>
</section>
```

- [ ] **Step 2: Add the missing logout icon to the existing whitelist**

```php
'log-out' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M11 4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-7"/>',
```

- [ ] **Step 3: Add scoped CSS for the wordmark, footer, and shared banner**

```css
.learner-brand__logo { display: block; width: 160px; height: auto; }
.learner-sidebar__brand p { margin: 5px 0 0 48px; }
.learner-sidebar__footer { display: grid; gap: 4px; margin-top: 14px; }
.learner-sidebar__link--logout { color: var(--danger); }
.learner-sidebar__link--logout:hover { color: var(--danger); background: color-mix(in srgb, var(--danger) 10%, var(--surface)); }
.learner-page-banner { display: flex; min-height: 142px; margin-bottom: 22px; padding: 26px 30px; align-items: center; gap: 18px; background: linear-gradient(125deg, var(--primary-light), var(--surface) 54%, var(--secondary-light)); border: 1px solid rgba(249, 115, 22, 0.2); border-radius: var(--radius-md); }
.learner-page-banner__icon { display: inline-flex; width: 52px; height: 52px; align-items: center; justify-content: center; flex: 0 0 52px; color: var(--surface); background: var(--primary); border-radius: var(--radius-md); }
.learner-page-banner__eyebrow { color: var(--primary); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
.learner-page-banner h1 { margin: 5px 0 7px; font-size: clamp(1.8rem, 3vw, 2.55rem); letter-spacing: -0.045em; }
.learner-page-banner p { max-width: 760px; color: var(--text-secondary); line-height: 1.7; }
```

- [ ] **Step 4: Add the existing responsive rule**

```css
@media (max-width: 620px) {
    .learner-page-banner { min-height: 0; padding: 22px; align-items: flex-start; }
}
```

### Task 3: Move learner account actions into the sidebar

**Files:**
- Modify: `app/learner/includes/sidebar.php`
- Modify: `app/learner/includes/header.php`

**Interfaces:**
- Consumes: `learner_icon('arrow-left', 18)` and `learner_icon('log-out', 18)`.
- Produces: a sidebar footer with functional links to `/role-selection.php` and `/logout.php`.

- [ ] **Step 1: Add the footer after the level card**

```php
<div class="learner-sidebar__footer">
    <a class="learner-sidebar__link learner-sidebar__link--switch" href="/role-selection.php">
        <span class="learner-sidebar__icon" aria-hidden="true"><?= learner_icon('arrow-left', 18); ?></span>
        <span>Đổi vai trò</span>
    </a>
    <a class="learner-sidebar__link learner-sidebar__link--logout" href="/logout.php">
        <span class="learner-sidebar__icon" aria-hidden="true"><?= learner_icon('log-out', 18); ?></span>
        <span>Đăng xuất</span>
    </a>
</div>
```

- [ ] **Step 2: Remove the desktop role switch from the header**

Keep the `learner-header__left` container because it contains the mobile sidebar button. Remove only the `<a class="learner-role-switch" …>` element.

- [ ] **Step 3: Run the focused test to confirm the sidebar assertions are green**

Run: `node --test tests/learner_sidebar_banner_ui_test.js`

Expected: the sidebar placement and styling-hook assertions pass; the command still exits nonzero because the banner-route assertion remains failing until Task 4.

### Task 4: Apply the common banner to every in-scope primary page

**Files:**
- Modify: `app/learner/profile.php`
- Modify: `app/learner/discover.php`
- Modify: `app/learner/activities.php`
- Modify: `app/learner/checkin.php`
- Modify: `app/learner/evaluation.php`
- Modify: `app/learner/ai-recommendations.php`
- Modify: `app/learner/badges.php`
- Modify: `app/learner/statistics.php`
- Modify: `app/learner/my-activities.php`

**Interfaces:**
- Consumes: the `$learnerPageBanner` contract from Task 2.
- Produces: the common banner before each page's existing title/content, while retaining existing filters and controls.

- [ ] **Step 1: Set page-specific banner data and include the shared component**

For each page, define the array immediately before the include and remove the duplicate existing `h1`/description block. Use the following data:

```php
// profile.php
$learnerPageBanner = ['id' => 'learner-profile-page-title', 'eyebrow' => 'Hành trình phát triển', 'title' => 'Hồ sơ năng lực', 'description' => 'Theo dõi những năng lực, thành tích và trải nghiệm tạo nên hồ sơ của bạn.', 'icon' => 'user'];

// discover.php
$learnerPageBanner = ['id' => 'learner-discover-page-title', 'eyebrow' => 'Hiểu bản thân hơn', 'title' => 'Khám phá năng khiếu', 'description' => 'Bộ bài đánh giá giúp bạn hiểu rõ điểm mạnh và định hướng phát triển.', 'icon' => 'compass'];

// activities.php
$learnerPageBanner = ['id' => 'learner-activities-page-title', 'eyebrow' => 'Trải nghiệm để trưởng thành', 'title' => 'Khám phá hoạt động', 'description' => 'Tìm cơ hội phù hợp để học hỏi, trải nghiệm và kết nối.', 'icon' => 'calendar'];

// checkin.php
$learnerPageBanner = ['id' => 'learner-checkin-page-title', 'eyebrow' => 'Ghi nhận trải nghiệm', 'title' => 'Check-in trải nghiệm', 'description' => 'Quét mã tại địa điểm hoạt động để ghi nhận giờ trải nghiệm tự động.', 'icon' => 'qr'];

// evaluation.php
$learnerPageBanner = ['id' => 'learner-evaluation-page-title', 'eyebrow' => 'Theo dõi tiến bộ', 'title' => 'Đánh giá năng lực', 'description' => 'Xem điểm số và nhận xét từ giáo viên, huấn luyện viên.', 'icon' => 'clipboard'];

// ai-recommendations.php
$learnerPageBanner = ['id' => 'learner-ai-page-title', 'eyebrow' => 'Gợi ý cá nhân hóa', 'title' => 'AI phân tích năng lực', 'description' => 'Hiểu rõ điểm mạnh, điểm cần cải thiện và lộ trình phát triển của bạn.', 'icon' => 'sparkles'];

// badges.php
$learnerPageBanner = ['id' => 'learner-badges-page-title', 'eyebrow' => 'Ghi nhận nỗ lực', 'title' => 'Huy hiệu và cấp độ', 'description' => 'Theo dõi các cột mốc học tập và trải nghiệm của bạn.', 'icon' => 'award'];

// statistics.php
$learnerPageBanner = ['id' => 'learner-statistics-page-title', 'eyebrow' => 'Nhìn lại hành trình', 'title' => 'Thống kê cá nhân', 'description' => 'Theo dõi tiến bộ học tập và trải nghiệm của bạn theo thời gian.', 'icon' => 'chart'];

// my-activities.php
$learnerPageBanner = ['id' => 'learner-my-activities-page-title', 'eyebrow' => 'Hành trình trải nghiệm', 'title' => 'Hoạt động của tôi', 'description' => 'Theo dõi đăng ký, check-in, giờ trải nghiệm và phản hồi.', 'icon' => 'calendar'];
```

Each include uses:

```php
<?php include __DIR__ . '/includes/page-banner.php'; ?>
```

- [ ] **Step 2: Preserve page controls**

Keep the activities filters, evaluation term selector, statistics period selector, AI state message, and the my-activities CTA immediately after the banner. The banner must not become an interactive replacement for those controls.

- [ ] **Step 3: Run test to verify all contracts pass**

Run: `node --test tests/learner_sidebar_banner_ui_test.js`

Expected: PASS with 3 tests and 0 failures.

### Task 5: Verify the UI changes and commit only scoped files

**Files:**
- Test: `tests/learner_sidebar_banner_ui_test.js`
- Modify: all Task 2–4 files only.

- [ ] **Step 1: Run focused regression verification**

Run: `node --test tests/learner_sidebar_banner_ui_test.js`

Expected: PASS with 3 tests and 0 failures.

- [ ] **Step 2: Run source checks**

Run: `git diff --check -- app/learner/includes/icons.php app/learner/includes/page-banner.php app/learner/includes/sidebar.php app/learner/includes/header.php app/learner/profile.php app/learner/discover.php app/learner/activities.php app/learner/checkin.php app/learner/evaluation.php app/learner/ai-recommendations.php app/learner/badges.php app/learner/statistics.php app/learner/my-activities.php assets/css/learner.css tests/learner_sidebar_banner_ui_test.js`

Expected: no output and exit code 0.

- [ ] **Step 3: Inspect scope before commit**

Run: `git status --short` and confirm only the files listed above plus this implementation plan are staged. Leave `app/learner/data/bootstrap.php`, `Database/migrations/learner/`, runtime files, and existing untracked test files untouched.

- [ ] **Step 4: Commit the implementation**

```bash
git add -- app/learner/includes/icons.php app/learner/includes/page-banner.php app/learner/includes/sidebar.php app/learner/includes/header.php app/learner/profile.php app/learner/discover.php app/learner/activities.php app/learner/checkin.php app/learner/evaluation.php app/learner/ai-recommendations.php app/learner/badges.php app/learner/statistics.php app/learner/my-activities.php assets/css/learner.css tests/learner_sidebar_banner_ui_test.js docs/superpowers/plans/2026-08-16-learner-sidebar-banner-consistency.md
git commit -m "feat(student): unify sidebar actions and page banners"
```
