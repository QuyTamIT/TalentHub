# Assessment Band Inference and Runner UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Infer university/middle/high assessment bands from existing school and class data, eliminate repeated onboarding band prompts, and repair/polish the assessment answer layout.

**Architecture:** Extend the existing `EducationBandResolver` read query to include `schools.level` and keep all selection authority on the server. Extract option DOM construction into a small browser helper so numeric badges, labels, and selected indicators have stable elements that CSS can lay out responsively.

**Tech Stack:** PHP 8.3, PDO/MySQL and SQLite test fixtures, vanilla JavaScript, Node.js test runner, HTML/CSS, Playwright with Microsoft Edge.

## Global Constraints

- Do not add or alter database tables, columns, seed data, or migration files.
- Do not add an education-band field to registration.
- Preserve server-side scoring, autosave, attempt version pinning, onboarding order, and API authorization.
- Continue rendering question and option text with `textContent`; never use `innerHTML`.
- Unknown schools and primary grades 1–5 must retain the explicit confirmation path.
- Do not stage or edit unrelated DOCX or `.codex_tmp` files.

---

### Task 1: Infer education band from school and class

**Files:**
- Modify: `tests/learner_assessment_catalog_test.php:18-185`
- Modify: `app/learner/assessment/Service/EducationBandResolver.php:22-56`

**Interfaces:**
- Consumes: `EducationBandResolver::resolve(string $studentId, ?string $confirmedBand): string`.
- Produces: the same method signature with deterministic `schools.level` + `classes.gradeLevel` inference.

- [ ] **Step 1: Write the failing university inference test**

Extend the SQLite fixture with a nullable school level, a university school, a year-1 class, and a university learner:

```php
const STUDENT_UNIVERSITY_ID = '55555555-5555-4555-8555-555555555555';

$pdo->exec('CREATE TABLE schools (id CHAR(36) NOT NULL PRIMARY KEY, name TEXT NOT NULL, level TEXT NULL)');

$universitySchoolId = '00000000-0000-4000-8000-000000000005';
$universityClassId = '00000000-0000-4000-8000-000000000006';
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('{$schoolId}', 'High School', 'Trung học Phổ thông')");
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('{$universitySchoolId}', 'Đại học FPT', 'Đại học')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('{$universityClassId}', '{$universitySchoolId}', 'Năm 1', 1, '2026-2027')");
$pdo->exec("INSERT INTO users (id, email, fullName) VALUES ('u5', 's5@test.local', 'University Student')");
$pdo->exec("INSERT INTO student_profiles (id, userId, classId) VALUES ('" . STUDENT_UNIVERSITY_ID . "', 'u5', '{$universityClassId}')");

catalog_assert($bandResolver->resolve(STUDENT_UNIVERSITY_ID, null) === 'college', 'University year 1 resolves to college from school level');
catalog_assert($bandResolver->resolve(STUDENT_UNIVERSITY_ID, 'high') === 'college', 'Authoritative university level cannot be overridden');
```

- [ ] **Step 2: Run the resolver test and verify RED**

Run:

```powershell
& 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe' tests/learner_assessment_catalog_test.php
```

Expected: FAIL because the resolver still ignores `schools.level` and throws `EducationBandRequired` for the year-1 university learner.

- [ ] **Step 3: Implement minimal server-side inference**

Change the query and add a focused helper:

```php
$statement = $this->pdo->prepare(
    'SELECT c.gradeLevel, s.level AS schoolLevel '
    . 'FROM student_profiles sp '
    . 'LEFT JOIN classes c ON c.id = sp.classId '
    . 'LEFT JOIN schools s ON s.id = c.schoolId '
    . 'WHERE sp.id = :student_id LIMIT 1'
);

$schoolBand = $this->bandFromSchoolLevel($row['schoolLevel'] ?? null);
if ($schoolBand === 'college') {
    return 'college';
}
if ($grade >= 6 && $grade <= 9) {
    return 'middle';
}
if ($grade >= 10 && $grade <= 12) {
    return 'high';
}
if ($schoolBand !== null) {
    return $schoolBand;
}
```

```php
private function bandFromSchoolLevel(mixed $level): ?string
{
    if (!is_string($level) || trim($level) === '') {
        return null;
    }
    $normalized = mb_strtolower(trim($level), 'UTF-8');
    if (
        str_contains($normalized, 'đại học')
        || str_contains($normalized, 'cao đẳng')
        || str_contains($normalized, 'dai hoc')
        || str_contains($normalized, 'cao dang')
        || str_contains($normalized, 'university')
        || str_contains($normalized, 'college')
    ) {
        return 'college';
    }
    if (str_contains($normalized, 'trung học cơ sở') || preg_match('/\bthcs\b/u', $normalized) === 1) {
        return 'middle';
    }
    if (str_contains($normalized, 'trung học phổ thông') || preg_match('/\bthpt\b/u', $normalized) === 1) {
        return 'high';
    }
    return null;
}
```

Keep explicit confirmation as the last fallback and preserve its existing validation.

- [ ] **Step 4: Run focused and API tests and verify GREEN**

Run:

```powershell
& 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe' tests/learner_assessment_catalog_test.php
& 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe' tests/learner_assessment_api_test.php
& 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe' tests/learner_onboarding_service_test.php
```

Expected: all three scripts exit 0 and print their `OK` result.

- [ ] **Step 5: Commit the resolver change**

```powershell
git add -- app/learner/assessment/Service/EducationBandResolver.php tests/learner_assessment_catalog_test.php
git commit -m "fix(assessment): infer university band from school"
```

---

### Task 2: Repair option DOM and polish the assessment runner

**Files:**
- Modify: `tests/learner_assessment_ui_test.js`
- Modify: `assets/js/learner-assessment.js:582-605`
- Modify: `assets/css/learner.css:4859-4985,5116-5142`

**Interfaces:**
- Produces: `renderLikertOption(doc, option, selectedValue): HTMLLabelElement`, exported for unit testing and used by the runner view.
- Preserves: radio name `assessment-answer`, option values, `change` event delegation, and safe `textContent` rendering.

- [ ] **Step 1: Write failing DOM and CSS regression tests**

Add tests that express the desired option structure and flexible column:

```js
test('likert option separates its badge, label, and selected indicator', () => {
  const { renderLikertOption } = require(modulePath);
  const doc = { createElement: (tag) => createDomNode(tag) };
  const option = renderLikertOption(doc, { value: 1, label: 'Hoàn toàn không đồng ý với phát biểu này' }, '1');
  const input = option.children[0];
  const shell = option.children[1];

  assert.equal(input.name, 'assessment-answer');
  assert.equal(input.checked, true);
  assert.equal(shell.children[0].className, 'learner-likert-option__value');
  assert.equal(shell.children[0].textContent, '1');
  assert.equal(shell.children[1].className, 'learner-likert-option__label');
  assert.equal(shell.children[1].textContent, 'Hoàn toàn không đồng ý với phát biểu này');
  assert.equal(shell.children[2].className, 'learner-likert-option__check');
});

test('likert CSS gives answer text a flexible non-zero-width column', () => {
  const css = fs.readFileSync(path.join(__dirname, '..', 'assets', 'css', 'learner.css'), 'utf8');
  assert.match(css, /grid-template-columns:\s*42px minmax\(0, 1fr\) 24px/);
  assert.match(css, /\.learner-likert-option__label\s*\{[^}]*min-width:\s*0;/s);
});
```

- [ ] **Step 2: Run the UI test and verify RED**

Run:

```powershell
node tests/learner_assessment_ui_test.js
```

Expected: FAIL because `renderLikertOption` is not exported and the current CSS has no flexible label selector.

- [ ] **Step 3: Implement the option rendering helper**

Add and export this helper, then replace the inline option construction in `renderQuestion`:

```js
function renderLikertOption(doc, option, selectedValue) {
    const value = typeof option === 'object' ? option.value : option;
    const labelText = typeof option === 'object' ? (option.label ?? option.value) : option;
    const label = doc.createElement('label');
    label.className = 'learner-likert-option';

    const input = doc.createElement('input');
    input.type = 'radio';
    input.name = 'assessment-answer';
    input.value = String(value ?? '');
    input.checked = String(selectedValue ?? '') === String(value ?? '');

    const shell = doc.createElement('span');
    const badge = doc.createElement('b');
    badge.className = 'learner-likert-option__value';
    badge.setAttribute('aria-hidden', 'true');
    badge.textContent = String(value ?? '');
    const text = doc.createElement('span');
    text.className = 'learner-likert-option__label';
    text.textContent = String(labelText ?? '');
    const check = doc.createElement('span');
    check.className = 'learner-likert-option__check';
    check.setAttribute('aria-hidden', 'true');
    check.textContent = '✓';
    shell.append(badge, text, check);
    label.append(input, shell);
    return label;
}
```

Use `renderLikertOption(doc, option, answers[question.id])` for each option. Add it to the module export object used by Node tests.

- [ ] **Step 4: Apply the focused visual refresh**

Update the option shell to a stable three-column card and add clear states:

```css
.learner-likert-option > span {
    display: grid;
    min-height: 64px;
    padding: 11px 16px;
    grid-template-columns: 42px minmax(0, 1fr) 24px;
    align-items: center;
    gap: 14px;
    border: 1px solid #dbe4f0;
    border-radius: 14px;
    background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
    box-shadow: 0 5px 16px rgba(15, 23, 42, 0.04);
}

.learner-likert-option__value {
    display: inline-flex;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.learner-likert-option__label {
    min-width: 0;
    color: var(--text-primary);
    font-size: 0.94rem;
    line-height: 1.5;
    overflow-wrap: break-word;
}

.learner-likert-option__check {
    display: inline-flex;
    width: 22px;
    height: 22px;
    align-items: center;
    justify-content: center;
    opacity: 0;
    border-radius: 50%;
}

.learner-likert-option input:checked + span {
    border-color: var(--primary);
    background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%);
    box-shadow: 0 8px 22px rgba(37, 99, 235, 0.12);
    transform: translateY(-1px);
}

.learner-likert-option input:checked + span .learner-likert-option__check {
    color: #fff;
    background: var(--primary);
    opacity: 1;
}
```

Polish the existing runner header, question card, navigator, and mobile rules without changing HTML or behavior. On screens at most 620px, use `grid-template-columns: 36px minmax(0, 1fr) 22px`, reduce card padding, and keep actions and labels within the viewport.

- [ ] **Step 5: Run UI tests and verify GREEN**

Run:

```powershell
node tests/learner_assessment_ui_test.js
```

Expected: all UI tests pass, including the new DOM and CSS regressions.

- [ ] **Step 6: Commit the runner UI change**

```powershell
git add -- assets/js/learner-assessment.js assets/css/learner.css tests/learner_assessment_ui_test.js
git commit -m "feat(assessment): refresh question answer cards"
```

---

### Task 3: Full verification and Edge E2E

**Files:**
- No production files expected.
- Temporary E2E harness may be created under `.tmp/` and must not be committed.

**Interfaces:**
- Verifies the combined server inference and browser layout behavior.

- [ ] **Step 1: Run PHP lint and focused suites**

```powershell
$php = 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
& $php -l app/learner/assessment/Service/EducationBandResolver.php
& $php tests/learner_assessment_catalog_test.php
& $php tests/learner_assessment_api_test.php
& $php tests/learner_onboarding_endpoint_test.php
& $php tests/learner_onboarding_gate_test.php
& $php tests/learner_onboarding_service_test.php
node tests/learner_assessment_ui_test.js
node tests/learner_onboarding_ui_test.js
```

Expected: lint reports no syntax errors; every PHP script exits 0; Node reports zero failures.

- [ ] **Step 2: Verify database and migration state is unchanged**

```powershell
& $php bin/connect-check.php --quick
& $php bin/migrate.php validate
git diff --check
```

Expected: DB connection OK, migration validation OK, and no whitespace errors. `bin/migrate.php status` must show no new or pending migration from this change.

- [ ] **Step 3: Run visible Edge E2E with a new FPT university learner**

Exercise registration, mandatory onboarding acceptance, and all four assessments. Assert:

```text
registration school = Đại học FPT
registration class = Năm 1
Holland detail education_band = college without chooser
after Holland submit URL = assessment.php?code=mbti
MBTI band chooser hidden
after MBTI submit URL = assessment.php?code=disc
DISC band chooser hidden
after DISC submit URL = assessment.php?code=multiple_intelligence
Multiple Intelligence band chooser hidden
answer option label occupies a normal-width column at desktop and mobile viewport
logout/login history and resume remain available
final onboarding status = completed
```

- [ ] **Step 4: Inspect final database evidence**

For the E2E email, query `learner_onboarding_states`, `test_attempts`, `test_results`, and `learner_assessment_answers`. Expected: `completed`, four submitted attempts, four results, and answer counts 30/32/28/32.

- [ ] **Step 5: Review commits and working tree**

```powershell
git log -3 --oneline
git status --short
```

Expected: only the planned commits are new; unrelated user files remain untracked and untouched.
