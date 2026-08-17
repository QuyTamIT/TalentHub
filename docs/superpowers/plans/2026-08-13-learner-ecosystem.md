# Learner Ecosystem & Opportunities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the seven approved learner ecosystem views without modifying enterprise, database, or other-role code.

**Architecture:** A learner-owned adapter normalizes the read-only enterprise internship mocks and learner-owned school/application demo records. Three PHP routes render the hub, partner detail, and opportunity detail; modal and drawer states are implemented as accessible client-side interactions in the existing learner JS/CSS system.

**Tech Stack:** PHP 8.3, HTML5, existing TalentHub CSS tokens, vanilla JavaScript, Node `node:test`, PHP CLI, optional Playwright browser smoke tests.

## Global Constraints

- Modify production files only in `app/learner/**`, `assets/css/learner.css`, and `assets/js/learner.js`.
- Treat `app/enterprise/**`, `Database/**`, and non-learner roles as read-only.
- Read internship data through the existing enterprise mock provider without mutating it.
- Keep school data visibly marked `Dữ liệu minh họa`.
- Follow the approved seven mockups, Be Vietnam Pro typography, supplied color tokens, and existing learner responsive/accessibility patterns.
- Use test-first development for every data helper and JavaScript behavior.

---

### Task 1: Learner ecosystem data contract

**Files:**
- Create: `tests/learner_ecosystem_data_test.php`
- Create: `app/learner/includes/ecosystem-data.php`

**Interfaces:**
- Consumes: `getMockInternships(): array` from the read-only enterprise mock provider.
- Produces: `learner_ecosystem_enterprises()`, `learner_ecosystem_schools()`, `learner_ecosystem_opportunities()`, `learner_ecosystem_applications()`, `learner_ecosystem_partner(string $type, string $id)`, and `learner_ecosystem_opportunity(string $type, string|int $id)`.

- [ ] Write PHP assertions that require the provider and verify FPT Software, active/closed internship normalization, draft filtering, demo schools, applications, and null lookups.
- [ ] Run `php tests/learner_ecosystem_data_test.php` and confirm it fails because the provider is missing.
- [ ] Implement the minimal normalized data provider and lookup helpers.
- [ ] Run the PHP data test and confirm all assertions pass.

### Task 2: Navigation, icons, and route contract

**Files:**
- Create: `tests/learner_ecosystem_static_test.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/includes/icons.php`
- Modify: `assets/js/learner.js`

**Interfaces:**
- Consumes: existing `$learnerNav` and `learner_icon()` conventions.
- Produces: `/app/learner/ecosystem.php` navigation item and learner ecosystem icon names; `LearnerUI.isImplementedRoute()` recognizes all three routes.

- [ ] Write static assertions for one new sidebar entry, the three implemented routes, required icons, and an unchanged enterprise-tree hash.
- [ ] Run the test and confirm the missing route/icon assertions fail.
- [ ] Add the ecosystem navigation item, icons, and route recognition.
- [ ] Run static and existing learner unit checks.

### Task 3: Ecosystem hub and application drawer

**Files:**
- Create: `app/learner/ecosystem.php`
- Modify: `assets/css/learner.css`
- Modify: `assets/js/learner.js`
- Create: `tests/learner_ecosystem_ui_test.js`

**Interfaces:**
- Consumes: normalized schools, enterprises, opportunities, and applications.
- Produces: hub tabs/cards/filter data attributes; `LearnerUI.ecosystemItemMatches(item, filters)` and `LearnerUI.applicationMatches(application, query, status)`.

- [ ] Write Node tests for accent-insensitive search, per-tab filters, application status filtering, and empty matches.
- [ ] Run `node --test tests/learner_ecosystem_ui_test.js` and confirm the new helpers are missing.
- [ ] Add minimal pure helpers to `LearnerUI` and make tests pass.
- [ ] Build semantic hub markup for enterprise, school, and opportunity tabs plus the tracking drawer.
- [ ] Add scoped responsive styles and DOM wiring for tabs, filters, result counts, drawer focus, expansion, and mock withdrawal.
- [ ] Re-run Node tests and PHP/static checks.

### Task 4: Shared partner detail route

**Files:**
- Create: `app/learner/partner.php`
- Modify: `assets/css/learner.css`
- Modify: `tests/learner_ecosystem_static_test.php`

**Interfaces:**
- Consumes: `learner_ecosystem_partner()` and related normalized opportunities.
- Produces: enterprise and school detail variants plus accessible not-found state.

- [ ] Add failing static/render assertions for enterprise details, demo school details, and unknown partner handling.
- [ ] Run the static test and verify the new route assertions fail.
- [ ] Implement the shared partner detail page with type-specific sections.
- [ ] Add responsive partner page styles.
- [ ] Run PHP render/static tests until green.

### Task 5: Opportunity detail and application modal

**Files:**
- Create: `app/learner/opportunity.php`
- Modify: `assets/css/learner.css`
- Modify: `assets/js/learner.js`
- Modify: `tests/learner_ecosystem_ui_test.js`
- Modify: `tests/learner_ecosystem_static_test.php`

**Interfaces:**
- Consumes: `learner_ecosystem_opportunity()` and the learner Talent Passport summary.
- Produces: `LearnerUI.canApplyToOpportunity(opportunity, today)`, `LearnerUI.validateApplication(data)`, application modal state, and submitted CTA state.

- [ ] Add failing Node tests for active/closed/expired eligibility and required consent/message length validation.
- [ ] Add failing PHP render assertions for internship and school opportunity details plus unknown IDs.
- [ ] Implement pure JS helpers and make unit tests pass.
- [ ] Implement opportunity markup, closed state, Talent Passport preview, related opportunities, and accessible modal.
- [ ] Wire consent validation, focus management, submission feedback, and local submitted state.
- [ ] Add scoped responsive styles and re-run all focused tests.

### Task 6: Full responsive and regression verification

**Files:**
- Create or update: `tests/learner_ecosystem_browser_smoke.js` when bundled Playwright is available.
- Modify only if a verified defect is found: learner files from Tasks 1–5.

**Interfaces:**
- Consumes: all new learner routes and interactions.
- Produces: repeatable smoke evidence for seven approved views and three viewports.

- [ ] Start PHP with `php -S 127.0.0.1:8765 -t D:/TalentHub`.
- [ ] Run browser smoke checks for hub enterprise, enterprise detail, opportunity detail, application modal, application drawer, hub schools, and school detail at desktop/tablet/mobile.
- [ ] Confirm no horizontal overflow, console errors, broken focus restoration, or missing active navigation.
- [ ] Run `php -l` for every learner PHP file, PHP data/static tests, and Node unit tests.
- [ ] Compare `git diff --name-only` against the global scope constraint and verify enterprise/database/non-learner paths are unchanged.
- [ ] Review the final diff against all seven mockups and the design spec.
