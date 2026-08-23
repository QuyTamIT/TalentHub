# Phase 2 Review Report — Talent Passport Read Model

**Date:** 2026-08-22
**Branch / HEAD:** `feature/student` / `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
**Environment:** PHP 8.3.30, MySQL 8.4.3
**Review result:** **PASS — `APPROVED_PHASE_2`**

## Outcome

Phase 2 now provides a truthful, owner-scoped Talent Passport aggregate and database-mode Dashboard/Profile rendering. Future Phase 3/9 tables remain optional: a missing, partial, or incompatible optional schema returns an unavailable capability and `[]`; it does not block Phase 2 and never falls back to demo facts.

No migration, seed, DML, commit, push, or merge was performed during this remediation.

## Review blockers resolved

1. **One canonical optional-schema contract**
   - Added `TalentPassportOptionalSchema` and reused it in readiness and `DatabaseTalentPassportRepository`.
   - Certificates use the approved Phase 3 names, including `issuingOrganization` and `idx_certificates_student_status`.
   - Projects/project members use the approved Phase 3 columns and `uq_project_members_student`.
   - Badges use the reserved Phase 9 names, including `iconUrl`, `uq_badges_code`, and `uq_student_badges_award`.
   - Readiness distinguishes clean absence, partial presence, incompatible schema, and full availability.

2. **Missing authenticated Student fails safely**
   - A missing `student_profiles` identity now throws `LearnerDataQueryException` with a non-PII message instead of fabricating `['id' => $studentId]`.

3. **Production wiring is explicit**
   - Added `learner_configure_authenticated_student_context()`.
   - `student-data.php` configures the database repository only from the authenticated `StudentAppContext` student ID and PDO.
   - `StudentAppContext::boot()` PHPDoc now includes the returned PDO.

4. **Database-mode demo leakage removed from Phase 2 surfaces**
   - Dashboard activity cards come from confirmed canonical experience entries.
   - The section is labelled “Hoạt động đã xác nhận”; it no longer presents history as upcoming activities.
   - The AI card uses an explicit unavailable state rather than the hard-coded IoT/Drone recommendation.
   - Sidebar does not display the demo Innovator level in database mode.
   - Badges and Statistics render explicit unavailable states until their Phase 9 data/rules exist.
   - Mock mode keeps deterministic fixtures for UI tests.

5. **Previously fixed aggregate behavior retained**
   - Skill scores remain on the canonical 0–100 scale.
   - Only confirmed experience contributes hours.
   - Submitted assessment attempts require persisted `test_results`.
   - Teacher names resolve through `assessments.teacherId -> teacher_profiles.id -> users.id`.
   - All Student facts remain scoped by the authenticated `studentId`.

## RED/GREEN evidence

The new regression tests first failed for the expected reasons:

- shared optional schema contract did not exist;
- missing Student returned a fabricated partial passport;
- database Dashboard rendered demo activities/AI/level data;
- database Badges/Statistics rendered demo facts;
- database activity history was labelled as upcoming.

After the implementation changes, all focused regression tests passed.

## Fresh verification

### PHP suites

17 relevant PHP suites exited `0`, including:

- Phase requirements and optional capabilities;
- Talent Passport contract, database data isolation, and render tests;
- learner data foundation and database render integration;
- readiness/shared readiness;
- permission compatibility and four-role contracts;
- QR migration contract;
- activity data/render;
- assessment API;
- recommendation API and recommendation render.

### Browser/Node suites

All 7 Node files passed: **53 tests, 0 failures**.

### Static checks

- PHP lint: **32 changed/new PHP files, 0 syntax errors**.
- `git diff --check`: exit `0`; only Git line-ending notices were emitted.

### Runtime database checks (read-only)

- `bin/connect-check.php --json --quick`: connection `OK`, MySQL `8.4.3`.
- `bin/migrate.php status`: **15 applied, 0 pending**.
- `bin/migrate.php validate`: `OK`.
- Runtime table count remains **45**.
- Relevant row counts remain unchanged:
  - `activities`: 26
  - `activity_registrations`: 40
  - `activity_qr_sessions`: 8
  - `checkins`: 20
  - `experience_logs`: 20
  - `test_attempts`: 42
  - `assessments`: 20
- `certificates`, `projects`, `project_members`, `badges`, and `student_badges` remain absent, as required before Phase 3/9.

## Invariants

- Branch and HEAD did not change.
- Learner migrations `001`–`004` have no Git diff; current SHA-256 values:
  - `001`: `6382F12F89BEC03D232957C3914FD8EC736332381BEB82614F728843A7C417EE`
  - `002`: `F218EC8E2A2A730197DD07F4F00F4EC5B14B5651F5B41B6E2307C4A87619B970`
  - `003`: `07A5AE89B21433E893C54E168A06B464CB8E6092108DD31CBB18A3999A7EC75B`
  - `004`: `83F684C68827CA8C1623668552DF5B44663421F5714165FCB077F2496C56B287`
- `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- `.env`, `.claude/`, and `.qwen/` were not edited by this remediation.

## Gate decision

`APPROVED_PHASE_2`

Phase 3 may now begin with contract tests and disposable-schema rehearsal. This approval does **not** authorize applying Phase 3 migrations to `talenthub_local`; that still requires the separate exact authorization `APPROVED_PHASE_3_DCR_APPLY` after rehearsal and backup evidence.
