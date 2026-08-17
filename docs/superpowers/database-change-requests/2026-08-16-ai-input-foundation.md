# Database Change Request: Canonical AI-input foundation

**Requested migration path:** `Database/migrations/learner/002_create_ai_input_foundation.php`
**Version:** `002_create_ai_input_foundation`
**Scope owner:** Learner module
**Status:** exact-DDL approval granted; disposable proof and the separately authorized shared-database execution completed on 2026-08-16

## Approval Gate

The user granted exact-DDL source/disposable approval in this session. The migration source was created from SHA-256 `af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a` and is limited to the statement sequence below.

After the backup, fresh live-baseline, preflight, and disposable-run evidence was collected, the user explicitly authorized execution that does not delete the database or create conflicts. Migration `002` was then executed once on `talenthub_local`; this document records its evidence below. That authorization does not authorize a later extension, recommendation, seed, shared-module, runtime-write, `ALTER`, `DROP`, deletion, truncation, or data rewrite.

This request is additive and forward-only. It creates no seed data, alters no existing table, and changes no existing row. It must not be treated as approval for any later extension, recommendation, seed, shared-module, or runtime-write change.

## Purpose and boundary

The change supplies missing canonical inputs for learner AI readiness: a skill catalog and claims, test definitions/questions/attempts/results, consent events, and activity QR/check-in/experience facts. Existing identity, activity, and registration records remain their current owners' source of truth. AI has read-only use of source facts and must never verify a skill, award hours, or alter an existing shared record.

No shared data changes are permitted before or after the existing-table row-count proofs in this request. The migration contains only `CREATE TABLE IF NOT EXISTS` statements after a fail-closed compatibility preflight; it contains no `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `TRUNCATE`, rename, conversion, or backfill.

## Evidence collected before approval

### Current live-table inventory and row-count baseline

On 2026-08-16, the controller used the repository connection configuration in a `SET SESSION TRANSACTION READ ONLY` transaction to inspect metadata and execute `COUNT(*)`. No credentials, personal rows, DDL, DML, or schema name were printed. The server reported MySQL `8.4.3`; the live schema contained 20 tables: `activities`, `activity_registrations`, `assessment_criteria`, `assessment_scores`, `assessments`, `audit_logs`, `auth_rate_limits`, `classes`, `enterprise_members`, `enterprises`, `permissions`, `reports`, `role_permissions`, `roles`, `schema_migrations`, `school_members`, `schools`, `student_profiles`, `teacher_profiles`, and `users`.

| Table | Role | Exact live count | Evidence/status |
|---|---|---:|---|
| `student_profiles` | existing shared FK parent | 12 | present; `id CHAR(36) NOT NULL PRIMARY KEY` |
| `activities` | existing Teacher/School FK parent | 0 | present; `id CHAR(36) NOT NULL PRIMARY KEY` |
| `activity_registrations` | existing shared FK parent | 0 | present; `id CHAR(36) NOT NULL PRIMARY KEY` |
| `skills` | proposed canonical table | N/A | absent from live schema |
| `student_skills` | proposed canonical table | N/A | absent from live schema |
| `talent_tests` | proposed canonical table | N/A | absent from live schema |
| `test_questions` | proposed canonical table | N/A | absent from live schema |
| `test_attempts` | proposed canonical table | N/A | absent from live schema |
| `test_results` | proposed canonical table | N/A | absent from live schema |
| `privacy_consents` | proposed canonical table | N/A | absent from live schema |
| `activity_qr_tokens` | proposed canonical table | N/A | absent from live schema |
| `checkins` | proposed canonical table | N/A | absent from live schema |
| `experience_logs` | proposed canonical table | N/A | absent from live schema |
| `learner_forward_migrations` | learner migration registry | N/A | absent; it will be created only when an approved migration actually runs |

### Fresh shared-execution baseline (read-only)

Captured at `2026-08-16T06:30:20+00:00` in a `SET SESSION TRANSACTION READ ONLY` transaction. The MySQL server was `8.4.3` and `@@session.time_zone` was `+00:00`. No source rows, schema objects, or session data were written. The parent-table fingerprints are SHA-256 of normalized `SHOW CREATE TABLE` output (whitespace normalized and `AUTO_INCREMENT=<n>` normalized to `AUTO_INCREMENT=?`).

| Parent table | Row count | Schema SHA-256 |
|---|---:|---|
| `student_profiles` | 12 | `0c85d3ba8fef1ba0fccb7d34191e5f1b0b0fcff17e272b359b0138e5ea6267bf` |
| `activities` | 0 | `d94c87bea21513d25caa23e4123020137710792acf0f3ee54e58a3badc5ef951` |
| `activity_registrations` | 0 | `6f843809380fea4633105bd8d107f9dc09413c0b27403f658b10b021f4a2e95d` |

All ten canonical targets and `learner_forward_migrations` were absent at capture time. Immediately before any shared execution, repeat this capture and require the same parent counts and fingerprints; any difference is a hard stop requiring a new review.

Committed static evidence is not evidence of the live database: `Database/Talenthub_DB.sql` contains legacy shapes for all ten target names, but this live inventory confirms that none of those target tables exists in the current shared schema. In particular, the static file uses `token`/`expiresAt` rather than `tokenHash`/`validFrom`/`validUntil`, omits several statuses, uses cascade FKs, and lacks required canonical fields. The authoritative application migration `20260815000100_create_teacher_activity_assessments.php` defines the shared parent contracts below. Static differences still make a fresh live shape verification mandatory immediately before execution.

| Existing parent | Required compatible live contract used by this DCR |
|---|---|
| `student_profiles` | `id CHAR(36)` primary key |
| `activities` | `id CHAR(36)` primary key; existing table is not modified |
| `activity_registrations` | `id CHAR(36)` primary key; existing table is not modified |

### Existing-query compatibility

Existing learner activity reads select only `activities` and `activity_registrations`; student reads select `student_profiles` joined to identity/class/school tables. The proposed statements neither alter those tables nor write their rows. New FK child tables add no query-visible columns or required fields to old code. The approved runner only discovers learner migrations and records an applied version after schema validation; existing role migrations and Teacher/School/Enterprise code are outside this request.

Compatibility preflight is required immediately before shared execution. For every target table, query `information_schema.tables`, `columns`, `statistics`, `key_column_usage`/`referential_constraints`, and `table_constraints` (including CHECK clauses). If the table is absent it may be created. If present, every canonical column type, nullability, default, primary/unique/key name and order, FK target and `RESTRICT/CASCADE` actions, CHECK expression, **ENGINE = InnoDB**, table character set **utf8mb4**, and table collation **utf8mb4_unicode_ci** below must match exactly; otherwise abort before running any DDL. Parent keys must be `CHAR(36)` and InnoDB/utf8mb4 compatible. `CREATE TABLE IF NOT EXISTS` is never evidence of compatibility.

## Exact proposed DDL

All identifiers and constraint names below are part of the reviewed contract. All timestamps are UTC application values with microsecond precision. JSON is stored as `LONGTEXT` and is constrained with `JSON_VALID`; no native JSON alias is relied on. All new FKs are `ON DELETE RESTRICT ON UPDATE CASCADE`.

### Exact-DDL approval fingerprint

The canonical artifact is **only** the SQL code fence below: UTF-8 bytes, LF (`\n`) line endings, no fence markers, and no byte-order mark. Its SHA-256 is:

```text
af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a
```

After approval, migration `002` must reproduce this exact MySQL statement sequence (with the same canonical line endings) and the disposable-schema proof must record the same SHA-256. Any semantic or formatting change that changes this fingerprint requires a new DCR approval; no migration source may be treated as equivalent merely because it has a similar table name.

```sql
CREATE TABLE IF NOT EXISTS skills (
  id CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(150) NOT NULL, category VARCHAR(100) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active',
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_skills_code (code), KEY idx_skills_status_category (status, category), CONSTRAINT chk_skills_status CHECK (status IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_skills (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, skillId CHAR(36) NOT NULL, levelScore DECIMAL(5,2) NOT NULL, sourceType VARCHAR(50) NOT NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'self_declared', verifiedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_student_skills_student_skill_source (studentId, skillId, sourceType), KEY idx_student_skills_skill (skillId), KEY idx_student_skills_student_verification (studentId, verificationStatus),
  CONSTRAINT fk_student_skills_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_student_skills_skill FOREIGN KEY (skillId) REFERENCES skills(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_student_skills_level_score CHECK (levelScore >= 0 AND levelScore <= 100), CONSTRAINT chk_student_skills_source_type CHECK (sourceType IN ('self_declared','assessment','teacher','activity','import')), CONSTRAINT chk_student_skills_verification CHECK (verificationStatus IN ('self_declared','pending','verified','rejected')), CONSTRAINT chk_student_skills_verified_at CHECK ((verificationStatus = 'verified' AND verifiedAt IS NOT NULL) OR (verificationStatus <> 'verified' AND verifiedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talent_tests (
  id CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_talent_tests_code (code), KEY idx_talent_tests_status_type (status, type), CONSTRAINT chk_talent_tests_type CHECK (type IN ('interest','aptitude','personality','skills')), CONSTRAINT chk_talent_tests_status CHECK (status IN ('draft','published','retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_questions (
  id CHAR(36) NOT NULL, testId CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, content VARCHAR(4000) NOT NULL, optionsJson LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_test_questions_test_code (testId, code), KEY idx_test_questions_test_status (testId, status), CONSTRAINT fk_test_questions_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_test_questions_options_json CHECK (JSON_VALID(optionsJson)), CONSTRAINT chk_test_questions_status CHECK (status IN ('draft','published','retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_attempts (
  id CHAR(36) NOT NULL, testId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'in_progress', startedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), submittedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_test_attempts_test (testId), KEY idx_test_attempts_student_status (studentId, status), CONSTRAINT fk_test_attempts_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_test_attempts_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_test_attempts_status CHECK (status IN ('in_progress','submitted','expired','abandoned')), CONSTRAINT chk_test_attempts_submitted_at CHECK ((status = 'submitted' AND submittedAt IS NOT NULL) OR (status <> 'submitted' AND submittedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_results (
  id CHAR(36) NOT NULL, attemptId CHAR(36) NOT NULL, resultCode VARCHAR(100) NOT NULL, summary VARCHAR(4000) NOT NULL, dimensionScoresJson LONGTEXT NOT NULL, scoringVersion VARCHAR(100) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_test_results_attempt (attemptId), CONSTRAINT fk_test_results_attempt FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_test_results_dimension_scores_json CHECK (JSON_VALID(dimensionScoresJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_consents (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, scope VARCHAR(50) NOT NULL, isGranted TINYINT UNSIGNED NOT NULL DEFAULT 0, policyVersion VARCHAR(100) NOT NULL, grantedAt DATETIME(6) NULL, revokedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_privacy_consents_student_scope_policy_created (studentId, scope, policyVersion, createdAt), KEY idx_privacy_consents_student_scope_granted (studentId, scope, isGranted), CONSTRAINT fk_privacy_consents_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_privacy_consents_scope CHECK (scope IN ('assessment','skills','activity','evaluation')), CONSTRAINT chk_privacy_consents_granted CHECK (isGranted IN (0,1)), CONSTRAINT chk_privacy_consents_dates CHECK ((isGranted = 1 AND grantedAt IS NOT NULL AND revokedAt IS NULL) OR (isGranted = 0 AND grantedAt IS NULL AND revokedAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_qr_tokens (
  id CHAR(36) NOT NULL, activityId CHAR(36) NOT NULL, tokenHash CHAR(64) NOT NULL, validFrom DATETIME(6) NOT NULL, validUntil DATETIME(6) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_activity_qr_tokens_token_hash (tokenHash), KEY idx_activity_qr_tokens_activity_status (activityId, status), CONSTRAINT fk_activity_qr_tokens_activity FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_activity_qr_tokens_status CHECK (status IN ('active','revoked','expired')), CONSTRAINT chk_activity_qr_tokens_window CHECK (validUntil >= validFrom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkins (
  id CHAR(36) NOT NULL, registrationId CHAR(36) NOT NULL, qrTokenId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', checkedInAt DATETIME(6) NULL, confirmedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_checkins_registration (registrationId), KEY idx_checkins_qr_token (qrTokenId), CONSTRAINT fk_checkins_registration FOREIGN KEY (registrationId) REFERENCES activity_registrations(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_checkins_qr_token FOREIGN KEY (qrTokenId) REFERENCES activity_qr_tokens(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_checkins_status CHECK (status IN ('pending','checked_in','confirmed','rejected')), CONSTRAINT chk_checkins_checked_in_at CHECK ((status IN ('checked_in','confirmed') AND checkedInAt IS NOT NULL) OR (status IN ('pending','rejected') AND checkedInAt IS NULL)), CONSTRAINT chk_checkins_confirmed_at CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS experience_logs (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, activityId CHAR(36) NOT NULL, checkinId CHAR(36) NOT NULL, hours DECIMAL(7,2) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', auditReason VARCHAR(500) NULL, confirmedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_experience_logs_checkin (checkinId), KEY idx_experience_logs_student_status (studentId, status), KEY idx_experience_logs_activity (activityId), CONSTRAINT fk_experience_logs_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_experience_logs_activity FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_experience_logs_checkin FOREIGN KEY (checkinId) REFERENCES checkins(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_experience_logs_hours CHECK (hours >= 0 AND hours <= 24), CONSTRAINT chk_experience_logs_status CHECK (status IN ('pending','confirmed','rejected')), CONSTRAINT chk_experience_logs_confirmed_at CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Ownership and source-of-truth matrix

| Table/fields/constraints | Owner and source of truth | Learner/AI authority |
|---|---|---|
| `student_profiles.id`; `activities.id`; `activity_registrations.id` and existing columns/indexes/FKs | Existing shared Identity/School/Teacher contracts | Read-only FK targets; never altered or written here |
| `skills` including `code`, catalog status and indexes | Canonical skill catalog | Learner/AI reads only; catalog administration is outside this request |
| `student_skills` including score/source/verification | Learner claim plus Teacher/School verification process | learner owns self-claim; AI reads only and never verifies |
| `talent_tests`, `test_questions` | Canonical assessment catalog | Learner reads published definitions; AI reads only |
| `test_attempts`, `test_results` | Learner assessment persistence | learner owns active attempt; result is immutable once created; AI reads only |
| `privacy_consents` | Learner consent record | learner grants/revokes own scope; AI reads an effective grant only |
| `activity_qr_tokens` | Activity/check-in authority | only SHA-256 token hash is stored; raw QR material is never stored |
| `checkins`, `experience_logs` | Event/check-in confirmation authority | AI reads confirmed rows and never awards hours |
| All FKs, unique keys, CHECKs and JSON checks | This DDL contract | enforces identity, cardinality, lifecycle, and payload validity |

## `learner_forward_migrations` registry contract

`LearnerForwardMigrationRunner` owns registry table `learner_forward_migrations` and serializes MySQL runs with lock `talenthub:learner_forward_migrations`. Its registry schema is `version VARCHAR(191) PRIMARY KEY`, `name VARCHAR(255) NOT NULL`, `checksum CHAR(64) NOT NULL`, `description TEXT NOT NULL`, and `appliedAt VARCHAR(40) NOT NULL`. The runner computes SHA-256 of each migration file, rejects applied-version checksum drift, validates expected tables/columns/indexes, then inserts exactly one registry record only after successful statements and schema validation. `migrateApproved(['002_create_ai_input_foundation'])` is the only proposed execution entry point. A second approved invocation must return `[]`; it must not write another registry row or change any existing-table count.

## Mandatory preflight, backup, and execution evidence

### Backup / restore proof status

A logical backup was created on `2026-08-16` at `D:\TalentHub\.tmp\learner-ai-backups\shared-pre-002-20260816T063020Z-no-tablespaces.sql`: 75,590 bytes, SHA-256 `723ca792c96ca94fc28604c05b16262574f6be5283d2f18cf628c878a6a50b89`, with an empty stderr log. It contains the accessible schema, data, and triggers. The application database principal does not have `PROCESS` or `EVENT` privileges, so the backup intentionally uses `--no-tablespaces` and excludes events/routines; the original all-options attempt is retained separately for audit and must not be used as restore proof.

Restore validation is **complete**. The original application-principal `CREATE DATABASE` attempt was refused with `ERROR 1044 (42000)` and is retained as audit evidence. Under the user's explicit authority, the local MySQL administrator created only the disposable schema `talenthub_ai_backup_verify_20260816` and granted the application principal access; no `talenthub_local` object or row was changed. A restore-ready copy at `D:\TalentHub\.tmp\learner-ai-backups\shared-pre-002-20260816T063020Z-restore-verify.sql` (75,444 bytes, SHA-256 `2d1dfa6d3044b8c2de2804d7cbcc273f8041bf83b48de3f69e924da6019f72b6`) restored with empty stderr. Source/restored counts were `student_profiles=12`, `activities=0`, and `activity_registrations=0`. Their normalized `SHOW CREATE TABLE` fingerprints matched after normalizing MySQL's semantically redundant explicit `CHARACTER SET utf8mb4` table option; engine, character set, and collation also matched. No backup artifact contains credentials in this document.

1. Use a dedicated read-only credential/session to capture `SHOW CREATE TABLE` and exact `COUNT(*)` for every inventory row, plus a deterministic schema fingerprint (SHA-256 of normalized `SHOW CREATE TABLE` output) for the three existing parents and registry. Record timestamp, database/server version, counts, and hashes without credentials or personal data.
2. Verify the migration's canonical MySQL statement sequence SHA-256 equals `af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a`; re-display the exact DDL above and ensure static scope audit has no destructive token. The source was created under the exact-DDL source/disposable approval recorded above; that approval does not authorize shared execution.
3. Before any shared execution, take a restorable, access-controlled logical backup or provider snapshot. Record backup identifier, UTC completion time, database/schema fingerprint, checksum, and a successful restore-validation result. Do not include credentials or raw personal records in this DCR.
4. Run compatibility preflight described above. Also assert `@@session.time_zone = '+00:00'` before any default `CURRENT_TIMESTAMP(6)` may be evaluated; otherwise stop. Any existing target table that is absent from the exact contract, including legacy `activity_qr_tokens`, `checkins`, or `experience_logs`, is a hard stop. Do not repair it with `ALTER` or data copy; submit a new DCR.
5. Run migration only on a disposable schema first; run it twice and retain logs proving the runner's table/column/index checks pass and the second run applies no versions. Independently verify the complete DDL contract (FK endpoints/actions, CHECK/JSON checks, and table options) from the exact source fingerprint and MySQL metadata before shared execution. Then obtain second explicit approval with disposable output, backup proof, live row baseline, and hashes before calling `migrateApproved()` on shared infrastructure. This was completed as recorded below.

## Post-approval verification/test checklist

- `tests/learner_ai_input_schema_test.php` scans the exact source for destructive SQL and uses `SchemaInspector` for every listed table, column, named index, and FK endpoint in an isolated SQLite fixture. The exact MySQL DDL fingerprint separately covers FK actions, CHECK/`JSON_VALID` expressions, and table options; MySQL metadata evidence below verifies the executed result.
- Run `& $php tests\learner_ai_scope_policy_test.php` and disposable schema test; retain PASS output and prove second run reports no applied versions.
- Immediately before and after shared execution, capture exact counts and schema hashes for `student_profiles`, `activities`, and `activity_registrations`. Require before == after for every count and hash; no shared data changes are allowed either side of those proofs.
- Verify all ten canonical tables, FK violations = 0, registry checksum/version match, second run `[]`, protected-role smoke checks PASS, and no secret/token/plaintext QR value appears in logs or database columns.

## Execution record (completed)

The exact approved MySQL statement sequence retained fingerprint `af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a`. The PHP migration source file checksum was `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc`.

### Disposable execution

On the restored disposable schema `talenthub_ai_backup_verify_20260816`, `migrateApproved(['002_create_ai_input_foundation'])` returned `['002_create_ai_input_foundation']` on the first run and `[]` on the second. The runner's expected table/column/index checks passed before it recorded the one matching registry version; `@@session.time_zone` was `+00:00`. Independent metadata queries found all ten canonical tables and 13 foreign keys; the three restored parent counts remained `12`, `0`, and `0`.

### Shared execution

At `2026-08-16T06:58:46Z`, immediately before shared execution, a read-only transaction against `talenthub_local` reconfirmed MySQL `8.4.3`, `@@session.time_zone = '+00:00'`, the three parent counts and schema fingerprints from the earlier baseline, and that all ten canonical targets plus `learner_forward_migrations` were absent. The migration runner's preflight therefore ran against the same compatible parent contract before it created the registry or executed DDL.

The first shared invocation returned `['002_create_ai_input_foundation']`; the immediate second invocation returned `[]`. The registry stores version `002_create_ai_input_foundation` with checksum `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc`, exactly matching the source file. Post-run read-only verification confirmed:

| Check | Result |
|---|---|
| Existing parent row counts and normalized schema hashes | identical to the pre-execution baseline: `student_profiles=12`, `activities=0`, `activity_registrations=0` |
| Canonical target tables | 10/10 present |
| New-table rows | all 10 tables contain 0 rows; no seed or backfill ran |
| Foreign keys | 13 total, each metadata rule is `ON DELETE RESTRICT ON UPDATE CASCADE`; `@@foreign_key_checks=1`. All new child tables contain 0 rows, so there are no new-child FK violations to check. |
| Session time zone | `+00:00` |
| Registry idempotency | exactly one matching migration record; second run `[]` |

A non-mutating protected-role syntax smoke check passed for all 57 PHP files under `app/teacher`, `app/school`, and `app/enterprise`; the committed diff contains no protected-role, `src`, or `api` path. The migration and MySQL column metadata use only `activity_qr_tokens.tokenHash`; no raw QR token/plaintext-token column exists, and operational command logs record only aggregate counts, hashes, versions, and metadata—not credentials, personal rows, QR material, or provider secrets.

No existing table was altered and no existing row was written, deleted, truncated, or replaced. The database change is complete and remains forward-only; any future schema or data change requires a new DCR and approval.

## Operational rollback

There is no destructive down migration. If a problem is found, disable learner read/write feature flags or route configuration, stop new migration approvals, and preserve all schema and data. Restore is only from the verified pre-change backup under a separately authorized incident procedure; this DCR does not authorize `DROP`, deletion, truncation, or data rewrite. A remediation needs a new reviewed DCR with its own exact DDL, backup plan, compatibility proof, and approval gate.
