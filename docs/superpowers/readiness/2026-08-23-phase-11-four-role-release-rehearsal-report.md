# Phase 11 Four-Role Release Rehearsal Report

**Decision:** `APPROVED_PHASE_11`
**Workspace:** `D:\TalentHub`  
**Branch:** `feature/student`  
**Date:** 2026-08-23  
**Base before Phase 11:** `d78502e8fbc2be12375e36475d48c105ce63f1e3`  
**Verified implementation HEAD:** `fb703ef9bc115a404657a13d28e322d65983a190`  
**Database:** `talenthub_local`, MySQL 8.4.3  
**Primary mutation:** none

Phase 11 has complete executable implementation evidence. The user/Product Owner supplied the required human release approval on 2026-08-23, and the approval was recorded after a fresh read-only verification against the real `talenthub_local` database and the production HTTP front controller. The release artifact is approved for a separately authorized production deployment. No production deployment was performed by this approval step, and Phase 12 has not started.

## Real runtime approval evidence

The approval did not rely only on backup or disposable-test evidence. On 2026-08-23 at 19:00 ICT, the production application composition was started locally through `api/v1/index.php` and connected to the real `talenthub_local` database without inserting, updating, or deleting primary data.

| Runtime gate | Fresh result |
|---|---|
| Database connection | `talenthub_local`, MySQL 8.4.3, connection OK |
| Schema | 61 base tables, 3,151 exact rows |
| Migrations | validation OK, 29 applied, 0 pending |
| Phase 11 readiness | `READY`, exit 0 |
| Production HTTP health | `GET /api/v1/health` = 200, database available |
| Anonymous ownership boundary | Student, Teacher, School, and Enterprise protected reads = 401 `AUTHENTICATION_REQUIRED` |
| Real role data | 20 Student, 11 Teacher, 3 School, 1 Enterprise users |
| Real learner facts | 40 registrations, 20 check-ins, 20 experiences, 42 attempts/results, 20 assessments |
| Real downstream facts | 54 notifications, 5 badge definitions, 54 student badge awards |
| AI visibility | `TALENTHUB_AI_VISIBLE_PERCENT=0` |

The shared HTTP router constructs real PDO repositories and production services for authentication, Student, Teacher, School, and Enterprise routes. Mutation routes enforce session authentication, RBAC and CSRF according to their contracts. Authenticated write journeys were previously executed on the restored full-data clone to protect primary data; the approval verification deliberately used only read-only calls against `talenthub_local`.

## Delivered scope

- Phase 11 readiness is the deterministic union of the required Phase 1–10 schema contracts instead of a registry-only check.
- The guarded rehearsal refuses missing test/disposable gates, requires source `talenthub_local`, and creates/drops only `talenthub_phase11_rehearsal_YYYYMMDDHHMMSS`.
- The rehearsal creates and SHA-256 verifies a physical mysqldump, restores it, validates migrations, and requires two no-op migration replays.
- Two de-identified Students, Teachers, Schools, and Enterprises exercise positive permission and negative ownership cases.
- Production services/repositories drive profile/share, activity registration/approval/waitlist, QR/check-in/experience, assessment/evaluation, internship application/review, notification, badge, statistics, and School aggregate behavior.
- Deterministic snapshots verify every pre-existing restored row and the complete primary database before/after state.
- A release checklist, authorization matrix, and recovery runbook are executable and contain no credentials.

## Fresh rehearsal evidence

Command:

```powershell
$env:APP_ENV='test'
$env:TALENTHUB_DISPOSABLE_TEST_DB='1'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_DATABASE='talenthub_local'
$env:DB_USERNAME='talenthub_app'
$env:DB_PASSWORD=''
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\student_portal_four_role_e2e_mysql_test.php
```

Result: exit `0`, `student_portal_four_role_e2e_mysql_test: OK; cleanup verified`.

| Evidence | Verified value |
|---|---:|
| MySQL version | 8.4.3 |
| Backup path | `C:\Users\CHINGU~1\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase11_20260823114934.sql` |
| Backup size | 874,271 bytes |
| Backup SHA-256 | `362cc457b0ed4fbbc3db4101838050b5cda6c61639bc5d56ae602fdf7438bd81` |
| Restored base tables | 61 |
| Restored migration records | 29 |
| First/second migration replay | `[]` / `[]` |
| Disposable actors | 2 Student, 2 Teacher, 2 School, 2 Enterprise |
| Permission cases | 9 allowed, 9 denied |
| Total E2E assertions | 3,397 |
| Baseline rows verified unchanged | 3,151 |
| Baseline digest | `d57833f1d950997d3336b6dfbee3dc0efc2815215bf5bd4620753e09e5b37b17` |
| Foreign-key constraints/orphans | 84 / 0 |
| Uniqueness checks | 7 |
| Actor ownership checks | 8 |
| Remaining rehearsal schemas/grants | 0 / 0 |

Primary before and after evidence is identical: 61 tables, 3,151 rows, and digest `d57833f1d950997d3336b6dfbee3dc0efc2815215bf5bd4620753e09e5b37b17`.

## Journey evidence

- Profile share: create, allow-listed projection, revoke, and cross-owner denial passed.
- Activity registration: Student A approved = 1; Student B waitlisted = 1; cross-Teacher activity/registration/QR mutations denied.
- Check-in/experience: confirmed check-ins = 1; confirmed experiences = 1; replay duplicates = 0; waitlisted cross-Student use denied.
- Assessment/evaluation: submitted results = 1; published evaluations = 1; cross-Student read/write and cross-Teacher grade denied.
- Application/review: applications = 1; immutable snapshot/history present; final status `reviewing`; cross-Student read/withdraw and cross-Enterprise read/list/review denied.
- Notifications: owner-visible, cross-owner invisible, stable event replay inserted zero duplicates.
- Badge/statistics: producer award exists, explicit replay awards = 0, facts and level/statistics remain owner-scoped and confirmed-only.
- School aggregate: School A sees one check-in and 1.50 confirmed hours; School B sees zero foreign facts.

## Regression and static verification

| Gate | Result |
|---|---|
| Learner JavaScript suites | 13/13 PASS |
| Safe PHP regression suites | 110/110 PASS |
| Phase 11 rehearsal contract | PASS |
| Phase 11 MySQL full journey | PASS |
| MySQL persistent limiter suite | PASS; cleanup verified |
| MySQL badge concurrency suite | PASS; cleanup verified |
| PHP lint (`app`, `src`, `Database`, `bin`, `tests`) | 528/528 PASS |
| `bin/migrate.php validate` | OK |
| `bin/migrate.php status` | 29 applied, 0 pending |
| Phase 11 readiness | READY, exit 0 |
| `git diff --check` | clean; line-ending notices only |
| Changed-file secret scan | 0 matches |
| Protected/applied-migration diff | 0 paths |
| Phase 11/10/9 disposable schemas and grants | 0 / 0 |

Four PHP files were correctly excluded from the safe in-process matrix because they require their own explicitly named disposable schema: the AI pilot seed, application endpoint runtime, career-group MySQL E2E, and notification endpoint runtime suites. Their exclusion is a gate contract, not a test failure. Phase 11 covers their production-facing four-role path on a full restored MySQL clone; additional self-contained MySQL limiter and badge-concurrency suites also passed.

During the full matrix, `learner_checkin_endpoint_runtime_test.php` exposed a Phase 10 fixture regression: the endpoint had gained persistent rate limiting but its SQLite fixture lacked `auth_rate_limits`, then accumulated buckets masked later domain assertions. The fixture now includes the canonical table and resets rate buckets between independent endpoint contract cases. The endpoint suite and the dedicated clock-controlled limiter suite both pass; production code was not changed.

## Security and protected scope

- `.env`, `.claude/`, `.qwen/`, all applied migrations, and learner migrations `001`–`004` were not edited.
- Reviewed hashes remain:
  - `.claude/settings.local.json`: `B9CA7EDEE4FFE523C6C7458DC159CE8B693AC78B68B4D11C8BFFF5F2BC55E722`
  - `.qwen/settings.json`: `6979FF28D933BBB504CAE4EEE75F07AFF325AA9B8CB93C07CE6C8EF53202ADF2`
- `TALENTHUB_AI_VISIBLE_PERCENT=0` is unchanged.
- Raw QR/share tokens, password hashes, and database credentials are not emitted in evidence or documents.
- No migration, seed, backfill, primary insert/update/delete, push, or merge occurred.

## Phase 11 commits

1. `63d33cf` — approved rehearsal design.
2. `a86a172` — implementation plan.
3. `d130653` — complete Phase 11 readiness contract.
4. `f6c5c26` — fail-closed disposable/backup shell.
5. `0408148` — four-role actor and RBAC proof.
6. `5a01c4a` — complete portal journey.
7. `9f78ba0` — invariant gate and recovery documentation.
8. `b385d04` — endpoint rate-limit fixture regression fix.
9. `fb703ef` — strengthened negative ownership journey.

## Review gate

No unresolved Critical or Important issue was found in the final requirements/code review. Human release approval was received from the user/Product Owner and recorded in `student-portal-release-checklist.md`; the truthful Phase 11 state is now `APPROVED_PHASE_11`.

This approval means the database schema/data, application code, and real API composition are accepted as a production-deployable release artifact. It does not claim that a production host was changed: target infrastructure, production secrets, TLS/domain configuration, maintenance window, and the actual deployment command remain a separate operational authorization. Phase 12 may now enter its own gate, while learner-visible model output remains disabled until Phase 12 explicitly approves it.
