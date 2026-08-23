# Phase 7 Basic Preflight Report — 2026-08-22

- **Author:** Antigravity (Model: Gemini 3.7 Flash)
- **Status:** APPROVED_PHASE_7_BASIC_HANDOFF
- **Branch:** `feature/student`
- **Baseline HEAD:** `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- **Target Database:** `talenthub_local` (MySQL 8.4.3)

---

## 1. Environment & Invariant State

- **Branch / Commit:** `feature/student` @ `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- **Worktree:** Dirty worktree preserved according to design (contains uncommitted modifications from Phase 2–6).
- **Migration Engine:** 23 applied migrations, 0 pending, `bin/migrate.php validate` is `[OK]`.
- **Database Mutation:** ZERO primary mutations occurred in this turn.
- **AI Visibility Flag:** `TALENTHUB_AI_VISIBLE_PERCENT=0` maintained.

---

## 2. Runtime Schema Audit

A full read-only audit of `talenthub_local` was conducted via `information_schema` and SHA-256 data snapshotting:

- **Total Base Tables:** 52 tables.
- **Phase 7 Opportunity & Application Tables:**
  - `internship_posts`: Non-existent (0 rows).
  - `internship_applications`: Non-existent (0 rows).
  - `application_status_history`: Non-existent (0 rows).
  - `application_profile_snapshots`: Non-existent (0 rows).
- **Associated Parent Entities:**
  - `enterprises`: 1 active record (`10000000-0000-4000-8000-000000000003`).
  - `enterprise_members`: 1 active record (`10000000-0000-4000-8000-000000000024`).
  - `student_profiles`: 20 active records.
  - `privacy_consents`: 0 rows (CHECK constraint `chk_privacy_consents_scope` already contains `application_profile_share`).
- **Data Preservation Fingerprint:**
  - Manifest SHA-256: `910fbfc4f134bfd79227141b2ee30d16c468fed97bdb087884003376785ebf00`.

---

## 3. Source Consumer & Ownership Audit

| Area / File | Current Role / Behavior | Phase 7 Canonical Target |
|---|---|---|
| `app/learner/opportunity.php` | Renders opportunity detail; mock submit modal. | Connect to `DatabaseApplicationCommandRepository` / `ApplicationCommandService` & `DatabaseEcosystemRepository`. |
| `app/learner/ecosystem.php` | Lists ecosystem opportunities using mock provider. | Query active database posts (`internship_post.read_available`). |
| `app/learner/data/Database/DatabaseApplicationRepository.php` | Read model querying `internship_applications` joined to `internship_posts`. | Already written; validated against target table schema and UUID normalization. |
| `app/enterprise/internships/` | Enterprise internship listing and applicant management; currently uses mock provider. | Wire to `src/Modules/Business/` services; resolve enterprise identity strictly via session &rarr; `enterprise_members`. |
| `src/Modules/Business/` | Contains `BusinessRepository` and `BusinessProfileService`. | Add `InternshipRepository` and `InternshipService` for posting and reviewing. |
| `Database/seeds/System/RolePermissionSeeder.php` | Contains all 12 Phase 7 permissions seeded for Student and Enterprise roles. | Reused without changes. |

---

## 4. Locked Contracts & Transaction Boundaries

1. **Canonical Statuses:**
   - Post: `draft`, `active`, `closed`, `cancelled`.
   - Application: `submitted`, `reviewing`, `interview`, `accepted`, `declined`, `withdrawn`.
   - Terminal application states: `accepted`, `declined`, `withdrawn`.
   - Pre-terminal withdrawal states: `submitted`, `reviewing`, `interview`.
2. **Student Create Application Transaction:**
   - Atomic verification of active post & deadline.
   - Idempotency / duplicate check on `(postId, studentId)`.
   - Verify an existing active `application_profile_share` consent. Submission never grants consent implicitly; missing/revoked consent rejects with zero application writes.
   - Allow-listed snapshot JSON generation.
   - Insert application (`submitted`), snapshot, and initial status history in a single transaction.
3. **Student Withdraw Transaction:**
   - Locks owned application row.
   - Validates pre-terminal status.
   - Updates status to `withdrawn` and appends history. Application row is never deleted.
4. **Enterprise Review Transaction:**
   - Resolves `enterpriseId` from session `userId` via `enterprise_members`.
   - Locks application joined with enterprise-owned post.
   - Enforces `expectedCurrentStatus` optimistic locking.
   - Updates status, reviewer note, reviewed timestamp, and appends history.

---

## 5. Verification Results

| Check / Test Suite | Type | Status | Details |
|---|---|:---:|---|
| `bin/migrate.php validate` | CLI | **PASS** | 23 applied migrations valid |
| `bin/migrate.php status` | CLI | **PASS** | 0 pending migrations |
| `git diff --check` | Git | **PASS** | Clean formatting, no whitespace errors |
| `learner_talent_passport_contract_test.php` | Unit | **PASS** | Passport contracts verified |
| `learner_talent_passport_data_test.php` | Unit | **PASS** | Data models verified |
| `learner_database_render_test.php` | Unit | **PASS** | Student portal database render verified |
| `learner_data_foundation_test.php` | Unit | **PASS** | Foundation contracts pass |
| `learner_phase_requirements_test.php` | Unit | **PASS** | Requirements verified |
| `student_portal_cross_role_contract_test.php` | Unit | **PASS** | Cross-role isolation pass |
| `phase5_source_namespace_contract_test.php` | Unit | **PASS** | Namespaces intact |
| `phase5_applied_schema_contract_test.php` | Unit | **PASS** | Phase 5 schema intact |
| `phase5_enterprise_denial_test.php` | Unit | **PASS** | Enterprise denial pass |
| `phase5_school_aggregate_test.php` | Unit | **PASS** | School aggregates pass |
| `student_passport_sharing_migration_test.php` | Unit | **PASS** | Passport sharing migration test pass |
| `student_certificates_projects_migration_test.php` | Unit | **PASS** | Certificates/projects test pass |
| `activity_registration_lifecycle_migration_test.php`| Unit | **PASS** | Activity registration test pass |
| `application_profile_snapshot_migration_test.php` | Unit | **expected RED** | Fails cleanly on missing migration `20260821000500` (implementation not yet assigned) |
| Live MySQL AI Suites (11 suites) | DB/AI | **NOT RUN** | Live database/AI configuration required; preserved for disposable gates |

---

## 6. Risks & Product Decisions

- **Identified Risks:**
  - Mock vs Database ID mismatches: Mock data uses integers (e.g. `postId = 1`, `applicantId = 101`), whereas production database uses UUIDv4 strings. The migration and repositories must use standard UUID normalization via `Uuid::normalizeDatabase()`.
  - Application Deletion: Ensure UI does not expose any "Delete Application" action; only "Withdraw" (`withdrawn`) is supported.
- **Blocked Product Decisions:** None. All technical contracts, status transitions, and permission rules are fully specified.

---

## 7. Codex Reviewer Addendum

Independent verification confirmed the runtime schema facts, all 13 reported
baseline suites, the expected RED migration contract, the 52-table / 23-migration
baseline, the `+00:00` session timezone, and the required runtime role-permission
assignments.

Before approval, the reviewer corrected four binding issues:

1. Application submission may only verify an existing active consent; it cannot
   silently grant one. Consent grant/renewal is a separate explicit learner action.
2. Phase 7 creates no notification interface, producer, adapter, or placeholder.
3. History and snapshot foreign keys use `ON DELETE RESTRICT` to block hard delete.
4. Rehearsal preservation excludes the expected new `schema_migrations` row: 51
   non-registry tables remain byte-stable, while the registry preserves its 23
   prior checksums and appends exactly one Phase 7 row.

The protected migration contract now verifies exact SHA-256 hashes instead of
file existence alone. With these corrections, the basic handoff is approved for
Codex CLI execution; Phase 7 itself is not yet approved.
