# Learner Centered Brand Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Match the learner sidebar logo and `Khu vực Học sinh` subtitle to the approved centered school-reference lockup.

**Architecture:** The learner sidebar will render its existing learner-specific star mark and TalentHub wordmark instead of the shared wide SVG. Scoped learner CSS centers the brand block, sizes the orange rounded-square icon, and keeps the subtitle beneath the wordmark.

**Tech Stack:** PHP 8 templates, scoped CSS, Node.js built-in test runner.

## Global Constraints

- Modify only `app/learner/includes/sidebar.php`, `assets/css/learner.css`, and `tests/learner_sidebar_banner_ui_test.js`.
- Do not alter `assets/images/logo.svg` or school/teacher/enterprise files.
- Preserve the accessible home link and the learner subtitle text `Khu vực Học sinh`.

---

### Task 1: Replace the learner brand lockup and verify it

**Files:**
- Modify: `tests/learner_sidebar_banner_ui_test.js`
- Modify: `app/learner/includes/sidebar.php:12-17`
- Modify: `assets/css/learner.css:77-132`

**Interfaces:**
- Consumes: `learner_icon('star', 20)` and the existing learner brand class names.
- Produces: an accessible learner brand link with `learner-brand__mark`, `learner-brand__name`, and centered `Khu vực Học sinh` subtitle.

- [x] **Step 1: Write the failing regression assertion**

```js
test('learner sidebar uses the centered icon and wordmark lockup', () => {
    const sidebar = read('app/learner/includes/sidebar.php');
    const css = read('assets/css/learner.css');

    assert.match(sidebar, /class="learner-brand__mark"/);
    assert.match(sidebar, /class="learner-brand__name">Talent<span>Hub<\/span>/);
    assert.match(sidebar, />Khu vực Học sinh</);
    assert.doesNotMatch(sidebar, /learner-brand__logo/);
    assert.match(css, /\.learner-sidebar__brand\s*\{[\s\S]*text-align:\s*center/);
    assert.match(css, /\.learner-brand__mark\s*\{[\s\S]*width:\s*36px/);
});
```

- [x] **Step 2: Run the focused test to verify it fails**

Run: `node --test tests/learner_sidebar_banner_ui_test.js`

Expected: FAIL because the sidebar still uses `learner-brand__logo` and the brand is not centered.

- [x] **Step 3: Replace the sidebar markup**

```php
<a class="learner-brand" href="../../index.php" aria-label="Về trang chủ TalentHub">
    <span class="learner-brand__mark" aria-hidden="true"><?= learner_icon('star', 20); ?></span>
    <span class="learner-brand__name">Talent<span>Hub</span></span>
</a>
<p>Khu vực Học sinh</p>
```

- [x] **Step 4: Apply the centered learner-only CSS**

```css
.learner-sidebar__brand { padding: 0 8px 20px; text-align: center; }
.learner-sidebar__brand p { margin: 5px 0 0; line-height: 1.2; }
.learner-brand { justify-content: center; gap: 10px; }
.learner-brand__mark { width: 36px; height: 36px; flex-basis: 36px; border-radius: 8px; }
```

- [x] **Step 5: Run focused verification**

Run: `node --test tests/learner_sidebar_banner_ui_test.js`

Expected: PASS with 5 tests and 0 failures.

- [x] **Step 6: Run PHP syntax and whitespace checks**

Run: `D:\xampp\php\php.exe -l app/learner/includes/sidebar.php` and `git diff --check -- app/learner/includes/sidebar.php assets/css/learner.css tests/learner_sidebar_banner_ui_test.js`

Expected: PHP reports no syntax errors and the diff check emits no whitespace errors.

- [ ] **Step 7: Commit only scoped work**

```bash
git add -- app/learner/includes/sidebar.php assets/css/learner.css tests/learner_sidebar_banner_ui_test.js docs/superpowers/plans/2026-08-16-learner-centered-brand.md
git commit -m "fix(student): center learner sidebar brand"
```
