# Design Document: Assessment Runner UI Optimization & Anti-Jumping Navigation

- **Date:** 2026-09-09
- **Status:** Approved
- **Scope:** All 4 learner assessments (DISC, Holland, MBTI, Multiple Intelligence) in `app/learner/assessment.php`

---

## 1. Problem Statement & User Pain Points

When learners take any of the 4 aptitude tests:
1. **Vertical Overflow & Forced Scrolling:**
   - The question card (`.learner-question-card`) has extensive vertical padding (`40px`), oversized heading fonts (`clamp(1.35rem, 2.3vw, 1.9rem)`), and oversized Likert option blocks (`min-height: 64px`, `gap: 12px`, total ~368px for 5 options).
   - Together with the runner header (~120px) and page navigation, the total vertical height reaches **850px+**.
   - On standard laptop displays (viewport height ~650px–750px), the navigation actions ("Câu trước" and "Câu tiếp") are pushed below the screen fold, forcing learners to scroll down on every question to click "Câu tiếp".
2. **Layout Shift ("Nhảy nút"):**
   - Because question statements vary in length (1 line vs 2–3 lines), the "Câu tiếp" button changes its vertical coordinate between consecutive questions.
   - Learners cannot keep their cursor stationary to proceed through questions smoothly.
3. **Question Navigator Alignment:**
   - In the 6-column question navigator grid, items on the 6th column (e.g., 6, 12, 18, 24) slightly touch or clip the container padding.

---

## 2. Design Goals & Principles

- **Zero-Scroll Viewport Fit:** Ensure the active runner layout (header + question card + options + action buttons + navigator) fits comfortably within standard laptop screens without requiring vertical scrolling.
- **Fixed Button Positioning (Anti-Jumping):** The action buttons ("Câu trước" and "Câu tiếp") are pinned to the bottom of the card with `margin-top: auto`, maintaining a stable, predictable position across all questions regardless of prompt text length.
- **Visual Polish & Modern Likert Styling:** Streamline option items into sleek, touch-friendly rows (46px height) with proportional badges, clear contrast, and smooth transitions.
- **Cross-Framework Consistency:** Apply uniformly across all 4 assessments: DISC, Holland, MBTI, and Multiple Intelligence.

---

## 3. Detailed Technical Specifications

### 3.1 Runner Header (`.learner-assessment-runner__header`)
- Reduce padding from `20px 24px` to `12px 20px`.
- Align title, version tag, and timer neatly on the first row; progress bar and status text on the second row.
- Total header height reduced from ~130px to ~75px.

### 3.2 Question Card Geometry (`.learner-question-card`)
- Padding: Adjusted from `clamp(24px, 4vw, 40px)` to `20px 24px`.
- Container Layout: Set `display: flex; flex-direction: column; min-height: 480px; max-height: calc(100vh - 170px);`.
- Question Heading (`h2`):
  - Font size adjusted from `clamp(1.35rem, 2.3vw, 1.9rem)` to `clamp(1.18rem, 1.6vw, 1.35rem)`.
  - Line height: `1.4`.
  - Margin: `6px 0 16px`.
  - Accommodates up to 3 lines within ~60px without expanding the card.

### 3.3 Likert Options Styling (`.learner-likert-options`)
- Container: `display: grid; gap: 8px;` (reduced from `12px`).
- Option Surface (`.learner-likert-option__surface`):
  - `min-height`: Reduced from `64px` to `46px`.
  - `padding`: Reduced from `10px 16px` to `6px 14px`.
  - Grid columns: `32px minmax(0, 1fr) 22px; gap: 12px;`.
  - Border radius: `10px` (modern, clean).
- Badge Value (`.learner-likert-option__value`):
  - Size: `30px × 30px` (reduced from `40px × 40px`).
  - Font size: `0.85rem`, bold, centered.
- Label (`.learner-likert-option__label`):
  - Font size: `0.92rem`, font weight: `500`.
- Check Indicator (`.learner-likert-option__check`):
  - Size: `20px × 20px`.
- 5-Option Total Height: `5 × 46px + 4 × 8px = 262px` (savings of **106px**).

### 3.4 Pinned Action Bar (`.learner-question-card__actions`)
- Position: `margin-top: auto; padding-top: 14px; border-top: 1px solid rgba(15, 23, 42, 0.07);`.
- Alignment: `display: flex; align-items: center; justify-content: space-between; gap: 12px;`.
- Buttons:
  - Height: `40px`.
  - Padding: `0 20px`.
  - Font size: `0.92rem`.
  - "Câu trước": Secondary outline/surface.
  - "Câu tiếp": Primary accent with subtle hover elevation.
- Guarantee: The buttons never move up or down when navigating between short and long questions because the parent card maintains a stable flex structure with `margin-top: auto`.

### 3.5 Question Navigator (`.learner-question-navigator`)
- Sticky offset updated: `top: calc(var(--learner-header-height) + 100px);`.
- Padding: `16px 18px`.
- Grid: `grid-template-columns: repeat(6, 1fr); gap: 6px;`.
- Item buttons: `aspect-ratio: 1; min-height: 36px; border-radius: 8px; font-size: 0.85rem;`.
- Prevents horizontal badge clipping on column 6.

---

## 4. Verification Plan

1. **Visual & Responsive Verification:**
   - Test on 1366×768 (standard laptop viewport) and 1920×1080.
   - Verify that questions with 1 line, 2 lines, and 3+ lines keep the "Câu tiếp" button at the exact same screen position.
   - Verify zero vertical scrollbar on standard viewport.
2. **Framework Uniformity:**
   - Check DISC (28 questions, 5 Likert options).
   - Check Holland (30 questions, 5 Likert options).
   - Check MBTI (32 questions, 2 Likert options / binary choice).
   - Check Multiple Intelligence (32 questions, 5 Likert options).
3. **Automated Regression Tests:**
   - `node tests/learner_assessment_retake_contract_test.js`
   - `node tests/learner_holland_ui_test.js`
   - `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests/learner_assessment_catalog_test.php`
   - `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests/learner_assessment_persistence_test.php`
