# Learner AI Recommendations Simplification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the learner `AI gợi ý` page around a scannable 90-day roadmap, an accessible talent radar, and collapsed secondary content without changing its API or controller behavior.

**Architecture:** Keep the PHP page, vanilla-JavaScript roadmap module, and learner-scoped stylesheet boundaries. Normalize presentation data in `buildRoadmapViewModel()`, render the timeline and SVG radar through safe DOM APIs in `createDomView()`, and rearrange existing semantic hooks in the PHP template so live recommendations, credentials, evidence, feedback, and version controls keep working.

**Tech Stack:** PHP templates, vanilla JavaScript, SVG DOM, CSS, Node.js built-in test runner.

## Global Constraints

- Use `Be Vietnam Pro`, sans-serif with 32/24/20/16/14/12px hierarchy.
- Use `#F97316`, `#EA580C`, `#FFF7ED`, `#2563EB`, `#EFF6FF`, `#16A34A`, `#F8FAFC`, `#FFFFFF`, `#0F172A`, `#64748B`, and `#E2E8F0` exactly as design tokens.
- Use 8px control radii and 12px card radii; no gradients, glassmorphism, chart dependency, or fabricated scores.
- Preserve all roadmap API states, version selection, generation, task updates, feedback, live recommendations, credentials, and evidence/engine hooks.
- Render learner-provided and model-provided copy with `textContent` or SVG DOM APIs; never use `innerHTML`.
- Keep production styles scoped under `.learner-page-ai` and render a horizontal desktop timeline that becomes vertical below 720px.

---

### Task 1: Normalize Talent Scores and Derive Roadmap Presentation State

**Files:**
- Modify: `tests/learner_ai_roadmap_ui_test.js:108-125`
- Modify: `assets/js/learner-ai-roadmap.js:20-69`

**Interfaces:**
- Consumes: API `talent_map[].score` values in either `0..1` or `0..100`, plus phase progress and task status.
- Produces: `normalizeTalentScore(value): number`; view-model phases with `displayTasks`, `status`, and `isCurrent`; `overallPercent: number`; `currentPhaseIndex: number`.

- [ ] **Step 1: Write failing score-normalization and phase-state tests**

Add focused assertions to `tests/learner_ai_roadmap_ui_test.js`:

```js
test('view model normalizes fractional talent scores without changing percentage scores', () => {
  const { buildRoadmapViewModel } = require(modulePath);
  const fractional = payload();
  fractional.talent_map = [
    { field: 'Logic', score: 0.82 },
    { field: 'Thực hành', score: 72 },
    { field: 'Điều phối', score: -4 },
    { field: 'Sáng tạo', score: 120 },
  ];
  assert.deepEqual(buildRoadmapViewModel(fractional).talentMap.map((item) => item.score), [82, 72, 0, 100]);
});

test('view model exposes one current phase and compact direction rows', () => {
  const { buildRoadmapViewModel } = require(modulePath);
  const model = buildRoadmapViewModel(payload());
  assert.equal(model.currentPhaseIndex, 0);
  assert.deepEqual(model.phases.map((phase) => phase.status), ['current', 'upcoming', 'upcoming']);
  assert.equal(model.phases.every((phase) => phase.displayTasks.length === 2), true);
  assert.equal(model.overallPercent, 11);
});
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `node --test --test-name-pattern="normalizes fractional|exposes one current" tests/learner_ai_roadmap_ui_test.js`

Expected: FAIL because `0.82` remains `0.82`, and phase presentation fields do not exist.

- [ ] **Step 3: Implement the minimal normalization and phase derivation**

Add a pure helper and derive presentation fields before returning the view model:

```js
function normalizeTalentScore(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return 0;
    const percentage = numeric > 0 && numeric <= 1 ? numeric * 100 : numeric;
    return Math.max(0, Math.min(100, Math.round(percentage)));
}
```

For sorted phases, cap `displayTasks` at two. Determine the first phase with incomplete tasks as `current`; prior phases are `completed` and later phases are `upcoming`. Calculate `overallPercent` from canonical `progress.completed_tasks / progress.total_tasks`, guarding zero totals. Replace the existing talent score clamp with `normalizeTalentScore(item?.score)` and export the helper for direct testing.

- [ ] **Step 4: Run the full roadmap test and verify GREEN**

Run: `node --test tests/learner_ai_roadmap_ui_test.js`

Expected: PASS with all roadmap tests green.

- [ ] **Step 5: Commit the independent data-model change**

```bash
git add tests/learner_ai_roadmap_ui_test.js assets/js/learner-ai-roadmap.js
git commit -m "fix: normalize learner AI talent scores"
```

### Task 2: Render the Accessible Radar and Compact 90-Day Timeline

**Files:**
- Modify: `tests/learner_ai_roadmap_ui_test.js:177-207`
- Modify: `assets/js/learner-ai-roadmap.js:185-520`

**Interfaces:**
- Consumes: Task 1 view-model fields `talentMap`, `phases[].displayTasks`, `phases[].status`, `overallPercent`, `nextActions`.
- Produces: an SVG rooted at `.learner-roadmap-radar` with `role="img"`; three `.learner-roadmap-phase` cards with state classes; compact highlighted insight rows; synchronized progress label/bar.

- [ ] **Step 1: Extend the fake DOM and write failing semantic renderer tests**

Extend `FakeNode` with `className`, `style`, `ownerDocument`, `createElementNS()`, and `querySelectorAll()` support needed by the new renderer. Add assertions:

```js
assert.equal(nodes['[data-roadmap-talent-map]'].children[0].tagName, 'SVG');
assert.equal(nodes['[data-roadmap-talent-map]'].children[0].attributes.role, 'img');
assert.match(nodes['[data-roadmap-talent-map]'].children[0].attributes['aria-label'], /Bản đồ năng khiếu/);
assert.equal(nodes['[data-roadmap-phases]'].children.length, 3);
assert.match(nodes['[data-roadmap-phases]'].children[0].className, /is-current/);
assert.equal(nodes['[data-roadmap-phases]'].children[0].children.some((item) => item.textContent === 'Bạn đang ở đây'), true);
assert.equal(nodes['[data-roadmap-next-actions]'].children.length, 1);
assert.equal(nodes['[data-roadmap-overall-progress]'].attributes['aria-valuenow'], '11');
```

- [ ] **Step 2: Run the DOM renderer test and verify RED**

Run: `node --test --test-name-pattern="DOM view renders" tests/learner_ai_roadmap_ui_test.js`

Expected: FAIL because talent-map children are text rows, next actions render three items, and no progressbar attributes exist.

- [ ] **Step 3: Implement safe SVG radar rendering**

Add an `svgElement(tag, attributes)` helper using `doc.createElementNS('http://www.w3.org/2000/svg', tag)`. Implement `renderTalentRadar(items)` that:

```js
const svg = svgElement('svg', {
    class: 'learner-roadmap-radar', viewBox: '0 0 420 300', role: 'img',
    'aria-label': `Bản đồ năng khiếu: ${items.map((item) => `${text(item.field, 'Lĩnh vực')} ${item.score}%`).join(', ')}`,
});
```

Build four neutral grid polygons, axis lines, one orange data polygon, point circles, and text labels from polar coordinates. Append a visually-hidden HTML list containing every field and score. If fewer than three records exist, retain an explicit empty-state/list presentation rather than inventing dimensions.

- [ ] **Step 4: Implement the compact phase and summary renderers**

Update capability rendering to show the radar and only one highlighted record for strength, improvement, and trend. Update phase rendering so each card contains number/title, `Ngày X–Y`, goal, at most two real task-direction rows, status class, and a current-stage badge. Render one next action only. Set `role="progressbar"`, `aria-valuemin="0"`, `aria-valuemax="100"`, `aria-valuenow`, and CSS custom property `--roadmap-progress` on the overall progress node.

- [ ] **Step 5: Run the full roadmap test and verify GREEN**

Run: `node --test tests/learner_ai_roadmap_ui_test.js`

Expected: PASS with SVG accessibility, three phases, compact next action, and existing controller tests intact.

- [ ] **Step 6: Commit the renderer change**

```bash
git add tests/learner_ai_roadmap_ui_test.js assets/js/learner-ai-roadmap.js
git commit -m "feat: render learner AI roadmap timeline and radar"
```

### Task 3: Recompose the Page and Apply Scoped Responsive Styling

**Files:**
- Modify: `tests/learner_ai_roadmap_ui_test.js:209-257`
- Modify: `app/learner/ai-recommendations.php:25-102`
- Modify: `assets/css/learner.css:7600-8120`

**Interfaces:**
- Consumes: every existing `data-roadmap-*` and `data-ai-*` hook plus Task 2 classes.
- Produces: compact header; two-card overview; dominant roadmap section; two-column talent/insight section; collapsed recommendation, credential, evidence, and engine regions; responsive timeline.

- [ ] **Step 1: Write failing page and CSS contract assertions**

Add page assertions for exact structure and copy:

```js
assert.match(page, /ROADMAP PHÁT TRIỂN 90 NGÀY/);
assert.match(page, /learner-roadmap-hero/);
assert.match(page, /learner-roadmap-analysis/);
assert.match(page, /<details[^>]+learner-roadmap-secondary/);
assert.doesNotMatch(page, /learner-roadmap-capability__grid/);
```

Add CSS assertions:

```js
assert.match(css, /font-family:\s*['"]Be Vietnam Pro['"],\s*sans-serif/);
assert.match(css, /\.learner-page-ai \.learner-roadmap-timeline/);
assert.match(css, /\.learner-page-ai \.learner-roadmap-radar/);
assert.match(css, /\.learner-page-ai \.learner-roadmap-secondary/);
assert.match(css, /@media \(max-width: 720px\)[\s\S]*grid-template-columns:\s*1fr/);
```

- [ ] **Step 2: Run page/CSS tests and verify RED**

Run: `node --test --test-name-pattern="page exposes|CSS is scoped" tests/learner_ai_roadmap_ui_test.js`

Expected: FAIL because the old six-card capability grid remains and the new layout classes do not exist.

- [ ] **Step 3: Recompose the PHP template while preserving all hooks**

Change the page title to `Lộ trình phát triển cá nhân`. Move the summary/direction content into `.learner-roadmap-hero`, with the summary card titled `Định hướng của bạn` and next-action card beside it. Move the roadmap plan directly below as the dominant `ROADMAP PHÁT TRIỂN 90 NGÀY` section. Replace the six-card capability grid with `.learner-roadmap-analysis`: one radar card and one `Nhận định nổi bật` card containing the existing strength/improvement/trend hooks plus a disclosure for potential paths and growth hypotheses.

Wrap live recommendations and roadmap activities together in a closed `<details class="learner-card learner-roadmap-secondary">` titled `Gợi ý hoạt động & cơ hội phù hợp`. Wrap credentials in another closed disclosure. Keep evidence/engine disclosures and feedback below without removing any semantic hooks. Preserve the existing cache-busting stylesheet URLs because they are pre-existing user changes.

- [ ] **Step 4: Replace the old AI-page presentation block with scoped mockup-aligned CSS**

Under `.learner-page-ai`, define page-local tokens, typography, hero grid, roadmap progress, timeline connector, three phase states, radar sizing, highlighted insight rows, disclosure summaries, focus styles, and reduced motion. At `max-width: 1100px`, allow phase cards to remain usable with smaller gaps. At `max-width: 720px`, switch the hero/analysis to one column and timeline to a vertical connector with each phase full width.

- [ ] **Step 5: Run focused tests and PHP syntax check**

Run:

```bash
node --test tests/learner_ai_roadmap_ui_test.js
php -l app/learner/ai-recommendations.php
```

Expected: all Node tests PASS and PHP reports `No syntax errors detected`.

- [ ] **Step 6: Run regression tests for adjacent learner AI and credential behavior**

Run:

```bash
php tests/learner_ai_customer_output_contract_test.php
php tests/learner_school_credential_ui_test.php
node --test tests/learner_ai_roadmap_ui_test.js tests/learner_recommendations_ui_test.js
```

Expected: all commands exit 0 and report no failed assertions.

- [ ] **Step 7: Inspect the final diff and commit only scoped files**

Run: `git diff --check -- app/learner/ai-recommendations.php assets/js/learner-ai-roadmap.js assets/css/learner.css tests/learner_ai_roadmap_ui_test.js`

Expected: no whitespace errors. Preserve unrelated worktree changes and the pre-existing cache-busting edit.

```bash
git add app/learner/ai-recommendations.php assets/js/learner-ai-roadmap.js assets/css/learner.css tests/learner_ai_roadmap_ui_test.js
git commit -m "feat: simplify learner AI recommendations page"
```
