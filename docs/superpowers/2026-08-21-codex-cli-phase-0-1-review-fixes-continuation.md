# Codex CLI Continuation — Phase 0/1 Review Fixes

## Purpose

Continue the interrupted review-fix session in the existing `D:\TalentHub` working tree. The previous Codex CLI session exhausted its context window after making partial edits. Preserve those edits, inspect them, and finish only the reviewer-requested corrections.

Do not restart Phase 0, redo the runtime inventory, begin Phase 2/3, or revert the working tree.

## Required workflow

1. Read applicable repository instructions and this file completely.
2. Use `superpowers:systematic-debugging`, `superpowers:test-driven-development`, and `superpowers:verification-before-completion`.
3. Inspect current files/diff before editing; treat all existing changes as user/previous-session work.
4. Work through the blockers below one at a time with focused RED/GREEN evidence.
5. Do not commit, push, merge, seed, apply migrations, or mutate `talenthub_local`.
6. Stop after updating the reports and return evidence for reviewer approval.

## Locked baseline and accepted evidence

- Repository: `D:\TalentHub`
- Branch: `feature/student`
- HEAD before uncommitted work: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- Node: `C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe`
- MySQL: 8.4.3; connection previously OK; 15 applied, 0 pending, validation OK.
- Phase 0 runtime/schema audit evidence remains accepted. Do not repeat it.
- Phase 2 remains `SKIPPED`.
- `.env`, `.claude/`, `.qwen/`, learner migrations `001`–`004`, and the primary DB must remain untouched.

## Current interrupted checkpoint

The interrupted session already made partial edits:

- `app/learner/data/Enums/Statuses.php`
  - replaced legacy activity/registration enum cases with runtime canonical cases;
  - retained/added `StudentPortalStatusContract`.
- `app/learner/data/Database/DatabaseActivityRepository.php`
  - changed activity filters toward canonical statuses;
  - added inline registration status mapping.
- `app/learner/tools/readiness-check.php`
  - removed the unsafe `TALENTHUB_READINESS_SCOPE_ROOT` override; this file currently matches the safe production behavior and may no longer appear in `git status`.
- Earlier Phase 1 changes remain in the RBAC seed, readiness test, contract test, plan, and reports.

Do not assume these partial edits are correct. Review them against the contracts below.

Fresh targeted verification after interruption produced:

```text
tests/learner_readiness_test.php                 FAIL exit 1
  Assertion failed: phase 0 CLI exits READY

tests/learner_data_foundation_test.php           PASS exit 0
tests/student_portal_cross_role_contract_test.php PASS exit 0

tests/qr_session_migration_contract_test.php     FAIL exit 255
  expected roles=4 permissions=100 mappings=118
  actual   roles=4 permissions=103 mappings=124
```

The readiness failure is expected at this interrupted checkpoint because the production override was removed but `tests/learner_readiness_test.php` still sets `TALENTHUB_READINESS_SCOPE_ROOT`.

## Blocker 1 — Canonical status behavior in production

Runtime/database canonical contracts:

- `activities`: `draft`, `published`, `ongoing`, `completed`, `archived` when allowed by the actual constraint.
- Student-visible catalog/history query must include only statuses proven visible by the product contract. Use `published`, `ongoing`, and `completed`; do not expose `archived` unless an existing requirement/test proves archived activities are learner-visible.
- `activity_registrations`: `pending`, `approved`, `rejected`, `cancelled`, `attended`.
- `waitlisted` is future schema work and must not be written before its approved migration.
- UI/mock aliases such as `active`, `closed`, `registered`, `checked_in`, and `completed` are boundary aliases only and must never be persisted as canonical DB values.

Required work:

1. Inspect every production and mock consumer of `ActivityStatus` and `ActivityRegistrationStatus`.
2. Add focused failing tests proving:
   - `ongoing` normalizes to `ongoing`;
   - `approved` normalizes to `approved`;
   - `attended` normalizes to `attended`;
   - unknown input remains `unknown`;
   - DB activity queries use canonical visible values and exclude legacy `active/closed`;
   - archived visibility follows the documented product contract;
   - DB repository registration normalization uses one canonical mapper rather than duplicated inline vocabulary;
   - mock/demo aliases still map deliberately at the UI/mock boundary or fixtures are converted to canonical values;
   - no alias can enter a DB write path.
3. Prefer one source of truth. Do not keep production enums, `StudentPortalStatusContract`, repository `match` blocks, and mock mappings with contradictory lists.
4. Run all learner activity/data/render tests and Teacher activity/registration/grading regressions affected by these values.

Do not merely modify the new contract test to match the code.

## Blocker 2 — Deterministic readiness test without production bypass

Production `app/learner/tools/readiness-check.php` must always inspect its real repository root. Do not restore `TALENTHUB_READINESS_SCOPE_ROOT`, a hidden environment override, or another caller-controlled production bypass.

Required work:

1. Keep direct `ReadinessChecker` unit tests on the temporary clean Git fixture.
2. Remove `$fixtureEnv` and all `TALENTHUB_READINESS_SCOPE_ROOT` use from `tests/learner_readiness_test.php`.
3. Test the real CLI against the real repository and compare its phase-0 result with `GitScopeGuard::inspectWorkspace(dirname(__DIR__))`:
   - clean/allowed scope must yield `READY`/exit 0;
   - dirty forbidden scope must yield the corresponding non-ready status/exit without pretending the workspace is clean.
4. Keep the independent direct test proving Phase 0 never invokes the PDO factory.
5. Add safe cleanup for `talenthub-readiness-*` fixtures. Use a shutdown/finally cleanup with an explicit validated temp-directory prefix; never recursively delete a broad or unresolved path.
6. Prove forbidden production paths are still rejected.

## Blocker 3 — RBAC regression contract

The three reviewed permission additions intentionally yield:

```text
roles       = 4
permissions = 103
mappings    = 124
```

Update `tests/qr_session_migration_contract_test.php` deliberately, with a comment or assertion structure showing why counts increased:

- `notification.manage_preferences_own` is common to four roles: +1 unique permission, +4 mappings.
- `certificate.manage_own` belongs to Student: +1 permission, +1 mapping.
- `activity_registration.update_managed` belongs to Teacher: +1 permission, +1 mapping.

Run all permission/seeder/QR tests. Do not run the seed against the database.

## Blocker 4 — Complete the Phase 0 plan amendment

Update `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`. Do not create migrations now.

For each missing group, define the exact future owning migration, unique migration ID, dependency order, tables, essential foreign keys, uniqueness barriers, canonical statuses, owning service/phase, and four-role/AI regression consumers:

- `certificates`, `projects`, `project_members`;
- `badges`, `student_badges`;
- `internship_posts`, `internship_applications`, `application_status_history`;
- `notifications`.

Reconcile these additions with IDs `20260821000100`–`20260821000600`. If six IDs are insufficient, reserve additional monotonically increasing IDs in the plan. Do not overload an unrelated migration silently.

Update phase dependencies so no phase requires a table before its owning migration/DCR task. Preserve the rule that no migration is applied without DCR, verified backup, and disposable-schema rehearsal.

## Blocker 5 — Reports must reflect fresh evidence

Update:

- `docs/superpowers/readiness/2026-08-21-student-portal-runtime-audit.md`
- `docs/superpowers/readiness/2026-08-21-phase-0-1-conditional-2-review-report.md`

Requirements:

- Preserve accepted runtime audit evidence.
- Record this continuation and the interrupted checkpoint.
- Do not claim `qr_session_migration_contract_test.php` passed until a fresh run passes.
- Do not mark Phase 0/1 PASS until every blocker and verification below passes.
- Keep Phase 2 `SKIPPED` and do not begin it.
- Record exact plan amendments and remaining DCR decisions.

## Required final verification

Use the absolute PHP/Node paths. Run fresh:

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$node='C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'

& $php tests\learner_readiness_test.php
& $php tests\learner_phase_requirements_test.php
& $php tests\learner_shared_readiness_test.php
& $php tests\permission_service_driver_compatibility_test.php
& $php tests\learner_data_foundation_test.php
& $php tests\student_portal_cross_role_contract_test.php
& $php tests\qr_session_migration_contract_test.php
& $php tests\learner_assessment_api_test.php
& $php tests\learner_recommendation_api_test.php
& $node --test tests\learner_api_client_test.js
```

Also run:

- every new/focused activity status and repository test;
- affected Teacher activity/registration/grading tests;
- PHP lint on every changed PHP file;
- `git diff --check`;
- `git status --short --branch`;
- `bin/connect-check.php --json --quick`;
- `bin/migrate.php status`;
- `bin/migrate.php validate`;
- protected learner migration hash comparison;
- secret scan of changed/tracked files without printing secret values.

Expected database invariant: connection OK, 15 applied, 0 pending, validation OK, no seed/migration/data mutation.

## Mandatory stop/report format

Stop after verification. Do not commit or start Phase 2/3.

Return:

1. Overall: `GO_FOR_REVIEW` or `NO-GO`.
2. Phase 0 and Phase 1 status.
3. Exact files changed in this continuation.
4. RED/GREEN evidence per blocker.
5. Full test matrix with exit codes and every failure.
6. Database invariants.
7. Plan amendment summary with migration ownership/dependencies.
8. Remaining risks/blockers.
9. Proposed atomic commits, explicitly confirming none were made.
10. Updated report paths.
11. End with: `Đã dừng để Codex reviewer và người dùng duyệt; chưa được phép bắt đầu Phase 2 hoặc Phase 3.`
