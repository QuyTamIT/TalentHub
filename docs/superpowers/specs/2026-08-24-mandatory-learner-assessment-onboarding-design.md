# Mandatory Learner Assessment Onboarding Design

**Date:** 2026-08-24

**Status:** Approved for implementation planning

**Scope:** Newly self-registered student accounts only

## 1. Purpose

Align the implemented learner journey with the project presentation: a newly registered student must complete the four published discovery assessments before using the normal learner portal. The flow must preserve progress across sessions and devices and must be enforced by the server, not only by the browser.

## 2. Confirmed Product Decisions

- The requirement applies only to student accounts created after this feature is deployed.
- Existing student accounts remain unlocked, regardless of their assessment history.
- A newly registered student may log in and reach the learner overview, but the overview is blocked by a mandatory onboarding decision dialog.
- Declining destroys the authenticated session and returns the student to the login page.
- Accepting starts a mandatory sequence of four assessments:
  1. Holland — career interests;
  2. MBTI-inspired — educational personality preferences;
  3. DISC — learning and communication behavior;
  4. Multiple Intelligence — multidimensional aptitude orientation.
- Saved answers and attempts are resumed after logout, browser closure, connection loss, or login on another device.
- The normal learner portal is unlocked only after the server confirms one submitted result for each of the four required assessment types.
- Existing assessment retake and expiry policies remain independent after onboarding is complete.

## 3. Non-goals

- Do not apply the gate retroactively to existing students.
- Do not require teachers, schools, enterprises, or platform administrators to complete learner onboarding.
- Do not permit the client to declare onboarding complete.
- Do not change assessment scoring algorithms or treat assessment preferences as verified skills.
- Do not require a student to repeat a submitted onboarding assessment because a later retake becomes available.
- Do not add an administrative bypass in this delivery slice.

## 4. State Model

Create one onboarding record only when a student self-registers after deployment.

| State | Meaning | Allowed entry behavior |
|---|---|---|
| `pending` | The student has not accepted the mandatory assessment journey | Login lands on the overview and displays the blocking decision dialog |
| `accepted` | The student accepted but has not submitted all four assessments | Login and protected learner routes redirect to the current or next missing assessment |
| `completed` | All four required assessment types have a server-confirmed submitted result | Normal learner portal access |

There is no persistent `declined` state. Declining is an audited action that logs the student out. On the next successful login, the `pending` dialog is shown again.

Allowed transitions are:

```text
pending --accept--> accepted --submit all four--> completed
pending --decline--> pending + session destroyed
```

`completed` is terminal for onboarding. Assessment retakes do not return the account to a gated state.

## 5. End-to-End User Flow

### 5.1 Registration and first login

1. Student registration creates the user, student profile, and `pending` onboarding record in one database transaction.
2. Registration continues to redirect to the login page.
3. The first successful login follows the normal student destination and opens the learner overview.
4. The overview displays the mandatory onboarding dialog over an inert, visually dimmed page.

### 5.2 Decision dialog

The dialog contains:

- title: `Hoàn thành đánh giá ban đầu`;
- a short explanation that four assessments are required for interests, aptitude, and personalized development guidance;
- the four assessment names;
- a notice that progress is saved and can be resumed later;
- primary action: `Đồng ý và bắt đầu`;
- destructive/secondary action: `Từ chối và đăng xuất`.

The dialog has no close control and cannot be dismissed by clicking the backdrop or pressing Escape. Background controls are not focusable or interactive while it is open.

Accepting changes the server state to `accepted` and redirects to the first missing assessment. Declining records an audit event, destroys the session, and redirects to login with a neutral explanatory message.

### 5.3 Mandatory assessment journey

The existing discovery page becomes the onboarding progress hub and displays:

- total progress from `0/4` through `4/4`;
- `Tiếp tục` for an in-progress assessment;
- `Bắt đầu` for the next required assessment;
- `Đã hoàn thành` and a result link for submitted assessments;
- `Chưa đến lượt` for later assessments;
- `Đăng xuất và tiếp tục sau`.

The assessment order is Holland, MBTI-inspired, DISC, then Multiple Intelligence. After a successful submission, the server identifies the next missing type and redirects to it. Answers continue to use the existing database-backed autosave and resumable attempt lifecycle.

### 5.4 Completion

After the fourth successful submission, the server recomputes completion from canonical assessment data and atomically changes onboarding to `completed`. The UI shows a `4/4` completion screen with a `Vào hệ thống` action. Normal learner navigation and endpoints become available immediately.

If the final submission succeeds but the browser loses the response, the next request reconciles the four submitted types and completes onboarding idempotently.

## 6. Server-side Gate

The gate is evaluated for authenticated students before normal learner page or API behavior:

1. No onboarding record: allow access. This is the compatibility rule for existing students.
2. `completed`: allow access.
3. `pending`: allow only the overview decision flow, onboarding decision endpoint, logout, and required static/session support.
4. `accepted`: allow only the onboarding progress hub, assessment runner/results, assessment catalog/attempt/answer/submit APIs, session support, and logout.

For a gated browser-page request outside the allowlist, redirect to:

- learner overview when state is `pending`;
- the current in-progress assessment, otherwise the first missing assessment, when state is `accepted`.

For a gated JSON request outside the allowlist, return a safe authorization response with code `ONBOARDING_REQUIRED`; do not execute the endpoint action.

The allowlist is centralized in one learner onboarding gate component rather than repeated across pages. The gate must validate normalized local paths and must not trust a client-supplied destination.

## 7. Persistence Design

Add a canonical table named `learner_onboarding_states`:

| Column | Contract |
|---|---|
| `studentId` | `CHAR(36)`, primary key and foreign key to `student_profiles.id` |
| `status` | `VARCHAR`, constrained to `pending`, `accepted`, `completed` |
| `acceptedAt` | nullable UTC timestamp; required for `accepted` and `completed` |
| `completedAt` | nullable UTC timestamp; required only for `completed` |
| `createdAt` | non-null UTC creation timestamp |
| `updatedAt` | non-null UTC update timestamp |

Database constraints must keep timestamps consistent with status. The migration creates an empty table and performs no backfill, which is what exempts existing students.

Self-registration inserts the onboarding record within the existing user/profile transaction. If the onboarding insert fails, registration rolls back instead of creating an untracked new student.

This shared database change requires a Database Change Request and explicit approval before the migration is applied to any shared or primary database.

## 8. Completion Calculation

The server determines onboarding progress using canonical submitted attempts and their immutable results. A type counts as complete only when:

- the attempt belongs to the authenticated student's profile;
- the attempt status is `submitted`;
- a corresponding immutable result exists;
- the associated canonical test type is one of `holland`, `mbti`, `disc`, or `multiple_intelligence`.

At most one completion credit is given per required type, regardless of retakes. The client receives a presentation read model containing required type, current state, progress count, next assessment, and safe URLs; it never receives authority to set completion.

Reconciliation runs after assessment submission and whenever an `accepted` student enters the gate. Updating `completed` is idempotent and safe under repeated requests.

## 9. UI and Accessibility

- The mandatory dialog uses proper dialog semantics, labelled title and description, initial focus, and focus containment.
- The overview behind the dialog is inert and hidden from sequential keyboard interaction.
- The progress hub uses text labels in addition to color and exposes progress semantics to assistive technology.
- Save, retry, expired-attempt, validation, and service-unavailable states remain keyboard accessible.
- During `pending` or `accepted`, unavailable navigation is hidden or rendered non-interactive; server enforcement remains authoritative.
- The interface continues to state that results are educational guidance, not clinical diagnosis or mandatory admissions evaluation. The onboarding action itself is mandatory for use of this product, but the score is not an admissions judgment.

## 10. Error and Recovery Behavior

| Condition | Required behavior |
|---|---|
| Answer autosave fails | Keep the current selection visible, show unsaved status, and allow retry |
| Browser closes or network is lost | Preserve server-saved answers and resume the owned in-progress attempt later |
| Assessment catalog is unavailable | Keep the account gated, show retry and logout actions, and do not infer completion |
| Attempt expires | Start a new version-correct attempt for that assessment; preserve other submitted results |
| Final submit response is lost | Reconcile submitted types on the next request and complete idempotently |
| Unauthorized direct page request | Redirect according to `pending` or `accepted` state |
| Unauthorized direct API request | Return `ONBOARDING_REQUIRED` without performing the requested action |
| Onboarding state data is internally inconsistent | Fail closed for the learner, log a request ID, show a safe retry/logout state |

## 11. Audit and Security

Audit events are recorded for acceptance, decline, and completion. Logs contain identifiers and state transitions but not raw assessment answers.

All onboarding reads and mutations are scoped to the authenticated learner. CSRF protection applies to accept and decline actions. Session destruction on decline uses the existing logout/session lifecycle. Redirect targets are fixed server routes. API and page gates run before repository commands that could mutate registrations, applications, check-ins, profile sharing, consent, or other normal portal state.

## 12. Testing Strategy

### Registration and compatibility

- A new self-registered student receives exactly one `pending` onboarding row.
- Registration rolls back when onboarding creation fails.
- Existing students without a row remain unlocked.
- Non-student roles are unaffected.

### State and routing

- `pending` reaches the overview dialog but cannot reach other learner functionality.
- Accept transitions once to `accepted` and starts Holland.
- Decline audits, destroys the session, and redirects to login.
- An `accepted` login resumes an owned in-progress attempt or opens the first missing type.
- Direct URLs and JSON requests cannot bypass the gate.
- `completed` has normal learner access.

### Assessment progress

- Zero through three distinct submitted types remain gated.
- Four distinct valid submitted types complete and unlock onboarding.
- Duplicate submissions or retakes do not inflate progress.
- Another student's attempt or result never contributes to progress.
- Lost-response reconciliation completes exactly once.
- Autosave/resume works across sessions and devices.

### UI, accessibility, and regression

- The pending dialog cannot be dismissed without accept or decline.
- Focus remains inside the dialog and background content is inert.
- Progress and assessment states are understandable without color.
- Registration, login, logout, assessment catalog, runner, autosave, submit, results, learner authorization, and AI recommendation test suites continue to pass.

## 13. Delivery Boundaries

Implementation will be planned as one learner onboarding feature with these bounded parts:

1. approved database migration and onboarding repository/service;
2. transactional registration integration;
3. centralized learner page/API gate;
4. decision endpoints and session behavior;
5. overview dialog and gated discovery progress UI;
6. assessment submission reconciliation;
7. focused security, integration, UI, and regression tests.

No unrelated refactoring or cross-role UI redesign is included.

## 14. Acceptance Criteria

1. Only newly self-registered students receive mandatory onboarding.
2. First login reaches the blocked overview decision dialog.
3. Declining logs the student out; accepting begins the four-test journey.
4. Saved progress resumes after a later login or on another device.
5. Server-side routing and API checks prevent access to normal learner functionality before completion.
6. Only four distinct, owned, submitted assessment results unlock the account.
7. Completing all four permanently opens the learner portal without changing retake behavior.
8. Existing students and all other roles remain unaffected.
