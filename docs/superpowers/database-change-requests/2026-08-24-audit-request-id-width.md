# Database Change Request: Audit Request ID Width

Date: 2026-08-24
Owner: TalentHub Platform
Migration: `20260824000200_widen_audit_request_id.php`

## Purpose

Align `audit_logs.requestId` with the application request-ID contract. Browser requests use UUID values with 36 characters and the server accepts request IDs between 16 and 64 characters, while the existing audit column is `CHAR(26)`. The mismatch can commit a business transaction and then return HTTP 500 when its audit insert exceeds 26 characters.

The migration widens the nullable column to `VARCHAR(64)`. It does not update or delete rows, and the existing `idx_audit_logs_request` index remains in place.

## Deployment risk and lock strategy

`audit_logs` is shared and indexed. MySQL may rebuild or lock the table while changing `CHAR(26)` to `VARCHAR(64)`, depending on the production server version, storage-engine capabilities, and table size. Before production execution:

1. Rehearse the exact migration on a restored production-sized copy using the same MySQL version and record elapsed time and observed locking.
2. Record `TABLE_ROWS`, `DATA_LENGTH`, and `INDEX_LENGTH` from the preflight query below.
3. Confirm the query plan/algorithm supported by the target server. Use an approved online-schema-change procedure if a nonblocking in-place change is supported and operational tooling is available.
4. If nonblocking execution cannot be demonstrated, schedule a maintenance window and stop application writes to `audit_logs` for the measured migration interval.

Do not assume `LOCK=NONE` support without a rehearsal on the target MySQL version.

## Rollout order

1. Confirm the exact target database and a restorable backup.
2. Run and record all preflight queries.
3. Rehearse on a production-sized restore and approve the lock strategy.
4. Apply this migration before deploying onboarding code that audits completion with browser request IDs.
5. Run the verification queries and one new-student onboarding smoke test.

## Preflight

```sql
SELECT DATABASE() AS target_database, @@version AS mysql_version,
       @@session.time_zone AS session_time_zone;

SELECT DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE,
       CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.columns
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_logs'
  AND COLUMN_NAME = 'requestId';

SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.statistics
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_logs'
  AND COLUMN_NAME = 'requestId';

SELECT TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
FROM information_schema.tables
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_logs';

SELECT COUNT(*) AS rows_over_64
FROM audit_logs
WHERE CHAR_LENGTH(requestId) > 64;
```

Required results: `audit_logs.requestId` exists as nullable `CHAR(26)` or is already a compatible character column of at least 64 characters; `idx_audit_logs_request` references it; and `rows_over_64` is `0`.

## Backup and rollback

Create a verified logical or physical backup before execution and record its location, checksum, and restore-test evidence in the deployment ticket. An application rollback does not require shrinking this backward-compatible column.

The migration is intentionally irreversible because new request IDs may exceed 26 characters immediately after deployment. Do not shrink the column after writes begin. If the DDL itself must be reversed, stop writes and restore the verified backup or execute a separately reviewed data-preserving rollback after proving no stored value exceeds the target width.

## Verification

```sql
SELECT COLUMN_TYPE, IS_NULLABLE, COLLATION_NAME
FROM information_schema.columns
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_logs'
  AND COLUMN_NAME = 'requestId';

SELECT INDEX_NAME, COLUMN_NAME
FROM information_schema.statistics
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_logs'
  AND INDEX_NAME = 'idx_audit_logs_request';

SELECT action, CHAR_LENGTH(requestId) AS request_id_length
FROM audit_logs
WHERE action = 'learner.onboarding_completed'
ORDER BY createdAt DESC
LIMIT 1;
```

Required results: the column is nullable `VARCHAR(64)`, `idx_audit_logs_request` remains present, and the smoke test stores `learner.onboarding_completed` with a 36-character browser request ID without an HTTP 500 response.

## Approval and execution record

Record database-owner approval, backup identifier, target environment, production-sized rehearsal evidence, chosen lock strategy, operator, start/end timestamps, migration output, and verification output in the deployment ticket.

## APPROVAL REQUIRED

Do not execute this migration against a shared, staging, or production database until the authorized database owner approves that exact environment and the lock strategy above. Committing and reviewing the migration does not authorize production execution.
