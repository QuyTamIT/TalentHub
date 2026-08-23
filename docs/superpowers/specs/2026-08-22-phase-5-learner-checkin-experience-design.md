# Phase 5 Design — Learner QR Check-in and Confirmed Experience

Date: 2026-08-22
Status: implemented; awaiting review

## Scope and ownership

- Teacher owns QR create/list/revoke and configures `confirmedHours` for an owned `ongoing` activity. The raw token is returned once; only its SHA-256 hash is stored.
- Student submits only `{token}`. Identity and registration ownership come from the authenticated session. A successful scan atomically creates a confirmed check-in and confirmed experience, moves registration `approved -> attended`, increments the session scan count, and records a token-free audit event.
- School receives only `confirmedCheckins` and `confirmedHours` scoped by the authenticated School profile through the existing analytics route.
- Enterprise has no QR/check-in/experience permissions or routes.

Phase 6+, notifications, badges, opportunities, AI rollout, location policy, and review-based confirmation are excluded.

## Schema

The only migration is `Database/migrations/20260821000400_create_activity_experience_policies.php`.

`activity_experience_policies` contains exactly:

- `activityId CHAR(36)` primary key and cascading FK to `activities.id`.
- `confirmedHours DECIMAL(7,2) NOT NULL`, constrained to `0 <= hours <= 24`, matching the existing `experience_logs.hours` constraint.
- `createdAt` and `updatedAt` as UTC `DATETIME(6)` timestamps.

No existing domain table is altered and no row is backfilled. Existing unique constraints `uq_checkins_registration` and `uq_experience_logs_checkin` remain the replay barriers.

## API contract

`POST /app/learner/api/v1/checkins.php`

- Requires Student session, `checkin.create_own`, JSON content, valid CSRF, and exact body allow-list `{token}`.
- Returns HTTP 201 using the shared `{data, meta}` envelope.

`GET /app/learner/api/v1/checkins.php?limit=25&offset=0`

- Requires Student session and `experience_log.read_own`.
- Returns only registrations belonging to the session-derived Student.

Stable errors: `VALIDATION_FAILED`, `CSRF_INVALID`, `PERMISSION_DENIED`, `QR_TOKEN_INVALID`, `QR_SESSION_EXPIRED`, `QR_SESSION_REVOKED`, `QR_SESSION_EXHAUSTED`, `ACTIVITY_NOT_CHECKIN_ELIGIBLE`, `REGISTRATION_NOT_ELIGIBLE`, `CHECKIN_ALREADY_EXISTS`, `EXPERIENCE_POLICY_MISSING`, and `CHECKIN_STATE_CONFLICT`.

## Transaction and state machine

The raw token is trimmed, format-validated, SHA-256 hashed, then discarded before repository work. A token-hash pre-read discovers the candidate activity but is never authoritative.

The transaction lock order is fixed:

1. Student profile.
2. Activity.
3. Student-owned registration.
4. QR session.
5. Experience policy.

All facts are revalidated under lock. Database time (`UTC_TIMESTAMP(6)`) determines expiry. Success performs, in one transaction:

`approved registration -> confirmed check-in -> scan increment -> attended registration -> confirmed experience -> audit -> commit`

Any exception rolls back every write. A replay returns stable `409 CHECKIN_ALREADY_EXISTS`; response idempotency is not claimed because Phase 5 adds no idempotency-response store.

## Concurrency outcomes

- Same registration scanned twice: one success, one duplicate; one check-in/experience/scan.
- Two Students compete for the final scan: one success; the loser receives exhaustion/state conflict; `usedScans <= maxScans`.
- Teacher revoke races with scan: either scan serializes first and revoke follows, or revoke wins and the scan is rejected.
- Policy update races with scan: the experience stores one complete locked policy value, never a mixed value.

## Browser states

The learner page uses `BarcodeDetector` with `getUserMedia` when available and an always-available manual token form. It supports camera active, permission denied, unsupported camera, unsupported decoder, success, duplicate, expired, revoked, exhausted, invalid token, and generic retry. Media tracks stop after a decoded submission, explicit stop/reset, page hide, or unload. Raw tokens are not stored in module state, URL, local/session storage, console, or request identifiers.
