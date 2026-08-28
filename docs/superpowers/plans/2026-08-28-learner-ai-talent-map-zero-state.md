# Learner AI Talent Map Zero-State Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Luôn hiển thị bản đồ năng khiếu ba trục trên trang AI gợi ý, dùng `0%` cho trục chưa có dữ liệu và buộc các roadmap Gemini mới trả đúng ba nhóm năng lực chuẩn.

**Architecture:** Frontend có một hàm thuần ánh xạ payload cũ sang ba trục chuẩn trước khi render; radar vì vậy luôn nhận đúng ba điểm và không cần nhánh empty state. Backend nâng phiên bản prompt, siết JSON schema và validator để các kết quả Gemini mới có đúng ba nhãn duy nhất, trong khi dữ liệu cũ vẫn được đọc tương thích mà không migration.

**Tech Stack:** Vanilla JavaScript, SVG DOM API, PHP 8.3, Gemini structured-output JSON schema, Node.js built-in test runner, PHP contract tests.

## Global Constraints

- Ba trục cố định là `Tư duy Logic & Hệ thống`, `Kỹ năng Thực hành & Thao tác`, `Tổ chức & Điều phối`.
- `0%` khi thiếu dữ liệu có nghĩa “chưa được xác định”, không phải năng lực thấp.
- Không nhân bản một điểm legacy sang nhiều trục và không tạo điểm lớn hơn 0 nếu không có record hợp lệ.
- Không sửa trực tiếp roadmap đã lưu và không thêm migration.
- Gemini mới phải trả đúng ba record, mỗi nhãn xuất hiện một lần và mỗi record có evidence hợp lệ.
- Dữ liệu động tiếp tục dùng `textContent`/SVG attributes; không dùng `innerHTML`.
- Giữ Be Vietnam Pro, token màu hiện có và responsive hiện tại.
- Không thay đổi roadmap 90 ngày, huy hiệu hoặc chứng chỉ.

---

## File Structure

- Modify: `assets/js/learner-ai-roadmap.js` — chuẩn hóa ba trục, render radar và chú thích 0%.
- Modify: `assets/css/learner.css` — trạng thái trục chưa xác định và chú thích dưới radar.
- Modify: `app/learner/ai/Model/RoadmapPromptRegistry.php` — prompt version 1.4.0 và structured-output schema bắt buộc ba trục.
- Modify: `app/learner/ai/Validation/RoadmapAnalysisValidator.php` — bắt buộc đủ ba nhãn duy nhất cho payload model mới.
- Modify: `tests/fixtures/learner_ai_roadmap_v1.php` — fixture provider hợp lệ theo contract mới.
- Modify: `tests/learner_ai_roadmap_ui_test.js` — unit/DOM/CSS regression cho radar 0%.
- Modify: `tests/learner_ai_roadmap_prompt_test.php` — contract prompt/schema.
- Modify: `tests/learner_ai_roadmap_contract_test.php` — contract validator.

---

### Task 1: Chuẩn hóa payload cũ thành ba trục cố định

**Files:**
- Modify: `assets/js/learner-ai-roadmap.js:5-165,900-910`
- Test: `tests/learner_ai_roadmap_ui_test.js:155-185`

**Interfaces:**
- Produces: `TALENT_AXES: Array<{field:string,keywords:string[]}>`.
- Produces: `completeTalentMap(value: unknown): Array<{field:string,score:number,hasEvidence:boolean,evidence_ref_ids:string[]}>`.
- Consumes: existing `normalizeTalentScore(value)`.

- [ ] **Step 1: Write failing tests for empty, partial and combined legacy records**

Add after the current talent-score normalization test:

```js
test('talent map always exposes three canonical axes with zero for missing data', () => {
  const { completeTalentMap } = require(modulePath);
  assert.deepEqual(completeTalentMap([]), [
    { field: 'Tư duy Logic & Hệ thống', score: 0, hasEvidence: false, evidence_ref_ids: [] },
    { field: 'Kỹ năng Thực hành & Thao tác', score: 0, hasEvidence: false, evidence_ref_ids: [] },
    { field: 'Tổ chức & Điều phối', score: 0, hasEvidence: false, evidence_ref_ids: [] },
  ]);
});

test('legacy talent records map once and never duplicate a combined score', () => {
  const { completeTalentMap } = require(modulePath);
  const result = completeTalentMap([
    { field: 'Tư duy Logic & Kỹ thuật Thực hành', score: 0.85, evidence_ref_ids: ['evidence-001'] },
    { field: 'Tổ chức & Quản lý Quy trình', score: 0.82, evidence_ref_ids: ['evidence-002'] },
  ]);
  assert.deepEqual(result.map((item) => item.score), [85, 0, 82]);
  assert.deepEqual(result.map((item) => item.hasEvidence), [true, false, true]);
  assert.deepEqual(result[0].evidence_ref_ids, ['evidence-001']);
});
```

- [ ] **Step 2: Run the focused UI suite and verify RED**

Run:

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
```

Expected: FAIL because `completeTalentMap` is not exported.

- [ ] **Step 3: Implement the canonical mapper**

Add after `PROCESSING_STEPS`:

```js
const TALENT_AXES = [
  { field: 'Tư duy Logic & Hệ thống', keywords: ['logic', 'he thong', 'phan tich', 'tu duy'] },
  { field: 'Kỹ năng Thực hành & Thao tác', keywords: ['thuc hanh', 'thao tac', 'ky thuat', 'ung dung'] },
  { field: 'Tổ chức & Điều phối', keywords: ['to chuc', 'dieu phoi', 'quan ly', 'quy trinh'] },
];
```

Add after `normalizeTalentScore`:

```js
function searchableTalentField(value) {
  return text(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').toLowerCase();
}

function completeTalentMap(value) {
  const result = TALENT_AXES.map((axis) => ({
    field: axis.field,
    score: 0,
    hasEvidence: false,
    evidence_ref_ids: [],
  }));
  const records = Array.isArray(value) ? value.filter((item) => item && typeof item === 'object').slice(0, 8) : [];
  for (const record of records) {
    const field = searchableTalentField(record?.field);
    const axisIndex = TALENT_AXES.findIndex((axis) => axis.keywords.some((keyword) => field.includes(keyword)));
    if (axisIndex < 0 || !Number.isFinite(Number(record?.score))) continue;
    const score = normalizeTalentScore(record.score);
    if (result[axisIndex].hasEvidence && result[axisIndex].score >= score) continue;
    result[axisIndex] = {
      field: TALENT_AXES[axisIndex].field,
      score,
      hasEvidence: true,
      evidence_ref_ids: Array.isArray(record?.evidence_ref_ids)
        ? record.evidence_ref_ids.filter((item) => typeof item === 'string')
        : [],
    };
  }
  return result;
}
```

In `buildRoadmapViewModel`, replace the current `talentMap` assignment with:

```js
talentMap: completeTalentMap(payload?.talent_map),
```

Export both symbols:

```js
TALENT_AXES, completeTalentMap,
```

- [ ] **Step 4: Run tests and verify GREEN**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: all tests PASS, including exact `[85, 0, 82]` legacy mapping.

- [ ] **Step 5: Commit Task 1**

```powershell
git add -- assets/js/learner-ai-roadmap.js tests/learner_ai_roadmap_ui_test.js
git commit -m "fix: normalize learner talent map axes"
```

---

### Task 2: Luôn render radar và giải thích điểm 0%

**Files:**
- Modify: `assets/js/learner-ai-roadmap.js:585-650`
- Modify: `assets/css/learner.css:8632-8685`
- Test: `tests/learner_ai_roadmap_ui_test.js:260-360,430-470`

**Interfaces:**
- Consumes: `completeTalentMap()` output from Task 1.
- Produces: `.learner-roadmap-radar-note` and `.is-unmeasured` visual states.

- [ ] **Step 1: Write a failing DOM test for a zero-value radar**

Extend the existing semantic DOM renderer test with a second fixture render:

```js
const emptyTalentPayload = payload();
emptyTalentPayload.talent_map = [];
createDomView(rootNode).render('ready-model', buildRoadmapViewModel(emptyTalentPayload));
const zeroRadar = nodes['[data-roadmap-talent-map]'].children[0];
assert.equal(zeroRadar.tagName, 'SVG');
assert.match(zeroRadar.attributes['aria-label'], /Tư duy Logic & Hệ thống 0%/);
assert.match(zeroRadar.attributes['aria-label'], /Kỹ năng Thực hành & Thao tác 0%/);
assert.match(zeroRadar.attributes['aria-label'], /Tổ chức & Điều phối 0%/);
assert.equal(nodes['[data-roadmap-talent-map]'].children[1].className, 'learner-roadmap-radar-note');
assert.match(nodes['[data-roadmap-talent-map]'].children[1].textContent, /chưa được xác định/i);
```

Add a static regression assertion:

```js
assert.doesNotMatch(source, /Chưa đủ dữ liệu để vẽ bản đồ năng khiếu/);
```

- [ ] **Step 2: Run the UI suite and verify RED**

Run `node --test tests/learner_ai_roadmap_ui_test.js`.

Expected: FAIL because the renderer still replaces fewer than three records with the empty-state paragraph.

- [ ] **Step 3: Replace the empty-state branch with unconditional radar rendering**

Change `renderCapabilityAnalysis` to:

```js
function renderCapabilityAnalysis(model) {
  clear(nodes.talentMap);
  nodes.talentMap?.appendChild(renderTalentRadar(model.talentMap));
  if (model.talentMap.some((item) => item.hasEvidence === false)) {
    nodes.talentMap?.appendChild(element(
      'p',
      'learner-roadmap-radar-note',
      '0% là dữ liệu chưa được xác định, không phải đánh giá năng lực thấp.',
    ));
  }
  renderTextRecords(nodes.strengths, model.strengths, 'Chưa có điểm mạnh đủ bằng chứng.');
  renderTextRecords(nodes.improvements, model.improvements, 'Chưa có điểm cần cải thiện đủ bằng chứng.');
  renderTextRecords(nodes.trends, model.trendSignals, 'Chưa có xu hướng đủ bằng chứng.', 'label');
  renderTextRecords(nodes.potentialPaths, model.potentialPaths, 'Chưa có hướng phát triển đủ bằng chứng.', 'label');
  renderTextRecords(nodes.growthHypotheses, model.growthHypotheses, 'Chưa có giả thuyết phát triển đủ bằng chứng.');
}
```

When creating label and point nodes inside `renderTalentRadar`, append `is-unmeasured` when `item.hasEvidence === false`:

```js
const measurementClass = item.hasEvidence === false ? ' is-unmeasured' : '';
svg.appendChild(svgElement('circle', {
  class: `learner-roadmap-radar__point${measurementClass}`,
  cx: position.x,
  cy: position.y,
  r: 5,
  'aria-label': `${text(item?.field, 'Lĩnh vực')}: ${item.score}%`,
}));
const labelNode = svgElement('text', {
  class: `learner-roadmap-radar__label${measurementClass}`,
  x: labelPosition.x,
  y: labelPosition.y,
  'text-anchor': 'middle',
});
```

- [ ] **Step 4: Add scoped CSS for the note and unmeasured axes**

Add after the current roadmap radar styles:

```css
.learner-page-ai .learner-roadmap-radar-note {
  margin: 2px auto 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.5;
  text-align: center;
}

.learner-page-ai .learner-roadmap-radar__point.is-unmeasured {
  fill: #94A3B8;
}

.learner-page-ai .learner-roadmap-radar__label.is-unmeasured {
  fill: #64748B;
}
```

- [ ] **Step 5: Add and run the CSS regression assertion**

Add to the scoped CSS test:

```js
assert.match(css, /\.learner-page-ai \.learner-roadmap-radar-note/);
assert.match(css, /\.learner-roadmap-radar__point\.is-unmeasured/);
```

Run:

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
node --check assets/js/learner-ai-roadmap.js
```

Expected: all UI tests PASS and syntax check exits 0.

- [ ] **Step 6: Commit Task 2**

```powershell
git add -- assets/js/learner-ai-roadmap.js assets/css/learner.css tests/learner_ai_roadmap_ui_test.js
git commit -m "fix: always render learner talent radar"
```

---

### Task 3: Bắt Gemini trả đúng ba nhóm năng lực

**Files:**
- Modify: `app/learner/ai/Model/RoadmapPromptRegistry.php:15,80-100,185-225`
- Modify: `app/learner/ai/Validation/RoadmapAnalysisValidator.php:13-30,292-330`
- Modify: `tests/fixtures/learner_ai_roadmap_v1.php:45-115`
- Modify: `tests/learner_ai_roadmap_prompt_test.php:98-132`
- Modify: `tests/learner_ai_roadmap_contract_test.php:34-140`

**Interfaces:**
- Produces: `RoadmapPromptRegistry::VERSION = 'learner-roadmap-prompt-1.4.0'`.
- Produces: provider payload contract requiring exactly three canonical `talent_map` records.
- Consumes: existing `RoadmapAnalysisValidator::extendedRecords()` evidence validation.

- [ ] **Step 1: Update the valid provider fixture and write failing validator tests**

Add this field to `learner_ai_roadmap_provider_fixture()` before `phases`:

```php
'talent_map' => [
    ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.82, 'evidence_ref_ids' => ['evidence-001']],
    ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.74, 'evidence_ref_ids' => ['evidence-002']],
    ['field' => 'Tổ chức & Điều phối', 'score' => 0.68, 'evidence_ref_ids' => ['evidence-003']],
],
```

Also change `learner_ai_roadmap_model_metadata()` to return:

```php
'prompt_version' => 'learner-roadmap-prompt-1.4.0',
```

Add before the final success line in `tests/learner_ai_roadmap_contract_test.php`:

```php
$missingTalentAxis = learner_ai_roadmap_provider_fixture();
array_pop($missingTalentAxis['talent_map']);
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($missingTalentAxis, learner_ai_roadmap_model_metadata()),
    'exactly three talent map records',
);

$duplicateTalentAxis = learner_ai_roadmap_provider_fixture();
$duplicateTalentAxis['talent_map'][2]['field'] = 'Tư duy Logic & Hệ thống';
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($duplicateTalentAxis, learner_ai_roadmap_model_metadata()),
    'talent map fields',
);

$unknownTalentAxis = learner_ai_roadmap_provider_fixture();
$unknownTalentAxis['talent_map'][2]['field'] = 'Năng lực chưa được định nghĩa';
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($unknownTalentAxis, learner_ai_roadmap_model_metadata()),
    'talent map fields',
);
```

- [ ] **Step 2: Write failing prompt-schema assertions**

Change the expected version to `learner-roadmap-prompt-1.4.0`, add `talent_map` to `$expectedTopLevel`, and add:

```php
$talentSchema = $schema['properties']['talent_map'] ?? [];
roadmap_prompt_assert(($talentSchema['minItems'] ?? null) === 3, 'talent map requires three records');
roadmap_prompt_assert(($talentSchema['maxItems'] ?? null) === 3, 'talent map allows only three records');
roadmap_prompt_assert(
    ($talentSchema['items']['properties']['field']['enum'] ?? null) === [
        'Tư duy Logic & Hệ thống',
        'Kỹ năng Thực hành & Thao tác',
        'Tổ chức & Điều phối',
    ],
    'talent map fields use the three canonical learner axes',
);
```

- [ ] **Step 3: Run contract tests and verify RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_contract_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_prompt_test.php
```

Expected: contract test FAIL because two records are still accepted; prompt test FAIL because version/schema are still 1.3.0 and optional.

- [ ] **Step 4: Implement exact validator rules**

Add to `RoadmapAnalysisValidator`:

```php
private const TALENT_MAP_FIELDS = [
    'Tư duy Logic & Hệ thống',
    'Kỹ năng Thực hành & Thao tác',
    'Tổ chức & Điều phối',
];
```

Move `talent_map` from `EXTENDED_FIELDS` into `PAYLOAD_FIELDS` so the two arrays contain:

```php
private const PAYLOAD_FIELDS = [
    'alternative_directions',
    'executive_summary',
    'insights',
    'phases',
    'primary_direction',
    'recommended_activity_source_ids',
    'talent_map',
];
private const EXTENDED_FIELDS = [
    'confidence',
    'evidence',
    'growth_hypotheses',
    'improvements',
    'potential_paths',
    'strengths',
    'trend_signals',
];
```

Immediately after `extendedRecords()` returns `$talentMap`, add:

```php
if (count($talentMap) !== 3) {
    throw new \InvalidArgumentException('Roadmap requires exactly three talent map records.');
}
$talentFields = array_column($talentMap, 'field');
sort($talentFields, SORT_STRING);
$expectedTalentFields = self::TALENT_MAP_FIELDS;
sort($expectedTalentFields, SORT_STRING);
if ($talentFields !== $expectedTalentFields) {
    throw new \InvalidArgumentException('Roadmap talent map fields are invalid.');
}
```

- [ ] **Step 5: Implement prompt version and structured-output schema**

Set:

```php
public const VERSION = 'learner-roadmap-prompt-1.4.0';
```

Add to the instructions:

```php
'talent_map phải có đúng ba record, mỗi record dùng duy nhất một trong ba field chuẩn: Tư duy Logic & Hệ thống; Kỹ năng Thực hành & Thao tác; Tổ chức & Điều phối. Không gộp hai nhóm vào cùng một record.',
```

Add `talent_map` to the top-level `required` array. Replace its property schema with:

```php
'talent_map' => [
    'type' => 'array',
    'minItems' => 3,
    'maxItems' => 3,
    'items' => [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['field', 'score', 'evidence_ref_ids'],
        'properties' => [
            'field' => ['type' => 'string', 'enum' => self::TALENT_MAP_FIELDS],
            'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'evidence_ref_ids' => $evidence,
        ],
    ],
],
```

Define the same canonical list once as `private const TALENT_MAP_FIELDS` in `RoadmapPromptRegistry`.

- [ ] **Step 6: Run contract tests and verify GREEN**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_contract_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_prompt_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_provider_test.php
```

Expected: all three scripts end with `: OK`.

- [ ] **Step 7: Commit Task 3**

```powershell
git add -- app/learner/ai/Model/RoadmapPromptRegistry.php app/learner/ai/Validation/RoadmapAnalysisValidator.php tests/fixtures/learner_ai_roadmap_v1.php tests/learner_ai_roadmap_prompt_test.php tests/learner_ai_roadmap_contract_test.php
git commit -m "fix: require canonical Gemini talent axes"
```

---

### Task 4: Full regression and acceptance verification

**Files:**
- Verify: all files modified in Tasks 1–3.

**Interfaces:**
- No new interfaces; validates the complete vertical slice.

- [ ] **Step 1: Run JavaScript tests and syntax check**

```powershell
node --test tests/learner_ai_roadmap_ui_test.js
node --test tests/learner_ai_recommendation_ui_test.js
node --check assets/js/learner-ai-roadmap.js
```

Expected: zero failed tests and JavaScript syntax exits 0.

- [ ] **Step 2: Run PHP contract and provider suites**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_contract_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_prompt_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/learner_ai_roadmap_provider_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l app/learner/ai/Model/RoadmapPromptRegistry.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l app/learner/ai/Validation/RoadmapAnalysisValidator.php
```

Expected: each test ends with `: OK`; both files report no syntax errors.

- [ ] **Step 3: Verify safety and diff quality**

```powershell
rg -n "innerHTML|generativelanguage|x-goog-api-key" assets/js/learner-ai-roadmap.js app/learner/ai/Model/RoadmapPromptRegistry.php app/learner/ai/Validation/RoadmapAnalysisValidator.php
git diff --check -- assets/js/learner-ai-roadmap.js assets/css/learner.css app/learner/ai/Model/RoadmapPromptRegistry.php app/learner/ai/Validation/RoadmapAnalysisValidator.php tests/fixtures/learner_ai_roadmap_v1.php tests/learner_ai_roadmap_ui_test.js tests/learner_ai_roadmap_prompt_test.php tests/learner_ai_roadmap_contract_test.php
```

Expected: safety grep has no matches; diff check exits 0. CRLF conversion warnings are informational.

- [ ] **Step 4: Verify browser acceptance**

1. Open `AI gợi ý` with the current two-record roadmap.
2. Confirm the card shows a three-axis radar instead of `Chưa đủ dữ liệu để vẽ...`.
3. Confirm Logic and Tổ chức use stored scores while Thực hành shows `0%`.
4. Confirm the note explains that `0%` means unmeasured.
5. Test an account without a roadmap talent map and confirm all three axes remain visible at `0%`.
6. Refresh analysis and confirm a newly generated roadmap has all three canonical axes.
7. Check mobile width below 720px for clipping and horizontal scroll.

- [ ] **Step 5: Confirm repository scope**

```powershell
git status --short
git log --oneline -6
```

Expected: feature commits are present on `feature/student`; unrelated existing changes in `.gitignore`, profile/badge files, `assets/css/home.css`, mockup images and credential tests remain unstaged and untouched.
