# Learner Project Registration and AI Collapse Design

**Date:** 2026-08-30
**Status:** Approved
**Scope:** Learner project detail registration, the ecosystem AI panel, and AI-action visibility between ecosystem tabs.

## Goal

Let a learner join an authorized school project directly from its internal detail page, while keeping the project list easy to reach after AI analysis and ensuring project AI controls never appear on the enterprise tab.

This is an incremental change to the approved learner projects experience. It does not add a review workflow for teachers or schools in this phase.

## Confirmed Product Decisions

- Add a primary `Đăng ký dự án` action to an eligible learner project detail page.
- Registration is approved immediately for now: a successful action creates or reactivates an `active` project membership with role `member`.
- If the learner is already an active member, show `Đã tham gia dự án` and do not create a duplicate membership.
- Keep the current database schema and the existing `project_members` lifecycle (`active`, `left`, `removed`).
- Defer pending approval, rejection, and teacher/school review interfaces to a later phase.
- Add a manual `Thu gọn` / `Mở rộng` control to the AI project panel. Collapsing hides all AI messages, progress, analysis, and result cards while preserving a compact header and status.
- The `AI gợi ý dự án phù hợp` action is visible only while the `Dự án` tab is active. It must remain hidden on the `Doanh nghiệp` tab on initial render, mouse navigation, and keyboard tab navigation.
- Keep the existing AI matching, scoring, generation, and internal project-detail navigation unchanged.

## Learner Registration Flow

The project detail page renders one of two primary states:

1. `Đăng ký dự án` when the authenticated learner does not have an active membership.
2. `Đã tham gia dự án` when the learner already has an active membership.

Registration uses a same-origin POST protected by the authenticated learner session and CSRF token. The command accepts only the project identifier from the route/form context; learner identity is always resolved from the session and is never accepted from browser input.

Before writing, the repository must reapply the same authorization policy as project detail:

- the learner profile is active;
- the project belongs to the learner's school;
- the school is active;
- the project status is `in_progress`.

Inside a transaction:

- no existing membership: insert a UUID membership with role `member`, status `active`, and current timestamps;
- existing `active` membership: return the existing state idempotently;
- existing `left` or `removed` membership: reactivate it as `active`, restore role `member`, clear `leftAt`, and refresh membership timestamps.

The unique `(projectId, studentId)` constraint remains the duplicate-protection boundary. After success, use a POST/Redirect/GET response back to the same internal detail URL with a success message. A failed registration leaves the page readable and shows an actionable error without exposing database details.

The member count shown on the detail page must reflect the newly active membership after redirect.

## AI Panel Collapse

The AI card header remains visible at all times and contains:

- the existing icon, title, and explanatory copy;
- the existing live status;
- a new disclosure button with `aria-expanded` and an explicit controlled content region.

The controlled region wraps every AI body state: not generated, loading/progress, consent, insufficient data, low-fit/no-fit analysis, error, and result cards. `Thu gọn` hides that entire region so the normal project heading and grid move upward into view. `Mở rộng` restores the same rendered state without regenerating or discarding the current AI result.

The panel begins expanded on page load. Collapse state is local to the current page view; no new persistence storage is introduced.

## Enterprise-Tab Isolation

The tab controller is the source of truth for shared toolbar visibility. Whenever a tab is activated, it synchronously sets the AI trigger to hidden unless the active tab is `opportunities` (the internal identifier retained for compatibility).

Because the common `.learner-btn` author style sets a display value, add an explicit scoped `[hidden]` rule for the AI trigger so the semantic `hidden` attribute cannot be overridden by button styling. The AI module may retain a defensive visibility sync, but it must not be the only mechanism controlling the tab-level action.

## Error and Edge States

- A forged project ID or another school's project is treated as unavailable and cannot create membership.
- Draft, completed, or archived projects cannot be joined through the learner action.
- A double submission resolves to one active membership and a stable joined state.
- If registration storage is unavailable, show a general retry message and do not alter the project detail content.
- Collapsing during a generated or loaded AI result preserves that result in the DOM and reveals it again when expanded.
- Switching to `Doanh nghiệp` while AI generation is running hides the project AI trigger; returning to `Dự án` restores it without changing the AI request state.

## Testing Strategy

Implementation follows test-driven development.

Backend tests cover:

- first-time same-school registration creates one active `member` row;
- repeat registration is idempotent;
- `left` and `removed` memberships reactivate correctly;
- another school's project and non-`in_progress` projects cannot be joined;
- detail read state identifies an existing active member;
- CSRF/session ownership is enforced at the page boundary.

Frontend tests cover:

- the detail page renders `Đăng ký dự án` or `Đã tham gia dự án` from membership state;
- the AI disclosure exposes correct accessible state and hides/restores the complete AI body;
- the AI trigger is hidden on initial enterprise view and after mouse or keyboard navigation to that tab;
- returning to the project tab restores the AI trigger;
- existing AI generation and internal `Xem dự án` actions continue to work.

Manual browser verification covers desktop and mobile layouts, one real registration, the post-registration member count/state, AI collapse after results render, and enterprise/project tab switching.

## Deferred Work

- Pending registration state.
- Teacher or school approval/rejection interfaces.
- Reviewer authorization and audit history.
- Approval/rejection notifications.
- Learner cancellation or leaving a project.
