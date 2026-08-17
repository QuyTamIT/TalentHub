# Learner Ecosystem & Opportunities Design

## Goal

Build a new learner-only module named **Hệ sinh thái & Cơ hội** from the seven approved mockups. The module lets students discover schools, enterprises, and opportunities; inspect partner and opportunity details; submit a mock Talent Passport application with consent; and track mock application statuses.

## Scope and ownership

- All production changes stay inside `app/learner/**`, `assets/css/learner.css`, and `assets/js/learner.js`.
- Learner tests may be added under `tests/`.
- `app/enterprise/**`, `Database/**`, and every non-learner role remain read-only.
- The learner data adapter may include the existing enterprise internship mock provider, but must not mutate it or require changes to its public functions.
- The current MySQL schema is not changed. School and application data remain explicit demo data.

## Routes and seven approved views

1. `app/learner/ecosystem.php?tab=enterprises` — enterprise discovery hub.
2. `app/learner/partner.php?type=enterprise&id=fpt-software` — FPT Software detail.
3. `app/learner/opportunity.php?type=internship&id=1` — internship detail.
4. Application modal opened from the opportunity detail page.
5. `Ứng tuyển của tôi` drawer opened from the ecosystem hub.
6. `app/learner/ecosystem.php?tab=schools` — school discovery hub with a visible `Dữ liệu minh họa` label.
7. `app/learner/partner.php?type=school&id=thtech` — demo school detail.

The hub also supports `?tab=opportunities`. Modal and drawer states are interactions, not separate routes.

## Data architecture

`app/learner/includes/ecosystem-data.php` is the only data provider consumed by the new pages.

- Enterprise identity and internship opportunities are adapted from `app/enterprise/includes/internships-data.php`.
- Draft enterprise posts are never exposed to learners.
- Active and closed posts are normalized into learner-facing opportunity records.
- School, school program, school event, school opportunity, and application records live as learner-owned demo arrays.
- Lookup helpers return `null` for unknown IDs so pages can render an accessible not-found state.
- UI pages consume normalized partner/opportunity records and do not read enterprise arrays directly.

This boundary allows a later MySQL provider to replace the arrays without rewriting the page templates or interactions.

## Interaction design

### Hub

- Tabs switch between schools, enterprises, and all opportunities.
- Search is accent-insensitive and matches names, fields, locations, skills, and opportunity titles.
- Select filters narrow visible cards; reset restores the complete active tab.
- The result count is announced with `aria-live` and an empty state appears when nothing matches.
- The application summary button opens a focus-managed right drawer.

### Partner detail

- The shared page resolves either an enterprise or a school from query parameters.
- Enterprise detail shows FPT Software identity, contact information, skills, workplace benefits, and learner-visible posts.
- School detail shows the demo marker, programs, events, facilities, scholarships, and open opportunities.

### Opportunity and application

- Opportunity detail shows status, deadline, requirements, skills, benefits, related opportunities, and Talent Passport readiness.
- Closed or expired opportunities disable the application action.
- Application modal previews the exact fields shared, requires consent, validates the optional message length, and simulates successful submission locally.
- After submission the CTA changes to `Đã ứng tuyển`; no backend or cross-role data is mutated.

### Tracking drawer

- Shows mock applications, KPI counts, status filters, search, and a four-step timeline.
- Supports expanding/collapsing cards and mock withdrawal only for eligible statuses.
- Closing restores focus to the trigger; Escape closes modal/drawer before the mobile sidebar.

## Visual and responsive system

- Reuse the learner sidebar, header, buttons, cards, modal primitives, logo, icons, and responsive breakpoints.
- Use Be Vietnam Pro and the existing TalentHub tokens: orange primary, blue secondary, green success, slate background/text, 8px/12px radii.
- Desktop follows the approved `1536 × 1024` mockups.
- Tablet collapses complex grids to two/one columns.
- Mobile uses the existing learner navigation drawer; opportunity actions become non-sticky and the application tracker becomes full width.

## Accessibility and failure states

- One active sidebar route, semantic headings, labelled forms, visible focus, and keyboard-operable tabs.
- Modal and drawer trap focus, close on Escape/backdrop, and restore trigger focus.
- Missing partner/opportunity IDs render an in-page not-found state with a safe return link.
- Search/filter empty states never remove navigation.
- Demo school data is explicitly labelled; no unofficial real-world school branding is used.

## Verification

- PHP data contract tests validate normalization, draft filtering, lookup helpers, and demo labels.
- Node tests validate accent-insensitive matching, filter behavior, application state, and route recognition.
- PHP syntax checks cover every learner PHP file.
- Browser smoke checks cover the three routes, seven states, desktop/tablet/mobile overflow, modal/drawer keyboard behavior, filters, not-found states, and console errors when Playwright is available.
- Git scope verification must show no modifications under enterprise, database, teacher, or school modules.
