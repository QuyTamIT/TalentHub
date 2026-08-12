# Learner Activities, Check-in, and Evaluation Design

## Goal

Extend the existing TalentHub Learner portal with three frontend-only pages for activities, QR check-in, and competency evaluation. The implementation must match the approved orange, blue, and green visual system, reuse the current Learner shell, and preserve the first three Learner pages.

## Confirmed Routes

- `/app/learner/activities.php` — Khám phá hoạt động
- `/app/learner/checkin.php` — Check-in trải nghiệm
- `/app/learner/evaluation.php` — Đánh giá năng lực

The repository does not contain `app/student`. The existing role area is `app/learner`, so all new pages and routes stay under that directory.

## Isolation and Conflict Prevention

Implementation changes are limited to:

- `app/learner/activities.php`
- `app/learner/checkin.php`
- `app/learner/evaluation.php`
- `app/learner/includes/student-data.php`
- `app/learner/includes/icons.php` only if a missing whitelisted icon is required
- `assets/css/learner.css`
- `assets/js/learner.js`
- Learner-specific test files under `tests/`
- Existing Learner overview links that still mark the activities route as pending

The implementation must not modify:

- `app/enterprise/**`
- `assets/css/enterprise.css`
- `assets/js/enterprise.js`
- Home page assets or markup
- Future Teacher or School role code
- Database files, schemas, or APIs

All new selectors, IDs, data attributes, and exported JavaScript behavior remain prefixed or scoped to `learner-`/`LearnerUI`. A final Git diff check will verify that no Enterprise files changed.

## Shared Architecture

Each page follows the existing structure:

1. Require `includes/student-data.php` and `includes/icons.php`.
2. Set `$pageTitle` and `$currentRoute`.
3. Include the existing `includes/sidebar.php` and `includes/header.php`.
4. Load the existing `home.css`, `learner.css`, and `learner.js` assets.
5. Render page-specific semantic content inside `<main class="learner-content">`.

No second layout, sidebar, header, stylesheet, or JavaScript bundle will be created. Existing shared classes such as `.learner-card`, `.learner-btn`, `.learner-progress`, `.learner-modal`, `.learner-page-heading`, badges, toast, and responsive drawer behavior will be reused.

The Learner navigation data will mark the three new routes as implemented. The active item is determined by the existing `$currentRoute` comparison. AI suggestions, badges, and statistics remain pending routes.

## Mock Data

All page data remains in `app/learner/includes/student-data.php`.

### Activity catalog

Six records contain:

- Stable ID
- Category and tone
- Title
- Time
- Location
- Participant count
- Capacity
- Derived remaining places

The existing overview uses a three-item subset of this same catalog, avoiding duplicate activity definitions.

Categories exposed to the filter are `Tất cả`, `Kỹ thuật`, `Kinh doanh`, `Sáng tạo`, and `Cộng đồng`. The AI Bootcamp record is grouped under `Kỹ thuật` while its visible badge can remain `Công nghệ`, matching the mockup without adding a sixth filter.

### Check-in history

Four records contain activity name, timestamp, location, earned hours, and confirmed state. Every rendered value is escaped through `learner_escape()`.

### Evaluation terms

Evaluation data is keyed by stable term ID. A published term contains label, status, four criteria, total score, classification, ranking, and reviewer comment. At least one term has no evaluation so the empty state can be exercised.

PHP renders the default term. The complete term dataset is serialized with `json_encode()` using safe JSON hex flags for frontend switching without a backend request.

## Activities Page

The page heading reads “Khám phá hoạt động” with the approved supporting copy. The existing header search becomes the activity search on this page; its label and placeholder are adjusted through page-provided variables while the shared header markup stays unchanged.

Filter pills sit beside or below the heading depending on viewport width. Six activity cards form a three-column desktop grid, two-column tablet grid, and one-column mobile list. Each card includes category, title, time, location, participants/capacity, remaining places, a semantic progress bar, and a “Đăng ký ngay” button.

Search is case-insensitive and accent-insensitive across title, category, and location. Search and category filters combine. Hidden cards use the `hidden` attribute, and an `aria-live` empty state appears when no activity matches.

Selecting “Đăng ký ngay” opens the shared accessible modal with the selected activity name. Confirming closes the modal, disables only that card’s button, changes its text to “Đã đăng ký”, and shows a success toast. Cancelling makes no state change. State is frontend-only and resets on reload.

## Check-in Page

The page heading reads “Check-in trải nghiệm”. The main area uses a two-column desktop layout: QR card on the left and check-in history on the right. Tablet and mobile stack the cards.

The QR sample is an inline SVG with a deterministic black-and-white module pattern. It is presented as a clear visual sample and does not claim to encode production data. The surrounding card uses `--primary-light` with orange accents, never a pink-purple gradient.

“Mở camera quét” opens an accessible modal containing a simulated scan frame, an animated scan line that respects `prefers-reduced-motion`, and explicit copy stating that this is a demo interface. It does not call `getUserMedia()` or request camera permission.

History records show an icon, activity, time, location, earned hours, and the green “Đã xác nhận” badge.

## Evaluation Page

The heading reads “Đánh giá năng lực”. A labeled semester selector and publication status sit in the heading actions. The desktop body uses a wide criteria card and a narrower total-score summary card; mobile stacks them.

The criteria are `Chuyên môn`, `Sáng tạo`, `Kỷ luật`, and `Làm việc nhóm`. Each row includes the earned/max score and an accessible progress bar. The default term renders total `90/100`, classification `Xuất sắc`, and the latest teacher or coach comment.

Changing the semester updates the status, criteria, score, classification, ranking, and comment without reloading. If a term has no evaluation, both the criteria area and score summary switch to a clear empty state. The selector remains usable so the user can return to a published term.

## JavaScript Design

Pure functions are added to `LearnerUI` before DOM wiring so they can be tested in Node:

- Normalize Vietnamese text for matching.
- Decide whether an activity matches a query and category.
- Resolve evaluation data for a selected term.

DOMContentLoaded wiring remains page-safe: each initializer exits when its page markers are absent. Existing profile, discovery, sidebar, modal, toast, and role-navigation behavior must continue to work.

The shared modal controller remains responsible for focus movement, focus trapping, Escape handling, backdrop closing, and focus restoration. No inline `onclick` attributes are used.

## Accessibility and States

- Icon-only controls retain meaningful `aria-label` values.
- Filter state uses `aria-pressed`.
- Progress bars expose minimum, maximum, and current values.
- Empty results and dynamic status changes use polite live regions.
- Modal dialogs use `role="dialog"`, `aria-modal="true"`, and labelled headings.
- Disabled registration buttons remain visibly distinct.
- Keyboard focus indicators and reduced-motion behavior reuse existing foundations.

## Responsive Design

- Desktop: fixed sidebar, shared sticky header, mockup-aligned two- and three-column layouts.
- Tablet at `max-width: 1100px`: accessible drawer sidebar, two-column activity grid, stacked check-in and evaluation panels when needed.
- Mobile at `max-width: 720px`: compact header, one-column activity cards, stacked filter controls and panels, full-width modal actions.
- Narrow mobile at `max-width: 480px`: content and metadata wrap without horizontal overflow.

## Verification Strategy

Development follows red-green-refactor cycles:

1. Add failing PHP assertions for routes, data, semantic markup, active navigation, and safe escaping.
2. Add failing Node tests for activity matching and evaluation selection.
3. Implement the minimum PHP/data/JavaScript required to pass.
4. Add page styles and browser interaction tests.
5. Run PHP lint for every PHP file under `app/learner`.
6. Render all six Learner pages at desktop, tablet, and mobile viewports.
7. Verify activity search/filter/empty state, registration modal, QR demo modal, semester changes, evaluation empty state, mobile menu, keyboard focus, and console output.
8. Compare the three new pages against mockups 04–06.
9. Run the complete existing Learner suite to prove the first three pages still work.
10. Verify `git diff --check` and confirm no Enterprise file appears in the branch diff.

## Acceptance Criteria

- The three confirmed `app/learner` URLs return HTTP 200 and show the correct active sidebar entry.
- Page content and hierarchy closely follow the approved mockups using only established design tokens.
- Activity search, filtering, confirmation, and empty results work without reload.
- QR scanning is clearly identified as a demo and never requests camera permission.
- Semester switching updates published data and supports an empty term.
- All dynamic PHP values are escaped appropriately.
- All six Learner pages remain responsive and free of PHP or JavaScript console errors.
- No Enterprise, Teacher, School, database, or unrelated shared-role code is modified.
