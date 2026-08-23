# Phase 7: Codex CLI Complex Work Implementation Handoff

- **Date:** 2026-08-22
- **Author:** Antigravity (Phase 7 Basic Preflight)
- **Status:** APPROVED_FOR_CODEX_CLI_ASSIGNMENT
- **Branch:** `feature/student`
- **Baseline HEAD:** `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- **Specification:** `docs/superpowers/specs/2026-08-22-phase-7-enterprise-application-lifecycle-design.md`
- **Database Change Request:** `docs/superpowers/database-change-requests/2026-08-22-phase-7-enterprise-application-lifecycle.md`

---

## 1. Overview & Operational Principles

This handoff details the execution steps for the Codex CLI agent to implement Phase 7 of TalentHub. All work is broken down into structured, reviewable units.

### Mandatory Rules for Codex CLI:
1. **Preserve Phase 2–6 Dirty Worktree:** Do not run `git clean`, `git reset --hard`, `git checkout`, or `git stash`.
2. **Primary DB Protection:** Do NOT apply migration directly to `talenthub_local` until Unit 1 passes complete rehearsal on a disposable schema AND the human reviewer gives explicit authorization.
3. **No Phase 8 Work:** Do not create notification tables, endpoints, or UI in this phase.
4. **No Arbitrary ID Input:** Never trust `enterpriseId` or `studentId` from request bodies or URL parameters for authorization. Always resolve from session and membership tables.

---

## 2. Review Units

### Unit 1: Migration + Disposable Rehearsal + DCR Gate

**Objective:** Implement the Phase 7 migration and verify it on a disposable database schema with idempotency and zero-data-loss assertions.

- **Exact Files to Create:**
  - `Database/migrations/20260821000500_create_internships_and_application_lifecycle.php`
  - `tests/phase7_rehearsal_integrity_test.php`
- **Key Invariants:**
  - Forward-only: `isReversible() === false`.
  - Tables created: `internship_posts`, `internship_applications`, `application_status_history`, `application_profile_snapshots`.
  - Enforce `uq_internship_applications_post_student` unique constraint on `(postId, studentId)`.
  - Enforce `uq_application_profile_snapshots_application` unique constraint on `applicationId`.
  - Enforce CHECK constraints for statuses and `JSON_VALID()`.
  - Preflight checks require `@@session.time_zone === '+00:00'` and parent table presence.
  - Preserve all 51 non-registry baseline tables byte-for-byte. `schema_migrations` may only append the Phase 7 row; its 23 existing rows/checksums remain unchanged.
- **Verification Commands:**
  ```powershell
  # 1. Verify contract test turns GREEN
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\application_profile_snapshot_migration_test.php

  # 2. Run migration validate on workspace
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php validate

  # 3. Execute disposable rehearsal through the guarded Phase 7 rehearsal test/helper.
  # The helper must generate and validate an exact talenthub_phase7_rehearsal_* name,
  # assert DATABASE() is never talenthub_local before mutation, and own cleanup.
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\phase7_rehearsal_integrity_test.php
  ```

---

### Unit 2: Student Application Command Transaction & API

**Objective:** Implement student-side application creation and withdrawal commands and REST endpoints.

- **Exact Files to Create / Modify:**
  - Create: `app/learner/data/Contracts/ApplicationCommandRepository.php`
  - Create: `app/learner/data/Database/DatabaseApplicationCommandRepository.php`
  - Create: `app/learner/data/Service/ApplicationCommandService.php`
  - Create: `app/learner/api/v1/applications.php`
  - Test: `tests/learner_application_api_test.php`
- **Key Invariants:**
  - Submit transaction: Verify active post (`status = 'active'` and `deadline >= NOW(6)`), active `application_profile_share` consent, insert `submitted` application, insert allow-listed snapshot JSON, insert initial status history.
  - Submission must not silently create consent. Missing or revoked consent produces zero application writes. If the current portal has no grant path, implement a separate explicit learner-confirmed consent command protected by `privacy_consent.manage_own`; only retry submission after that command succeeds.
  - Reject duplicate submission with `409 DUPLICATE_APPLICATION`.
  - Withdraw transaction: Verify status is in `['submitted', 'reviewing', 'interview']`, update to `withdrawn`, append history. Disallow withdrawal if `accepted` or `declined`.
  - Permissions required: `internship_application.create_own`, `internship_application.read_own`, `internship_application.withdraw_own`.
- **Verification Commands:**
  ```powershell
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_application_api_test.php
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l app\learner\api\v1\applications.php
  ```

---

### Unit 3: Enterprise Ownership & Review Transaction / Routes

**Objective:** Implement enterprise-side post management and applicant review commands with strict cross-enterprise isolation.

- **Exact Files to Create / Modify:**
  - Create: `src/Modules/Business/Repository/InternshipRepository.php`
  - Create: `src/Modules/Business/Service/InternshipService.php`
  - Modify: `src/Bootstrap/Application.php` (add `/api/v1/businesses/me/internships` and `/api/v1/businesses/me/internships/{postId}/applications/{applicationId}`)
  - Test: `tests/Integration/EnterpriseApplicationLifecycleTest.php`
- **Key Invariants:**
  - Resolve `enterpriseId` solely from session user ID via `enterprise_members`.
  - Enterprise review transaction: `SELECT ... FOR UPDATE` joined with `internship_posts WHERE enterpriseId = :enterpriseId`.
  - Guard against lost updates using `expectedCurrentStatus`.
  - Enforce status transition matrix: `submitted` &rarr; `reviewing` &rarr; `interview` &rarr; `accepted` / `declined`.
  - Return `404 Not Found` for cross-enterprise access (do not leak existence with 403).
  - Snapshot / CV access authorized strictly through application ownership.
- **Verification Commands:**
  ```powershell
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\Integration\EnterpriseApplicationLifecycleTest.php
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l src\Modules\Business\Service\InternshipService.php
  ```

---

### Unit 4: Student & Enterprise Server-Confirmed UI Integration

**Objective:** Wire the Student learner opportunity page and Enterprise applicant review pages to live database mode with fallback / mock safety.

- **Exact Files to Modify:**
  - `app/learner/opportunity.php`
  - `app/learner/ecosystem.php`
  - `app/enterprise/internships/index.php`
  - `app/enterprise/internships/applicants.php`
  - Reuse `assets/js/learner-api.js`; modify it only if a failing contract test proves the existing client cannot call the approved route.
  - Test: `tests/learner_opportunity_ui_test.js` (or PHP render test)
  - Test: `tests/enterprise_applicant_render_test.php`
- **Key Invariants:**
  - In database mode, render actual database posts and application statuses.
  - Modals and forms send JSON requests with `x-csrf-token` to the corresponding API.
  - Zero raw exceptions or stack traces exposed in UI. Proper empty and error states rendered.
  - Immutability guarantee: Snapshot view displays data as submitted, ignoring later profile changes.
- **Verification Commands:**
  ```powershell
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_database_render_test.php
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l app\learner\opportunity.php
  & 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l app\enterprise\internships\applicants.php
  ```

---

### Unit 5: Full Cross-Role, Concurrency & Regression Verification

**Objective:** Execute full cross-role regression, concurrency tests, and PHP lint.

- **Exact Test Suites to Run:**
  - `tests/student_portal_cross_role_contract_test.php`
  - `tests/learner_application_api_test.php`
  - `tests/Integration/EnterpriseApplicationLifecycleTest.php`
  - `tests/learner_database_render_test.php`
  - `tests/phase5_applied_schema_contract_test.php`
  - Full PHP lint over entire repository (`474+ files`).
- **Verification Commands:**
  ```powershell
  # Run every PHP file through php -l, record each exit code, and fail the
  # aggregate command when any file exits non-zero. Do not infer success from
  # filtered text output.
  ```

---

### Unit 6: Backup, Approved Primary Apply (Reviewer-Gated)

**Objective:** If and only if the reviewer authorizes primary apply:

1. Create a fresh timestamped backup of `talenthub_local`.
2. Compute and log backup SHA-256.
3. Run `bin/migrate.php up` on `talenthub_local`.
4. Verify table count increments from 52 to 56.
5. Run `bin/migrate.php validate` & `status`.
6. Assert all 51 non-registry pre-existing tables maintain stable row counts and data hashes; verify `schema_migrations` changed only by appending the Phase 7 row while all prior checksums remain stable.

---

### Unit 7: Phase 7 Final Review Report

**Objective:** Produce the final Phase 7 review report documenting all changes, verification evidence, database state, and handoff to Phase 8.
