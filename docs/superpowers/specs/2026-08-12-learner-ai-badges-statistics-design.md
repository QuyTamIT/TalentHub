# Learner AI Suggestions, Badges, and Personal Statistics Design

## Goal

Complete the TalentHub Learner portal with three frontend-only pages for AI guidance, badges and levels, and personal statistics. The implementation extends the approved Learner shell and design tokens, keeps all data replaceable by a future API or database, and preserves the six existing Learner pages and every other role area.

## Confirmed Routes

- `/app/learner/ai-recommendations.php` — AI phân tích năng lực
- `/app/learner/badges.php` — Huy hiệu và cấp độ
- `/app/learner/statistics.php` — Thống kê cá nhân

The request names `app/student`, but the repository and the user's earlier role-path decision use `app/learner`. Creating a second role tree would duplicate the portal and cause inconsistent navigation, so the final three pages remain under `app/learner`.

## Selected Architecture

The approved approach is server-rendered PHP with progressive JavaScript enhancement:

1. PHP renders the complete default state so every page remains meaningful before JavaScript runs.
2. `student-data.php` owns all mock records and stable identifiers.
3. `learner.js` enhances only dynamic states: simulated AI loading, badge filtering, and statistics period changes.
4. Safe JSON payloads provide alternate mock states without copying data into page markup or JavaScript.
5. Charts use semantic inline SVG and CSS; no Chart.js package or frontend framework is added.

This approach supports accessibility, avoids a JavaScript-only blank page, and provides a direct replacement boundary for a future API response.

## Isolation and Shared Components

Implementation changes are limited to:

- `app/learner/ai-recommendations.php`
- `app/learner/badges.php`
- `app/learner/statistics.php`
- `app/learner/includes/student-data.php`
- `app/learner/includes/icons.php` only if an icon is missing from its whitelist
- `assets/css/learner.css`
- `assets/js/learner.js`
- Learner-specific tests under `tests/`
- Learner-specific design and plan documents

The existing `sidebar.php`, `header.php`, `.learner-card`, `.learner-btn`, `.learner-progress`, toast, modal, typography, spacing, focus, and responsive drawer patterns are reused. No second layout, stylesheet, JavaScript bundle, or design system is created.

The implementation must not modify `app/enterprise/**`, Enterprise assets, home page markup/assets, database files, or code owned by future School and Teacher areas. New classes, IDs, data attributes, and JavaScript exports stay under the `learner-` or `LearnerUI` namespace.

## Shared Navigation

`$learnerNav` continues to contain exactly nine unique entries. The final three routes are changed from pending to implemented, and the AI route is corrected from the provisional `ai-suggestions.php` path to `ai-recommendations.php`. Each new page sets `$currentRoute`, allowing the existing sidebar comparison to provide one and only one active item.

After this extension, all nine sidebar links route to real Learner pages:

1. Tổng quan
2. Hồ sơ năng lực
3. Khám phá năng khiếu
4. Hoạt động
5. Check-in QR
6. Đánh giá
7. AI gợi ý
8. Huy hiệu
9. Thống kê

## Mock Data Boundary

All new data lives in `app/learner/includes/student-data.php`. Pages may derive display percentages from those records but do not define content arrays locally.

### AI guidance data

The AI dataset contains:

- A `sufficient` flag and analysis timestamp.
- Summary copy highlighting IoT and Drone.
- Three groups for strengths, improvement areas, and development potential.
- Three monthly roadmap steps.
- A disclaimer explaining that the analysis is directional and not a professional assessment.

The default mock record has sufficient data. The page also contains a reusable insufficient-data state that becomes active if the future API or mock payload sets `sufficient` to false.

### Level and badge data

Level records contain stable IDs, names, required hours, display state, tone, and current progress. They represent Explorer, Innovator, Expert, and Master. Innovator is the current level, and progress toward Expert is `64/100` hours.

Six badge records contain ID, name, description, icon, status, current value, target value, and display tone. Status IDs are `achieved`, `in_progress`, and `locked`; visible Vietnamese labels are kept in the data provider. Only achieved badges use success green. In-progress badges use the primary orange, and locked badges use neutral styling.

### Personal statistics data

Statistics are keyed by stable period IDs such as six, three, and twelve months. Each period contains:

- Four personal KPI records.
- Monthly experience-hour series and comparison series.
- Field allocation records whose hours add up to the period total.
- Four skill-progress records.
- Registration, check-in, completion, and cancellation totals.

No school-wide averages, rankings of other students, or personally identifiable data from another learner is included.

## AI Suggestions Page

The heading reads “AI phân tích năng lực” and follows the mockup with a blue AI icon and supporting copy. A secondary-light summary panel highlights IoT and Drone. Three cards present Điểm mạnh, Cần cải thiện, and Khả năng phát triển, followed by a three-month roadmap and a real CTA to `/app/learner/activities.php`.

On initial load, JavaScript briefly exposes a simulated loading panel, then restores the server-rendered analysis. The loading transition is skipped when reduced motion is requested. If the data record is insufficient, the analysis cards and roadmap are hidden and a clear empty state explains which profile activities or assessments are needed before analysis can be generated. No network request or AI service call occurs.

The directional disclaimer remains visible in all states. Dynamic state text is announced politely without moving focus.

## Badges and Levels Page

The heading reads “Huy hiệu và cấp độ”. A wide level card shows Innovator as the current level, `64/100` hours toward Expert, and the four-level path Explorer → Innovator → Expert → Master. Current, achieved, and locked level states are visually distinct without introducing colors outside the established tokens.

The collection contains six badge cards. Each card includes its icon, title, description, state label, semantic progress bar, and current/target value. A filter row provides Tất cả, Đã đạt, Đang tiến hành, and Chưa đạt.

JavaScript combines only the selected badge status, updates `aria-pressed`, hides nonmatching cards with the `hidden` attribute, and updates a polite result count. A filter with no matches exposes an empty state even though the default mock dataset supplies at least one badge for every status.

## Personal Statistics Page

The heading reads “Thống kê cá nhân” and includes a labeled period selector. PHP renders the default six-month period. Four KPI cards show Giờ trải nghiệm, Huy hiệu, Hoạt động hoàn thành, and Điểm năng lực.

The content area contains:

- An accessible inline SVG combination chart with monthly orange bars for experience hours and a blue comparison line.
- An accessible SVG donut chart for personal field allocation, accompanied by a text legend with exact hours and percentages.
- A skill-progress list using the existing semantic progress component.
- A four-item activity summary for Đăng ký, Đã check-in, Hoàn thành, and Đã hủy.

Changing the selected period resolves the matching mock record and updates every visible number, chart geometry, legend, skill row, and summary without reloading. SVG elements are constructed with `createElementNS`, and text is assigned with `textContent`; no untrusted string is injected with `innerHTML`. If an unknown or empty period is received, a polite empty state replaces the statistics content while leaving the selector usable.

## JavaScript Interfaces

Pure functions are exported on `LearnerUI` before DOM initialization for Node testing:

- `getAiRecommendationState(data)` returns `ready` or `insufficient`.
- `badgeMatchesStatus(badgeStatus, activeStatus)` returns whether a badge remains visible.
- `getStatisticsPeriod(periods, periodId)` returns a known period or `null`.
- `buildLineChartPoints(values, width, height, maxValue)` returns safe SVG point coordinates for a numeric series.

DOMContentLoaded behavior stays page-safe by requiring page-specific markers before initializing. Existing sidebar, modal, profile, discovery, activities, check-in, and evaluation behavior remains unchanged.

## Accessibility and Responsive Behavior

- Icon-only buttons and chart SVGs receive meaningful accessible labels.
- Filter state uses `aria-pressed`; selector fields use explicit labels.
- All progress bars expose minimum, maximum, and current values.
- Dynamic counts, loading completion, insufficient-data states, and statistics empty states use polite live regions.
- Charts include a short textual summary so information is not color-only.
- Green is reserved for achieved/success states.
- Focus-visible styling and reduced-motion behavior reuse the current Learner foundations.
- Desktop retains the fixed sidebar and mockup-aligned multi-column layouts.
- Tablet uses the existing accessible drawer and reduces grids to two or one columns.
- Mobile stacks all panels, makes filters horizontally scrollable or wrapping, and prevents SVG/chart overflow.

## Verification Strategy

Development follows red-green-refactor cycles:

1. Add failing PHP assertions for the three routes, nine unique navigation entries, mock data, semantic markup, safe JSON, and page active states.
2. Add failing Node tests for AI state resolution, badge matching, statistics period resolution, and chart point generation.
3. Implement the minimum data and markup required to pass the PHP checks.
4. Add page-safe JavaScript enhancements and make Node tests pass.
5. Extend the existing stylesheet without Enterprise selectors or prohibited gradients.
6. Extend browser smoke coverage for loading AI, insufficient fallback, badge filtering, period switching, SVG updates, all sidebar routes, and keyboard/mobile navigation.
7. Lint every PHP file under `app/learner` and syntax-check JavaScript.
8. Open all nine Learner pages at desktop, tablet, and mobile viewports, checking HTTP status, overflow, active navigation, and console errors.
9. Compare pages 07–09 against the supplied mockups.
10. Run `git diff --check` and verify that Enterprise and home page files are absent from the extension diff.

## Acceptance Criteria

- The three new `app/learner` URLs return HTTP 200 and complete the nine-link sidebar without duplicate or pending items.
- Default content is server-rendered and remains readable without JavaScript.
- AI loading and insufficient-data states work without any real AI request.
- Badge filtering updates cards, filter state, result count, and empty state without reload.
- Statistics period changes update personal KPIs, both SVG charts, skills, and activity totals without exposing school-wide or other-student data.
- All data values are sourced from `student-data.php` and escaped or safely serialized.
- All nine pages remain responsive, keyboard accessible, and free of PHP or browser console errors.
- Existing Learner pages, Enterprise files, and home page files remain unaffected.
