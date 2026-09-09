# Assessment Runner UI Optimization & Anti-Jumping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize the assessment test runner interface across all 4 tests (DISC, Holland, MBTI, Multiple Intelligence) to eliminate vertical overflow on standard laptop viewports and pin the action buttons to permanently prevent layout shifts ("nhảy nút").

**Architecture:** Pure CSS enhancements in `assets/css/learner.css` leveraging flexbox (`display: flex; flex-direction: column; margin-top: auto`) for pinned bottom actions, compact Likert option rows (46px instead of 64px), balanced typography, and automatic cache-busting via `filemtime` in `app/learner/assessment.php`.

**Tech Stack:** CSS3 (Flexbox/Grid, clamp typography), PHP 8.3, Vanilla JavaScript, Node.js test runner.

## Global Constraints

- Must apply uniformly across all 4 tests: DISC, Holland, MBTI, Multiple Intelligence.
- Must eliminate vertical scroll on standard 1366×768 laptop viewports.
- Action buttons ("Câu trước", "Câu tiếp") must maintain a strictly stable position across consecutive questions regardless of prompt text length.
- Option rows must remain touch-accessible and keyboard-accessible (`:focus-visible`).
- Existing test suites must pass without regressions.

---

### Task 1: Write Automated Contract Test for Runner Layout & Anti-Jumping

**Files:**
- Create: `tests/learner_assessment_runner_layout_test.js`
- Read: `assets/css/learner.css`

**Interfaces:**
- Validates that `learner.css` defines the compact Likert surface (`min-height: 46px` or `min-height: 48px`, `gap: 8px`), flexbox column question card with `margin-top: auto` on actions, bounded heading font sizes, and question navigator grid gap.

- [ ] **Step 1: Write the failing test**

Create `tests/learner_assessment_runner_layout_test.js`:
```javascript
'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const cssPath = path.resolve(__dirname, '../assets/css/learner.css');
const css = fs.readFileSync(cssPath, 'utf8');

test('assessment runner header has compact padding', () => {
    assert.match(css, /\.learner-assessment-runner__header\s*\{[^}]*padding:\s*1[2-4]px\s*20px/);
});

test('question card uses flex column with pinned actions to prevent layout shift', () => {
    assert.match(css, /\.learner-question-card\s*\{[^}]*display:\s*flex/);
    assert.match(css, /\.learner-question-card\s*\{[^}]*flex-direction:\s*column/);
    assert.match(css, /\.learner-question-card__actions\s*\{[^}]*margin-top:\s*auto/);
    assert.match(css, /\.learner-question-card__actions\s*\{[^}]*border-top:/);
});

test('likert options have compact min-height and spacing', () => {
    assert.match(css, /\.learner-likert-options\s*\{[^}]*gap:\s*8px/);
    assert.match(css, /\.learner-likert-option__surface\s*\{[^}]*min-height:\s*4[6-8]px/);
    assert.match(css, /\.learner-likert-option__value\s*\{[^}]*width:\s*3[0-2]px/);
    assert.match(css, /\.learner-likert-option__value\s*\{[^}]*height:\s*3[0-2]px/);
});

test('question heading font size is balanced to avoid overflowing', () => {
    assert.match(css, /\.learner-question-card\s+h2\s*\{[^}]*clamp\(1\.1[5-8]rem/);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/learner_assessment_runner_layout_test.js`
Expected: FAIL on compact header, flex column, pinned actions, or compact min-height.

---

### Task 2: Implement Compact Header, Question Card & Pinned Actions Styling

**Files:**
- Modify: `assets/css/learner.css:7368-7550`

- [ ] **Step 1: Update CSS rules in `assets/css/learner.css`**

Update:
1. `.learner-assessment-runner__header`:
   ```css
   .learner-assessment-runner__header {
       display: grid;
       padding: 12px 20px;
       grid-template-columns: minmax(0, 1fr) auto;
       align-items: center;
       gap: 6px 16px;
       background: linear-gradient(120deg, var(--surface), var(--primary-light));
       border-color: rgba(249, 115, 22, 0.22);
       box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
   }
   ```
2. `.learner-question-card`:
   ```css
   .learner-question-card {
       display: flex;
       flex-direction: column;
       min-height: 470px;
       padding: 20px 24px;
       scroll-margin-top: calc(var(--learner-header-height) + 120px);
       border-color: rgba(15, 23, 42, 0.09);
       box-shadow: 0 16px 36px rgba(15, 23, 42, 0.06);
   }
   ```
3. `.learner-question-card h2`:
   ```css
   .learner-question-card h2 {
       max-width: 850px;
       margin: 6px 0 16px;
       font-size: clamp(1.18rem, 1.6vw, 1.35rem);
       line-height: 1.4;
   }
   ```
4. `.learner-likert-options`:
   ```css
   .learner-likert-options {
       display: grid;
       padding: 0;
       gap: 8px;
       border: 0;
   }
   ```
5. `.learner-likert-option__surface`:
   ```css
   .learner-likert-option__surface {
       display: grid;
       min-height: 46px;
       padding: 6px 14px;
       grid-template-columns: 32px minmax(0, 1fr) 22px;
       align-items: center;
       gap: 12px;
       color: var(--text-secondary);
       background: linear-gradient(135deg, var(--surface), rgba(248, 250, 252, 0.92));
       border: 1px solid var(--border);
       border-radius: 10px;
       box-shadow: 0 3px 10px rgba(15, 23, 42, 0.03);
       transition: border-color var(--transition-fast), background var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
   }
   ```
6. `.learner-likert-option__value`:
   ```css
   .learner-likert-option__value {
       display: inline-flex;
       width: 30px;
       height: 30px;
       align-items: center;
       justify-content: center;
       color: var(--secondary);
       background: var(--secondary-light);
       border-radius: 50%;
       font-size: 0.85rem;
       font-weight: 600;
       font-variant-numeric: tabular-nums;
   }
   ```
7. `.learner-likert-option__label`:
   ```css
   .learner-likert-option__label {
       min-width: 0;
       color: var(--text-primary);
       font-size: 0.92rem;
       font-weight: 500;
       line-height: 1.4;
       overflow-wrap: break-word;
   }
   ```
8. `.learner-question-card__actions`:
   ```css
   .learner-question-card__actions {
       display: flex;
       margin-top: auto;
       padding-top: 16px;
       border-top: 1px solid rgba(15, 23, 42, 0.07);
       align-items: center;
       justify-content: space-between;
       gap: 12px;
   }
   ```
9. `.learner-question-navigator`:
   ```css
   .learner-question-navigator {
       position: sticky;
       top: calc(var(--learner-header-height) + 110px);
       padding: 16px 18px;
   }
   .learner-question-navigator__grid {
       display: grid;
       margin-top: 14px;
       grid-template-columns: repeat(6, 1fr);
       gap: 6px;
   }
   .learner-question-navigator__grid button {
       aspect-ratio: 1;
       min-height: 36px;
       border-radius: 8px;
       font-size: 0.85rem;
   }
   ```

- [ ] **Step 2: Run layout test to verify it passes**

Run: `node tests/learner_assessment_runner_layout_test.js`
Expected: All 4 tests PASS.

---

### Task 3: Update CSS Cache-Buster & Validate Markup

**Files:**
- Modify: `app/learner/assessment.php` (verify `learner.css?v=...` is present)

- [ ] **Step 1: Check and ensure `learner.css` has `filemtime` in `assessment.php`**
- [ ] **Step 2: Run PHP syntax check**

Run: `php -l app/learner/assessment.php`
Expected: `No syntax errors detected in app/learner/assessment.php`

---

### Task 4: Full Regression Testing & Live Verification

**Files:**
- Test: `tests/learner_assessment_runner_layout_test.js`
- Test: `tests/learner_assessment_retake_contract_test.js`
- Test: `tests/learner_holland_ui_test.js`
- Test: `tests/learner_assessment_catalog_test.php`
- Test: `tests/learner_assessment_persistence_test.php`

- [ ] **Step 1: Run all test suites**
- [ ] **Step 2: Verify live server output**
