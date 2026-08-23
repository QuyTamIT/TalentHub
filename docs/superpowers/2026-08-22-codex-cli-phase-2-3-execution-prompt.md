# Codex CLI Execution Prompt — Student Portal Phase 2 and Phase 3

You are implementing the approved Student Portal Phase 2 and conditional Phase 3 in `D:\TalentHub`.

## Read first

Read these files completely before changing code:

1. `docs/superpowers/specs/2026-08-22-phase-2-talent-passport-design.md`
2. `docs/superpowers/plans/2026-08-22-phase-2-3-talent-passport-implementation.md`
3. `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`
4. `docs/superpowers/readiness/2026-08-21-phase-0-1-conditional-2-review-report.md`
5. `docs/superpowers/readiness/2026-08-21-student-portal-runtime-audit.md`

Then inspect the current Git diff and live schema. The working tree already contains approved Phase 0/1 work. Preserve it exactly; never reset, clean, checkout, or overwrite unrelated changes.

## Runtime

- Working directory: `D:\TalentHub`
- Branch must remain: `feature/student`
- Starting reference HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`

Use absolute executable paths. If MySQL is unavailable, continue only with work that can be truthfully verified without it, report the blocker, and do not declare a phase PASS.

## Required workflow

Use `superpowers:executing-plans` and `superpowers:test-driven-development`. For failures, use `superpowers:systematic-debugging`. Before any completion claim, use `superpowers:verification-before-completion`.

Execute `docs/superpowers/plans/2026-08-22-phase-2-3-talent-passport-implementation.md` task by task. Update its checkboxes only after fresh evidence exists. Keep changes small and reviewable. Do not substitute a different architecture.

## Phase 2 gate

Execute Tasks 1–6 only.

Phase 2 rules:

- No migration, seed, INSERT, UPDATE, DELETE, TRUNCATE, DROP, or database mutation.
- Do not create `certificates`, `projects`, `project_members`, `badges`, or `student_badges`.
- Required canonical facts come from DB; missing Phase 3/9 facts return `[]`.
- No mock or localStorage fallback in database mode.
- Student isolation and Teacher/School/Enterprise ownership must remain intact.
- Keep AI visible percentage at `0`.

At Task 6, create `docs/superpowers/readiness/2026-08-22-phase-2-talent-passport-review-report.md`, print a concise report in the terminal, and STOP. Do not begin Phase 3 in the same turn unless the user/reviewer supplies the exact authorization string:

```text
APPROVED_PHASE_2
```

An ordinary “continue”, old approval, or your own PASS decision is not authorization.

## Conditional Phase 3 gate

After receiving `APPROVED_PHASE_2`, re-read the implementation plan, current diff, and Phase 2 report. Execute Tasks 7–14.

Phase 3 rules:

- Create shared schema only through:
  - `Database/migrations/20260821000100_create_student_passport_sharing.php`
  - `Database/migrations/20260821000200_create_student_certificates_and_projects.php`
- Never edit learner migrations `001`–`004`.
- Never create badge tables; badges remain Phase 9.
- Use TDD and prove migration contracts RED before migration files exist.
- Use the existing shared `PATCH /api/v1/students/me`; do not create a duplicate profile update endpoint.
- All mutations require session, role, permission, CSRF, ownership scope, validation, and transaction boundaries.
- Persist only SHA-256 share-token hashes; return a raw token once; never log or store raw tokens in DB/localStorage.
- Enterprise cannot access a passport without an active, unexpired, non-revoked share and consented fields.

Task 14 may use only an explicitly named disposable clone. After the rehearsal report and DCR are complete, STOP. Do not mutate `talenthub_local` unless the user/reviewer supplies the exact authorization string:

```text
APPROVED_PHASE_3_DCR_APPLY
```

After that approval, execute Task 15 only: verify backup, apply the two migrations through the canonical runner, run full verification, and write the final Phase 3 report.

## Protected invariants

- Do not edit `.env`, `.claude/`, or `.qwen/`.
- Do not commit, push, merge, reset, clean, stash, or discard user changes.
- Do not expose secrets or PII in logs/reports.
- Preserve the protected migration hashes.
- Do not claim tests passed unless the current run shows zero failures.
- A PHP render test that exits `0` without its explicit `OK` marker is a failure.
- Do not mark a task complete because the context window is low. Write an exact continuation note in the phase report and stop at an atomic checkpoint.

## Required final report format

For every gate, report:

1. Overall decision: PASS, FAIL, or BLOCKED.
2. Tasks completed and tasks remaining.
3. Files created/modified.
4. RED evidence and GREEN evidence with exact commands and exit codes.
5. Database connection, migration state, row counts, and whether any mutation occurred.
6. Student isolation and four-role interaction results.
7. CSRF, authorization, transaction, privacy, token, and AI visibility invariants.
8. Protected migration hashes and `git diff --check` result.
9. Risks/blockers.
10. Exact next authorization required.

Do not ask broad planning questions already answered by the spec and plan. Ask only if a newly discovered schema collision or irreversible decision cannot be resolved safely from repository evidence.
