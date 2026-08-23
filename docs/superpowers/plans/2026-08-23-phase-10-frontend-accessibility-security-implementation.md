# Phase 10 Frontend, Accessibility, Error, and Security Hardening Implementation Plan

**Goal:** Close the remaining learner fake-success, request lifecycle, accessibility, unsafe rendering, and learner action rate-limit gaps without changing the database schema or starting Phase 11.

**Source of truth:** `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`, Phase 10 / Task 14.

## Invariants

- Work only on `feature/student`; do not push or merge.
- Preserve `.env`, `.claude/`, `.qwen/`, all applied migrations, and learner migrations `001`–`004`.
- Do not create a Phase 10 migration, seed, backfill, or primary database mutation.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`; Rule remains learner-visible and model execution remains shadow-only.
- Keep Phase 11 and Phase 12 out of scope.

## Task 1 — Lock transport lifecycle with tests

**Files:** `assets/js/learner-api.js`, `tests/learner_api_client_test.js`

1. Add failing coverage for caller abort, timeout during fetch and response decoding, safe GET retry, mutation no-retry, stale/freshness opt-out, `429 Retry-After`, and request correlation.
2. Give each logical request one safe `X-Request-ID`, preserved across GET retries.
3. Keep one timeout active through response body decoding and clean up timers/listeners on every exit.
4. Retry only GET network/timeout/502/503/504 failures, at most twice; never retry mutations or `429` automatically.
5. Preserve canonical same-origin API roots, CSRF, credentials, idempotency keys, and normalized error envelopes.

## Task 2 — Remove fake-success and unsafe rendering paths

**Files:** `assets/js/learner.js`, `assets/js/learner-activities.js`, `app/learner/index.php`, `app/learner/opportunity.php`, affected UI tests

1. Remove dashboard, registration-modal, assessment-modal, and saved-opportunity handlers that mutate UI without canonical server confirmation.
2. Route dashboard activity actions to the existing authoritative activity screen.
3. Disable saved opportunity honestly until a persistence endpoint exists.
4. Build activity history with `createElement`, `textContent`, allow-listed status classes, encoded IDs, and `replaceChildren`; do not parse server strings as HTML.
5. Retain explicit mock-only behavior only where the learner data source is actually `mock`.

## Task 3 — Consolidate read request freshness and recovery

**Files:** `assets/js/learner-notifications.js`, `assets/js/learner-statistics.js`, their tests

1. Replace direct `fetch` calls with the shared learner API client.
2. Abort superseded notification/statistics reads and ignore stale responses by sequence.
3. Keep existing content on recoverable errors where appropriate and expose retry/status messages.
4. Render all dynamic notification/statistics values through DOM text APIs.

## Task 4 — Complete accessibility and responsive contracts

**Files:** `assets/css/learner.css`, affected learner pages, `tests/learner_accessibility_render_test.php`

1. Verify Escape close, Tab focus trap, focus return, visible labels, inline `role=alert`, and polite live regions.
2. Add a visible `:focus-visible` fallback and modern enhanced color, 44px control targets, viewport-safe dialogs, and explicit 360/768/1024 handling.
3. Preserve reduced-motion behavior and keyboard-only completion of primary learner flows.

## Task 5 — Add persistent learner action limits

**Files:** learner data security service, learner endpoints, JSON responder, focused tests

1. Reuse the applied `auth_rate_limits` table with namespaced hash buckets; do not add schema.
2. Enforce student and IP limits before check-in, application submit, and recommendation generation work.
3. Preserve the existing login limiter.
4. Return a safe `RATE_LIMIT_EXCEEDED` envelope with a numeric allow-listed `Retry-After` header.
5. Prove threshold, reset, action isolation, identity isolation, IP limit, hashing, rollback, and MySQL disposable cleanup.

## Task 6 — Verification and review gate

1. Run every learner Node suite.
2. Run the safe PHP render/API/security/cross-role matrix and focused disposable MySQL limiter test.
3. Lint all PHP under application, migration, source, bin, and tests.
4. Run connection, migration validate/status, Phase 10 readiness, protected-path/hash, secret, and `git diff --check` gates.
5. Record exact evidence in the Phase 10 review report.
6. Commit by domain, update the tracker only after verification, and stop before Phase 11.

