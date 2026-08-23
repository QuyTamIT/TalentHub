# Student Portal Four-Role Completion Revised Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Do not start a phase whose entry gate is not green.

**Goal:** Complete the real, database-backed Student Portal and its interactions with Teacher, School, and Enterprise without duplicating canonical data, breaking existing role behavior, or exposing model-generated AI output before its release gate.

**Architecture:** Preserve the shared identity/profile routes in `src/Bootstrap/Application.php` and the existing learner-domain endpoint boundary in `app/learner/api/v1/`. Extend canonical shared tables additively through the main migration framework, keep one state-machine owner per entity, and deliver one testable vertical slice per phase. Rule recommendations remain learner-visible; 9Router remains Shadow-only.

**Tech Stack:** PHP 8.3.30, PDO MySQL, Laragon MySQL 8.4.x/MariaDB-compatible SQL, PHP sessions, JSON APIs, HTML/CSS/JavaScript, PHP script tests, Node.js built-in test runner.

## Global Constraints

- Work only on `feature/student`; verify `HEAD` before every phase.
- Baseline at plan creation: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`.
- Never edit an applied migration or learner migrations `001` through `004`.
- Never alter, delete, or reseed `talenthub_local` without an approved DCR, verified backup, and disposable-schema rehearsal.
- `.env`, `.claude/`, and `.qwen/` remain outside commits.
- Never print secrets, raw QR tokens, assessment answers, private CV/profile payloads, or raw provider payloads.
- Use prepared statements for all runtime values.
- Every mutation resolves actor and organization ownership from the authenticated session, never from client-supplied role IDs.
- Every mutation requires CSRF and an exact existing permission unless this plan explicitly marks a permission `PROPOSED`.
- Every multi-table mutation owns one transaction boundary and rolls back completely.
- Do not use `localStorage` as production truth; it may hold only an unsent draft/cache with explicit server reconciliation.
- Do not report a phase complete from mock data, seed data, a rendered button, or unit tests alone.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`; Rule remains visible and the model remains Shadow-only through Phase 11.
- Do not push or merge automatically. Stop at the review gate after every task and phase.

---

## 1. Locked Architecture Baseline

These decisions remove ambiguity from the previous plan. Changing one requires a design amendment before implementation.

### 1.1 API boundary

| Concern | Canonical boundary | Decision |
|---|---|---|
| Auth, CSRF bootstrap, current user | `/api/v1/auth/*` in `src/Bootstrap/Application.php` | Reuse; do not create `app/learner/api/v1/session.php`. |
| Basic student profile and dashboard | `/api/v1/students/me*`, `StudentProfileService`, `StudentRepository` | Extend existing service/routes; do not create a second profile write endpoint. |
| Assessment and AI | `app/learner/api/v1/*.php`, `LearnerApiContext` | Preserve current URLs and response envelope. |
| New learner-owned domain actions | `app/learner/api/v1/*.php`, `LearnerApiContext` | Use for registration, check-in, application, notifications, badges, and statistics. |
| Teacher, School, Enterprise actions | Shared `/api/v1` router plus role services under `src/Modules/**` | Add explicit routes/services; do not let a learner endpoint mutate another role's state. |
| Browser client | `assets/js/learner-api.js` | Reuse both allowed bases; no new API client. |

All new direct learner endpoints must use `TalentHub\Http\Request`, `LearnerApiContext`, and `JsonResponder`. All new shared routes must use `TalentHub\Http\Request`, `JsonResponse`, `SessionManager`, and `PermissionService` through `Application` composition.

### 1.2 Existing permission vocabulary

Reuse these exact codes from `Database/seeds/System/RolePermissionSeeder.php`:

- Student: `student_profile.read_own`, `student_profile.update_own`, `student_profile.share_own`, `student_dashboard.read_own`, `privacy_consent.read_own`, `privacy_consent.manage_own`, `student_skill.read_own`, `student_skill.manage_own`, `talent_test.read_catalog`, `test_attempt.create_own`, `test_attempt.read_own`, `test_attempt.submit_own`, `assessment.read_own`, `activity.read_available`, `activity_registration.create_own`, `activity_registration.read_own`, `activity_registration.cancel_own`, `checkin.create_own`, `experience_log.read_own`, `badge.read_own`, `certificate.read_own`, `internship_post.read_available`, `internship_application.create_own`, `internship_application.read_own`, `internship_application.withdraw_own`, `notification.read_own`, `notification.mark_read_own`.
- Teacher: `activity.read_managed`, `activity.create_managed`, `activity.update_managed`, `activity_registration.read_managed`, `qr_session.create_managed`, `qr_session.read_managed`, `qr_session.revoke_managed`, `checkin.read_managed`, `assessment.read_managed`, `assessment.update_managed`.
- School: `school_analytics.read_own`, `student_profile.read_own_school`, `student_profile.verify_own_school`, `activity.read_own_school`, `activity_registration.read_own_school`, `report.read_own_school`.
- Enterprise: `internship_post.read_own_business`, `internship_post.create_own_business`, `internship_post.update_own_business`, `internship_post.publish_own_business`, `internship_post.close_own_business`, `internship_application.read_own_business`, `internship_application.review_own_business`, `internship_application.read_cv_own_business`, `talent.read_consented`.

Only these permissions are proposed because current vocabulary lacks equivalent mutations:

- `certificate.manage_own`: Student creates, updates, or deletes only pending self-declared certificates.
- `activity_registration.update_managed`: Teacher approves, rejects, or promotes registrations for managed activities.
- `notification.manage_preferences_own`: User changes their own notification preferences.

Task 2 must prove each proposed permission is actually needed before changing the seed. If an existing permission safely covers the action, remove the proposed permission and its tests.

### 1.3 Canonical status vocabulary

| Entity | Database canonical values after reconciliation | UI/read-model aliases only |
|---|---|---|
| `activities.status` | Existing: `draft`, `published`, `ongoing`, `completed`, `archived` | `active` → `published|ongoing`; `closed` → derived from deadline/completed; never write aliases. |
| `activity_registrations.status` | Existing: `pending`, `approved`, `rejected`, `cancelled`, `attended`; add `waitlisted` only after Phase 4 migration preflight | `registered` → `approved`; `checked_in`/`completed` derive from check-in/experience, not registration writes. |
| `activity_qr_sessions.status` | `active`, `expired`, `revoked` | None. Expiry may be derived and then persisted by owner service. |
| `checkins.status` | `pending`, `checked_in`, `confirmed`, `rejected` | None. |
| `experience_logs.status` | `pending`, `confirmed`, `rejected` | None. |
| `test_attempts.status` | `in_progress`, `submitted`, `expired` plus runtime values confirmed by Phase 0 | Do not expand until runtime inventory and assessment tests agree. |
| Teacher `assessments.status` | `draft`, `published` | Separate from automated `test_results`. |
| `internship_posts.status` | Values discovered in Phase 0; target `draft`, `active`, `closed`, `cancelled` only if every consumer is compatible | No silent normalization on writes. |
| `internship_applications.status` | `submitted`, `reviewing`, `interview`, `accepted`, `declined`, `withdrawn` after preflight | None. |

### 1.4 State-machine owners

- Teacher activity lifecycle: `TeacherActivityService`.
- Student registration lifecycle: new `ActivityRegistrationService`; Teacher approval calls the same repository transition primitive.
- QR session lifecycle: `TeacherQrSessionService`.
- Student check-in and experience creation: new `LearnerCheckinService`.
- Automated assessment: existing `LearnerAssessmentService`; never merge with Teacher `assessments`.
- Teacher evaluation: existing `TeacherGradingService`.
- Enterprise opportunity and application review: new Business services under `src/Modules/Business`.
- Student application create/withdraw: new learner `ApplicationCommandService` using the same canonical application rows.
- Notification state: new `NotificationService`; producers insert only after their domain transaction succeeds, within the same transaction where practical.
- Badge awards: new `BadgeAwardService`; no page controller writes badges.

### 1.5 Migration namespace reservation

Learner forward migrations `Database/migrations/learner/001`–`004` remain untouched. Student Portal core changes span roles and therefore use the main shared migration framework:

1. `Database/migrations/20260821000100_create_student_passport_sharing.php` (Phase 3: `student_profile_details`, `student_profile_shares`, `privacy_consents` scope expansion)
2. `Database/migrations/20260821000200_create_student_certificates_and_projects.php` (Phase 3: `certificates`, `projects`, `project_members`)
3. `Database/migrations/20260821000204_validate_phase_3_canonical_contracts.php` (Phase 3: validation-only precursor for every canonical column/default/precision, exact CHECK semantics, FK actions, and consent owner/scope)
4. `Database/migrations/20260821000205_preflight_phase_3_reconciliation.php` (Phase 3: validation-only structural precursor before reconciliation)
5. `Database/migrations/20260821000206_validate_phase_3_exact_metadata.php` (Phase 3: validation-only exact CHECK grouping and column `EXTRA`/`ON UPDATE` behavior)
6. `Database/migrations/20260821000210_reconcile_phase_3_contracts.php` (Phase 3: forward repair for project evidence and linked profile-share consent)
7. `Database/migrations/20260821000300_extend_activity_registration_lifecycle.php` (Phase 4 complete: cancellation metadata, `waitlisted`, registration policies, Teacher managed-transition permission)
8. `Database/migrations/20260821000400_create_activity_experience_policies.php` (Phase 5: `activity_experience_policies`)
9. `Database/migrations/20260821000500_create_internships_and_application_lifecycle.php` (Phase 7: `internship_posts`, `internship_applications`, `application_status_history`, `application_profile_snapshots`)
9a. `Database/migrations/20260821000510_reconcile_phase7_exact_metadata.php` (Phase 7 forward repair; preserves applied `00500` checksum)
9b. `Database/migrations/20260821000520_reconcile_phase7_exact_indexes.php` (Phase 7 forward index repair; preserves applied `00500` and `00510` checksums)
10. `Database/migrations/20260821000600_create_notifications_and_preferences.php` (Phase 8: `notifications`, `learner_notification_preferences`)
10a. `Database/migrations/20260821000610_validate_phase8_notification_contracts.php` (Phase 8 forward validation; preserves applied `00600` checksum and records exact columns/indexes/FKs/RBAC verification)
11. `Database/migrations/20260821000700_create_badges_and_award_rules.php` (Phase 9: `badges`, `student_badges`, `badge_rule_definitions`)

Before creating any file, Task 1 must fail if that version or semantic equivalent already exists at implementation time. If another branch has claimed an ID, reserve the next monotonically increasing ID and update this plan in the same review checkpoint.

---

## 2. Program Tracker

| Phase | Deliverable | Entry dependency | Status at plan creation | Completion evidence |
|---|---|---|---|---|
| 0 | Runtime/schema/consumer audit | None | Ready to execute | Signed audit, no DB mutation |
| 1 | Architecture contracts, RBAC delta, migration test harness | Phase 0 | Blocked by audit | Contract tests green |
| 2 | Real Dashboard/Talent Passport reads | Phase 1 | Partial | DB render/integration tests |
| 3 | Profile, certificate, evidence, consent, sharing | Phase 2 | Missing/partial | Security and expiry tests |
| 4 | Registration, cancellation, approval, waitlist | Phase 1; Phase 2 read path | Complete — APPROVED_PHASE_4 | Concurrent MySQL tests passed |
| 5 | Learner QR check-in and experience | Phase 4 | Complete — APPROVED_PHASE_5 | Replay/rollback and MySQL concurrency E2E passed |
| 6 | Assessment gaps and published evaluations | Phase 2 | Complete — APPROVED_PHASE_6 | History/evaluation tests passed |
| 7 | Opportunity and application lifecycle | Phase 3 | Complete — APPROVED_PHASE_7 | Student/Enterprise MySQL lifecycle, ownership, rollback and final independent review passed |
| 8 | Notification Center and preferences | Phases 4–7 producers | Complete — APPROVED_PHASE_8 | Owner/API/UI, producer rollback, MySQL concurrency, forward-validation, and disposable rehearsal passed |
| 9 | Badges, levels, personal statistics | Phases 5–6 confirmed facts | Complete — APPROVED_PHASE_9 | Exact migration, deterministic backfill/replay, owner APIs/UI, rollback, concurrency and disposable rehearsal passed |
| 10 | UI, accessibility, errors, security hardening | Stable APIs from 2–9 | Partial | UI/a11y/security matrix |
| 11 | Four-role release rehearsal | Phases 0–10 | Blocked | Full MySQL E2E and checklist |
| 12 | Shadow evaluation and visible-pilot decision | Phase 11 | Model-visible blocked | Separate approval gate |

Tracking rule: update only the phase row and its checklist after verification output is captured. Never mark a phase complete because its code was committed.

---

## 3. Database Ownership and Consumer Matrix

| Canonical entity | State owner / allowed writers | Authorized readers | Existing consumers to regression-test | Planned additive change |
|---|---|---|---|---|
| `users`, `roles`, `permissions`, `role_permissions` | `AuthService`; system RBAC seed | All authenticated modules by permission | `src/Auth/**`, `src/Rbac/**`, all app contexts | At most three proven permission codes; no schema change expected |
| `student_profiles` | `StudentProfileService`; School only through current scoped admin API | Student own; Teacher/School scoped | `StudentRepository`, `SchoolRepository`, `TeacherStudentRepository`, learner adapters | No speculative columns; details go to `student_profile_details` |
| `student_profile_details` | Student own via `StudentProfileService` | Consent/scoped readers | New | `studentId`, `location`, `bio`, `avatarUrl`, `headline`, timestamps |
| `student_profile_shares` | New sharing service only | Token resolver and owner | New | Hashed token, allowed fields JSON, expiry, revoke |
| `student_skills` | Student evidence commands; verifier fields remain protected | Student; consent/scoped viewers | AI sources, learner pages, Enterprise talent views | Runtime table exists; verified skill level remains read-only |
| `certificates`, `projects`, `project_members` | Student evidence commands; Teacher project mentoring | Student; Teacher/School scoped; Enterprise consented | AI sources, learner pages, Enterprise talent views | Migration `20260821000200` (Phase 3); do not query before migration |
| `activities` | `TeacherActivityService` | Student catalog; Teacher owner; School scope | Teacher repositories/pages, learner activity repository | Canonical statuses only (`draft`, `published`, `ongoing`, `completed`, `archived`) |
| `activity_registrations` | Student create/cancel; Teacher managed transition via shared repository | Student own; Teacher managed; School scoped | `TeacherActivityRepository`, `TeacherGradingRepository`, learner repository | Migration `20260821000300` (Phase 4): add cancellation metadata and `waitlisted` |
| `activity_qr_sessions` | `TeacherQrSessionService` | Teacher managed; Student validates opaque token | Teacher QR repository/service | No raw token; no duplicate QR table |
| `checkins`, `experience_logs` | `LearnerCheckinService`; later confirmation only by explicit policy owner | Student own; Teacher managed; School aggregate | AI activity source, demo verifier, QR tests | Migration `20260821000400` (Phase 5): add experience policy table |
| `talent_tests`, `test_questions`, `test_attempts`, `test_results`, `learner_assessment_*` | Existing assessment services | Student own; safe aggregates | Assessment APIs/UI/tests, AI assessment source | History/read endpoints only; core remains stable |
| Teacher `assessments`, `assessment_scores` | `TeacherGradingService` | Student only when `published`; School scoped if permitted | Teacher grading, learner evaluation source | No merge with automated results |
| `internship_posts`, `internship_applications`, `application_status_history`, `application_profile_snapshots` | Enterprise opportunity management; Student application commands | Student available/own; Enterprise own | Enterprise internship/applicant pages, learner ecosystem repository | Migration `20260821000500` (Phase 7); do not query before migration |
| `notifications`, `learner_notification_preferences` | Domain producers through `NotificationService` | Owner user; scoped producer diagnostics | Learner header; School send permission | Migration `20260821000600` (Phase 8); do not invent fake rows |
| `badges`, `student_badges`, `badge_rule_definitions` | `BadgeAwardService` only | Student own; authorized aggregates | Learner badges/statistics, AI skill/evidence consumers | Migration `20260821000700` (Phase 9); versioned rules and deterministic awards |
| `learner_recommendation_*` | Existing recommendation services | Student own and audit | AI APIs/tests/demo verifier | No visible-rollout schema changes in core phases |

---

## 4. Endpoint and Error Contract Matrix

| Actor | Endpoint | Method/action | Permission | Transaction owner |
|---|---|---|---|---|
| Student | `/api/v1/students/me` | `GET`, `PATCH` | `student_profile.read_own`, `student_profile.update_own` | Existing `StudentProfileService` |
| Student | `/app/learner/api/v1/profile-shares.php` | `POST create|revoke`, `GET own` | `student_profile.share_own` | `ProfileSharingService` |
| Student | `/app/learner/api/v1/certificates.php` | `POST create|update|delete`, `GET` | `certificate.read_own`, proposed `certificate.manage_own` | `CertificateCommandService` |
| Student | `/app/learner/api/v1/activity-registrations.php` | `POST register|cancel`, `GET` | existing `activity_registration.*_own` | `ActivityRegistrationService` |
| Teacher | `/api/v1/teachers/me/activities/{activityId}/registrations/{registrationId}` | `PATCH approve|reject` | proposed `activity_registration.update_managed` | `TeacherActivityService` using shared registration repository |
| Student | `/app/learner/api/v1/checkins.php` | `POST checkin`, `GET history` | `checkin.create_own`, `experience_log.read_own` | `LearnerCheckinService` |
| Teacher | `/api/v1/teachers/me/activities/{activityId}/checkins` | `GET` | `checkin.read_managed` | Read-only Teacher service |
| Student | Existing assessment endpoints | Existing methods plus history query | existing test/assessment permissions | Existing `LearnerAssessmentService` |
| Student | `/app/learner/api/v1/applications.php` | `POST grant-consent|submit`, `PATCH withdraw`, `GET own/detail` | existing `privacy_consent.manage_own`, `internship_application.*_own` | `ApplicationCommandService` |
| Enterprise | `/api/v1/businesses/me/internships` and `/{postId}` | `GET`, `POST`, `PATCH` | existing `internship_post.*_own_business` | `InternshipService` |
| Enterprise | `/api/v1/businesses/me/internship-applications/{applicationId}` | `GET`, `PATCH review` | existing `internship_application.*_own_business` | `InternshipService` |
| Student | `/app/learner/api/v1/notifications.php` | `GET`, `POST mark_read|mark_all`, `PATCH preferences` | common notification permissions; proposed preference permission | `NotificationService` |
| Student | `/app/learner/api/v1/badges.php`, `/statistics.php` | `GET` | `badge.read_own`, `student_dashboard.read_own` | Read-only services; awards occur from domain events |
| School | Existing `/api/v1/schools/me/analytics` | `GET` extended aggregates | `school_analytics.read_own` | Existing `SchoolDashboardService` read boundary |

All endpoints use the existing success/error envelope and these semantics:

- `401 AUTHENTICATION_REQUIRED`: no valid session.
- `403 PERMISSION_DENIED`: wrong role, permission, organization, or ownership; `403 CSRF_INVALID` for mutation token failure.
- `404 RESOURCE_NOT_FOUND`: resource absent or deliberately non-enumerating when the actor must not learn it exists.
- `409 STATE_CONFLICT`: stale expected state, duplicate idempotency key with incompatible payload, capacity race, duplicate registration/application/check-in, or terminal-state mutation.
- `422 VALIDATION_FAILED`: malformed UUID/body, forbidden field, invalid transition request, closed window, missing required consent, or incomplete assessment.
- `429 RATE_LIMITED`: check-in, application, login, or AI limit exceeded with safe retry metadata.
- `503 SERVICE_UNAVAILABLE`: database/provider dependency unavailable; never fall back to mock data.

Every mutation body has an allow-list, ignores/rejects client-supplied actor IDs, accepts an idempotency key where duplicate submission is plausible, and returns a server-confirmed canonical state.

---

## 5. Phase 0 — Evidence and Runtime Reconciliation

### Task 1: Freeze the actual baseline

**Files:**
- Verify: all repository instructions, migrations, role services, learner APIs, and tests.
- Create later only after user review: `docs/superpowers/readiness/2026-08-21-student-portal-runtime-audit.md`
- Test: `tests/learner_phase_requirements_test.php`, `tests/learner_readiness_test.php`

**Produces:** An immutable audit identifying runtime tables, columns, indexes, checks, migrations, row counts, status values, and every cross-role reader/writer.

- [ ] **Step 1: Verify branch, HEAD, scope, and migration filenames**

```powershell
git branch --show-current
git rev-parse HEAD
git status --short --branch
Get-ChildItem Database\migrations,Database\migrations\learner -File -Recurse | Sort-Object FullName | Select-Object -ExpandProperty FullName
```

Expected: branch `feature/student`; no protected-role modifications caused by this phase; versions `20260821000100`–`20260821000700` are unclaimed or the plan is amended before implementation.

- [ ] **Step 2: Run read-only connection and migration validation**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php bin\connect-check.php --json --quick
& $php bin\migrate.php status
& $php bin\migrate.php validate
```

Expected: commands are read-only. If connection fails, record `BLOCKED_DATABASE` and do not create migrations.

- [ ] **Step 3: Capture schema inventory using `information_schema` through an approved read-only script**

Required inventory fields per table: column name/type/null/default, primary/unique/index definitions, foreign keys, check constraints, table collation, and row count. Required status counts: `activities`, `activity_registrations`, `activity_qr_sessions`, `checkins`, `experience_logs`, `test_attempts`, Teacher `assessments`, `internship_posts`, and `internship_applications`.

Expected: no SQL verb other than `SELECT`, `SHOW`, or `EXPLAIN`.

- [ ] **Step 4: Build the reader/writer evidence map**

For every shared table, list exact repository/service files and whether each performs `SELECT`, `INSERT`, `UPDATE`, or `DELETE`. Explicitly include Teacher, School, Enterprise, AI sources, demo seed/verifier, and Student code.

- [ ] **Step 5: Reconcile four schema sources**

Compare runtime, `Database/migrations/**`, `Database/Talenthub.sql`, and `PhaseRequirements.php`. Runtime plus applied migrations is authoritative. Treat `Talenthub.sql` as legacy/reference when it disagrees.

- [ ] **Step 6: Run existing non-mutating baseline tests**

```powershell
& $php tests\learner_readiness_test.php
& $php tests\learner_phase_requirements_test.php
& $php tests\permission_service_driver_compatibility_test.php
& $php tests\qr_session_migration_contract_test.php
& $php tests\learner_assessment_api_test.php
& $php tests\learner_recommendation_api_test.php
```

Expected: all pass or each failure is recorded as a baseline blocker, not silently fixed.

- [ ] **Step 7: Review gate**

Do not proceed until the audit answers: exact next migration ID, exact canonical statuses, whether every planned table exists, and whether the database can be safely cloned for rehearsal.

**Phase 0 exit:** audit reviewed; runtime drift classified; no database mutation; no unresolved canonical-table ambiguity.

---

## 6. Phase 1 — Contract and Migration Safety Foundation

### Task 2: Lock API, permission, and state contracts in tests

**Files:**
- Modify: `tests/learner_phase_requirements_test.php`
- Modify: `tests/learner_shared_readiness_test.php`
- Modify: `tests/permission_service_driver_compatibility_test.php`
- Modify only if proven: `Database/seeds/System/RolePermissionSeeder.php`
- Create: `tests/student_portal_cross_role_contract_test.php`

**Interfaces:**
- Consumes: current `Application`, `LearnerApiContext`, `RolePermissionSeeder`, and status enums.
- Produces: a test-enforced boundary for API bases, exact permissions, migration IDs, and canonical status mappings.

- [ ] **Step 1: Write failing contract assertions**

```php
contract_assert(in_array('activity_registration.create_own', $studentPermissions, true), 'reuse registration permission');
contract_assert(!in_array('student_activity.register_own', $allPermissions, true), 'reject duplicate permission vocabulary');
contract_assert($apiBases === ['/api/v1', '/app/learner/api/v1'], 'only two approved API bases');
contract_assert($registrationAliases['registered'] === 'approved', 'UI registered maps to DB approved');
```

Also assert that learner migration versions `001`–`004` remain unchanged and new shared migration IDs are unique.

- [ ] **Step 2: Run focused tests and verify RED only for missing locked contracts**

```powershell
& $php tests\student_portal_cross_role_contract_test.php
```

- [ ] **Step 3: Add only proven RBAC deltas**

If Task 1 confirms no existing equivalent, add `certificate.manage_own`, `activity_registration.update_managed`, and `notification.manage_preferences_own` to the appropriate roles. Do not rename existing permissions.

- [ ] **Step 4: Add canonical mapper contracts rather than widening DB writes**

Update learner status normalization so database values remain canonical and UI aliases are output-only. The mapper must never persist `registered`, `active`, `closed`, `checked_in`, or `completed` into columns whose constraints reject them.

- [ ] **Step 5: Run permission, status, API-client, and shared readiness regressions**

```powershell
& $php tests\permission_service_driver_compatibility_test.php
& $php tests\learner_data_foundation_test.php
& $php tests\student_portal_cross_role_contract_test.php
& 'C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' --test tests\learner_api_client_test.js
```

- [ ] **Step 6: Atomic commit checkpoint**

Suggested commit: `test(portal): lock four-role API and state contracts`.

**Phase 1 exit:** no duplicate API/session/profile endpoints are planned; permission vocabulary is exact; aliases cannot leak into canonical writes; migration IDs are reserved.

---

## 7. Phase 2 — Dashboard and Talent Passport Reads

### Task 3: Build the real Talent Passport aggregate

**Files:**
- Create: `app/learner/data/Contracts/TalentPassportRepository.php`
- Create: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Create: `app/learner/data/ReadModel/TalentPassportReadModel.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/index.php`
- Modify: `app/learner/profile.php`
- Test: `tests/learner_talent_passport_data_test.php`
- Test: `tests/learner_talent_passport_render_test.php`

**Interface:**

```php
interface TalentPassportRepository
{
    public function aggregateForStudent(string $studentId): array;
}
```

The result contains only canonical profile, verified/self-declared skills with labels, certificates, projects, confirmed experience, submitted automated results, published Teacher evaluations, awarded badges, and source timestamps.

- [x] Write failing tests for student isolation, empty state, published-only Teacher evaluation, confirmed-only hours, and absence of hard-coded KPIs.
- [x] Run focused tests and verify RED.
- [x] Implement prepared aggregate queries scoped by `studentId`; do not query school-wide totals.
- [x] Map missing facts to explicit empty/insufficient states; never invent verified values.
- [x] Replace static Dashboard/Profile aggregates while preserving mock fixtures only for explicit test/demo source mode.
- [x] Run render, database, AI-source, and Teacher evaluation regressions.
- [ ] Commit: `feat(learner): read talent passport from canonical data`.

**Phase 2 exit:** refresh and device change show the same DB-backed passport; no learner can read another learner's aggregate; Teacher published facts remain distinct from automated results.

**Review evidence (2026-08-22):** `APPROVED_PHASE_2`. See `docs/superpowers/readiness/2026-08-22-phase-2-talent-passport-review-report.md`. The commit checkbox remains open because this execution was explicitly no-commit.

---

## 8. Phase 3 — Profile Ownership, Evidence, Consent, and Sharing

### Task 4: Apply passport-sharing migration safely

**Files:**
- Create: `Database/migrations/20260821000100_create_student_passport_sharing.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`
- Test: `tests/student_passport_sharing_migration_test.php`

**Required schema after Phase 0 confirmation:**

```sql
student_profile_details(studentId PK/FK, location, bio, avatarUrl, headline, createdAt, updatedAt)
student_profile_shares(id PK, studentId FK, tokenHash UNIQUE, sharedFieldsJson, expiresAt, revokedAt, createdAt)
```

Expand the `privacy_consents.scope` constraint additively to preserve the four AI scopes and add exactly `profile_share` and `application_profile_share`. Certificate/evidence columns are added only when Phase 0 proves they are absent. Preflight rejects duplicate token hashes, invalid JSON, orphan students, unsupported existing consent scopes, or a semantic-equivalent table with incompatible columns.

- [x] Write migration contract tests before the migration exists.
- [x] Run RED.
- [x] Implement additive `up()` and forward-recovery documentation; do not implement destructive `down()` for rows containing shares.
- [x] Rehearse on disposable schema, apply twice, and compare row hashes outside test-owned fixtures.
- [x] Stop for DCR review before primary database mutation.

### Task 5: Implement profile/evidence/sharing commands

**Files:**
- Create: `Database/migrations/20260821000200_create_student_certificates_and_projects.php`
- Modify: `src/Modules/Student/Repository/StudentRepository.php`
- Modify: `src/Modules/Student/Service/StudentProfileService.php`
- Modify: `src/Bootstrap/Application.php` for the existing `PATCH /api/v1/students/me` contract only
- Create: `app/learner/data/Database/DatabaseCertificateCommandRepository.php`
- Create: `app/learner/data/Service/CertificateCommandService.php`
- Create: `app/learner/data/Service/ProfileSharingService.php`
- Create: `app/learner/api/v1/certificates.php`
- Create: `app/learner/api/v1/profile-shares.php`
- Create: `app/learner/shared-profile.php`
- Modify: `app/learner/profile.php`
- Modify: `assets/js/learner.js`
- Test: `tests/learner_profile_privacy_api_test.php`

**Profile ownership:** Student may update `fullName`, `dateOfBirth`, `phone`, `location`, `bio`, `avatarUrl`, and `headline`. Student may not update email, role, account status, school/class, verified skill level, confirmed hours, assessment scores, Teacher evaluations, or badges.

**Share contract:**

```text
POST /app/learner/api/v1/profile-shares.php action=create
permission: student_profile.share_own
body: { fields: string[], expiresAt: ISO-8601 }
response 201: { shareUrl, expiresAt, fields }

POST /app/learner/api/v1/profile-shares.php action=revoke
body: { shareId: UUID }
response 200: { shareId, revoked: true }
```

The service generates 32 random bytes, returns the raw token once, persists only SHA-256, and scopes revoke by current `studentId`.

- [x] Write failing tests for allowed fields, forbidden verified fields, certificate ownership, immutable verified certificate, share expiry/revoke, token hashing, and cross-student denial.
- [x] Implement profile update in the existing shared route; do not create `profile/update.php`.
- [x] Implement pending certificate create/update/delete with `certificate.manage_own`; verified/rejected rows remain read-only.
- [x] Reuse `privacy_consents` and `privacy_consent.manage_own` for profile/application scopes only after migration safely expands its CHECK constraint; do not reuse AI event rows as general consent.
- [x] Implement shared-profile field allow-list and `Cache-Control: no-store`; default excludes email and phone.
- [x] Replace DOM-only success with server-confirmed UI.
- [x] Run auth, profile, privacy, Enterprise consent-read, and XSS render tests.
- [ ] Commit by two boundaries: schema, then commands/UI.

**Phase 3 exit:** every displayed field has an owner; share expires and revokes; Enterprise cannot bypass consent; no raw share token is stored.

**Review evidence (2026-08-22):** `APPROVED_PHASE_3`. Independent final review found no Critical, Important, or actionable Minor issues after exact CHECK-grouping and `ON UPDATE` remediation. See `docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md`. The commit checkbox remains open because this execution was explicitly no-commit.

---

## 9. Phase 4 — Activity Registration, Approval, and Waitlist

### Task 6: Extend registration lifecycle without breaking Teacher

**Files:**
- Create: `Database/migrations/20260821000300_extend_activity_registration_lifecycle.php`
- Create: `tests/activity_registration_lifecycle_migration_test.php`
- Modify: `app/learner/data/Enums/Statuses.php`
- Modify: `src/Modules/Teacher/Repository/TeacherActivityRepository.php`
- Modify: `src/Modules/Teacher/Service/TeacherActivityService.php`
- Modify: `src/Bootstrap/Application.php`

**Additive target:** cancellation timestamps/reason; optional policy/detail table with `registrationOpensAt`, `registrationClosesAt`, `cancellationClosesAt`, and `approvalMode`; registration CHECK adds `waitlisted`. Existing values remain valid.

Default policy when no detail row exists: opens when activity is `published`; closes at `startAt`; cancellation closes at `startAt`; `approvalMode=automatic`.

- [x] Preflight actual CHECK definition and status counts.
- [x] Write migration and Teacher compatibility tests.
- [x] Rehearse apply twice and prove existing registrations unchanged.
- [x] Add Teacher transition permission only if Phase 1 retained `activity_registration.update_managed`.
- [x] Add Teacher managed transition route with optimistic expected status; invalid transition returns `409`.

### Task 7: Implement Student registration transaction

**Files:**
- Create: `app/learner/data/Contracts/ActivityCommandRepository.php`
- Create: `app/learner/data/Database/DatabaseActivityCommandRepository.php`
- Create: `app/learner/data/Service/ActivityRegistrationService.php`
- Create: `app/learner/api/v1/activity-registrations.php`
- Modify: `assets/js/learner-activities.js`
- Modify: `app/learner/activity-detail.php`
- Modify: `app/learner/my-activities.php`
- Test: `tests/learner_activity_registration_api_test.php`
- Test: `tests/learner_activity_registration_mysql_test.php`

**Transaction contract:**

1. Resolve current student from session.
2. `SELECT activities ... FOR UPDATE`.
3. Validate canonical status and windows.
4. Lock current student's active registrations needed for conflict detection.
5. Check unique `(activityId,studentId)` and schedule conflict.
6. Count `approved|attended` registrations under the activity lock.
7. Insert `pending` for teacher approval, `approved` for automatic capacity, or `waitlisted` when full.
8. Insert the audit row only after all validations, in the same transaction. Notification production remains Phase 8.
9. Commit; duplicate key or stale transition returns `409`.

Cancellation locks the owned registration and activity. If an approved seat is released before close, promote the earliest `waitlisted` row by `registeredAt,id` in the same transaction and notify both learners.

- [x] Write failing capacity, FIFO promotion, approval, conflict, deadline, duplicate, cross-student, and rollback tests.
- [x] Implement repository transaction primitives.
- [x] Implement `POST action=register` and `POST action=cancel` using exact existing Student permissions.
- [x] Replace localStorage truth with server results; retain only explicit mock-mode local state.
- [x] Run two-connection concurrency tests proving capacity and schedule invariants are not exceeded.
- [x] Run Teacher pages and cross-role regression because both consume registrations.
- [ ] Commit service/API separately from UI conversion.

**Phase 4 exit:** registration survives refresh/device change; capacity cannot overbook; waitlist promotion is deterministic; Teacher and School readers remain compatible.

---

## 10. Phase 5 — Learner QR Check-in and Confirmed Experience

### Task 8: Add experience policy and check-in transaction

**Files:**
- Create: `Database/migrations/20260821000400_create_activity_experience_policies.php`
- Create: `app/learner/data/Database/DatabaseCheckinRepository.php`
- Create: `app/learner/data/Service/LearnerCheckinService.php`
- Create: `app/learner/api/v1/checkins.php`
- Modify: `src/Modules/Teacher/Repository/TeacherQrSessionRepository.php` only if audit proves a contract gap
- Modify: `src/Modules/Teacher/Service/TeacherQrSessionService.php` only if audit proves a contract gap
- Test: `tests/learner_checkin_api_test.php`
- Test: `tests/learner_checkin_mysql_test.php`

**Transaction contract:** hash submitted token; lock matching `activity_qr_sessions`; lock owned `activity_registrations`; reject expired/revoked/exhausted/wrong-activity sessions; insert one `checkins` row; increment `usedScans`; set registration `attended`; insert one `experience_logs` row using configured hours; insert audit/notification; commit.

Unique `uq_checkins_registration` and `uq_experience_logs_checkin` remain the final replay barriers. Raw token never enters logs or error details.

- [x] Write failing expired, revoked, wrong activity, full session, duplicate scan, cross-student, and injected mid-transaction failure tests.
- [x] Reconcile `qrSessionId` versus legacy `qrTokenId` using runtime audit; never add a second link column if canonical reconciliation already completed.
- [x] Implement `POST /app/learner/api/v1/checkins.php` and `GET` history with `checkin.create_own` and `experience_log.read_own`.
- [x] Add camera flow using `getUserMedia`/supported decoder with manual token fallback and explicit permission-denied state.
- [x] Add Teacher managed check-in read and School aggregate verification tests without broadening Student data access.
- [ ] Commit schema/service, then browser flow. — `Intentionally skipped because this execution is explicitly no-commit.`

**Phase 5 exit:** one registration produces at most one check-in and experience; failures leave no partial rows; Teacher sees the event; Student sees confirmed/pending hours; School sees only scoped aggregates.

**Phase 5 execution (2026-08-22): APPROVED_PHASE_5.** Automatic confirmation, Teacher policy ownership, learner camera/manual flow, School scoped aggregate, Enterprise denial, rollback injection, four MySQL races, disposable rehearsal, fresh backup, and primary migration `20260821000400` all passed. Primary is at 52 tables / 23 applied / 0 pending. Reviewer found no Critical/Important blocker; see `docs/superpowers/readiness/2026-08-22-phase-5-learner-checkin-review-report.md` and `docs/superpowers/readiness/2026-08-22-phase-5-rehearsal-report.md`. Pre-migration backup `talenthub_local_pre_phase5_20260822_173500.sql` verified with SHA-256 `561E6DE4107CC76313A302D711D038AE6F7338C41E0AD9BC269D5CDC088B2020`. The commit checkbox remains open because this execution is explicitly no-commit. One recovery-preflight hardening item does not affect the reviewed runtime and would require a separately approved successor migration.

---

## 11. Phase 6 — Assessment Gaps, Not Core Reimplementation

### Task 9: Complete history and published-evaluation integration

**Files:**
- Modify: `app/learner/data/Contracts/AssessmentRepository.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentRepository.php`
- Modify: `app/learner/api/v1/assessments.php`
- Modify: `app/learner/assessment-result.php`
- Modify: `app/learner/evaluation.php`
- Test: `tests/learner_assessment_history_test.php`
- Test: `tests/learner_published_evaluation_access_test.php`

Do not rebuild catalog, start/resume, answer persistence, scoring, submit, versioning, or retake rules. Those are regression dependencies.

- [x] Write failing tests for complete own history, version references, no cross-student results, and published-only Teacher evaluations.
- [x] Add read-only history/detail modes to existing endpoints without changing submit contracts.
- [x] Keep automated results and Teacher evaluations as separate response sections with distinct source labels.
- [ ] ~~Emit assessment-submitted notification through Phase 8 producer adapter only after submit transaction commits.~~ **DEFERRED_TO_PHASE_8 (approved 2026-08-22).** See Amendment A1. Phase 6 must not create `notifications`, `learner_notification_preferences`, a `NotificationService`, a notification API, or alter the assessment submit transaction to prepare for one.
- [x] Treat appeal/review as excluded from v1 unless the Word specification or Product Owner explicitly requires it; if required, write a separate design before implementation. **LOCKED_EXCLUDED (approved 2026-08-22).** See Amendment A2.
- [x] Run all assessment catalog, scorer, API, persistence, immutability, AI source, and Teacher grading tests in the non-mutating Phase 6 gate. **Selected Phase 2–6 regression 33/33, scorer integration 644 assertions, JavaScript 76/76, and PHP lint 474/474 passed. Separately gated MySQL/AI suites were not represented as passing. See review report.**
- [ ] Commit: `feat(learner): add assessment history and published evaluations`.

**Phase 6 exit:** assessment core remains unchanged and green; history is complete and isolated; Teacher drafts never leak. **Review report: `docs/superpowers/readiness/2026-08-22-phase-6-assessment-review-report.md`**

**Phase 6 execution (2026-08-22): APPROVED_PHASE_6.** Complete own history, published-only Teacher evaluations, database-mode UI states, strict catalog/history query validation, and cross-Student/draft isolation passed. Final review additionally verified that explicit `view=catalog` remains valid, history sections are independent from the primary-result visibility state, decimal scores are preserved, and `maxScore=0` is safe in PHP and JavaScript. Selected Phase 2–6 regression 33/33, scorer integration 644 assertions, all 9 JavaScript suites, and PHP lint 474/474 passed; migration validation is clean at 23 applied / 0 pending. Independent re-review found no Critical/Important blocker. No database mutation, migration, notification, appeal/review work, commit, push, or merge occurred. Phase 7 is eligible but has not started.

**Phase 7 execution (2026-08-22): APPROVED_PHASE_7.** Canonical internship posts/applications/history/snapshot schema and forward-only exact-metadata/index repairs are applied after verified backups and self-orchestrating exact-prefix disposable apply-twice rehearsal. Explicit learner consent, atomic submit/withdraw, immutable minimized snapshot, canonical Learner history/post reads, Enterprise-owned post/review transitions, snapshot-only applicant UI, cross-scope isolation, runtime auth/RBAC/CSRF including missing exact permission, concurrent duplicate submission and rollback all passed. Runtime is 56 base tables / 26 applied migrations / 0 pending; all 51 pre-existing non-registry table count/checksum pairs were preserved in the final rehearsal, and cleanup left 0 rehearsal schemas/grants. Independent final review found no Critical/Important blocker. No Phase 8 notification artifact, demo row, commit, push or merge was created. Phase 8 is eligible but has not started.

---

## 12. Phase 7 — Enterprise Opportunity and Application Lifecycle

### Task 10: Add immutable application snapshot schema

**Files:**
- Create: `Database/migrations/20260821000500_create_internships_and_application_lifecycle.php`
- Test: `tests/application_profile_snapshot_migration_test.php`

Create `application_profile_snapshots` keyed by `applicationId`, referencing the consent event/record and storing a minimized JSON snapshot plus schema version and timestamp. Do not duplicate `application_status_history` if runtime already has it.

- [x] Preflight `internship_posts`, `internship_applications`, status history, unique post/student, current statuses, and orphan rows.
- [x] Write migration tests for immutable one-to-one snapshot and valid JSON.
- [x] Rehearse apply twice and preserve all existing application hashes.
- [x] Run the exact-prefix executable integrity gate and forward-only metadata repair without editing applied migration history.
- [x] Stop for DCR review before primary mutation.

### Task 11: Implement Student and Enterprise application commands

**Files:**
- Create: `app/learner/data/Database/DatabaseApplicationCommandRepository.php`
- Create: `app/learner/data/Service/ApplicationCommandService.php`
- Create: `app/learner/api/v1/applications.php`
- Create: `src/Modules/Business/Repository/InternshipRepository.php`
- Create: `src/Modules/Business/Service/InternshipService.php`
- Modify: `src/Bootstrap/Application.php`
- Modify: `app/learner/opportunity.php`
- Modify: `app/learner/ecosystem.php`
- Modify only corresponding Enterprise internship/applicant pages and JS
- Test: `tests/learner_application_api_test.php`
- Test: `tests/Integration/EnterpriseApplicationLifecycleTest.php`

**Student create transaction:** lock active post; validate deadline/eligibility; verify active application-profile consent; reject duplicate `(postId,studentId)`; build minimized passport snapshot; insert application `submitted`; insert snapshot; insert initial history; commit. Notification is deferred to Phase 8.

**Student withdraw transaction:** lock owned application; allow only configured pre-terminal statuses; transition to `withdrawn`; append history; commit. Never delete the application. Notification is deferred to Phase 8.

**Enterprise review transaction:** resolve `enterprise_members` from session; lock application joined to a post owned by that enterprise; validate expected status and transition; update application; append history; commit. Notification is deferred to Phase 8.

- [x] Write failing expired post, duplicate, missing/revoked consent, snapshot immutability, cross-enterprise, illegal transition, withdrawal, and rollback tests.
- [x] Implement Student endpoints with exact `internship_application.*_own` permissions.
- [x] Implement Enterprise shared routes with exact existing Business permissions.
- [x] Authorize CV/snapshot reads through application/post ownership; never accept an arbitrary file URL from the browser.
- [x] Replace learner and Enterprise mock-only state with server-confirmed responses one screen at a time.
- [x] Run all Enterprise render/profile/applicant regressions plus Student ecosystem tests.
- [x] Prove runtime auth/RBAC/CSRF, concurrent duplicate submit, and endpoint rollback on disposable MySQL.
- [ ] Commit Student command, Enterprise command, then UI integration as separate review units.

**Phase 7 exit:** Student and Enterprise observe one canonical application state/history; snapshot does not change after profile edits; no Enterprise reads another Enterprise's applications.

---

## 13. Phase 8 — Notifications and Preferences

### Task 12: Implement one notification writer and owner-scoped inbox

**Files:**
- Create: `Database/migrations/20260821000600_create_notifications_and_preferences.php`
- Create: `Database/migrations/20260821000610_validate_phase8_notification_contracts.php` as an additive validation marker because `00600` was already applied before final review
- Create: `app/learner/data/Database/DatabaseNotificationRepository.php`
- Create: `app/learner/data/Service/NotificationService.php`
- Create: `app/learner/api/v1/notifications.php`
- Create: `app/learner/notifications.php`
- Modify: `app/learner/includes/header.php`
- Modify: `app/learner/includes/sidebar.php`
- Test: `tests/learner_notifications_api_test.php`
- Test: `tests/notification_domain_producer_test.php`

Use existing `notification.read_own` and `notification.mark_read_own`; use proposed `notification.manage_preferences_own` only for preferences. Resolve `notifications.userId` from current student's profile or the domain event recipient; never accept it from request JSON.

Migration target after runtime reconciliation: create canonical `notifications` only if it is genuinely absent from the applied schema; otherwise add only missing compatible columns/indexes. Add `learner_notification_preferences(studentId,notificationType,inAppEnabled,emailEnabled,updatedAt)` and an idempotency barrier `notifications.eventKey` with unique `(userId,eventKey)`. Add an allow-listed `deepLink` column only if no equivalent related-entity route exists. Existing notification rows use `eventKey=NULL` and remain unchanged.

Supported v1 producer events: registration created/cancelled/promoted/approved/rejected, check-in committed, assessment submitted, application submitted/withdrawn/status-changed, badge awarded.

- [x] Write owner isolation, pagination, unread count, mark-one/read-all, preference, exact deep-link allow-list, duplicate-event, concurrency, and producer rollback tests.
- [x] Apply `00600` only after its DCR/rehearsal, then apply forward validation `00610` only after a second pinned-dump rehearsal and fresh primary backup.
- [x] Implement service methods `publish()`, `listForUser()`, `markRead()`, `markAllRead()`, and `updatePreference()` with fail-closed schema/recipient handling.
- [x] Integrate producers inside their domain transaction using the same PDO connection; injected failure and MySQL concurrency tests prove rollback/idempotency.
- [x] Render real global header count and inbox loading, pagination, unread, empty, error, keyboard-dialog, and preference-rollback states.
- [x] Do not implement an email worker; store preference only and disclose the v1 boundary in the UI.
- [x] Preserve the requested no-commit/no-push workflow; no commit was created during Phase 8.

**Phase 8 exit:** notification ownership is enforced, counts survive refresh, and no fake upstream notification exists.

---

## 14. Phase 9 — Badge Rules, Levels, and Personal Statistics

### Task 13: Implement versioned badge rules and deterministic awards

**Files:**
- Create: `Database/migrations/20260821000700_create_badges_and_award_rules.php`
- Create: `app/learner/data/Service/BadgeRuleEngine.php`
- Create: `app/learner/data/Service/BadgeAwardService.php`
- Create: `app/learner/data/Database/DatabaseStatisticsRepository.php`
- Create: `app/learner/api/v1/badges.php`
- Create: `app/learner/api/v1/statistics.php`
- Modify: `app/learner/badges.php`
- Modify: `app/learner/statistics.php`
- Modify: `app/learner/index.php`
- Test: `tests/learner_badge_rules_test.php`
- Test: `tests/learner_statistics_data_test.php`

V1 rule inputs are allow-listed confirmed facts only: confirmed experience hours, attended activity count, submitted assessment-type count, and published Teacher evaluation count. The engine cannot execute SQL or arbitrary expressions from rule JSON.

- [x] Write failing valid/invalid rule schema, duplicate award, cross-student, confirmed-only, boundary, and aggregate-date tests.
- [x] Apply rule-definition migration after rehearsal; preserve all pre-existing tables and rows.
- [x] Award with unique `(studentId,badgeId)` inside a transaction and publish notification once.
- [x] Calculate levels from a versioned configuration; do not infer from UI clicks.
- [x] Provide weekly/monthly personal statistics using current `studentId`; no default school ranking.
- [x] Run Dashboard, AI sources, School analytics, and notification regressions.
- [x] Preserve the requested no-commit handoff; Phase 9 remains reviewable as one worktree delta.

**Phase 9 exit:** reruns cannot duplicate awards; only confirmed facts contribute; all statistics are owner-scoped.

---

## 15. Phase 10 — Frontend, Accessibility, Error, and Security Hardening

### Task 14: Remove remaining fake-success paths

**Files:**
- Modify: `assets/js/learner.js`
- Modify: `assets/js/learner-activities.js`
- Modify: `assets/js/learner-assessment.js` only for history changes
- Modify: `assets/js/learner-recommendations.js` only for regressions
- Modify: `assets/css/learner.css`
- Modify: affected learner pages
- Test: `tests/learner_api_client_test.js`
- Create: `tests/learner_accessibility_render_test.php`
- Create: `tests/learner_security_contract_test.php`

- [ ] Inventory every button/form that mutates visible state and map it to a server endpoint.
- [ ] Write failing tests proving no success message or state transition occurs before a successful response.
- [ ] Add abort, timeout, double-submit, retry, and stale-response handling through existing `learner-api.js`.
- [ ] Add focus return, visible labels, `aria-live`, keyboard-only dialogs, reduced motion, and 360/768/1024/desktop checks.
- [ ] Replace unsafe dynamic `innerHTML` for untrusted fields with DOM text APIs or server escaping.
- [ ] Add/check rate limits for login, check-in, application, and AI with safe `429` responses.
- [ ] Run all learner Node tests and PHP render/security tests.
- [ ] Commit per screen/domain, not as one broad UI commit.

**Phase 10 exit:** every mutation is server-confirmed; keyboard flow works; no untrusted data is inserted unsafely; errors are recoverable.

---

## 16. Phase 11 — Four-Role Release Rehearsal

### Task 15: Prove production readiness on a disposable clone

**Files:**
- Create: `tests/student_portal_four_role_e2e_mysql_test.php`
- Create: `docs/superpowers/readiness/student-portal-release-checklist.md`
- Create: `docs/superpowers/readiness/student-portal-authorization-matrix.md`
- Modify: deployment documentation only after tests pass

- [ ] Take and verify a backup before any approved staging/primary migration.
- [ ] Restore to a disposable schema with an allow-listed name; refuse `talenthub_local` in destructive test cleanup.
- [ ] Apply all main migrations twice; require second run no-op and `drift=false`.
- [ ] Run two Students, two Teachers, two Schools, and two Enterprises through positive and cross-scope negative cases.
- [ ] Run E2E: profile/share → activity/approval/waitlist → QR/experience → assessment/evaluation → application/review → notification → badge/statistics.
- [ ] Verify database invariants, row counts, hashes of pre-existing rows, no orphan rows, and no cross-role reassignment.
- [ ] Run full PHP lint, PHP tests, Node tests, `git diff --check`, secret scan, and forbidden-scope review.
- [ ] Record exact commands, exit codes, database/version, migration counts, test counts, known gaps, and recovery procedure.
- [ ] Stop for human release review. Do not push or merge.

**Phase 11 exit:** all Student Portal DoD items have executable evidence; Teacher/School/Enterprise regressions pass; primary data mutation remains separately approved.

---

## 17. Phase 12 — AI Shadow Evaluation and Pilot Decision

### Task 16: Evaluate, do not auto-enable

**Files:**
- Modify only after core release: `docs/superpowers/readiness/learner-ai-evaluation-gate.md`
- Modify only after approval: `docs/superpowers/readiness/learner-ai-release-checklist.md`
- Create: a separate dated AI evaluation plan and DCR if database writes are required

- [ ] Keep Rule learner-visible and `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- [ ] Run a representative consented Shadow sample across education bands and documented demographic/cohort slices; insufficient samples report `insufficient_sample`.
- [ ] Require 100% schema validity, 100% evidence coverage, zero unsupported claims, zero unsafe output, and Rule fallback for every provider failure simulation.
- [ ] Prove consent revoke blocks both Shadow and visible calls.
- [ ] Record approved p50/p95 latency and cost-per-run thresholds.
- [ ] Obtain independent security, privacy, and bias review.
- [ ] Require Product Owner approval of an exact nonzero pilot percentage and rollback criteria.
- [ ] Enable only through environment configuration after approval; no schema bypass.

**Phase 12 exit:** either an explicitly approved monitored pilot exists or model-visible remains safely blocked. A successful Shadow run alone is not completion.

---

## 18. Review Checklists

### Database conflict checklist

- [ ] Runtime schema, applied migrations, and migration source agree or drift is documented.
- [ ] No applied migration or learner migration `001`–`004` changed.
- [ ] Every new migration ID is unique.
- [ ] Every unique constraint has duplicate preflight.
- [ ] Every foreign key has orphan preflight.
- [ ] Every CHECK/status change includes all existing values and consumer mappings.
- [ ] Every shared-table change lists Teacher, School, Enterprise, Student, AI, seed, and verifier consumers.
- [ ] Every multi-table workflow has one transaction, lock order, uniqueness barrier, and rollback test.
- [ ] Migration is rehearsed and applied twice on a disposable clone.
- [ ] Pre-existing row hashes/counts are preserved outside owned fixtures.
- [ ] Backup and forward-recovery procedure are verified before approved primary execution.

### Four-role regression checklist

- [ ] Teacher can still create/update activities, manage QR sessions, view registrations/check-ins, and publish evaluations within ownership.
- [ ] School can read only its classes, students, activities, registrations, reports, and authorized aggregates.
- [ ] Enterprise can manage only its profile, posts, applications, snapshots, and allowed statuses.
- [ ] Student can read/write only own data and only through allowed transitions.
- [ ] Cross-student, cross-teacher, cross-school, and cross-enterprise cases return `403` or non-enumerating `404` as the endpoint contract specifies.
- [ ] Shared auth/session/role routing remains green.
- [ ] No role independently writes a conflicting status owned by another state-machine service.

### Final Definition of Done

- [ ] No production Student action depends on mock or `localStorage` truth.
- [ ] Dashboard and Talent Passport use canonical, owner-scoped facts.
- [ ] Profile/evidence/sharing enforce field ownership and consent.
- [ ] Registration, waitlist, approval, cancellation, check-in, and experience are transaction-safe.
- [ ] Assessment core remains stable; history and published evaluations are complete.
- [ ] Applications have immutable snapshots, history, and Enterprise ownership.
- [ ] Notifications, badges, levels, and statistics derive from committed facts.
- [ ] All mutations enforce auth, permission, ownership, CSRF, validation, prepared SQL, audit, and idempotency where applicable.
- [ ] Four-role MySQL E2E, negative authorization, PHP, Node, lint, migration, and secret checks pass.
- [ ] AI model output remains hidden unless the independent Phase 12 gate is explicitly approved.

---

## 19. Execution Protocol

Implement one task at a time using TDD:

1. Re-run the phase entry gate.
2. Write the smallest failing contract/integration test.
3. Run it and record the expected failure.
4. Implement only the behavior covered by that test.
5. Run focused tests.
6. Run affected-role regressions.
7. Run database invariant checks where applicable.
8. Review the diff and stop at the task checkpoint.
9. Commit only after review; never combine unrelated phases.
10. Update the Program Tracker with evidence, not intent.

If a phase needs a material product decision not locked above, mark it `BLOCKED_PRODUCT_DECISION` and stop that phase. Do not select a behavior silently.


---

## Phase 6 Approved Amendments — 2026-08-22

These three amendments were approved together with `APPROVED_PHASE_5`. They bind Phase 6 execution.

### Amendment A1 — Notification producer deferred to Phase 8

Phase 6 originally required emitting an `assessment submitted` notification through a Phase 8 producer adapter. Runtime verification at Phase 6 entry found `notifications` **ABSENT**, `learner_notification_preferences` **ABSENT**, no `NotificationService`, and no notification API. The requirement is therefore **deferred to Phase 8**, where the producer runs after the submit transaction commits.

Phase 6 must not, for any preparatory reason:

- create the `notifications` table;
- create the `learner_notification_preferences` table;
- create a real or placeholder `NotificationService`;
- create a notification API endpoint;
- change the assessment submit transaction.

Phase 8 remains unstarted.

### Amendment A2 — Appeal / review / regrade excluded from v1

Product decision is locked: appeal, review, and regrade are **not** in Phase 6 and are excluded from the current v1. Phase 6 must not create schema, API, UI, status values, or placeholders for them. Reintroduction requires its own approved design and phase.

### Amendment A3 — Assessment catalog seed baseline test remediation

`tests/learner_assessment_catalog_seed_test.php` asserted a pristine post-seed state (`test_attempts`, `test_results`, `learner_assessment_answers`, `learner_assessment_attempt_metadata` all zero). `talenthub_local` is a legitimate demo database and legitimately holds 42 submitted attempts with related rows, so those four assertions were stale. This is **baseline test remediation** — not a Phase 5 defect and not an assessment-core change.

Approved resolution:

- Demo data is never deleted, truncated, or altered to turn a test green.
- The primary-database test stays read-only and verifies: 12 canonical talent tests, 366 questions, 12 published versions, 366 version/question bindings, UUID and code uniqueness, `schemaHash` and `scoringVersion`, and referential integrity of the attempts/results/answers that already exist.
- Transactional tables are no longer required to be zero on a shared/demo database.
- The "catalog seed creates no attempts, results, or answers" contract moved to a dedicated disposable/fresh-schema test.
- Existing catalog structure, hash, and content assertions were not weakened.

---

## Phase 0 Runtime Evidence Amendment & Missing Schema Reconciliation - 2026-08-21

Status: Phase 0 runtime audit found the live database authoritative schema differs from historical `Database/Talenthub.sql` and from later-phase `PhaseRequirements.php` expectations.

### Summary of Authoritative Facts:
- `talenthub_local` currently has 15 applied migrations, 0 pending, validation OK.
- Absent from runtime at Phase 0/1: `certificates`, `projects`, `project_members`, `badges`, `student_badges`, `internship_posts`, `internship_applications`, `application_status_history`, `notifications`. They are classified as `LEGACY_DUMP_ONLY` in `Database/Talenthub.sql` and `CODE_CONSUMER_WITHOUT_RUNTIME_TABLE` where current readers reference them.
- Existing runtime canonical status sets:
  - `activities`: `draft`, `published`, `ongoing`, `completed`, `archived` (student-visible catalog queries strictly select `published`, `ongoing`, `completed`).
  - `activity_registrations`: `pending`, `approved`, `rejected`, `cancelled`, `attended`, `waitlisted` (Phase 4 migration applied).
  - `activity_qr_sessions`: `active`, `expired`, `revoked`.
  - `checkins`: `confirmed`.
  - `experience_logs`: `confirmed`.
  - `test_attempts`: `submitted`.
  - Teacher `assessments`: `published`.
- Planned shared migration IDs `20260821000100` through `20260821000700` are reserved monotonically as planned IDs only; no migration file is created and no database mutation is performed during Phase 0/1.
- Phase 2 dependency rule: Phase 2 reads available canonical runtime facts (`student_profiles`, `student_skills`, `talent_tests`, `test_attempts`, `test_results`, Teacher `assessments`, `activity_registrations`, `checkins`, `experience_logs`). For facts whose tables are not yet created at Phase 2 (`certificates`, `projects`, `badges`), the Phase 2 repository returns an empty list `[]` safely rather than querying non-existent tables or inventing mocks.

---

### Detailed Schema Specifications for Missing Table Groups

#### 1. Certificates and Projects (`certificates`, `projects`, `project_members`)
- **Owning Migration:** `Database/migrations/20260821000200_create_student_certificates_and_projects.php`
- **Owning Service / Phase:** `StudentEvidenceService` / Phase 3
- **Dependency Order:** Depends on `20260821000100_create_student_passport_sharing.php` (Phase 3)
- **Tables and Columns:**
  - `certificates`: `id` CHAR(36) PK, `studentId` CHAR(36) NOT NULL, `title` VARCHAR(255) NOT NULL, `issuingOrganization` VARCHAR(255) NOT NULL, `issueDate` DATE NOT NULL, `expiryDate` DATE NULL, `credentialId` VARCHAR(255) NULL, `credentialUrl` VARCHAR(500) NULL, `verificationStatus` VARCHAR(32) NOT NULL DEFAULT 'unverified', `verifiedBy` CHAR(36) NULL, `verifiedAt` DATETIME NULL, `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.
  - `projects`: `id` CHAR(36) PK, `title` VARCHAR(255) NOT NULL, `category` VARCHAR(100) NOT NULL, `description` TEXT NULL, `mentorTeacherId` CHAR(36) NULL, `schoolId` CHAR(36) NULL, `status` VARCHAR(32) NOT NULL DEFAULT 'draft', `startAt` DATETIME NULL, `endAt` DATETIME NULL, `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.
  - `project_members`: `id` CHAR(36) PK, `projectId` CHAR(36) NOT NULL, `studentId` CHAR(36) NOT NULL, `role` VARCHAR(100) NOT NULL DEFAULT 'member', `contribution` TEXT NULL, `status` VARCHAR(32) NOT NULL DEFAULT 'active', `joinedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP.
- **Essential Foreign Keys:**
  - `certificates.studentId` -> `student_profiles(id)` ON DELETE RESTRICT
  - `certificates.verifiedBy` -> `users(id)` ON DELETE SET NULL
  - `projects.mentorTeacherId` -> `teacher_profiles(id)` ON DELETE SET NULL
  - `projects.schoolId` -> `schools(id)` ON DELETE SET NULL
  - `project_members.projectId` -> `projects(id)` ON DELETE CASCADE
  - `project_members.studentId` -> `student_profiles(id)` ON DELETE RESTRICT
- **Uniqueness Barriers:**
  - `uq_project_members_student`: `UNIQUE KEY (projectId, studentId)`
- **Canonical Statuses:**
  - `certificates.verificationStatus`: `'unverified'`, `'verified'`, `'rejected'`
  - `projects.status`: `'draft'`, `'in_progress'`, `'completed'`, `'archived'`
  - `project_members.status`: `'active'`, `'left'`, `'removed'`
- **Four-Role & AI Regression Consumers:**
  - Student: manage own certificates (`certificate.manage_own`), view project participation.
  - Teacher: verify student certificates in school scope, mentor projects.
  - School: aggregate evidence analytics.
  - Enterprise: view consented student certificates in Talent Explorer.
  - AI Engine: ingest verified evidence for skill mastery recommendations.

#### 2. Badges and Award Rules (`badges`, `student_badges`, `badge_rule_definitions`)
- **Owning Migration:** `Database/migrations/20260821000700_create_badges_and_award_rules.php`
- **Owning Service / Phase:** `BadgeAwardService` / Phase 9
- **Dependency Order:** Depends on confirmed activity, assessment, and evidence events from Phases 3–6.
- **Tables and Columns:**
  - `badges`: canonical catalog columns through `updatedAt`, unique `code`, exact level/status checks.
  - `student_badges`: unique `(studentId,badgeId)`, required `ruleDefinitionId`, `awardedAt`, `awardedBy`, and non-null JSON `awardContext`.
  - `badge_rule_definitions`: versioned threshold JSON, one unique `(badgeId,version)`, active-rule index, and exact FK/check contracts.
- **Essential Foreign Keys:**
  - `student_badges.studentId` -> `student_profiles(id)` ON DELETE RESTRICT
  - `student_badges.badgeId` -> `badges(id)` ON DELETE RESTRICT
  - `badge_rule_definitions.badgeId` -> `badges(id)` ON DELETE CASCADE
- **Uniqueness Barriers:**
  - `uq_badges_code`: `UNIQUE KEY (code)`
  - `uq_student_badges_award`: `UNIQUE KEY (studentId, badgeId)`
- **Canonical Statuses:**
  - `badges.status`: `'active'`, `'inactive'`, `'deprecated'`
  - `student_badges.awardedBy`: `'system'`, `'teacher'`, `'school_admin'`
- **Four-Role & AI Regression Consumers:**
  - Student: view awarded badges, personal statistics, progress.
  - Teacher/School: view class badge distribution.
  - AI Engine: badge award triggers rule evaluation in shadow mode.

#### 3. Internships and Application Lifecycle (`internship_posts`, `internship_applications`, `application_status_history`, `application_profile_snapshots`)
- **Owning Migrations:** `Database/migrations/20260821000500_create_internships_and_application_lifecycle.php` plus forward repairs `20260821000510_reconcile_phase7_exact_metadata.php` and `20260821000520_reconcile_phase7_exact_indexes.php`
- **Owning Services / Phase:** `ApplicationCommandService` and `InternshipService` / Phase 7
- **Dependency Order:** Depends on Enterprise profiles and Student passport sharing (Phase 3).
- **Tables and Columns:**
  - `internship_posts`: UUID PK/Enterprise FK; canonical title, field, location, work type, duration, education level, description/benefits; JSON skills/requirements; slots; UTC deadline; draft/active/closed/cancelled lifecycle.
  - `internship_applications`: UUID PK; post/Student FKs; submitted/reviewing/interview/accepted/declined/withdrawn lifecycle; candidate message; internal reviewer metadata; UTC applied/created/updated timestamps; no browser-selected CV URL.
  - `application_status_history`: immutable ordered transition facts (`fromStatus`, `toStatus`, actor user/role, internal note, UTC timestamp).
  - `application_profile_snapshots`: one-to-one application FK, canonical consent FK, `schemaVersion DEFAULT '1.0.0'`, allow-listed JSON payload and UTC capture timestamp.
- **Essential Foreign Keys:**
  - `internship_posts.enterpriseId` -> `enterprises(id)` ON DELETE RESTRICT
  - `internship_applications.postId` -> `internship_posts(id)` ON DELETE RESTRICT
  - `internship_applications.studentId` -> `student_profiles(id)` ON DELETE RESTRICT
  - `application_status_history.applicationId` -> `internship_applications(id)` ON DELETE RESTRICT
  - `application_status_history.changedByUserId` -> `users(id)` ON DELETE RESTRICT
  - `application_profile_snapshots.applicationId` -> `internship_applications(id)` ON DELETE RESTRICT
- **Uniqueness Barriers:**
  - `uq_internship_applications_student`: `UNIQUE KEY (postId, studentId)`
  - `uq_application_profile_snapshots_app`: `UNIQUE KEY (applicationId)`
- **Canonical Statuses:**
  - `internship_posts.status`: `'draft'`, `'active'`, `'paused'`, `'closed'`, `'cancelled'`
  - `internship_applications.status`: `'submitted'`, `'reviewing'`, `'shortlisted'`, `'accepted'`, `'rejected'`, `'withdrawn'`
- **Four-Role & AI Regression Consumers:**
  - Student: browse open posts (`internship.read_active`), apply (`internship_application.create_own`), withdraw (`internship_application.withdraw_own`).
  - Enterprise: manage posts (`internship_post.manage_own`), review applications (`internship_application.read_own_business`, `internship_application.review_managed`).
  - School: view employment outcomes for students of own school.
  - AI Engine: match student skill profiles to internship post requirements.

#### 4. Notifications and Preferences (`notifications`, `learner_notification_preferences`)
- **Owning Migration:** `Database/migrations/20260821000600_create_notifications_and_preferences.php`
- **Owning Service / Phase:** `NotificationService` / Phase 8
- **Dependency Order:** Depends on domain producers from Phases 4–7.
- **Tables and Columns:**
  - `notifications`: `id` CHAR(36) PK, `userId` CHAR(36) NOT NULL, `eventKey` VARCHAR(191) NULL, `notificationType` VARCHAR(100) NOT NULL, `title` VARCHAR(255) NOT NULL, `message` TEXT NOT NULL, `deepLink` VARCHAR(500) NULL, `readAt` DATETIME(6) NULL, `createdAt` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6).
  - `learner_notification_preferences`: `studentId` CHAR(36) NOT NULL, `notificationType` VARCHAR(100) NOT NULL, `inAppEnabled` TINYINT(1) NOT NULL DEFAULT 1, `emailEnabled` TINYINT(1) NOT NULL DEFAULT 0, `updatedAt` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PK (`studentId`,`notificationType`).
- **Essential Foreign Keys:**
  - `notifications.userId` -> `users(id)` ON DELETE RESTRICT ON UPDATE CASCADE
  - `learner_notification_preferences.studentId` -> `student_profiles(id)` ON DELETE RESTRICT ON UPDATE CASCADE
- **Uniqueness Barriers:**
  - `uq_notifications_user_event`: `UNIQUE KEY (userId, eventKey)`; the preference primary key prevents duplicate `(studentId, notificationType)` rows.
- **Canonical Statuses:**
  - `notifications.readAt`: `NULL` (unread), non-null UTC timestamp (read)
  - preferences store separate `inAppEnabled` and `emailEnabled` booleans; Phase 8 does not dispatch email.
- **Four-Role & AI Regression Consumers:**
  - All 4 roles (Student, Teacher, School, Enterprise): receive in-app notifications and manage preferences (`notification.manage_preferences_own`).
  - Domain Producers: Activity check-in confirmations, Teacher assessment publications, Enterprise application status updates.
