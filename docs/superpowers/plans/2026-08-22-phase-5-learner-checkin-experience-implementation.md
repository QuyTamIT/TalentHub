# Phase 5 Implementation Plan — QR Check-in and Confirmed Experience

Date: 2026-08-22
Status: executed; review pending

## Completed work packages

1. Schema contract
   - Added forward-only migration `20260821000400`.
   - Added strict preflight for prerequisite tables, replay-barrier indexes, UTC session, semantic duplicates, and exact existing-table metadata.
   - Matched policy hours to the existing `experience_logs` 0–24 constraint.

2. Learner backend
   - Added `CheckinRepository`, `LearnerCheckinService`, `DatabaseCheckinRepository`, and authenticated GET/POST endpoint.
   - Enforced session-derived Student ownership, CSRF, exact RBAC, exact request fields, SHA-256 token hashing, fixed lock order, database-clock expiry, atomic audit, and rollback.

3. Teacher and four-role integration
   - Added owned confirmed-hours policy to QR creation without changing one-time-token behavior.
   - Added managed check-in read rows to the Teacher QR page.
   - Added School-scoped confirmed count/hour aggregate to existing School analytics.
   - Proved Enterprise has no permissions/routes; retained the Phase 3 consent boundary.

4. Learner browser flow
   - Replaced mock history with the authenticated GET endpoint.
   - Added camera decoder and manual fallback, safe DOM rendering, double-submit protection, error states, track cleanup, and post-success history refresh.

5. Verification
   - API runtime test covers CSRF, permissions, spoof rejection, ownership, stable errors, persistence, history, duplicate replay, and token redaction.
   - Failure-injection test covers six write boundaries and full rollback.
   - Disposable MySQL test covers Teacher ownership and four concurrency races.
   - Rehearsal restores a primary backup clone, applies migration twice, compares hashes/counts, and refuses unsafe database names.
   - Phase 0–4 PHP regression, all learner JavaScript tests, full PHP lint, migration validation/status, diff check, and secret/protected-file checks are required before primary apply.

## Review checkpoint

Phase 5 becomes `GO_FOR_REVIEW` only after a fresh primary backup, migration apply, read-only post-apply verification, and final reports. No Phase 6 implementation is permitted in this plan.
