# Learner Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first three responsive TalentHub Learner pages at `/app/learner` without changing the Enterprise experience.

**Architecture:** Each PHP page loads one mock-data provider, sets its current route, and composes a shared Learner sidebar/header shell. `home.css` remains the shared token/reset layer while all new presentation and browser behavior are isolated in `learner.css` and `learner.js` with `learner-` namespacing.

**Tech Stack:** PHP 8.3, semantic HTML5, CSS custom properties/Grid/Flexbox, inline SVG, vanilla JavaScript, Node.js built-in test runner.

## Global Constraints

- Work on branch `feature/student` in `D:\TalentHub`.
- Use `app/learner`, not `app/student`, so the module matches the existing `/app/learner` role route.
- Keep PHP, HTML, CSS and vanilla JavaScript; install no frontend framework or package.
- Load Be Vietnam Pro and the shared design tokens from `assets/css/home.css`.
- Use `--primary: #F97316`, `--primary-hover: #EA580C`, `--primary-light: #FFF7ED`, `--secondary: #2563EB`, `--secondary-light: #EFF6FF`, `--accent: #16A34A`, `--background: #F8FAFC`, `--surface: #FFFFFF`, `--text-primary: #0F172A`, `--text-secondary: #64748B`, `--border: #E2E8F0`, `--success: #16A34A`, `--warning: #F59E0B`, `--danger: #DC2626`, `--radius-sm: 8px`, and `--radius-md: 12px`.
- Use no pink–purple gradient and no arbitrary colors outside the design system.
- Prefix Learner selectors and element IDs with `learner-`; do not modify `app/enterprise`, `assets/css/enterprise.css`, or `assets/js/enterprise.js`.
- Escape dynamic PHP output with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` through `learner_escape()`.
- Keep mock data in `app/learner/includes/student-data.php`; add no database query.
- Use the three files in `design/student-mockups` as the primary visual reference.

---

### Task 1: Test Harness, Data Contract, and Shared Shell

**Files:**
- Create: `tests/learner_frontend_test.php`
- Create: `app/learner/includes/student-data.php`
- Create: `app/learner/includes/sidebar.php`
- Create: `app/learner/includes/header.php`
- Create: `app/learner/includes/icons.php`

**Interfaces:**
- Consumes: shared variables from `assets/css/home.css`; page variables `$pageTitle` and `$currentRoute`.
- Produces: `learner_escape(mixed $value): string`, `$student`, `$learnerNav`, `$level`, `$dashboardKpis`, `$skills`, `$activities`, `$profileKpis`, `$certificates`, `$projects`, `$assessments`, `$radarScores`, `$careerDirections`, and `learner_icon(string $name, int $size = 20): string`.

- [ ] **Step 1: Write the failing shared-shell tests**

Create a zero-dependency PHP runner with the following core helpers and assertions:

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL: {$message}\n");
        return;
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

function render_page(string $path): string
{
    ob_start();
    require $path;
    return (string) ob_get_clean();
}

check(file_exists($root . '/app/learner/includes/student-data.php'), 'Learner data provider exists');
check(file_exists($root . '/app/learner/includes/sidebar.php'), 'Shared Learner sidebar exists');
check(file_exists($root . '/app/learner/includes/header.php'), 'Shared Learner header exists');

$dataSource = $root . '/app/learner/includes/student-data.php';
if (file_exists($dataSource)) {
    require $dataSource;
    check(learner_escape('<script>') === '&lt;script&gt;', 'Dynamic learner data is HTML escaped');
    check(count($learnerNav) === 9, 'Sidebar data contains nine navigation items');
    check(($student['name'] ?? '') === 'Nguyễn Văn A', 'Student mock data exposes the approved learner');
}

if ($failures !== []) {
    exit(1);
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_frontend_test.php
```

Expected: exit 1 with `FAIL: Learner data provider exists` and the two missing-partial failures.

- [ ] **Step 3: Implement the mock data and shared partials**

Create the data arrays with the approved Vietnamese copy and exact route map:

```php
$learnerNav = [
    ['label' => 'Tổng quan', 'route' => '/app/learner/index.php', 'icon' => 'grid', 'implemented' => true],
    ['label' => 'Hồ sơ năng lực', 'route' => '/app/learner/profile.php', 'icon' => 'user', 'implemented' => true],
    ['label' => 'Khám phá năng khiếu', 'route' => '/app/learner/discover.php', 'icon' => 'compass', 'implemented' => true],
    ['label' => 'Hoạt động', 'route' => '/app/learner/activities.php', 'icon' => 'calendar', 'implemented' => false],
    ['label' => 'Check-in QR', 'route' => '/app/learner/check-in.php', 'icon' => 'qr', 'implemented' => false],
    ['label' => 'Đánh giá', 'route' => '/app/learner/evaluations.php', 'icon' => 'clipboard', 'implemented' => false],
    ['label' => 'AI gợi ý', 'route' => '/app/learner/ai-suggestions.php', 'icon' => 'sparkles', 'implemented' => false],
    ['label' => 'Huy hiệu', 'route' => '/app/learner/badges.php', 'icon' => 'award', 'implemented' => false],
    ['label' => 'Thống kê', 'route' => '/app/learner/statistics.php', 'icon' => 'chart', 'implemented' => false],
];
```

`sidebar.php` must render logo, nine navigation links, `aria-current="page"` for `$currentRoute`, pending-route metadata, and the shared level card. `header.php` must render the mobile toggle, role switch, labeled search control, notification button, and avatar. `icons.php` must return only a whitelist of inline SVG strings and return an empty string for unknown names.

- [ ] **Step 4: Run the shared-shell tests and verify GREEN**

Run the PHP test command from Step 2.

Expected: every Task 1 assertion prints `PASS` and the process exits 0.

- [ ] **Step 5: Commit the shared foundation**

```powershell
git add tests/learner_frontend_test.php app/learner/includes
git commit -m "feat: add learner dashboard foundation"
```

### Task 2: Learner Overview Page

**Files:**
- Modify: `tests/learner_frontend_test.php`
- Create: `app/learner/index.php`

**Interfaces:**
- Consumes: every shared variable and partial from Task 1.
- Produces: rendered sections identified by `learner-welcome`, `learner-kpi-grid`, `learner-skills-card`, `learner-ai-card`, and `learner-activities`.

- [ ] **Step 1: Add failing overview render assertions**

Append guarded rendering and these checks:

```php
$overviewPath = $root . '/app/learner/index.php';
check(file_exists($overviewPath), 'Learner overview page exists');
if (file_exists($overviewPath)) {
    $overview = render_page($overviewPath);
    check(str_contains($overview, 'Chào mừng trở lại, Nguyễn Văn A'), 'Overview renders welcome copy');
    check(substr_count($overview, 'learner-kpi-card') >= 4, 'Overview renders four KPI cards');
    check(str_contains($overview, 'Hồ sơ kỹ năng'), 'Overview renders the skills summary');
    check(str_contains($overview, 'AI gợi ý cho bạn'), 'Overview renders the AI recommendation');
    check(str_contains($overview, 'Hoạt động sắp diễn ra'), 'Overview renders upcoming activities');
    check(str_contains($overview, 'href="discover.php"'), 'Overview aptitude CTA targets discover page');
}
```

- [ ] **Step 2: Run the PHP test and verify RED**

Expected: exit 1 with `FAIL: Learner overview page exists`.

- [ ] **Step 3: Implement semantic overview markup**

Set `$pageTitle = 'Tổng quan'` and `$currentRoute = '/app/learner/index.php'`, load the data, then render:

```php
<body class="learner-app learner-page-overview">
  <div class="learner-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="learner-main">
      <?php include __DIR__ . '/includes/header.php'; ?>
      <main class="learner-content" id="main-content">
        <section class="learner-welcome" aria-labelledby="welcome-title">...</section>
        <section class="learner-kpi-grid" aria-label="Chỉ số tổng quan">...</section>
        <div class="learner-dashboard-grid">...</div>
        <section class="learner-card learner-activities" aria-labelledby="activities-title">...</section>
      </main>
    </div>
  </div>
</body>
```

Loop over `$dashboardKpis`, `$skills`, and `$activities`; escape every label/value and set progress widths through escaped numeric CSS custom properties. Use buttons with `data-register-activity` for registration and real links for profile/discover destinations.

- [ ] **Step 4: Run the PHP test and verify GREEN**

Expected: Task 1 and Task 2 assertions pass with exit 0.

- [ ] **Step 5: Commit the overview page**

```powershell
git add tests/learner_frontend_test.php app/learner/index.php
git commit -m "feat: build learner overview page"
```

### Task 3: Competency Profile Page and Accessible Modals

**Files:**
- Modify: `tests/learner_frontend_test.php`
- Create: `app/learner/profile.php`

**Interfaces:**
- Consumes: `$student`, `$profileKpis`, `$skills`, `$certificates`, `$projects`, and shared shell.
- Produces: `#learner-edit-modal`, `#learner-share-modal`, `#learner-profile-form`, and `[data-copy-profile]` hooks.

- [ ] **Step 1: Add failing profile assertions**

```php
$profilePath = $root . '/app/learner/profile.php';
check(file_exists($profilePath), 'Learner profile page exists');
if (file_exists($profilePath)) {
    $profile = render_page($profilePath);
    check(str_contains($profile, 'Đã xác minh'), 'Profile renders verified status');
    check(str_contains($profile, 'Chia sẻ hồ sơ'), 'Profile renders share action');
    check(str_contains($profile, 'Chỉnh sửa'), 'Profile renders edit action');
    check(str_contains($profile, 'learner-edit-modal'), 'Profile provides edit modal');
    check(str_contains($profile, 'learner-share-modal'), 'Profile provides share modal');
    check(str_contains($profile, 'aria-modal="true"'), 'Profile modals expose dialog semantics');
    check(str_contains($profile, 'Dự án đã tham gia'), 'Profile renders projects');
}
```

- [ ] **Step 2: Run the PHP test and verify RED**

Expected: exit 1 with `FAIL: Learner profile page exists`.

- [ ] **Step 3: Implement profile markup and modal forms**

Set `$pageTitle = 'Hồ sơ năng lực'` and `$currentRoute = '/app/learner/profile.php'`. Render the profile hero, three KPIs, skills/certificates grid, project list, edit form fields `name`, `class`, `school`, `email`, `location`, and share URL `http://localhost/TalentHub/app/learner/profile.php?student=nguyen-van-a`. Give both dialogs `hidden`, `role="dialog"`, `aria-modal="true"`, and unique `aria-labelledby` values.

- [ ] **Step 4: Run the PHP test and verify GREEN**

Expected: all assertions through Task 3 pass.

- [ ] **Step 5: Commit the profile page**

```powershell
git add tests/learner_frontend_test.php app/learner/profile.php
git commit -m "feat: build learner competency profile"
```

### Task 4: Aptitude Discovery Page and Radar Visualization

**Files:**
- Modify: `tests/learner_frontend_test.php`
- Create: `app/learner/discover.php`

**Interfaces:**
- Consumes: `$assessments`, `$radarScores`, `$careerDirections`, and shared shell.
- Produces: `[data-assessment-action]`, `#learner-assessment-modal`, and accessible SVG radar markup.

- [ ] **Step 1: Add failing discovery assertions**

```php
$discoverPath = $root . '/app/learner/discover.php';
check(file_exists($discoverPath), 'Learner discovery page exists');
if (file_exists($discoverPath)) {
    $discover = render_page($discoverPath);
    foreach (['Holland', 'MBTI', 'DISC', 'Đa trí thông minh'] as $assessmentName) {
        check(str_contains($discover, $assessmentName), "Discovery renders {$assessmentName}");
    }
    check(str_contains($discover, 'role="img"'), 'Radar chart exposes image semantics');
    check(str_contains($discover, 'learner-radar-data'), 'Radar chart renders a data polygon');
    check(str_contains($discover, 'Kỹ thuật'), 'Discovery renders career directions');
    check(str_contains($discover, 'learner-assessment-modal'), 'Discovery provides assessment feedback modal');
}
```

- [ ] **Step 2: Run the PHP test and verify RED**

Expected: exit 1 with `FAIL: Learner discovery page exists`.

- [ ] **Step 3: Implement assessment cards, SVG radar, and result card**

Set `$pageTitle = 'Khám phá năng khiếu'` and `$currentRoute = '/app/learner/discover.php'`. Render four cards from `$assessments`, mapping `result`, `continue`, and `start` to the approved Vietnamese CTA. Render a six-axis SVG with viewBox `0 0 520 360`, four grid polygons, six axes, the data polygon `.learner-radar-data`, six points, a `<title>` and `<desc>`. Render four career-direction progress rows and one reusable assessment modal.

- [ ] **Step 4: Run the PHP test and verify GREEN**

Expected: all assertions through Task 4 pass.

- [ ] **Step 5: Commit the discovery page**

```powershell
git add tests/learner_frontend_test.php app/learner/discover.php
git commit -m "feat: build learner aptitude discovery"
```

### Task 5: Learner Interaction Model and Role Entry Route

**Files:**
- Create: `tests/learner_js_test.js`
- Create: `assets/js/learner.js`
- Modify: `assets/js/role-selection.js`
- Modify: `role-selection.php`

**Interfaces:**
- Produces pure helpers `window.LearnerUI.validateProfile(data)`, `window.LearnerUI.nextAssessmentState(state)`, and `window.LearnerUI.isImplementedRoute(route)` plus DOM initialization on `DOMContentLoaded`.

- [ ] **Step 1: Write failing JavaScript behavior tests**

Use Node's `node:test`, a minimal `global.window = {}`, and `require('../assets/js/learner.js')`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');

global.window = {};
global.document = undefined;
require('../assets/js/learner.js');

test('profile validation rejects blank required fields', () => {
  const result = window.LearnerUI.validateProfile({ name: ' ', school: 'THPT Nguyễn Du' });
  assert.equal(result.valid, false);
  assert.equal(result.field, 'name');
});

test('assessment start advances to continue state', () => {
  assert.equal(window.LearnerUI.nextAssessmentState('start'), 'continue');
  assert.equal(window.LearnerUI.nextAssessmentState('result'), 'result');
});

test('only the first three learner routes are implemented', () => {
  assert.equal(window.LearnerUI.isImplementedRoute('/app/learner/profile.php'), true);
  assert.equal(window.LearnerUI.isImplementedRoute('/app/learner/activities.php'), false);
});
```

- [ ] **Step 2: Run the Node tests and verify RED**

Run:

```powershell
node --test tests\learner_js_test.js
```

Expected: failure because `assets/js/learner.js` does not exist.

- [ ] **Step 3: Implement helpers and DOM interactions**

Implement an IIFE that exposes the three pure helpers, then on DOM ready initializes:

- Mobile sidebar open/close/backdrop/Escape and `aria-expanded`.
- Pending-route, search, and notification toasts.
- Activity registration state.
- Modal open/close, focus containment and focus restoration.
- Profile form validation and DOM-only profile update.
- Clipboard copy with fallback.
- Assessment modal text and `start` to `continue` card transition.

Guard DOM startup with `if (typeof document !== 'undefined')`. Change the learner route in `role-selection.php` from `/app/learner` to `app/learner/index.php`, and change `handleRoleNavigation()` so both `learner` and `enterprise` routes navigate directly.

- [ ] **Step 4: Run behavior and syntax checks and verify GREEN**

```powershell
node --test tests\learner_js_test.js
node --check assets\js\learner.js
node --check assets\js\role-selection.js
```

Expected: three Node tests pass and both syntax checks exit 0.

- [ ] **Step 5: Add PHP assertions for role entry and run the full suite**

Read `role-selection.php` and assert it contains `app/learner/index.php`; read `role-selection.js` and assert it contains `route.includes('learner')`. Run both PHP and Node suites.

- [ ] **Step 6: Commit Learner interactions and entry routing**

```powershell
git add tests/learner_frontend_test.php tests/learner_js_test.js assets/js/learner.js assets/js/role-selection.js role-selection.php
git commit -m "feat: add learner frontend interactions"
```

### Task 6: Scoped Visual System, Responsive Layout, and Final Verification

**Files:**
- Modify: `tests/learner_frontend_test.php`
- Create: `assets/css/learner.css`

**Interfaces:**
- Consumes: all `learner-` classes emitted by Tasks 1–4.
- Produces: desktop-first styling, tablet breakpoint at `1100px`, mobile breakpoint at `720px`, reduced-motion handling, modal/toast states, and no Enterprise selectors.

- [ ] **Step 1: Add failing CSS contract checks**

```php
$cssPath = $root . '/assets/css/learner.css';
check(file_exists($cssPath), 'Learner stylesheet exists');
if (file_exists($cssPath)) {
    $css = (string) file_get_contents($cssPath);
    check(str_contains($css, '.learner-layout'), 'Learner stylesheet scopes the app layout');
    check(str_contains($css, '@media (max-width: 1100px)'), 'Learner stylesheet defines tablet behavior');
    check(str_contains($css, '@media (max-width: 720px)'), 'Learner stylesheet defines mobile behavior');
    check(str_contains($css, 'prefers-reduced-motion'), 'Learner stylesheet respects reduced motion');
    check(!str_contains($css, '.ent-'), 'Learner stylesheet does not target Enterprise selectors');
    check(!preg_match('/linear-gradient\s*\(/i', $css), 'Learner stylesheet contains no gradient');
}
```

- [ ] **Step 2: Run the PHP test and verify RED**

Expected: exit 1 with `FAIL: Learner stylesheet exists`.

- [ ] **Step 3: Implement the complete scoped stylesheet**

Define the Learner shell first, then shared controls/cards, then page-specific layout. Use CSS Grid/Flexbox and the existing variables from `home.css`. Desktop sizes should approximate the 1600–1800px mockups: 274px sidebar, 68px header, content padding around 28–32px, 12px card radii, and 16–24px gaps. At 1100px move sidebar off-canvas and convert four-column grids to two columns. At 720px use a single content column, hide nonessential header text/search, make primary actions full-width when appropriate, and size dialogs to `calc(100vw - 24px)`.

- [ ] **Step 4: Run all automated checks**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_frontend_test.php
node --test tests\learner_js_test.js
node --check assets\js\learner.js
```

Expected: every assertion passes, Node reports 3 passing tests, and syntax check exits 0.

- [ ] **Step 5: Run PHP syntax checks for every PHP file**

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
Get-ChildItem -Path app\learner,tests -Recurse -Filter *.php -File | ForEach-Object { & $php -l $_.FullName }
& $php -l role-selection.php
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 6: Start a local server and perform browser verification**

Start `php -S 127.0.0.1:8765 -t D:\TalentHub` in a hidden background process. Verify HTTP 200 for all three URLs. Open and inspect each page at desktop `1600x1000`, tablet `834x1112`, and mobile `390x844`. Exercise sidebar, pending links, registration, edit/share modals, copy feedback, assessment actions, notification, and search; confirm no console errors.

- [ ] **Step 7: Compare screenshots with the approved mockups**

Capture each desktop page, compare side by side with its corresponding file under `design/student-mockups`, and correct visible differences in hierarchy, spacing, card sizing, line wrapping, and responsive overflow. Re-run Steps 4–6 after every correction.

- [ ] **Step 8: Check repository isolation and diff quality**

```powershell
git diff --check
git status --short
git diff -- app/enterprise assets/css/enterprise.css assets/js/enterprise.js
```

Expected: no whitespace errors, only Learner/test/docs/role-entry files changed, and the Enterprise-specific diff is empty.

- [ ] **Step 9: Commit the visual system**

```powershell
git add tests/learner_frontend_test.php assets/css/learner.css
git commit -m "feat: style responsive learner experience"
```

- [ ] **Step 10: Request code review and address findings**

Provide the design spec, this plan, base SHA `4d36d46`, and final HEAD to a reviewer. Fix all Critical and Important findings using a failing regression test first, then run the complete verification suite again.
