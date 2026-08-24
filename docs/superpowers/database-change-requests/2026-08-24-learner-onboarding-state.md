# Database Change Request: Learner Onboarding State

Date: 2026-08-24
Owner: TalentHub Student Portal
Migration: `20260824000100_create_learner_onboarding_states.php`

## Purpose

Add one server-owned onboarding state row for each newly registered student. The row controls mandatory completion of the four learner assessments before the rest of the student portal is unlocked.

No existing student rows are inserted. Existing accounts remain compatible and unlocked because absence of a row means onboarding is not required.

## Change contract

The migration creates an empty `learner_onboarding_states` table with these columns:

- `studentId CHAR(36)` primary key and foreign key to `student_profiles.id`.
- `status VARCHAR(20)` constrained to `pending`, `accepted`, or `completed`.
- `acceptedAt` and `completedAt` nullable UTC timestamps consistent with status.
- `createdAt` and `updatedAt` audit timestamps.

Expected row count immediately after migration: `0`. Application registration code creates rows only for student accounts registered after deployment.

The table uses InnoDB and `utf8mb4_unicode_ci`. The foreign key uses `ON DELETE RESTRICT ON UPDATE CASCADE`; status and timestamp consistency are protected by named check constraints. There is no backfill and no `INSERT INTO learner_onboarding_states` statement in the migration.

## Rollout order

1. Confirm a restorable backup and record its identifier.
2. Run the preflight queries below against the intended database.
3. Apply the migration before deploying application code that writes onboarding rows.
4. Deploy the application code.
5. Run the verification queries and a new-student registration smoke test.

Do not deploy the writing application code before the table exists.

## Preflight

```sql
SELECT DATABASE() AS target_database, @@session.time_zone AS session_time_zone;

SELECT ENGINE, TABLE_COLLATION
FROM information_schema.tables
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'student_profiles';

SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE,
       CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.columns
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'student_profiles'
  AND COLUMN_NAME = 'id';

SELECT COUNT(*) AS existing_onboarding_tables
FROM information_schema.tables
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'learner_onboarding_states';
```

Required results: the target database is explicitly confirmed; `student_profiles` is InnoDB with `utf8mb4_unicode_ci`; `student_profiles.id` is `CHAR(36) NOT NULL`; and `existing_onboarding_tables` is `0`.

## Backup and recovery

Before applying the migration, create a verified logical or physical backup using the environment's normal database runbook and record its location, checksum, and restore test evidence in the deployment ticket.

This migration is intentionally irreversible in the application migration framework. If rollout must be abandoned before application data is created, an authorized database operator may remove the empty table after verifying `SELECT COUNT(*) = 0`. If rows have been created, restore the pre-change backup or execute an independently reviewed data-preserving rollback; do not drop the table.

## Verification

```sql
SHOW CREATE TABLE learner_onboarding_states;

SELECT status, COUNT(*) AS row_count
FROM learner_onboarding_states
GROUP BY status;

SELECT COUNT(*) AS orphan_count
FROM learner_onboarding_states AS onboarding
LEFT JOIN student_profiles AS students ON students.id = onboarding.studentId
WHERE students.id IS NULL;
```

Immediately after migration, the grouped status query must return no rows and `orphan_count` must be `0`. After the smoke test, exactly one new student should have status `pending`; pre-existing students must still have no onboarding row.

## Approval and execution record

Record database owner approval, backup identifier, target environment, operator, start/end timestamps, migration runner output, and verification output in the deployment ticket.

## APPROVAL REQUIRED

Do not execute this migration against the primary, shared, staging, or production database until the user or authorized database owner gives explicit approval for that exact environment. This document and migration may be committed and reviewed without applying them.
