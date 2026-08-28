# AI Roadmap Processing Progress Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thay trạng thái loading khi tạo/cập nhật roadmap bằng bảng tiến trình AI bốn bước, đồng thời giữ roadmap hiện tại hiển thị và sử dụng được.

**Architecture:** Controller phân biệt tải dữ liệu với tạo mới và cập nhật, sau đó truyền metadata cho DOM view. Một hàm thuần tính tiến độ ước tính và một tracker có thể tiêm clock/scheduler quản lý timer; DOM view chỉ render trạng thái. Markup và CSS bổ sung một processing panel độc lập, không thay đổi API hoặc hợp đồng dữ liệu roadmap.

**Tech Stack:** PHP template, vanilla JavaScript, CSS, Node.js built-in test runner, PHP 8.3 syntax checks.

## Global Constraints

- Giữ roadmap đang có hiển thị trong suốt lần cập nhật.
- Bảng tiến trình gồm đúng bốn bước: chuẩn bị dữ liệu, Gemini phân tích, xây dựng roadmap 90 ngày, kiểm tra và hoàn thiện.
- Mọi phần trăm phải có nhãn “Tiến độ ước tính”; không mô tả đây là telemetry chính xác từ Gemini.
- Không hiển thị prompt, chain-of-thought, API key, request body hoặc dữ liệu đánh giá thô.
- Request tạo/cập nhật tiếp tục dùng CSRF, idempotency key và `timeoutMs: 90000`.
- Nội dung động dùng `textContent`, không dùng `innerHTML`.
- Dùng Be Vietnam Pro và các token `#F97316`, `#FFF7ED`, `#16A34A`, `#2563EB`, `#E2E8F0`.
- Responsive tại `1100px` và `720px`; tôn trọng `prefers-reduced-motion: reduce`.

---

## File Structure

- Modify: `assets/js/learner-ai-roadmap.js` — mô hình tiến độ, tracker, metadata controller và DOM renderer.
- Modify: `app/learner/ai-recommendations.php` — markup semantic cho processing panel.
- Modify: `assets/css/learner.css` — layout, trạng thái bước, responsive và reduced motion.
- Modify: `tests/learner_ai_roadmap_ui_test.js` — unit test tiến độ, controller, DOM, markup và CSS.

Không tạo endpoint hoặc file JavaScript mới vì logic chỉ phục vụ trang roadmap và module hiện tại đã có interface/controller/test tương ứng.

---

### Task 1: Mô hình tiến độ ước tính có thể kiểm thử

**Files:**
- Modify: `assets/js/learner-ai-roadmap.js:3-109`
- Test: `tests/learner_ai_roadmap_ui_test.js:80-151`

**Interfaces:**
- Produces: `processingProgressAt(elapsedMs: number): {elapsedSeconds:number, activeIndex:number, percent:number, steps:Array<{label:string,status:string}>}`
- Produces: `createProcessingTracker(options): {start():void, succeed():void, fail():void, stop():void}`
- `options` contains `onUpdate`, `now`, `schedule`, `cancelSchedule`, and optional `intervalMs`.

- [ ] **Step 1: Write failing tests for the pure progress projection**

Add to `tests/learner_ai_roadmap_ui_test.js`:

```js
test('processing progress moves through four honest estimated stages and stays below 100', () => {
  const { processingProgressAt } = require(modulePath);
  const snapshots = [0, 6000, 30000, 70000].map(processingProgressAt);
  assert.deepEqual(snapshots.map((item) => item.activeIndex), [0, 1, 2, 3]);
  assert.equal(snapshots.every((item) => item.percent >= 0 && item.percent <= 94), true);
  assert.deepEqual(snapshots[3].steps.map((step) => step.status), ['completed', 'completed', 'completed', 'active']);
  assert.equal(snapshots[2].elapsedSeconds, 30);
  assert.match(snapshots[1].steps[1].label, /Gemini đang phân tích/);
});
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
```

Expected: FAIL because `processingProgressAt` is not exported.

- [ ] **Step 3: Implement the pure projection**

Add near `READY_STATES` in `assets/js/learner-ai-roadmap.js`:

```js
const PROCESSING_STEPS = [
  'Chuẩn bị dữ liệu năng lực',
  'Gemini đang phân tích',
  'Xây dựng roadmap 90 ngày',
  'Kiểm tra và hoàn thiện',
];

function processingProgressAt(elapsedMs) {
  const elapsedSeconds = Math.max(0, Math.floor((Number(elapsedMs) || 0) / 1000));
  const activeIndex = elapsedSeconds < 5 ? 0 : elapsedSeconds < 25 ? 1 : elapsedSeconds < 55 ? 2 : 3;
  const ranges = [
    [0, 5, 8, 18],
    [5, 25, 18, 45],
    [25, 55, 45, 80],
    [55, 90, 80, 94],
  ];
  const [fromSecond, toSecond, fromPercent, toPercent] = ranges[activeIndex];
  const ratio = Math.max(0, Math.min(1, (elapsedSeconds - fromSecond) / (toSecond - fromSecond)));
  const percent = Math.min(94, Math.round(fromPercent + ((toPercent - fromPercent) * ratio)));
  return {
    elapsedSeconds,
    activeIndex,
    percent,
    steps: PROCESSING_STEPS.map((label, index) => ({
      label,
      status: index < activeIndex ? 'completed' : index === activeIndex ? 'active' : 'upcoming',
    })),
  };
}
```

Export `PROCESSING_STEPS` and `processingProgressAt` from the module.

- [ ] **Step 4: Run the test and verify GREEN**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: all current tests plus the new projection test PASS.

- [ ] **Step 5: Write failing tracker lifecycle test**

```js
test('processing tracker emits timed progress and terminal success without leaking timers', () => {
  const { createProcessingTracker } = require(modulePath);
  let time = 0;
  let scheduled = null;
  let cancelled = 0;
  const updates = [];
  const tracker = createProcessingTracker({
    now: () => time,
    schedule: (callback) => { scheduled = callback; return 9; },
    cancelSchedule: (handle) => { assert.equal(handle, 9); cancelled += 1; },
    onUpdate: (snapshot) => updates.push(snapshot),
  });
  tracker.start();
  time = 30000;
  scheduled();
  tracker.succeed();
  assert.equal(updates[0].activeIndex, 0);
  assert.equal(updates[1].activeIndex, 2);
  assert.equal(updates.at(-1).status, 'success');
  assert.equal(updates.at(-1).percent, 100);
  assert.equal(cancelled > 0, true);
});
```

- [ ] **Step 6: Run the tracker test and verify RED**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: FAIL because `createProcessingTracker` is missing.

- [ ] **Step 7: Implement the tracker**

```js
function createProcessingTracker({
  onUpdate,
  now = () => Date.now(),
  schedule = (callback, delay) => global.setTimeout(callback, delay),
  cancelSchedule = (handle) => global.clearTimeout(handle),
  intervalMs = 1000,
}) {
  let startedAt = 0;
  let handle = null;
  let running = false;
  const emit = () => onUpdate(processingProgressAt(now() - startedAt));
  const queue = () => { if (running) handle = schedule(tick, intervalMs); };
  const tick = () => { handle = null; if (!running) return; emit(); queue(); };
  const stop = () => { running = false; if (handle !== null) cancelSchedule(handle); handle = null; };
  const terminal = (status) => {
    const snapshot = processingProgressAt(now() - startedAt);
    stop();
    onUpdate({ ...snapshot, status, percent: status === 'success' ? 100 : snapshot.percent });
  };
  return {
    start() { stop(); startedAt = now(); running = true; emit(); queue(); },
    succeed() { terminal('success'); },
    fail() { terminal('error'); },
    stop,
  };
}
```

Export `createProcessingTracker`.

- [ ] **Step 8: Run tests and commit Task 1**

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
git add assets/js/learner-ai-roadmap.js tests/learner_ai_roadmap_ui_test.js
git commit -m "feat: add roadmap processing progress model"
```

Expected: tests PASS. If `.git/index.lock` is denied by the workspace, record that constraint and continue without claiming a commit exists.

---

### Task 2: Semantic processing panel and responsive styling

**Files:**
- Modify: `app/learner/ai-recommendations.php:38-46`
- Modify: `assets/css/learner.css:6752-6805, 7260-7305, 8666-8685`
- Test: `tests/learner_ai_roadmap_ui_test.js:350-420`

**Interfaces:**
- Produces DOM hooks: `data-roadmap-processing`, `data-roadmap-processing-title`, `data-roadmap-processing-copy`, `data-roadmap-processing-percent`, `data-roadmap-processing-elapsed`, `data-roadmap-processing-bar`, `data-roadmap-processing-steps`, `data-roadmap-processing-note`, and `data-roadmap-processing-retry`.
- Consumes existing click selector `[data-roadmap-retry]` on the retry button.

- [ ] **Step 1: Write failing static markup and CSS tests**

Extend the page/CSS tests:

```js
test('AI page provides an accessible four-step processing panel above the saved roadmap', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  for (const marker of [
    'data-roadmap-processing', 'data-roadmap-processing-title', 'data-roadmap-processing-copy',
    'data-roadmap-processing-percent', 'data-roadmap-processing-elapsed',
    'data-roadmap-processing-bar', 'data-roadmap-processing-steps',
    'data-roadmap-processing-note', 'data-roadmap-processing-retry',
  ]) assert.match(page, new RegExp(marker));
  assert.match(page, /role="status"/);
  assert.match(page, /aria-live="polite"/);
  assert.match(page, /Tiến độ ước tính/);
});

test('processing panel CSS supports four-step desktop, mobile and reduced motion layouts', () => {
  const css = fs.readFileSync(cssPath, 'utf8');
  assert.match(css, /\.learner-roadmap-processing__steps/);
  assert.match(css, /grid-template-columns:\s*repeat\(4,\s*minmax\(0,\s*1fr\)\)/);
  assert.match(css, /@media \(max-width: 720px\)[\s\S]*learner-roadmap-processing__steps[\s\S]*grid-template-columns:\s*1fr/);
  assert.match(css, /prefers-reduced-motion:\s*reduce[\s\S]*learner-roadmap-processing/);
});
```

- [ ] **Step 2: Run tests and verify RED**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: FAIL because processing hooks and CSS classes are absent.

- [ ] **Step 3: Add the processing panel before the existing loading section**

Add to `app/learner/ai-recommendations.php` immediately after `data-roadmap-status`:

```php
<section class="learner-card learner-roadmap-processing" data-roadmap-processing role="status" aria-live="polite" aria-atomic="false" hidden>
    <div class="learner-roadmap-processing__heading">
        <span class="learner-roadmap-processing__icon" aria-hidden="true"><?= learner_icon('sparkles', 24); ?></span>
        <div>
            <span class="learner-roadmap__eyebrow">AI ĐANG XỬ LÝ</span>
            <h2 data-roadmap-processing-title>Đang chuẩn bị roadmap của bạn</h2>
            <p data-roadmap-processing-copy>TalentHub đang tổng hợp dữ liệu đã được bạn cho phép.</p>
        </div>
        <div class="learner-roadmap-processing__meta">
            <strong data-roadmap-processing-percent>8%</strong>
            <span>Tiến độ ước tính · <span data-roadmap-processing-elapsed>0 giây</span></span>
        </div>
    </div>
    <div class="learner-roadmap-processing__bar" aria-hidden="true"><span data-roadmap-processing-bar></span></div>
    <ol class="learner-roadmap-processing__steps" data-roadmap-processing-steps>
        <li data-processing-step="0"><span>1</span><strong>Chuẩn bị dữ liệu năng lực</strong></li>
        <li data-processing-step="1"><span>2</span><strong>Gemini đang phân tích</strong></li>
        <li data-processing-step="2"><span>3</span><strong>Xây dựng roadmap 90 ngày</strong></li>
        <li data-processing-step="3"><span>4</span><strong>Kiểm tra và hoàn thiện</strong></li>
    </ol>
    <div class="learner-roadmap-processing__footer">
        <p data-roadmap-processing-note>Bạn có thể tiếp tục xem roadmap hiện tại trong lúc chờ.</p>
        <button class="learner-btn learner-btn--outline" type="button" data-roadmap-processing-retry data-roadmap-retry hidden>Thử cập nhật lại</button>
    </div>
</section>
```

- [ ] **Step 4: Add scoped CSS**

Add under existing roadmap state styles in `assets/css/learner.css`:

```css
.learner-page-ai .learner-roadmap-processing {
  display: grid;
  gap: 20px;
  margin-bottom: 20px;
  border: 1px solid #FED7AA;
  background: linear-gradient(135deg, #FFFFFF 0%, #FFF7ED 100%);
}
.learner-page-ai .learner-roadmap-processing[hidden] { display: none; }
.learner-page-ai .learner-roadmap-processing__heading {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
}
.learner-page-ai .learner-roadmap-processing__icon {
  display: grid;
  width: 48px;
  height: 48px;
  place-items: center;
  border-radius: 12px;
  color: #F97316;
  background: #FFF7ED;
}
.learner-page-ai .learner-roadmap-processing__heading h2,
.learner-page-ai .learner-roadmap-processing__heading p { margin: 0; }
.learner-page-ai .learner-roadmap-processing__meta { text-align: right; color: #64748B; }
.learner-page-ai .learner-roadmap-processing__meta strong { display: block; color: #F97316; font-size: 24px; }
.learner-page-ai .learner-roadmap-processing__bar { height: 8px; overflow: hidden; border-radius: 999px; background: #E2E8F0; }
.learner-page-ai .learner-roadmap-processing__bar span { display: block; width: 8%; height: 100%; border-radius: inherit; background: #F97316; transition: width .35s ease; }
.learner-page-ai .learner-roadmap-processing__steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 0; padding: 0; list-style: none; }
.learner-page-ai .learner-roadmap-processing__steps li { display: grid; grid-template-columns: 32px 1fr; align-items: center; gap: 10px; color: #64748B; }
.learner-page-ai .learner-roadmap-processing__steps li > span { display: grid; width: 32px; height: 32px; place-items: center; border: 1px solid #E2E8F0; border-radius: 50%; background: #FFFFFF; }
.learner-page-ai .learner-roadmap-processing__steps .is-active { color: #0F172A; }
.learner-page-ai .learner-roadmap-processing__steps .is-active > span { border-color: #F97316; color: #FFFFFF; background: #F97316; }
.learner-page-ai .learner-roadmap-processing__steps .is-completed > span { border-color: #16A34A; color: #FFFFFF; background: #16A34A; }
.learner-page-ai .learner-roadmap-processing__footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.learner-page-ai .learner-roadmap-processing__footer p { margin: 0; color: #64748B; }
.learner-page-ai .learner-roadmap-processing.is-error { border-color: #FCA5A5; background: #FFFFFF; }
.learner-page-ai .learner-roadmap-processing.is-success { border-color: #86EFAC; }

@media (max-width: 720px) {
  .learner-page-ai .learner-roadmap-processing__heading { grid-template-columns: auto 1fr; }
  .learner-page-ai .learner-roadmap-processing__meta { grid-column: 1 / -1; text-align: left; }
  .learner-page-ai .learner-roadmap-processing__steps { grid-template-columns: 1fr; }
  .learner-page-ai .learner-roadmap-processing__footer { align-items: stretch; flex-direction: column; }
}

@media (prefers-reduced-motion: reduce) {
  .learner-page-ai .learner-roadmap-processing,
  .learner-page-ai .learner-roadmap-processing__bar span { animation: none; transition: none; }
}
```

- [ ] **Step 5: Run tests and commit Task 2**

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
git add app/learner/ai-recommendations.php assets/css/learner.css tests/learner_ai_roadmap_ui_test.js
git commit -m "feat: add AI roadmap processing panel"
```

Expected: markup/CSS tests PASS. Record `.git/index.lock` denial if commit is unavailable.

---

### Task 3: Controller context and DOM progress lifecycle

**Files:**
- Modify: `assets/js/learner-ai-roadmap.js:109-235, 245-350, 620-650`
- Test: `tests/learner_ai_roadmap_ui_test.js:150-310`

**Interfaces:**
- Consumes: `createProcessingTracker`, `processingProgressAt`, and processing DOM hooks from Tasks 1–2.
- Changes `createDomView(root, options = {})` to accept injectable `now`, `schedule`, and `cancelSchedule`.
- Controller sends `view.render('loading', { mode: 'initial-load' })` for GET and `view.render('processing', { mode, preserveReady })` for generation.

- [ ] **Step 1: Write failing controller-context tests**

```js
test('controller distinguishes initial loading from first generation and refresh generation', async () => {
  const { createRoadmapController } = require(modulePath);
  const view = viewRecorder();
  let current = { state: 'not_generated' };
  const api = {
    async get() { return current; },
    async send() { return payload(); },
  };
  const controller = createRoadmapController({ api, view });
  await controller.load();
  assert.deepEqual(view.events[0], ['loading', { mode: 'initial-load' }]);
  await controller.generate('generate');
  assert.deepEqual(view.events.at(-2), ['processing', { mode: 'first-generation', preserveReady: false }]);
  current = payload();
  await controller.load();
  await controller.generate('refresh');
  assert.deepEqual(view.events.at(-2), ['processing', { mode: 'refresh-generation', preserveReady: true }]);
  controller.dispose();
});
```

- [ ] **Step 2: Run tests and verify RED**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: FAIL because controller still renders generic `loading` with `{}`.

- [ ] **Step 3: Implement controller context**

Change `load` and `generate`:

```js
async function load(showLoading = true) {
  if (showLoading) view.render('loading', { mode: 'initial-load' });
  try { return render(await api.get('/ai-roadmap.php')); }
  catch (error) { return render({ state: 'source_unavailable', message: error?.message }); }
}

function generate(action = 'generate') {
  if (generation !== null) return generation;
  const safeAction = action === 'refresh' ? 'refresh' : 'generate';
  const preserveReady = lastReadyPayload !== null;
  view.render('processing', {
    mode: preserveReady ? 'refresh-generation' : 'first-generation',
    preserveReady,
  });
  generation = Promise.resolve(api.send(
    'POST', '/ai-roadmap.php', { action: safeAction },
    { idempotencyKey: createIdempotencyKey(), timeoutMs: 90000 },
  )).then(render)
    .catch((error) => render({ state: 'source_unavailable', message: error?.message }))
    .finally(() => { generation = null; });
  return generation;
}
```

- [ ] **Step 4: Run controller tests and verify GREEN**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: controller tests PASS, including in-flight request reuse and `timeoutMs: 90000`.

- [ ] **Step 5: Write failing DOM lifecycle test**

Extend the existing `FakeNode` fixture with `classList.toggle`, `querySelectorAll`, and processing nodes. Add:

```js
test('DOM processing state preserves ready content during refresh and exposes estimated progress', () => {
  const { createDomView, buildRoadmapViewModel } = require(modulePath);
  const fixture = createRoadmapDomFixture();
  let time = 0;
  let tick = null;
  const view = createDomView(fixture.root, {
    now: () => time,
    schedule: (callback) => { tick = callback; return 1; },
    cancelSchedule: () => {},
  });
  view.render('ready-model', buildRoadmapViewModel(payload()));
  view.render('processing', { mode: 'refresh-generation', preserveReady: true });
  assert.equal(fixture.nodes.processing.hidden, false);
  assert.equal(fixture.nodes.ready.hidden, false);
  assert.match(fixture.nodes.processingPercent.textContent, /%/);
  assert.match(fixture.nodes.processingNote.textContent, /tiếp tục xem roadmap hiện tại/i);
  time = 30000;
  tick();
  assert.match(fixture.nodes.processingCopy.textContent, /roadmap 90 ngày/i);
});
```

Also add a failure assertion:

```js
view.render('stale-model', buildRoadmapViewModel({
  ...payload(), state: 'stale_model', refresh_state: 'fallback_not_applied', last_refresh_error: 'provider_unavailable',
}));
assert.equal(fixture.nodes.processing.hidden, false);
assert.match(fixture.nodes.processing.className, /is-error/);
assert.equal(fixture.nodes.processingRetry.hidden, false);
assert.equal(fixture.nodes.ready.hidden, false);
```

- [ ] **Step 6: Run DOM test and verify RED**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: FAIL because the view has no processing nodes or tracker lifecycle.

- [ ] **Step 7: Implement DOM processing lifecycle**

Add processing nodes to the `nodes` map and create a tracker inside `createDomView`:

```js
const tracker = createProcessingTracker({
  now: options.now,
  schedule: options.schedule,
  cancelSchedule: options.cancelSchedule,
  onUpdate: renderProcessingSnapshot,
});
```

Implement `renderProcessingSnapshot(snapshot)` using `textContent`, class toggles and `style.width`:

```js
function renderProcessingSnapshot(snapshot) {
  set(nodes.processingPercent, `${snapshot.percent}%`);
  set(nodes.processingElapsed, `${snapshot.elapsedSeconds} giây`);
  if (nodes.processingBar?.style) nodes.processingBar.style.width = `${snapshot.percent}%`;
  const stepNodes = Array.from(nodes.processingSteps?.querySelectorAll?.('[data-processing-step]') || []);
  for (const [index, step] of snapshot.steps.entries()) {
    stepNodes[index]?.classList?.toggle('is-active', step.status === 'active');
    stepNodes[index]?.classList?.toggle('is-completed', step.status === 'completed' || snapshot.status === 'success');
  }
  const activeCopy = [
    'TalentHub đang tổng hợp dữ liệu đã được bạn cho phép.',
    'Gemini đang phân tích điểm mạnh và hướng phát triển phù hợp.',
    'AI đang xây dựng ba giai đoạn cùng các nhiệm vụ cụ thể.',
    'TalentHub đang kiểm tra cấu trúc, đầu ra và cách đo lường.',
  ];
  set(nodes.processingCopy, activeCopy[snapshot.activeIndex]);
}
```

In `render(state, payload)`:

- For `processing`, show `nodes.processing`, keep `nodes.ready` visible only when `payload.preserveReady`, set title/note, hide retry, clear `is-error`/`is-success`, and call `tracker.start()`.
- For successful ready states reached from processing, call `tracker.succeed()`, mark `is-success`, set “Roadmap mới đã sẵn sàng”, render the new roadmap, and schedule hiding the processing panel after 1500 ms.
- For stale refresh failure (`payload.refresh_state === 'fallback_not_applied'`), call `tracker.fail()`, keep processing visible with `is-error`, show retry, and keep ready visible.
- For initial `source-error` without a previous roadmap, stop the tracker and show the existing error section.
- For every unrelated stable state, stop the tracker and hide processing.

Return `dispose()` from the view to stop tracker and success-hide timer. Update controller `dispose()` to invoke `view.dispose?.()` in addition to stopping polling:

```js
function dispose() {
  stopPolling();
  view.dispose?.();
}
```

- [ ] **Step 8: Disable duplicate generation controls while processing**

In the DOM view, query all `[data-roadmap-generate]` buttons and set `disabled = state === 'processing'`. Restore them for every terminal state. Existing controller promise reuse remains the authoritative duplicate-request guard.

- [ ] **Step 9: Run tests and commit Task 3**

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
node --check assets/js/learner-ai-roadmap.js
git add assets/js/learner-ai-roadmap.js tests/learner_ai_roadmap_ui_test.js
git commit -m "feat: show AI roadmap generation progress"
```

Expected: all roadmap UI tests PASS and JavaScript syntax check exits 0.

---

### Task 4: Full regression and acceptance verification

**Files:**
- Verify: `app/learner/ai-recommendations.php`
- Verify: `assets/js/learner-ai-roadmap.js`
- Verify: `assets/css/learner.css`
- Verify: `tests/learner_ai_roadmap_ui_test.js`

**Interfaces:**
- No new interfaces. This task validates the completed vertical slice.

- [ ] **Step 1: Run roadmap and recommendation UI suites**

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
node --test tests/learner_ai_recommendation_ui_test.js
```

Expected: zero failed tests.

- [ ] **Step 2: Run syntax and provider checks**

```powershell
node --check assets/js/learner-ai-roadmap.js
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l app/learner/ai-recommendations.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_provider_test.php
```

Expected: JS check exits 0, PHP reports no syntax errors, provider test ends with `learner_ai_roadmap_provider_test: OK`.

- [ ] **Step 3: Verify safety and diff quality**

```powershell
rg -n "innerHTML|generativelanguage|x-goog-api-key" assets/js/learner-ai-roadmap.js app/learner/ai-recommendations.php
git diff --check -- app/learner/ai-recommendations.php assets/js/learner-ai-roadmap.js assets/css/learner.css tests/learner_ai_roadmap_ui_test.js
```

Expected: safety grep has no matches; `git diff --check` exits 0. CRLF conversion warnings are informational if no whitespace errors are reported.

- [ ] **Step 4: Verify acceptance behavior in the browser**

1. Reload the AI page: only “Đang tải lộ trình đã lưu” appears briefly.
2. With an existing roadmap, click “Cập nhật phân tích”: processing panel appears above and roadmap remains visible.
3. Observe all four estimated stages and elapsed time; percentage remains below 100 while waiting.
4. Confirm the update button is disabled during the request.
5. On success, confirm the panel announces readiness, reaches 100%, and then hides while the new version renders.
6. Simulate an unavailable provider in a controlled local test: confirm the old roadmap remains and retry is visible.
7. Check viewport widths above 1100px and below 720px; verify horizontal and vertical step layouts.
8. Enable reduced motion and confirm no transition/animation remains.

- [ ] **Step 5: Commit the completed feature**

```powershell
git add app/learner/ai-recommendations.php assets/js/learner-ai-roadmap.js assets/css/learner.css tests/learner_ai_roadmap_ui_test.js
git commit -m "feat: visualize AI roadmap processing"
```

Expected: commit succeeds. If `.git/index.lock` remains read-only, report the exact constraint and leave the verified working tree unchanged.

