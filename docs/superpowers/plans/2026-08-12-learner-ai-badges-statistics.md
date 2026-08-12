# Learner AI, Badges, and Statistics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the nine-page TalentHub Learner portal with server-rendered AI guidance, badges and levels, and personal statistics enhanced by framework-free JavaScript.

**Architecture:** Extend the existing `app/learner` shell and central mock-data provider. PHP renders default states, safe JSON carries alternate states, `LearnerUI` pure functions support testable behavior, and page-scoped DOM initializers enhance filters, simulated loading, and SVG charts.

**Tech Stack:** PHP 8.3, semantic HTML5, existing CSS design tokens, vanilla JavaScript, inline SVG, Node test runner, Playwright browser smoke checks.

## Global Constraints

- Keep all role pages under `app/learner`; do not create `app/student`.
- Reuse `sidebar.php`, `header.php`, `student-data.php`, `learner.css`, and `learner.js`.
- Do not install a frontend framework, Chart.js, database client, or AI SDK.
- Keep all mock data in `app/learner/includes/student-data.php`.
- Preserve the six existing Learner pages, Enterprise files, and home page files.
- Use only the established Learner tokens and no CSS gradient.
- Escape PHP output with `learner_escape()` and serialize JSON with all `JSON_HEX_*` flags.
- Keep exactly nine unique sidebar entries and make all nine real routes.

---

### Task 1: Lock the Final Portal Contract with Failing Tests

**Files:**
- Modify: `tests/learner_frontend_test.php`
- Modify: `tests/learner_js_test.js`

**Interfaces:**
- Consumes: Existing `$learnerNav`, `render_page()`, and `global.window.LearnerUI` test harness.
- Produces: Failing assertions for new data, routes, semantic markup, and pure JavaScript helpers.

- [ ] **Step 1: Add PHP contract assertions**

Add checks after the existing data assertions:

```php
check(count(array_unique(array_column($learnerNav, 'route'))) === 9, 'Sidebar routes are unique');
check(!in_array(false, array_column($learnerNav, 'implemented'), true), 'All learner routes are implemented');
check(count($learnerBadges ?? []) === 6, 'Badge collection contains six records');
check(count($learnerLevels ?? []) === 4, 'Level path contains four levels');
check(isset($learnerStatisticsPeriods[$defaultStatisticsPeriod]), 'Default statistics period exists');
check(isset($aiRecommendation['sufficient']), 'AI recommendation exposes data sufficiency');
```

Add route/render checks for `ai-recommendations.php`, `badges.php`, and `statistics.php`, including headings, active route, loading/empty markers, six badge cards, four filters, SVG chart semantics, and safe JSON flags.

- [ ] **Step 2: Add JavaScript pure-function tests**

```js
test('AI recommendation state resolves ready and insufficient data', () => {
    assert.equal(learnerUI.getAiRecommendationState({ sufficient: true }), 'ready');
    assert.equal(learnerUI.getAiRecommendationState({ sufficient: false }), 'insufficient');
    assert.equal(learnerUI.getAiRecommendationState(null), 'insufficient');
});

test('badge status matching supports all and exact states', () => {
    assert.equal(learnerUI.badgeMatchesStatus('achieved', 'all'), true);
    assert.equal(learnerUI.badgeMatchesStatus('achieved', 'achieved'), true);
    assert.equal(learnerUI.badgeMatchesStatus('locked', 'achieved'), false);
});

test('statistics period resolver rejects unknown periods', () => {
    const periods = { six: { kpis: [] } };
    assert.equal(learnerUI.getStatisticsPeriod(periods, 'six'), periods.six);
    assert.equal(learnerUI.getStatisticsPeriod(periods, 'missing'), null);
});

test('line chart points stay inside the requested SVG area', () => {
    assert.deepEqual(
        learnerUI.buildLineChartPoints([0, 10, 20], 200, 100, 20),
        [[0, 100], [100, 50], [200, 0]]
    );
});
```

- [ ] **Step 3: Run tests and verify RED**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_frontend_test.php
node --test tests\learner_js_test.js
```

Expected: PHP fails because the new routes/data do not exist; Node fails because the four helpers are undefined.

- [ ] **Step 4: Commit the failing contract**

```powershell
git add tests\learner_frontend_test.php tests\learner_js_test.js
git commit -m "test: define final learner portal contract"
```

### Task 2: Add Central Data and Complete Navigation

**Files:**
- Modify: `app/learner/includes/student-data.php`
- Modify: `assets/js/learner.js`

**Interfaces:**
- Consumes: Existing `learner_escape()`, `$level`, `$learnerNav`, and `LearnerUI` export pattern.
- Produces: `$aiRecommendation`, `$learnerLevels`, `$learnerBadgeFilters`, `$learnerBadges`, `$defaultStatisticsPeriod`, `$learnerStatisticsPeriods`, and the four pure JavaScript helpers.

- [ ] **Step 1: Change the final three navigation records**

Use these exact routes and mark them implemented:

```php
['label' => 'AI gợi ý', 'route' => '/app/learner/ai-recommendations.php', 'icon' => 'sparkles', 'implemented' => true],
['label' => 'Huy hiệu', 'route' => '/app/learner/badges.php', 'icon' => 'award', 'implemented' => true],
['label' => 'Thống kê', 'route' => '/app/learner/statistics.php', 'icon' => 'chart', 'implemented' => true],
```

- [ ] **Step 2: Add the approved mock-data records**

Define AI summary/groups/roadmap/disclaimer, four levels, six badges spanning all three statuses, and three statistics periods. Store stable machine values separately from Vietnamese display labels. Ensure each field-allocation collection adds up to its period's experience total.

- [ ] **Step 3: Implement pure JavaScript helpers**

```js
function getAiRecommendationState(data) {
    return data && data.sufficient === true ? 'ready' : 'insufficient';
}

function badgeMatchesStatus(badgeStatus, activeStatus) {
    return activeStatus === 'all' || badgeStatus === activeStatus;
}

function getStatisticsPeriod(periods, periodId) {
    if (!periods || typeof periods !== 'object') return null;
    return Object.prototype.hasOwnProperty.call(periods, periodId) ? periods[periodId] : null;
}

function buildLineChartPoints(values, width, height, maxValue) {
    if (!Array.isArray(values) || values.length === 0 || maxValue <= 0) return [];
    const denominator = Math.max(1, values.length - 1);
    return values.map((value, index) => [
        width * index / denominator,
        height - Math.max(0, Math.min(maxValue, Number(value) || 0)) / maxValue * height,
    ]);
}
```

Add all three new routes to `implementedRoutes` and export the helpers through `LearnerUI`.

- [ ] **Step 4: Run focused tests**

Run `node --test tests\learner_js_test.js`.

Expected: All JavaScript tests pass; PHP route tests still fail because pages are not created.

- [ ] **Step 5: Commit data and navigation**

```powershell
git add app\learner\includes\student-data.php assets\js\learner.js
git commit -m "feat: add final learner portal data"
```

### Task 3: Build the AI Guidance Page

**Files:**
- Create: `app/learner/ai-recommendations.php`
- Modify: `assets/js/learner.js`
- Test: `tests/learner_frontend_test.php`

**Interfaces:**
- Consumes: `$aiRecommendation`, shared includes, `getAiRecommendationState()`, modal-free page state markers.
- Produces: Server-rendered default analysis, simulated loading, and insufficient-data fallback.

- [ ] **Step 1: Create server-rendered semantic markup**

Set:

```php
$pageTitle = 'AI phân tích năng lực';
$currentRoute = '/app/learner/ai-recommendations.php';
$aiState = $aiRecommendation['sufficient'] ? 'ready' : 'insufficient';
```

Render a page heading, secondary-light summary, three analysis cards, three roadmap steps, activities CTA, always-visible disclaimer, a loading region, and an insufficient region. Every data value passes through `learner_escape()`.

- [ ] **Step 2: Add safe state payload and page initializer**

Serialize only the AI state fields with `JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. On DOMContentLoaded, briefly reveal loading only when state is ready, then show ready content. Use a zero-delay transition under `prefers-reduced-motion`. If state is insufficient, skip loading and show the fallback.

- [ ] **Step 3: Run PHP contract and JS syntax checks**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l app\learner\ai-recommendations.php
node --check assets\js\learner.js
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_frontend_test.php
```

Expected: AI-specific assertions pass; badge/statistics page assertions remain RED.

- [ ] **Step 4: Commit the AI page**

```powershell
git add app\learner\ai-recommendations.php assets\js\learner.js tests\learner_frontend_test.php
git commit -m "feat: build learner AI guidance"
```

### Task 4: Build Badge Collection and Filtering

**Files:**
- Create: `app/learner/badges.php`
- Modify: `assets/js/learner.js`
- Test: `tests/learner_frontend_test.php`

**Interfaces:**
- Consumes: `$learnerLevels`, `$learnerBadgeFilters`, `$learnerBadges`, and `badgeMatchesStatus()`.
- Produces: Four-level path, six-card collection, filter behavior, count announcement, and empty state.

- [ ] **Step 1: Render level and badge markup**

Set `$currentRoute = '/app/learner/badges.php'`. Render four level nodes and exactly six badge cards with `data-badge-card` and `data-badge-status`. Each progress component exposes `aria-valuemin`, `aria-valuemax`, and `aria-valuenow`. Use success classes only when `status === 'achieved'`.

- [ ] **Step 2: Add accessible filter controls**

Render four buttons with `data-badge-filter` and `aria-pressed`; add a visually hidden polite count and a hidden empty state.

- [ ] **Step 3: Implement page-safe filtering**

On filter click, update `aria-pressed`, toggle each card's `hidden` property through `badgeMatchesStatus()`, update the visible count, and toggle the empty state. Do not use inline handlers.

- [ ] **Step 4: Run focused checks**

Run PHP lint, PHP frontend tests, Node tests, and `node --check assets\js\learner.js`.

Expected: AI and badge assertions pass; statistics page assertions remain RED.

- [ ] **Step 5: Commit badges**

```powershell
git add app\learner\badges.php assets\js\learner.js tests\learner_frontend_test.php
git commit -m "feat: build learner badges and levels"
```

### Task 5: Build Personal Statistics and SVG Updates

**Files:**
- Create: `app/learner/statistics.php`
- Modify: `assets/js/learner.js`
- Test: `tests/learner_frontend_test.php`

**Interfaces:**
- Consumes: `$defaultStatisticsPeriod`, `$learnerStatisticsPeriods`, `getStatisticsPeriod()`, and `buildLineChartPoints()`.
- Produces: Default server-rendered statistics and complete period-switch DOM updates.

- [ ] **Step 1: Render the default statistics period**

Set `$currentRoute = '/app/learner/statistics.php'`. Render four KPI cards, an SVG bar/line chart with textual summary, an SVG donut with exact legend, four skills, four activity totals, and a hidden empty state.

- [ ] **Step 2: Serialize statistics safely**

Embed the period map in `#learner-statistics-data` using all `JSON_HEX_*` flags. Do not copy any period arrays into inline JavaScript.

- [ ] **Step 3: Implement period switching**

Resolve the selected period, update text through `textContent`, update progress attributes/styles, recreate SVG nodes with `document.createElementNS`, and update the chart summary. Unknown periods hide the content and expose the polite empty state.

- [ ] **Step 4: Run all contract tests**

Run PHP lint for the new page, the full PHP frontend suite, Node tests, and JavaScript syntax checks.

Expected: All contract and unit tests pass.

- [ ] **Step 5: Commit statistics**

```powershell
git add app\learner\statistics.php assets\js\learner.js tests\learner_frontend_test.php
git commit -m "feat: build learner personal statistics"
```

### Task 6: Match Mockups with Shared Responsive Styles

**Files:**
- Modify: `assets/css/learner.css`
- Modify: `tests/learner_frontend_test.php`

**Interfaces:**
- Consumes: Existing Learner tokens, card/button/progress foundations, and new page class names.
- Produces: Mockup-aligned desktop layouts and responsive tablet/mobile layouts without new global selectors.

- [ ] **Step 1: Add a failing stylesheet contract**

Assert the stylesheet contains page-scoped rules for AI, badges, and statistics; continues to contain no `gradient`; and does not contain `.ent-` selectors.

- [ ] **Step 2: Verify RED**

Run the PHP frontend suite and confirm only the new style assertions fail.

- [ ] **Step 3: Add page-scoped styles**

Extend `learner.css` with:

- AI summary/cards/roadmap/loading and empty states.
- Level path, badge grid, filters, and state styling.
- KPI grid, chart panels, SVG containment, legends, skills, and activity totals.
- Existing `1100px`, `720px`, and `480px` responsive sections.
- Reduced-motion handling for loading animation.

Reuse CSS variables exclusively; do not add a gradient declaration.

- [ ] **Step 4: Run static tests**

Run PHP frontend tests, Node tests, JavaScript syntax check, and `git diff --check`.

Expected: All pass.

- [ ] **Step 5: Commit styles**

```powershell
git add assets\css\learner.css tests\learner_frontend_test.php
git commit -m "feat: style final learner portal pages"
```

### Task 7: Browser Regression, Visual Comparison, and Isolation

**Files:**
- Modify: `tests/learner_browser_smoke.js`
- Modify as required by failing regression: only Learner files listed in this plan

**Interfaces:**
- Consumes: Running PHP server and all nine Learner routes.
- Produces: Automated viewport and interaction evidence for final acceptance.

- [ ] **Step 1: Extend browser smoke coverage before fixes**

Add all three routes to the page matrix. Add assertions for exactly nine navigable sidebar links, one active entry per page, AI loading completion and insufficient fallback, badge filter counts/status, statistics period KPI/chart changes, SVG accessibility, mobile drawer focus behavior, HTTP 200, zero console errors, and zero horizontal overflow.

- [ ] **Step 2: Run browser suite and capture RED failures**

Run with the bundled Codex Node runtime and `NODE_PATH` against `http://127.0.0.1:8765`.

Expected: Any missing visual or interaction requirement fails with a precise assertion.

- [ ] **Step 3: Fix only evidenced Learner regressions**

Apply minimal changes in the three new pages, `student-data.php`, `learner.css`, or `learner.js`, rerunning the specific failing assertion until green.

- [ ] **Step 4: Run full final verification**

Run:

```powershell
$phpExe='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
Get-ChildItem app\learner -Recurse -Filter '*.php' | ForEach-Object { & $phpExe -l $_.FullName }
& $phpExe tests\learner_frontend_test.php
node --test tests\learner_js_test.js
node --check assets\js\learner.js
git diff --check
git diff --name-only 12d8911...HEAD -- app\enterprise assets\css\enterprise.css assets\js\enterprise.js index.php assets\css\home.css assets\js\home.js
```

Then run the browser suite for nine pages at desktop, tablet, and mobile. Visually inspect screenshots for pages 07–09 against their supplied mockups.

Expected: Zero PHP errors, zero test failures, zero console errors, zero horizontal overflow, correct active sidebar states, no Enterprise/home changes, and close visual alignment.

- [ ] **Step 5: Request code review and fix Critical/Important findings through TDD**

Use the requesting-code-review checklist with base `12d8911` and current HEAD. Reproduce every valid Critical or Important issue with a failing automated test before changing production code.

- [ ] **Step 6: Commit browser coverage and reviewed fixes**

```powershell
git add tests\learner_browser_smoke.js app\learner assets\css\learner.css assets\js\learner.js tests\learner_frontend_test.php tests\learner_js_test.js
git commit -m "test: verify complete learner portal"
```

