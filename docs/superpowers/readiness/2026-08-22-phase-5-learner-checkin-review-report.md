# Phase 5 Review Report — Learner QR Check-in and Confirmed Experience

Date: 2026-08-22
Branch: `feature/student`
Baseline HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
Decision: **GO_FOR_REVIEW**

## Delivered behavior

- Teacher creates, lists, and revokes owned QR sessions and configures 0–24 confirmed hours for an owned ongoing activity. Only the token hash is stored; raw token remains a one-time response.
- Student camera/manual flow submits only the opaque token. The authenticated API resolves Student ownership and exact RBAC before reading the request token, requires CSRF, hashes the token, clears the caller-held token before repository access, and uses database time plus a fixed lock order.
- Successful processing atomically creates one confirmed check-in, increments one scan, changes registration `approved -> attended`, creates one confirmed experience using the locked policy snapshot, and writes a token-free audit row.
- Student history is server-backed, own-scoped, and confirmed-only. Browser rendering is text-only, refreshes from the server, blocks in-flight double submit, supports retry/manual fallback, and releases media tracks. Camera generations prevent stale `getUserMedia`, `BarcodeDetector.detect`, or `video.play` completions from reactivating a stopped scanner or submitting a late token.
- Teacher sees only confirmed check-ins for managed activities and the page requires both managed QR-read and managed check-in-read permissions. School analytics exposes only school-scoped confirmed count/hours. Enterprise has no QR/check-in/experience permissions or routes.

## Final contract

Lock order: Student -> Activity -> Student-owned registration -> QR session -> experience policy. Expiry and conditional scan increment use `UTC_TIMESTAMP(6)`. Duplicate requests rely on canonical unique barriers and return stable `409 CHECKIN_ALREADY_EXISTS`; response idempotency is deliberately not claimed.

The only schema change is forward-only migration `20260821000400_create_activity_experience_policies`, adding an empty policy table with exact metadata. No existing domain table or row was changed.

## Verification

- Full PHP lint: `471/471` files passed. Flattened/untracked CLI artifacts `TalentHubapplearnercheckin.php`, `TalentHubassetsjslearner-checkin.js`, and `.tmp_audit.php` were verified as noncanonical scratch output and removed by exact path.
- Selected Phase 0–5 PHP regression: `45/45` passed. The initial `44/45` exposed the old foundation assumption that every learner repository was read-only; the approved Phase 5 command repository was added to the existing command-repository allow-list, then passed.
- Learner JavaScript: `74/74` tests passed, including `15/15` Phase 5 browser behavior tests. The camera suite covers stale stream resolution/rejection, decoder failure/rejection, cleanup while decode is pending, and cleanup while `video.play()` is pending.
- Endpoint runtime: CSRF, POST/GET permissions, spoof rejection, cross-Student isolation, all stable error states, persistence, history, duplicate replay, scan count, and raw-token redaction passed.
- Failure injection: six boundaries passed; every injected failure restored check-in, experience, audit, registration, and scan state.
- Disposable MySQL integration: Teacher ownership/create/revoke plus same-registration, final-scan, revoke/scan, and policy-update/scan races passed.
- Rehearsal: first apply passed; second apply returned `no changes`; 50 existing table count/hash snapshots matched primary; new table remained empty after cleanup.
- Migration test runner refused `talenthub_local` with exit code `2`.
- `git diff --check`, migration validation, the read-only exact applied-schema contract, protected-file diff, and AI visibility checks passed.
- High-confidence secret scan found no credentials in application/test/document changes. The only workspace hit was the protected local `.env`, which is untracked/unchanged and was not exposed or committed.

## Database execution

Fresh primary backup:

- Path: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase5_20260822_173500.sql`
- Bytes: `802774`
- SHA-256: `561E6DE4107CC76313A302D711D038AE6F7338C41E0AD9BC269D5CDC088B2020`

Disposable schema `talenthub_phase5_rehearsal_20260822170000` was restored, tested, and then dropped by exact validated name. Primary changed from 51 tables / 22 applied / 1 pending to 52 tables / 23 applied / 0 pending. Existing counts remained:

- activities 26; registrations 40; QR sessions 8; check-ins 20; experience logs 20; test attempts 42; assessments 20.
- policy rows 0; duplicate registration check-ins 0; duplicate check-in experiences 0; relevant orphans 0; attended-without-check-in 0.
- Runtime RBAC maps Student `checkin.create_own`/`experience_log.read_own`, Teacher managed QR/check-in read permissions, and School analytics; Enterprise/business Phase 5 permission count is 0.

No seed and no primary mutation test was run after apply.

## Final reviewer remediation

- Added the missing `DateTimeImmutable`/`DateTimeZone` imports so API timestamps reflect canonical database values instead of silently falling back to the current time.
- Hardened raw-token lifetime and exception-path coverage: the endpoint authorizes before extraction, drops the raw request container, and the service clears its by-reference token before calling the repository. Failure-injection tests verify the token is absent from exception traces.
- Restricted learner and Teacher history to confirmed check-in/experience pairs and extended the registration-state rejection matrix.
- Closed all known asynchronous camera cleanup races and added behavioral tests for pending decode and pending playback.
- Preserved the checksum of the already-applied migration. Exact current DDL and replay barriers are verified read-only by `tests/phase5_applied_schema_contract_test.php`. Hardening the migration's exceptional unregistered-pre-existing-table recovery path would require a separately authorized successor validation migration; it is not a runtime or fresh-install blocker for the reviewed schema.

## Invariants and exclusions

- Branch and HEAD remained `feature/student` / `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`.
- Existing Phase 2–4 dirty worktree changes were preserved.
- `.env`, `.claude/`, `.qwen/`, learner migrations `001`–`004`, and all applied migrations through Phase 4 were untouched.
- `TALENTHUB_AI_VISIBLE_PERCENT=0`; Rule remains learner-visible and model remains Shadow-only.
- No commit, push, merge, reset, clean, checkout, or stash occurred. Phase 6 did not start.
- Notifications, badges, opportunities/applications, review-mode confirmation, location policy, and AI visibility remain excluded.

## Proposed commit groups (not created)

1. `feat(checkin): add atomic learner QR confirmation flow`
2. `feat(portals): connect teacher policy and scoped school aggregate`
3. `test(checkin): cover endpoint rollback browser and MySQL races`
4. `docs(checkin): record phase 5 design DCR rehearsal and review`
