# Phase 5 resume checkpoint after Codex CLI context overflow

## Purpose

Resume Phase 5 in a fresh Codex CLI conversation. The previous conversation hit:

`[Error] Your input exceeds the context window of this model.`

Do not trust its final partial claims. Inspect the current workspace as the source of truth and continue from the files already written. Do not restart Phase 5 and do not apply the primary migration until every gate is green.

## Workspace checkpoint

- Workspace: `D:\TalentHub`
- Branch: `feature/student`
- HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Worktree remains intentionally dirty with Phase 2–5 changes.
- Primary database has not been mutated by Phase 5.
- No commit, push, merge, reset, clean, checkout or stash was performed.
- Protected files and applied migrations must remain untouched.
- AI learner visibility remains `0`.

## Mandatory source documents

Read these completely before editing:

1. `docs/superpowers/handoffs/2026-08-22-anti-project-context-after-phase-4.md`
2. `docs/superpowers/2026-08-22-anti-phase-5-execution-prompt.md`
3. This file.

The latest independent reviewer decision is `NOT_READY`, not `GO_FOR_REVIEW`.

## Exact state after the overflow

Files now present:

- `Database/migrations/20260821000400_create_activity_experience_policies.php`
- `app/learner/data/Contracts/CheckinRepository.php`
- `app/learner/data/Service/LearnerCheckinService.php`
- `app/learner/data/Database/DatabaseCheckinRepository.php`
- `app/learner/api/v1/checkins.php`
- `app/learner/checkin.php`
- `assets/js/learner-checkin.js`
- Teacher QR repository/service changes.
- `tests/phase5_source_namespace_contract_test.php`

Fresh verification immediately after the context overflow:

- `app/learner/checkin.php`: PHP syntax OK.
- `app/learner/data/Database/DatabaseCheckinRepository.php`: PHP syntax OK.
- `assets/js/learner-checkin.js`: Node syntax check returned success.
- `Database/migrations/20260821000400_create_activity_experience_policies.php`: **BROKEN** — PHP reports `strict_types declaration must be the very first statement in the script` at line 3.
- Because that migration cannot load, `bin/migrate.php validate` and `status` currently fail. Do not apply anything.

## Immediate source blockers

1. Repair the Phase 5 migration file start using `apply_patch`, preserving intended migration logic. Inspect the first bytes/lines for a duplicated PHP tag, BOM, whitespace or injected statement. Run PHP lint before migration validation.
2. Inspect the complete migration after the previous scripted rewrite. It must validate exact metadata when the target table already exists; it must not silently accept an incompatible same-name table.
3. `assets/js/learner-checkin.js` currently derives an idempotency key from `token.slice(0, 16)`. This leaks part of the raw token into a header/loggable identifier. Replace it with a token-independent cryptographically random request key.
4. The new JS builds a history row with `innerHTML` using server-returned activity title/hours. Replace this with safe DOM APIs/`textContent` or a trusted renderer to prevent stored XSS.
5. The camera code opens a stream but has not proven a QR decode loop. Implement and test a supported decoder such as `BarcodeDetector`, with an unsupported-decoder state and manual fallback.
6. The page still renders initial `$checkinHistory` from the old learner data include. In database mode, load own history from the Phase 5 GET endpoint and keep the server authoritative; do not merely prepend a DOM-only success row.
7. Verify all media tracks stop on success, manual submit, explicit stop, permission/error paths, visibility change and unload.
8. Teacher UI still needs a real `confirmedHours` input wired through service/repository and a managed check-in read view. Do not silently use the `1.00` default as if Teacher configured it.
9. School scoped confirmed check-in/hour aggregates and Enterprise negative access contracts are still absent.

## Required files still absent at checkpoint

- `tests/learner_checkin_api_test.php`
- `tests/learner_checkin_mysql_test.php`
- `tests/learner_checkin_ui_test.js`
- `docs/superpowers/specs/2026-08-22-phase-5-learner-checkin-experience-design.md`
- `docs/superpowers/plans/2026-08-22-phase-5-learner-checkin-experience-implementation.md`
- `docs/superpowers/database-change-requests/2026-08-22-phase-5-learner-checkin-experience.md`
- `docs/superpowers/readiness/2026-08-22-phase-5-rehearsal-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-5-learner-checkin-review-report.md`

The Teacher registration tests are not managed-check-in tests. Existing learner profile/activity/API-client JS tests are not scanner tests. Do not cite them as Phase 5 behavior coverage.

## Required continuation order

1. Read current files and `git diff`; never use malformed paths such as `D:TalentHub...`.
2. Repair migration syntax and exact-metadata preflight; run PHP lint, migration validate and migration status. Expected state before approved apply: Phase 5 migration pending.
3. Write failing Phase 5 tests before further production changes.
4. Finish backend/API behavior, rollback injection and stable duplicate semantics.
5. Finish Teacher policy/managed event UI.
6. Finish learner camera decoding/manual fallback/server-backed history and JS tests.
7. Implement School scoped aggregate and Enterprise denial tests.
8. Run Phase 5 MySQL disposable concurrency tests.
9. Complete all five Phase 5 design/DCR/rehearsal/review documents.
10. Run full Phase 0–4 regression, PHP lint, JS tests, secret scan and `git diff --check`.
11. Only after every gate is green: create a new primary backup, restore/rehearse on a disposable schema, verify idempotency and counts/hashes, then apply only `20260821000400` to `talenthub_local`.
12. Stop at `GO_FOR_REVIEW`; do not start Phase 6 and do not commit/push/merge.

## Required first response in the new conversation

After read-only inspection, reply:

```text
PHASE_5_RESUME_LOADED
- Branch/HEAD: ...
- Migration syntax/validate/status: ...
- Current Phase 5 files: ...
- Missing tests/docs: ...
- Primary DB mutation: none
- First repair action: ...
- Contradictions found: ...
```

Then continue implementation without asking for permission again unless a true external blocker or scope expansion appears.
