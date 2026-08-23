# Student Portal Release Checklist

This checklist is the human release gate for the Student, Teacher, School, and Enterprise portal workflows. It does not deploy, change `talenthub_local`, enable learner-visible AI, or authorize Phase 12.

## 1. Preconditions

- [x] Active branch is `feature/student` and the approved Phase 10/11 commits are present.
- [x] `.env`, `.claude/`, `.qwen/`, applied migrations, and learner migrations `001`–`004` are unchanged.
- [x] `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- [x] PHP is `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- [x] MySQL and mysqldump are from `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`.
- [x] Source database resolves to exactly `talenthub_local`.
- [x] No schema or grant matching `talenthub_phase11_rehearsal_%` remains.

## 2. Read-only database gates

Run from `D:\TalentHub`:

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php bin\connect-check.php --json --quick
& $php bin\migrate.php validate
& $php bin\migrate.php status
& $php app\learner\tools\readiness-check.php --phase=11 --json
```

Expected: connection OK, migration validation OK, 29 applied and 0 pending, Phase 11 `READY`.

## 3. Backup and disposable restore gate

The executable rehearsal performs the physical backup, SHA-256 verification, restore, double migration replay, journey, invariant verification, and unconditional cleanup:

```powershell
$env:APP_ENV = 'test'
$env:TALENTHUB_DISPOSABLE_TEST_DB = '1'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'talenthub_local'
$env:DB_USERNAME = 'talenthub_app'
$env:DB_PASSWORD = ''
& $php tests\student_portal_four_role_e2e_mysql_test.php
```

Required evidence:

- [x] Backup path is under the operating-system temporary `TalentHubBackups` directory.
- [x] Backup byte size is greater than zero and the reported SHA-256 is 64 lowercase hexadecimal characters.
- [x] Restored schema contains 61 base tables and 29 migration records.
- [x] Both migration replay arrays are empty and drift is false.
- [x] Two de-identified actors exist for each of Student, Teacher, School, and Enterprise.
- [x] Authorization evidence reports 9 positive and 9 denied cases.
- [x] The complete journey map is present and every counter/state matches the approved Phase 11 design.
- [x] All 61 baseline tables and all baseline rows are verified unchanged.
- [x] All discovered foreign-key constraints have zero orphan rows.
- [x] Primary before/after table count, row count, and deterministic digest are identical.
- [x] Output ends with `student_portal_four_role_e2e_mysql_test: OK; cleanup verified`.

## 4. Cleanup verification

```powershell
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
& $mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'talenthub_phase11_rehearsal_%'; SELECT COUNT(*) FROM mysql.db WHERE Db LIKE 'talenthub_phase11_rehearsal_%';"
```

Expected: `0` and `0`. Do not manually drop any database whose exact name is not allow-listed by the Phase 11 regex.

## 5. Regression and source gates

```powershell
& $php tests\phase11_release_rehearsal_contract_test.php
& $php tests\learner_phase_requirements_test.php
& $php tests\learner_shared_readiness_test.php
& $php tests\student_portal_cross_role_contract_test.php
git diff --check
git status --short
```

Run the full safe PHP and learner Node matrices recorded in the Phase 11 review report. Any failure keeps the decision at `NOT_READY`, even when the E2E rehearsal is green.

## 6. Privacy and security review

- [x] No raw QR token, password, database password, session token, or profile-share token appears in output or documentation.
- [x] Fixture emails end in `@example.invalid`; no real personal data is inserted.
- [x] Notifications expose only their owning user rows.
- [x] Cross-student, cross-teacher, cross-school, and cross-enterprise attempts fail with the documented denial.
- [x] Enterprise retains no check-in write permission; School reads only its owned aggregate.

## 7. Recovery procedure

If a later release attempt fails:

1. Stop application writes under a separate operational approval.
2. Locate the approved pre-release backup and recompute SHA-256; stop if it differs from the recorded digest.
3. Restore into a newly named recovery schema—never overwrite `talenthub_local` in place.
4. Run migration validation plus row-count, deterministic-hash, foreign-key, and ownership checks against the recovery schema.
5. Switch application configuration only after a separate human approval and an explicit rollback window.
6. Retain the failed schema for forensic review until an authorized owner releases it; do not delete it as part of this checklist.

## 8. Human decision

- Release reviewer: Codex verification recorded for the user-requested release gate
- Database reviewer: Codex read-only runtime verification against `talenthub_local`
- Product owner: User approval received in the Codex task
- Evidence timestamp/timezone: 2026-08-23 19:00 ICT (UTC+07:00)
- Backup path/SHA-256: `C:\Users\CHINGU~1\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase11_20260823114934.sql` / `362cc457b0ed4fbbc3db4101838050b5cda6c61639bc5d56ae602fdf7438bd81`
- Decision: `APPROVE` — `APPROVED_PHASE_11`
- Notes and accepted risks: Release artifact approved for deployment. No production host deployment is implied; target infrastructure, secrets, TLS/domain, maintenance window, and execution require separate operational authorization. Learner-visible AI remains disabled.

The Phase 11 human approval gate is satisfied. Phase 12 may start under its own scope and approval rules.
