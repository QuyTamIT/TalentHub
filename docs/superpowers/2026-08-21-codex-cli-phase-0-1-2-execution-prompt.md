# Codex CLI Execution Prompt — Student Portal Phases 0, 1, and Conditional Phase 2

## 1. Mission

Work in `D:\TalentHub` and execute the approved Student Portal plan through:

1. Phase 0 — Evidence and Runtime Reconciliation.
2. Phase 1 — Contract and Migration Safety Foundation, but only after the Phase 0 gate is green.
3. Phase 2 — Dashboard and Talent Passport Reads, only when every Phase 2 entry condition in this prompt is green.

At the end, stop and produce one complete review report. Do not start Phase 3. The report will be reviewed by another Codex session and the user before any later phase is authorized.

This prompt is explicit authorization to pass from Phase 0 to Phase 1, and from Phase 1 to Phase 2 only when the internal gates below pass. It is not authorization to bypass a failed gate.

## 1.1 Resume checkpoint — authoritative override

This is a continuation of an existing Codex CLI session, not a fresh Phase 0 run. Reuse the evidence already produced in that session. Do not rediscover or rerun accepted work merely to follow command blocks later in this document.

The following Phase 0 work is `ACCEPTED_COMPLETED`:

- Branch `feature/student` and starting HEAD `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4` were verified.
- PHP and MySQL absolute paths were verified.
- PHP 8.3.30 with `PDO` and `pdo_mysql` was verified.
- `bin/connect-check.php --json --quick` returned `connection: OK`.
- MySQL server version 8.4.3 was verified.
- `bin/migrate.php status` returned 15 applied and 0 pending migrations.
- `bin/migrate.php validate` returned validation OK/no drift.
- Read-only `information_schema` and status-count queries were run.
- Runtime table counts and the reported status snapshots were captured.
- The first-pass shared ownership/consumer overview was captured.
- No database mutation, migration apply, seed, commit, push, or merge occurred.

Do not repeat those completed checks now. They may be referenced directly in the Phase 0 audit. A single fresh connection/status/validate smoke check is required only at final verification after all authorized source changes, because that proves the work did not introduce drift.

The following Phase 0 work is `REMAINING`:

- Expand the existing inventory only where required fields were not captured: exact columns, indexes, foreign keys, checks, collations, and exact file/operation consumer evidence.
- Reconcile runtime, migrations, `Database/Talenthub.sql`, and `PhaseRequirements.php`.
- Resolve the plan gaps caused by absent opportunity/application/notification/badge tables.
- Run the six baseline tests and root-cause/document any failure.
- Create the runtime audit and amend the plan where evidence requires it.
- Evaluate and close the full Phase 0 exit gate.

If a missing detail cannot be recovered from the existing session output or source, run only the smallest targeted read-only query needed for that detail. State why the targeted query was necessary. Do not rerun the full inventory.

## 2. Sources of truth

Read these completely before making changes:

- `AGENTS.md` and every applicable nested `AGENTS.md`, if present.
- `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`
- `docs/superpowers/2026-08-21-codex-cli-student-portal-planning-prompt.md`
- Existing repository instructions and relevant tests.

Use `superpowers:using-superpowers` first. Use `superpowers:subagent-driven-development` when safe and available, otherwise `superpowers:executing-plans`. Use `superpowers:test-driven-development` for implementation and `superpowers:verification-before-completion` before every success claim.

The revised implementation plan is authoritative unless runtime evidence proves it wrong. If evidence conflicts with the plan, amend the plan explicitly; never silently code around the conflict.

## 3. Locked environment and baseline

- Repository: `D:\TalentHub`
- Required branch: `feature/student`
- Locked starting HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL client: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- MySQL server: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe`
- Expected DB host: `127.0.0.1:3306`
- Expected DB: `talenthub_local`
- Expected MySQL: `8.4.3`
- Node, if required: `C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe`

Do not require PHP or MySQL to be on `PATH`; use the absolute paths above.

Known pre-existing untracked/local-only files include `.claude/settings.local.json`, `.qwen/settings.json`, and planning documents under `docs/superpowers/`. Re-inventory them yourself. Do not modify, delete, stage, or commit `.env`, `.claude/`, or `.qwen/`.

## 4. Non-negotiable safety rules

- Work only on `feature/student`.
- Do not push or merge.
- Do not alter history, reset the branch, discard user changes, or clean untracked files.
- Do not print secrets, `.env`, passwords, raw QR tokens, private profiles/CVs, assessment answers, or raw AI-provider payloads.
- Do not edit any applied migration or `Database/migrations/learner/001` through `004`.
- Do not apply migrations, seed, truncate, delete, or update `talenthub_local` during Phases 0–2.
- Phase 0 database work is strictly `SELECT`, `SHOW`, `EXPLAIN`, connection validation, migration status, and migration validation.
- Phase 1 may change contract tests and proven RBAC seed definitions, but must not apply the seed to the primary database.
- Phase 2 must be read-only at runtime. It may not introduce a schema mutation.
- Any test that writes data must use an explicitly allow-listed disposable test database or a transaction that is proven to roll back. Never point destructive cleanup at `talenthub_local`.
- Preserve `TALENTHUB_AI_VISIBLE_PERCENT=0`. Rule output remains learner-visible; 9Router remains Shadow-only.
- Preserve current Teacher, School, Enterprise, shared authentication, and AI behavior.
- Use prepared statements for runtime values and authenticated session identity for ownership.
- Do not claim completion from static inspection, mock data, rendered UI, or a partial test suite.
- Do not commit automatically. At the end, propose atomic commits and their file lists, but leave all changes uncommitted for review.

## 5. Execution protocol

For each phase:

1. Recheck branch, HEAD, worktree scope, and database safety.
2. Mark only the current phase/task as in progress in your own execution checklist.
3. Investigate before changing behavior.
4. For code changes, follow RED → minimal GREEN → focused regressions → affected-role regressions.
5. Capture exact commands, exit codes, and concise evidence.
6. Run `git diff --check` and review the full diff.
7. Evaluate the phase gate literally.
8. If the gate fails, stop all later phases and produce the final report with `NO-GO`.
9. If the gate passes, proceed only to the next phase authorized by this prompt.

Do not run independent agents concurrently against the same database or files. Parallelize only read-only source inspection with disjoint scope.

---

# Phase 0 — Complete the Remaining Evidence and Close the Gate

## P0.1 Baseline and scope

Status: `ACCEPTED_COMPLETED` for the initial baseline. Do not repeat the full migration-file inventory. Before editing, run only `git branch --show-current`, `git rev-parse HEAD`, and `git status --short --branch` to ensure the continuation is still on the accepted workspace. Record any change since the accepted checkpoint.

The original reference commands were:

```powershell
Set-Location -LiteralPath 'D:\TalentHub'
git branch --show-current
git rev-parse HEAD
git status --short --branch
git diff --name-only
git diff --cached --name-only
Get-ChildItem Database\migrations,Database\migrations\learner -File -Recurse |
    Sort-Object FullName |
    Select-Object -ExpandProperty FullName
```

Required conclusions:

- Confirm branch and explain any HEAD difference from the locked baseline.
- Classify every modified/untracked file as pre-existing, created by this execution, allowed, protected, or blocker.
- Prove migration IDs `20260821000100` through `20260821000600` are unclaimed, or reserve new monotonically increasing IDs and amend the plan before implementation.
- Hash learner migrations `001`–`004` at start and compare again at the end.

## P0.2 Runtime and migration verification

Status: `ACCEPTED_COMPLETED`. Reuse the accepted results. Do not run these commands again at the start. They appear here as evidence/reference and will be run once at final verification after authorized source changes:

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
Test-Path -LiteralPath $php
& $php -v
& $php -m
& $php bin\connect-check.php --json --quick
& $php bin\migrate.php status
& $php bin\migrate.php validate
```

Confirm PHP, `PDO`, `pdo_mysql`, database name, server version, applied/pending migration counts, and drift. Redact passwords and secrets.

Expected baseline previously observed: PHP 8.3.30, MySQL 8.4.3, connection OK, 15 applied, 0 pending, validation OK.

## P0.3 Complete runtime schema inventory

Continue from the existing read-only inventory. Do not start it over. Use source inspection first, then only the smallest missing read-only `information_schema` queries. For every relevant table ensure the final audit captures:

- table name and collation;
- row count;
- column name, type, nullability, default, generated/extra metadata;
- primary, unique, and non-unique indexes with ordered columns;
- foreign keys and referenced targets;
- check constraints;
- existing status values and counts where applicable.

Inventory at minimum:

- identity/RBAC: `users`, `roles`, `permissions`, `role_permissions`;
- organization/profile: `schools`, `classes`, `student_profiles`, `teacher_profiles`, `school_members`, `enterprise_members`;
- evidence/passport: `student_skills`, `skills`, `certificates`, `projects`, `project_members`, `experience_logs`, `badges`, `student_badges`;
- activities: `activities`, `activity_registrations`, `activity_qr_sessions`, `checkins`;
- assessments: `talent_tests`, `test_questions`, `test_attempts`, `test_results`, `assessments`, `assessment_scores`, learner assessment support tables;
- opportunity/application: `internship_posts`, `internship_applications`, `application_status_history` and semantic equivalents;
- privacy/AI: `privacy_consents`, `learner_ai_consent_events`, `learner_recommendation_*`;
- notifications: `notifications` and semantic equivalents.

An absent table is evidence, not permission to create it.

## P0.4 Reader/writer and ownership map

For every shared table, list:

- exact repository/service/API/seed/verifier file paths;
- role/module: Student, Teacher, School, Enterprise, AI, demo tooling;
- operations: `SELECT`, `INSERT`, `UPDATE`, `DELETE`;
- state-machine owner;
- authorization and organization ownership boundary;
- status values read or written;
- affected regression tests.

Do not provide only class names. Include exact file paths and SQL operation evidence.

## P0.5 Reconcile four schema sources

Compare:

1. Runtime `information_schema`.
2. `Database/migrations/**` plus applied migration registry.
3. `Database/Talenthub.sql`.
4. `app/learner/data/Readiness/PhaseRequirements.php` and consumers.

Classify every mismatch as:

- `CONFIRMED_CANONICAL`;
- `LEGACY_DUMP_ONLY`;
- `CODE_CONSUMER_WITHOUT_RUNTIME_TABLE`;
- `PLANNED_NEW_TABLE`;
- `SEMANTIC_EQUIVALENT_REQUIRES_REUSE`;
- `BLOCKED_SCHEMA_AMBIGUITY`.

Runtime plus applied migrations is authoritative. Never create a duplicate table because a dump or mock uses another name.

Explicitly resolve or block on:

- `internship_posts`;
- `internship_applications`;
- `application_status_history`;
- `notifications`;
- `badges`;
- `student_badges`.

The current plan must be amended if it assumes these tables already exist. The amendment must specify the owning migration, unique migration ID, dependency order, foreign keys, unique constraints, canonical statuses, and four-role consumers. Do not implement these migrations in Phases 0–2.

## P0.6 Baseline tests

Run all six and record each exit code:

```powershell
& $php tests\learner_readiness_test.php
& $php tests\learner_phase_requirements_test.php
& $php tests\permission_service_driver_compatibility_test.php
& $php tests\qr_session_migration_contract_test.php
& $php tests\learner_assessment_api_test.php
& $php tests\learner_recommendation_api_test.php
```

Known independent result before this execution: five tests passed and `tests/learner_readiness_test.php` failed because `GitScopeGuard::inspectWorkspace()` reported `.claude/settings.local.json` and `.qwen/settings.json` as forbidden. The assertion label about the database factory is not proof that the factory ran.

Investigate and document this precisely. During Phase 0, do not fix the test, change the guard, or delete local config. Record it as `BASELINE_SCOPE_BLOCKER` with a proposed deterministic Phase 1 test strategy.

## P0.7 Phase 0 artifacts

Create or update:

- `docs/superpowers/readiness/2026-08-21-student-portal-runtime-audit.md`
- `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`, only where evidence requires a plan amendment or tracker update.

The audit must include environment, schema inventory, consumer map, four-source reconciliation, test matrix, blockers, canonical statuses, exact next migration ID, clone/rehearsal feasibility, and database non-mutation evidence.

## P0 exit gate

Phase 0 is green only when all are true:

- Runtime connection/status/validation succeeded and drift is classified.
- Schema and consumer inventories contain the required details.
- Every missing/semantic-equivalent table is resolved or the run stops as `NO-GO`.
- Exact canonical statuses and next migration IDs are documented.
- Disposable clone/rehearsal feasibility is established without cloning the primary database.
- Baseline test failure is root-caused and explicitly assigned to Phase 1.
- No database mutation occurred.
- Audit and necessary plan amendment are internally consistent.

If any canonical-table ambiguity remains, stop. Do not start Phase 1.

---

# Phase 1 — Contract and Migration Safety Foundation

Enter only after P0 is green.

## P1.1 Establish deterministic readiness tests

Resolve `BASELINE_SCOPE_BLOCKER` using TDD. Preserve the production purpose of `GitScopeGuard` and the rule that `.claude/` and `.qwen/` must not enter commits.

Required behavior:

- The unit assertion proving Phase 0 does not call the PDO factory must not depend on unrelated dirty files in the developer's real workspace.
- Scope enforcement must still reject actual forbidden source changes.
- Do not broadly ignore `.claude/`, `.qwen/`, arbitrary dot-directories, or protected paths without an explicit, tested contract.
- Prefer a deterministic temporary repository/controlled path fixture for the test over weakening production scope validation.

Run the test RED for the confirmed reason, implement the smallest correction, then prove GREEN and run the full readiness regressions.

## P1.2 Lock cross-role contracts with TDD

Follow Phase 1 Task 2 in the revised plan. Create/modify only evidence-backed files, including:

- `tests/learner_phase_requirements_test.php`
- `tests/learner_shared_readiness_test.php`
- `tests/permission_service_driver_compatibility_test.php`
- `tests/student_portal_cross_role_contract_test.php`
- `Database/seeds/System/RolePermissionSeeder.php` only for proven permission gaps.
- The smallest existing learner status mapper file proven by source inspection.

Tests must lock:

- Only `/api/v1` and `/app/learner/api/v1` as approved API bases.
- Shared auth/session/profile routes are reused, not duplicated.
- Existing permission vocabulary is reused.
- `certificate.manage_own`, `activity_registration.update_managed`, and `notification.manage_preferences_own` are added only if Phase 0 proves no safe existing equivalent.
- Learner migrations `001`–`004` remain unchanged.
- Every planned shared migration ID is unique.
- UI aliases are output-only and cannot be persisted as canonical statuses.
- Student, Teacher, School, and Enterprise ownership boundaries are explicit.
- AI visibility remains zero and Rule remains the visible fallback.

Do not change a permission seed merely because the plan listed it. Prove the exact mutation lacks an existing permission first.

## P1.3 RED/GREEN requirements

For every new contract:

1. Add the smallest failing assertion.
2. Run it and record the expected failure.
3. Implement only the minimum required contract.
4. Run the focused test and record GREEN.
5. Run affected-role regressions.

Do not bundle unrelated refactors.

## P1.4 Verification

Run at minimum:

```powershell
& $php tests\learner_readiness_test.php
& $php tests\learner_phase_requirements_test.php
& $php tests\learner_shared_readiness_test.php
& $php tests\permission_service_driver_compatibility_test.php
& $php tests\learner_data_foundation_test.php
& $php tests\student_portal_cross_role_contract_test.php
& 'C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' --test tests\learner_api_client_test.js
```

Also run syntax checks on every changed PHP file and all existing tests directly affected by API bases, permissions, status mappings, readiness, Teacher activity/QR/grading, School scoped reads, Enterprise reads, assessment, and AI sources.

If a named test does not exist, do not silently skip it. Report that fact and create it only when the plan explicitly requires it.

## P1 exit gate

Phase 1 is green only when:

- The baseline readiness failure is fixed without weakening scope protection.
- Contract tests demonstrate RED then GREEN.
- API boundaries, permissions, state owners, canonical statuses, and migration IDs are locked.
- No seed was applied and no database row/schema changed.
- All focused and affected-role regressions pass.
- Diff contains no unrelated/protected-role behavior changes.

If any condition fails, stop and report `NO-GO`; do not start Phase 2.

---

# Conditional Phase 2 — Dashboard and Talent Passport Reads

Phase 2 is optional in this execution. Start it only when every entry condition below is green.

## P2 entry decision

Before editing Phase 2 files, write a decision record in the final report:

- `GO_PHASE_2`, with evidence for every condition; or
- `SKIP_PHASE_2`, with exact blockers and required next action.

All conditions required for `GO_PHASE_2`:

- P0 and P1 exit gates are green.
- No unresolved canonical-table or semantic-equivalent ambiguity remains.
- Phase 2 can be completed without applying a migration or mutating primary DB data.
- Required canonical evidence tables exist, or the revised plan explicitly defines an honest response contract for a not-yet-created optional fact without masking schema drift.
- In particular, missing `badges`/`student_badges` cannot be silently ignored if `PhaseRequirements.php` or the Phase 2 aggregate requires them.
- No new product decision, DCR, backup/restore, seed apply, or schema creation is required.
- Student/Teacher published-evaluation boundaries and owner scoping are already testable.

If any condition is false, do not implement a partial Phase 2. Record `SKIP_PHASE_2` and stop after Phase 1.

## P2.1 Implement the real Talent Passport read slice with TDD

If `GO_PHASE_2`, follow Phase 2 Task 3 exactly. Expected scope:

- Create `app/learner/data/Contracts/TalentPassportRepository.php`.
- Create `app/learner/data/Database/DatabaseTalentPassportRepository.php`.
- Create `app/learner/data/ReadModel/TalentPassportReadModel.php`.
- Modify `app/learner/data/RepositoryFactory.php`.
- Modify `app/learner/data/bootstrap.php`.
- Modify `app/learner/includes/student-data.php`.
- Modify `app/learner/index.php`.
- Modify `app/learner/profile.php`.
- Create `tests/learner_talent_passport_data_test.php`.
- Create `tests/learner_talent_passport_render_test.php`.

Required aggregate contract:

```php
interface TalentPassportRepository
{
    public function aggregateForStudent(string $studentId): array;
}
```

The aggregate may contain only canonical, owner-scoped facts:

- student profile;
- verified or self-declared skills with truthful labels;
- certificates;
- projects;
- confirmed experience only;
- submitted automated assessment results;
- published Teacher evaluations only, labeled separately;
- awarded badges only when canonical tables exist;
- source timestamps and explicit empty/insufficient states.

It must not include school-wide totals, draft Teacher evaluations, another student's rows, inferred verification, hard-coded KPIs, mock success, or model-generated AI claims.

## P2.2 Required tests

Use RED → GREEN for:

- current-student isolation;
- cross-student denial/non-enumeration;
- honest empty state;
- confirmed-only experience hours;
- submitted-only automated results;
- published-only Teacher evaluations;
- distinct source labels for automated and Teacher results;
- no hard-coded KPI values;
- prepared query behavior;
- render escaping/XSS safety;
- stable refresh/device-independent DB read behavior;
- compatibility with AI read sources without changing AI visibility.

Preserve explicit test/demo fixture modes, but production mode must never fall back silently to mocks.

## P2.3 Verification

Run:

- both new Talent Passport tests;
- all existing learner Dashboard/Profile render and data tests;
- assessment and Teacher evaluation regressions;
- AI source/recommendation regressions;
- shared auth/session/permission regressions;
- PHP syntax checks for every changed PHP file;
- Node tests for changed learner UI behavior, if any;
- a read-only runtime smoke test using a known demo student without printing private payloads.

Then run `bin/connect-check.php --json --quick`, `bin/migrate.php status`, and `bin/migrate.php validate` again to prove 15 applied/0 pending/no drift or document the exact current equivalent. Prove relevant primary row counts/hashes did not change.

## P2 exit gate

Phase 2 is green only when:

- Production Dashboard/Profile reads canonical DB facts.
- Refresh/device changes do not lose state.
- Owner isolation and published/confirmed filters pass.
- Missing facts render explicit empty/insufficient states, never invented values.
- Teacher, School, Enterprise, assessment, and AI regressions remain green.
- No database schema or row changed.

Regardless of result, do not start Phase 3.

---

# Final verification and mandatory review report

## Final commands

Run fresh after all allowed work:

```powershell
git branch --show-current
git rev-parse HEAD
git status --short --branch
git diff --check
git diff --stat
git diff -- docs/superpowers/
& $php bin\connect-check.php --json --quick
& $php bin\migrate.php status
& $php bin\migrate.php validate
```

Re-hash protected migrations and compare them with the Phase 0 baseline. Scan changed/tracked files for secrets without printing candidate secret values.

Create the final report:

- `docs/superpowers/readiness/2026-08-21-phase-0-1-conditional-2-review-report.md`

## Required report structure

```markdown
# Phase 0–1 and Conditional Phase 2 Review Report

## 1. Executive decision
- Overall: GO_FOR_REVIEW | NO-GO
- Phase 0: PASS | FAIL
- Phase 1: PASS | FAIL | NOT_STARTED
- Phase 2 decision: PASS | FAIL | SKIPPED | NOT_STARTED
- Authorized next phase: NONE (human review required)

## 2. Baseline and scope
- Branch, starting HEAD, ending HEAD
- Pre-existing changes
- Files created/modified by this execution
- Protected files/migrations hash result

## 3. Phase 0 evidence
- Runtime versions and commands
- Migration status/validation
- Schema inventory summary and link to full audit
- Consumer/ownership map summary
- Four-source reconciliation
- Missing tables and exact plan amendments
- Baseline test matrix with command and exit code

## 4. Phase 1 evidence
- RED evidence
- GREEN evidence
- API contract decisions
- Permission reuse/addition proof
- Canonical status mappings
- Four-role regression results

## 5. Conditional Phase 2
- GO_PHASE_2 or SKIP_PHASE_2 rationale
- Changed architecture/files if executed
- Isolation, published-only, confirmed-only, empty-state evidence
- Runtime smoke-test result

## 6. Database invariants
- Before/after migration counts and drift
- Before/after relevant row counts/hashes
- Confirmation of no migration, seed, or data mutation

## 7. Security and privacy
- Auth/permission/ownership/CSRF/prepared statement findings
- Secret scan count without secret values
- AI visibility/fallback state

## 8. Test matrix
| Command | Scope | Exit code | Pass/fail | Notes |

## 9. Unresolved risks and blockers
- Severity
- Evidence
- Required action
- Blocking phase

## 10. Diff and proposed commits
- git diff --stat
- Per-file purpose
- Proposed atomic commit groups and messages
- No commit performed

## 11. Reviewer checklist
- Exact questions requiring human/Codex review
- Recommendation: approve, request changes, or block
```

## Final response to the user

After writing the report, stop and return a concise message containing:

- Overall result.
- Phase status table.
- Files changed.
- Test pass/fail counts and every failed test.
- Confirmation that database was or was not mutated.
- `GO_PHASE_2` or `SKIP_PHASE_2` decision.
- All blockers and risks.
- Proposed commits, with confirmation that none were made.
- Exact path to both the runtime audit and final review report.
- The sentence: `Đã dừng để Codex reviewer và người dùng duyệt; chưa được phép bắt đầu Phase 3.`

Do not hide failed commands, downgrade failures to warnings, or claim a phase passed without fresh evidence from this execution.
