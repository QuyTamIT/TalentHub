# Database Change Request — Learner Assessment Catalog Seed

> **Document:** `docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md`
> **Plan ref:** `docs/superpowers/plans/2026-08-17-learner-assessment-catalog-content.md` (Section 11.2)
> **Task:** Task 9 — Database Change Request Document
> **Date:** 2026-08-19 (superseded by review addendum 2026-08-20)
> **Status:** TASK 13 EXECUTED — SHARED DATABASE SEEDED; POST-SEED VERIFICATION PASS.
> **Branch:** `feature/student`
> **Author (reviewer role):** Claude Opus — Database Change Reviewer / Technical Writer

## Codex Review Addendum — 2026-08-20

This addendum supersedes earlier draft observations and catalog snapshots in this document where they conflict with the current working-tree evidence.

- Product Owner approval: **NCnguyenn**, Option A (30/32/28/32 questions per band), approved at `2026-08-20T05:05:10Z`.
- Codex content, educational, bias/safety, scoring, and schema-contract review: **approved** at `2026-08-20T05:05:10Z` after independent review of all 366 prompts and fresh validator/scorer evidence.
- All 12 catalogs are now `review_state = published` and contain the six required review checkpoints. Stable IDs, question codes, dimensions, reverse keys, positions, and question counts are unchanged; only reviewed prompt text and corresponding canonical hashes changed.
- Current source-of-truth hashes are the `metadata.schema_hash` values in the 12 catalog files. Fresh cross-catalog validation passed 8,855 assertions, including 366 globally unique UUIDs, codes, and normalized prompts.
- Seeder contract and published immutability tests passed (7 and 13 assertions). No migration, seeder, or write was executed against any database in this review.
- `talenthub_local` remains outside this approval. Task 12 may create and destroy only a newly named disposable MySQL 8.4 database, capture evidence, and leave `talenthub_local` untouched. Production seeding requires a separate explicit approval after the disposable run.
- Schema reconciliation update (2026-08-20): migration `20260818000100` now adopts an existing complete eight-table assessment schema and applies only additive compatibility changes (framework type CHECK expansion and three missing indexes). Partial schemas fail closed; no table, key, column, or data is dropped or rewritten. Existing `retired` version status is preserved for compatibility with the learner runtime.
- Task 12 current evidence supersedes the earlier unavailable-MySQL rows below: disposable dry-run `talenthub_assessment_catalog_verify_20260820_122200` completed with 12 inserts / 366 questions, second run 12 idempotent no-ops, all 12 schema hashes matched, and the disposable database was dropped and verified absent. On `talenthub_local`, the authoritative full check is 44 existing tables, 12 applied migrations, `20260818000100` pending, `drift=false`, and zero rows in all eight assessment tables. No production migration or seed was executed.
- Task 13 execution evidence (2026-08-20): backup captured before write; migration `20260818000100` reconciled and recorded as applied; first seed inserted 12 catalogs / 366 questions / 12 versions / 366 bindings; second seed returned 12 idempotent no-ops; all 12 versions are `published`; source-to-DB schema hashes match; question IDs and codes are unique; attempt/answer/result tables remain empty. No writes were made outside the approved migration and four catalog seed tables.

---

## 0. Scope and Guardrails

- **Original Task 9 output:** this file only (`docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md`).
- The 2026-08-20 schema reconciliation is a separately reviewed implementation change in the migration and reconciliation test; it does not authorize a production migration or seed.
- No migration or seeder was executed against `talenthub_local`. Disposable verification databases were created and cleaned up only.
- No disposable dry-run database was created in this task (MySQL unavailable — see Section 19).
- No Gemini, 9Router, or external API was called.
- All hashes, UUID/code manifest rows, and row counts below were recomputed from the 12 real catalog files on disk at generation time. No prior report was trusted verbatim.

---

## 1. Schema / Database

| Item | Value | Evidence |
|---|---|---|
| Database | `talenthub_local` | `.env` `DB_DATABASE=talenthub_local` |
| Driver | `mysql` (PDO) | `app/learner/data/bootstrap.php` / plan Section 2.1 |
| Server | Laragon MySQL Community Server **8.4.3** | Plan Section 2.1 / `Database/migrations/20260818000100_create_learner_assessment_schema.php` header |
| Charset / Collation | `utf8mb4` / `utf8mb4_unicode_ci` (all 8 tables) | Migration DDL `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` |
| Engine | `InnoDB` (all tables) | Migration DDL `ENGINE=InnoDB` |
| Session time zone | `+00:00` required | Migration `preflight()` asserts `SELECT @@session.time_zone = '+00:00'` |

No other database is in scope. The disposable dry-run database (Section 9) is a separate, newly created database and is never `talenthub_local`.

---

## 2. Prerequisite Migrations

### 2.1 Required migration

| Version | File | Checksum (SHA-256) | Purpose |
|---|---|---|---|
| `20260818000100` | `Database/migrations/20260818000100_create_learner_assessment_schema.php` | `bcda805be57de3a396f036e71cac85a811fa7ff4061a48f77689a3ce0b674f03` | Create or reconcile learner assessment canonical schema (8 tables) |

File checksum was recomputed with `hash_file('sha256', $file)` on 2026-08-20 after the additive reconciliation update. Runner is `src/Database/Migration/MigrationRunner.php` using `Database/migrations/*.php` with `MigrationContext` / `AbstractMigration`.

### 2.2 Tables created by the migration

`talent_tests`, `test_questions`, `learner_assessment_versions`, `learner_assessment_question_versions`, `test_attempts`, `learner_assessment_attempt_metadata`, `learner_assessment_answers`, `test_results`.

### 2.3 Parent-table dependency

- `student_profiles` must exist with `id CHAR(36) NOT NULL`, `utf8mb4` / `utf8mb4_unicode_ci`, `InnoDB`, and `PRIMARY KEY (id)`. The migration `preflight()` asserts this contract and fails closed otherwise. `student_profiles` is the only external FK parent for this migration (`test_attempts.studentId`).

### 2.4 Applied / not applied — OBSERVED

- **OBSERVED on 2026-08-19:** MySQL was **unavailable** at `127.0.0.1:3306` (PDO `SQLSTATE[HY000] [2002]` — connection failed / host not responding). No live check of `schema_migrations` or `information_schema.tables` could be performed from this task.
- **Connect user in `.env`:** `talenthub_app` (no password in working tree). `CREATE DATABASE` privilege for `talenthub_app` is **NOT VERIFIED** — could not connect to test it.
- **Therefore:** `20260818000100` status is **NOT EXECUTED — MYSQL UNAVAILABLE**. Whether it is applied or pending on `talenthub_local` is **unknown until a live `MigrationRunner::status()` check is run with a working MySQL 8.4.3 connection**. The seeder preflight (Section 8) must gate on a successful live check; this DCR does not assert applied/pending by assumption.
- Pre-task report mentioning "Migrations applied: 0 / pending: 0" is **not adopted** as evidence by this DCR — it predates this verification and could not be re-confirmed live.

### 2.5 Runner behavior

- `MigrationRunner::migrate()` runs `preflight()` then `up()` under `GET_LOCK('talenthub:schema_migrations', 30)` and records `(version, name, checksum, batch, execution_time_ms)` in `schema_migrations`.
- Re-running an already-applied version is a no-op (`validateState()` checks checksum/name drift).
- `20260818000100::isReversible() === false`; `down()` throws — rollback is not available for this migration.

---

## 3. Affected Tables

Only the four seed-target tables are written by the catalog seeder. The other four tables created by the migration are **not** written by this seed (they are for attempts/answers/results at runtime).

| # | Table | Written by catalog seed | Operation |
|---|---|---|---|
| 1 | `talent_tests` | YES | INSERT 12 rows |
| 2 | `test_questions` | YES | INSERT 366 rows |
| 3 | `learner_assessment_versions` | YES | INSERT 12 rows |
| 4 | `learner_assessment_question_versions` | YES | INSERT 366 rows |
| 5 | `test_attempts` | NO | Not touched by catalog seed |
| 6 | `learner_assessment_attempt_metadata` | NO | Not touched by catalog seed |
| 7 | `learner_assessment_answers` | NO | Not touched by catalog seed |
| 8 | `test_results` | NO | Not touched by catalog seed |

PII/consent table `learner_ai_consent_events` is **not** seeded and is **not** a prerequisite for this seed (see Section 22.4).

---

## 4. Expected Inserted Rows

| Table | Expected inserted rows (this seed) | Derivation |
|---|---|---|
| `talent_tests` | **12** | 4 frameworks x 3 bands |
| `test_questions` | **366** | 90 + 96 + 84 + 96 (see Section 4.1) |
| `learner_assessment_versions` | **12** | One version per `talent_tests` row |
| `learner_assessment_question_versions` | **366** | One binding per `test_questions` row |

### 4.1 Per-framework breakdown

| Framework | Per-band | Bands | Subtotal |
|---|---|---|---|
| Holland | 30 | 3 | 90 |
| MBTI | 32 | 3 | 96 |
| DISC | 28 | 3 | 84 |
| Multiple Intelligence | 32 | 3 | 96 |
| **Total** | — | **12 catalogs** | **366** |

Row counts were re-counted from the 12 real catalog files (`count($catalog['questions'])`). No catalog deviates.

---

## 5. Stable Identity

| Concern | Contract |
|---|---|
| `talent_tests.id` / `test_questions.id` / `learner_assessment_versions.id` / `learner_assessment_question_versions.id` | Canonical hexadecimal UUID (`TalentHub\Support\Uuid::isValid()` — `^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`, case-insensitive). No semantic prefix is permitted (e.g. `holland-...` is invalid). `Uuid::orFail()` lowercases on accept. |
| Semantic identity for questions | `test_questions.code` (e.g. `holland_middle_r_001`, `mbti_high_jp_p_016`). Unique within `(testId, code)`. Stable and immutable after publication. Used for review, manifest, and idempotency. |
| UUID role | Opaque primary key only. Content hash and semantic `code` carry identity semantics; changing a UUID must not change the canonical schema hash (see Section 10). |
| Question code namespace | `{framework}_{band}_` (e.g. `holland_middle_`, `multiple_intelligence_college_`). Enforced by validator (`stable_code_namespace` check). |

All 366 question `id` values and 366 `code` values are unique — verified by `tests/learner_catalog_cross_consistency_test.php` and re-verified in this DCR (Section 12 / Appendix B).

---

## 6. Insert-Only Policy

- The catalog seeder is **INSERT-only**. It must not issue `UPDATE` or `DELETE` against any seeded row.
- Seeded question `content` / `optionsJson` / `dimensionCode` / `required` / `position` are **frozen at version creation time**. No row update after `published` is permitted by the seed path.
- `learner_assessment_versions` rows with `status = 'published'` are **immutable** after commit. No post-publish mutation of `scoringVersion`, `schemaHash`, `publishedAt`, or question bindings via the seeder.
- `test_attempts` / `learner_assessment_attempt_metadata` / `learner_assessment_answers` / `test_results` history is **never** rewritten. Old attempts remain bound to their original `versionId` indefinitely.
- `ARCHIVE` is **not** part of the seed transaction. Archiving a version is a separately approved operational state transition (`published` -> `archived`) with its own approval — never executed by `AbstractCatalogSeeder` / `AssessmentCatalogMasterSeeder` (see Sections 14–15).
- If the seeder encounters a state that would require an UPDATE/DELETE to proceed, it must **fail closed**.

---

## 7. Transaction Boundary

- **One transaction per catalog.** Each of the 12 catalogs is seeded inside its own database transaction.
- **Order inside a catalog transaction (after all in-memory hash/content checks pass):**
  1. `talent_tests` — one row (`status = 'published'`)
  2. `test_questions` — N rows (`status = 'published'`)
  3. `learner_assessment_versions` — one row (`status = 'published'`, `publishedAt = NOW(6)`, `schemaHash = <canonical hash>`)
  4. `learner_assessment_question_versions` — N rows
  5. Foreign-key / count / hash checks pass, then **COMMIT**. No post-commit status update is part of the seed.
- **Failure handling:**
  - If any check fails **before commit**, the transaction for that catalog is **rolled back**. No partial rows from that catalog remain.
  - A catalog that has already **committed** is **never deleted or rewritten** on a later catalog failure. Subsequent catalogs may be retried independently.
  - Rerun semantics are defined in Section 11 (idempotency / fail-closed on hash mismatch).

---

## 8. Preflight Checks (must all pass before any transaction begins)

The seeder must fail closed (no transaction opened) if any preflight fails. Required checks:

| # | Check | Contract | On failure |
|---|---|---|---|
| 1 | Schema and all 8 tables exist | `information_schema.tables` contains all 8 names in `DATABASE()` | Fail closed |
| 2 | Prerequisite migration applied | `20260818000100` present in `schema_migrations` with matching `checksum`/`name`; `MigrationRunner::validateState()` clean | Fail closed |
| 3 | Session time zone | `SELECT @@session.time_zone = '+00:00'` | Fail closed |
| 4 | Unique constraints match contract | DB introspection: `uq_talent_tests_code` on `(code)`, `uq_test_questions_test_code` on `(testId, code)`, `uq_learner_assessment_versions_test_version` on `(testId, version)`, `uq_learner_assessment_question_versions_version_question` on `(versionId, questionId)`, `uq_learner_assessment_question_versions_version_position` on `(versionId, position)` plus `CHECK`/`JSON_VALID` as in migration | Fail closed |
| 5 | No conflicting published version | No existing `learner_assessment_versions` row for same `(testId, version)` with different `schemaHash`/`scoringVersion` | Fail closed (see Section 11) |
| 6 | UUID / code identity consistency | If a `test_questions.id` or `(testId, code)` already exists, compare the stored row with the incoming question using the **per-question fingerprint** defined in Section 11.2. Do **not** compare a per-question fingerprint to the whole-catalog `schemaHash`. The UUID, stable code, content, decoded `optionsJson`, `dimensionCode`, `required`, and `position` must all match; otherwise fail closed | Fail closed |
| 7 | Canonical hash matches declared `schema_hash` | `computeCanonicalSchemaHash(questions) === metadata.schema_hash` for each catalog (validator contract) | Fail closed |
| 8 | Scoring version contract | `scoringVersion` in `learner_assessment_versions` matches `ScorerRegistry` (`holland-riasec-1.0`, `mbti-education-1.0`, `disc-education-1.0`, `multiple-intelligence-1.0`) | Fail closed |
| 9 | Consent table not required | Do not gate on `privacy_consents`; if consent is checked at runtime it is `learner_ai_consent_events` per plan Section 2.1 | Do not block on `privacy_consents` |

Item 6 is the idempotency / hash-mismatch guard: same identity + same per-question fingerprint and same catalog `schemaHash` -> no-op; any identity, per-question fingerprint, scoring-version, or catalog-hash mismatch -> fail closed (Section 11). The per-question fingerprint is computed in memory and is not an additional database column.

---

## 9. Dry-Run

### 9.1 Rules

- Dry-run must be on a **newly created disposable MySQL 8.4** database (e.g. `talenthub_assessment_catalog_verify_20260818`), **never** on `talenthub_local`.
- The disposable database is created with the same charset/collation (`utf8mb4` / `utf8mb4_unicode_ci`), no application data, and is dropped after evidence capture.
- The existing `talenthub_assessment_catalog_verify` backup, if any, must **not** be used as evidence for a "newly created" disposable database unless its schema, ownership, and creation time are explicitly re-verified.

### 9.2 Required commands (to be run when MySQL is available)

```powershell
# Windows PowerShell / Laragon. Requires CREATE DATABASE privilege.
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$dbName = 'talenthub_assessment_catalog_verify_20260819'

# 1. Create the disposable database only (never talenthub_local).
& $mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u talenthub_app -e "CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { throw 'Disposable database creation failed.' }

# 2–3. Run migration and master seeder with DB_DATABASE scoped to this process.
$previousDatabase = $env:DB_DATABASE
$env:DB_DATABASE = $dbName
try {
    & $php 'bin\migrate.php' migrate
    if ($LASTEXITCODE -ne 0) { throw 'Disposable migration failed.' }

    & $php 'Database\seeds\learner\AssessmentCatalogMasterSeeder.php'
    if ($LASTEXITCODE -ne 0) { throw 'Disposable catalog seed failed.' }
} finally {
    if ($null -eq $previousDatabase) {
        Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue
    } else {
        $env:DB_DATABASE = $previousDatabase
    }
}

# 4. Capture post-seed counts, schema hashes, spot checks and before/after evidence
#    (see Sections 12–13 and Appendix C).

# 5. After evidence is retained, drop only the disposable database.
if ($dbName -eq 'talenthub_local') { throw 'Refusing to drop the target database.' }
& $mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u talenthub_app -e "DROP DATABASE IF EXISTS $dbName;"
if ($LASTEXITCODE -ne 0) { throw 'Disposable database cleanup failed.' }
```

If `talenthub_app` lacks `CREATE DATABASE`, the fallback is a DBA-created disposable database with a grant to `talenthub_app`, or a local `root` creation for the disposable name only.

### 9.3 Evidence and acceptance criteria

| Evidence | How captured | Acceptance |
|---|---|---|
| Disposable DB creation proof | `SHOW DATABASES` / `CREATE DATABASE` output, timestamp | Newly created, empty, `utf8mb4_unicode_ci` |
| Migration on disposable | `bin/migrate.php` output + `schema_migrations` rows | `20260818000100` applied exactly once |
| Seeder log | Master seeder stdout (per-catalog progress) | 12 catalogs committed, 0 failures |
| `SELECT COUNT(*)` per table | Four counts | 12 / 366 / 12 / 366 exactly |
| Schema hash check | `SELECT schemaHash` vs DCR Section 10 | All 12 match exactly |
| Spot checks | 3 questions per catalog (Section 13) | UUID, code, content, dimensionCode, position, required all match source |

### 9.4 Status in this DCR

**NOT EXECUTED — MYSQL UNAVAILABLE.** MySQL at `127.0.0.1:3306` was unreachable on 2026-08-19 (PDO `[2002]`). No disposable database was created, no migration was run, and no seeder was executed. The table in Appendix C records `NOT EXECUTED` for every disposable-DB evidence row. The `talenthub_local` CREATE/DROP probe also could not be executed, so the `talenthub_app` `CREATE DATABASE` privilege remains **unknown** (recorded as `BLOCKED` where a DB write would be required).

---

## 10. Canonical Schema Hashes

### 10.1 Canonicalization contract

Per `tests/learner_catalog_content_validator.php::computeCanonicalSchemaHash()` and plan Section 2.7:

- Input: ordered list of `questions` for one catalog.
- Sort by `position` ascending.
- Per question, emit keys in fixed order: `code`, `content`, `options`, `dimension_code`, `required`, `position`.
- Per option, emit keys in fixed order: `value`, `label`.
- Serialize with `json_encode(..., JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` (UTF-8, stable key order, no escaping of unicode/slashes).
- `hash('sha256', json)` -> 64-char lowercase hex.
- **UUID (`id`) is excluded** from the hash — changing only the opaque UUID does not change `schemaHash`.
- Deterministic: same ordered dataset always yields the same hash; any change to `content`, `options`, `dimension_code`, `required`, `position`, or `code` changes the hash.

### 10.2 Hashes recomputed in this DCR

Hashes were recomputed on 2026-08-19 by `LearnerCatalogContentValidator::computeCanonicalSchemaHash()` from the 12 real catalog files on disk. Each computed hash matched the `metadata.schema_hash` declared inside the source file.

| Catalog (key) | Questions | Declared `metadata.schema_hash` | Recomputed (`computeCanonicalSchemaHash`) | Match |
|---|---|---|---|---|
| `holland_middle` | 30 | `621c22668bfef5ec6f4b9a9d2280be7d0a7510e68ee6169acbbeba1085a7753e` | `621c22668bfef5ec6f4b9a9d2280be7d0a7510e68ee6169acbbeba1085a7753e` | OK |
| `holland_high` | 30 | `4ea66cb4c84d548a7b732f6b02c1374c480f8f95bcc345d8ce5f15a283b73e5d` | `4ea66cb4c84d548a7b732f6b02c1374c480f8f95bcc345d8ce5f15a283b73e5d` | OK |
| `holland_college` | 30 | `6d6053595252aaeba977f0a3665cf67c30695116615a4a1fe8aa8ddb7c9d3e06` | `6d6053595252aaeba977f0a3665cf67c30695116615a4a1fe8aa8ddb7c9d3e06` | OK |
| `mbti_middle` | 32 | `5f29ea506b397ab6690bbe77a684d949e4ce55239c99927cec91ef1af0e72779` | `5f29ea506b397ab6690bbe77a684d949e4ce55239c99927cec91ef1af0e72779` | OK |
| `mbti_high` | 32 | `1800b735342c15160e0f37fd1bf17b9137471ec601eca2a05b798955d79592a4` | `1800b735342c15160e0f37fd1bf17b9137471ec601eca2a05b798955d79592a4` | OK |
| `mbti_college` | 32 | `0b4a50b864adc33ab158e7af4390b30ed816eba259a5ab8caba6526525cb231f` | `0b4a50b864adc33ab158e7af4390b30ed816eba259a5ab8caba6526525cb231f` | OK |
| `disc_middle` | 28 | `0e9b5654ed9f237baf47698976cb98e1db0c454574b47ed77b936b0299527955` | `0e9b5654ed9f237baf47698976cb98e1db0c454574b47ed77b936b0299527955` | OK |
| `disc_high` | 28 | `773a1bd161c5d3ff300ff6be6eb7b1b4f33254143ca792774b0e1da53433cd78` | `773a1bd161c5d3ff300ff6be6eb7b1b4f33254143ca792774b0e1da53433cd78` | OK |
| `disc_college` | 28 | `42749a295bf54d13b25b298eed9addbcbf610646bf3f36a40f9fa61e057dbf32` | `42749a295bf54d13b25b298eed9addbcbf610646bf3f36a40f9fa61e057dbf32` | OK |
| `multiple_intelligence_middle` | 32 | `e21f50e792591ac8a054622acb1854792ac89cf9cd876106861d0e796be6453a` | `e21f50e792591ac8a054622acb1854792ac89cf9cd876106861d0e796be6453a` | OK |
| `multiple_intelligence_high` | 32 | `31c3f12e5a9315f862e344cad37a6421bf661cfa138b7fca8fb2b86ca85763e1` | `31c3f12e5a9315f862e344cad37a6421bf661cfa138b7fca8fb2b86ca85763e1` | OK |
| `multiple_intelligence_college` | 32 | `cd59f7fb4e9573b6857dc193091c16e425497fcf8f2bf82f7329d3a48816a212` | `cd59f7fb4e9573b6857dc193091c16e425497fcf8f2bf82f7329d3a48816a212` | OK |

All 12 hashes are recorded in the Catalog Matrix (Appendix A) and are the **expected `learner_assessment_versions.schemaHash`** after seed.

---

## 11. Duplicate Prevention and Idempotency

### 11.1 Unique constraints (from migration DDL)

| Constraint | Table | Columns | Purpose |
|---|---|---|---|
| `uq_talent_tests_code` | `talent_tests` | `(code)` | One row per `testCode` |
| `uq_test_questions_test_code` | `test_questions` | `(testId, code)` | Stable question code unique per test |
| `uq_learner_assessment_versions_test_version` | `learner_assessment_versions` | `(testId, version)` | One version number per test |
| `uq_learner_assessment_question_versions_version_question` | `learner_assessment_question_versions` | `(versionId, questionId)` | One binding per (version, question) |
| `uq_learner_assessment_question_versions_version_position` | `learner_assessment_question_versions` | `(versionId, position)` | Position unique per version |

Plus `PRIMARY KEY (id)` on all four tables and FKs with `ON DELETE RESTRICT`.

### 11.2 Idempotency rules

#### 11.2.1 Per-question fingerprint

The catalog `schemaHash` covers the complete ordered question set and must remain
separate from the identity check for one existing `test_questions` row. The
seeder computes the following in-memory fingerprint for each incoming question:

```text
questionFingerprint = SHA-256(
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES of {
    "code": question.code,
    "content": question.content,
    "options": question.options,
    "dimension_code": question.dimension_code,
    "required": question.required,
    "position": question.position
  }
)
```

For an existing database row, reconstruct the same object from `code`,
`content`, decoded `optionsJson`, `dimensionCode`, `required`, and `position`,
then compare fingerprints. The seeder must also compare the canonical UUID
from the manifest. This fingerprint is not persisted as a new column and must
never be compared directly with the whole-catalog `schemaHash`.

- **Rerun with the same catalog `schemaHash`, scoring version, version key, UUID manifest and per-question fingerprints:** the seeder must treat it as a **no-op** — no new rows, no error, no duplicate. The second run succeeds with `0 rows inserted` for already-seeded catalogs.
- **Rerun with the same `(testCode, version, questionCode)` but a different catalog `schemaHash`, scoring version, UUID, or per-question fingerprint:** the seeder must **fail closed** (throw / abort). No `UPDATE` is issued to "fix" the row; the operator must publish a **new version** (Section 16) if corrected content is needed.
- **Rerun where a UUID already exists but maps to a different `code` or per-question fingerprint:** **fail closed**.
- **Partial seed:** catalogs that already committed remain; only uncommitted catalog transactions are retried.

### 11.3 Seeder-side enforcement

Task 10 seeder (`AbstractCatalogSeeder` / `AssessmentCatalogMasterSeeder`) must enforce the rules in Section 11.2 **before** opening a write transaction (read the existing rows, compare hashes, decide no-op vs fail-closed). DB unique keys are the final backstop, not the primary logic.

---

## 12. Post-Seed Expected Counts

After a successful full seed (all 12 catalogs committed):

| Table | Expected `COUNT(*)` | Filter |
|---|---|---|
| `talent_tests` | **12** | `code IN (12 test codes)` — no other test codes introduced by this seed |
| `test_questions` | **366** | All 366 question codes present |
| `learner_assessment_versions` | **12** | `status = 'published'` for all 12 |
| `learner_assessment_question_versions` | **366** | One per question; `position` 1..N per version |

Per-catalog expected counts (same as Section 4.1):

| Catalog | `test_questions` | `learner_assessment_question_versions` | `learner_assessment_versions` |
|---|---|---|---|
| `holland_middle` | 30 | 30 | 1 |
| `holland_high` | 30 | 30 | 1 |
| `holland_college` | 30 | 30 | 1 |
| `mbti_middle` | 32 | 32 | 1 |
| `mbti_high` | 32 | 32 | 1 |
| `mbti_college` | 32 | 32 | 1 |
| `disc_middle` | 28 | 28 | 1 |
| `disc_high` | 28 | 28 | 1 |
| `disc_college` | 28 | 28 | 1 |
| `multiple_intelligence_middle` | 32 | 32 | 1 |
| `multiple_intelligence_high` | 32 | 32 | 1 |
| `multiple_intelligence_college` | 32 | 32 | 1 |

Verification query pattern (to be run on the disposable DB and, after approval, on `talenthub_local`):

```sql
SELECT 'talent_tests', COUNT(*) FROM talent_tests
UNION ALL SELECT 'test_questions', COUNT(*) FROM test_questions
UNION ALL SELECT 'learner_assessment_versions', COUNT(*) FROM learner_assessment_versions
UNION ALL SELECT 'learner_assessment_question_versions', COUNT(*) FROM learner_assessment_question_versions;

SELECT code, COUNT(*) AS q FROM test_questions JOIN talent_tests ON test_questions.testId = talent_tests.id GROUP BY code ORDER BY code;
SELECT talent_tests.code AS test_code, learner_assessment_versions.version, learner_assessment_versions.scoringVersion, learner_assessment_versions.schemaHash, learner_assessment_versions.status FROM learner_assessment_versions JOIN talent_tests ON learner_assessment_versions.testId = talent_tests.id ORDER BY test_code;
```

No post-seed count is asserted as observed in this DCR because no seed has been executed (see Appendix C).

---

## 13. Spot Checks (3 per catalog — 36 total)

Spot checks must verify **UUID, stable `code`, `content`, `dimensionCode`, `position`, and `required`** against the source catalog file. The rows below are the **expected** values for the first / middle / last question of each catalog (source of truth: the 12 catalog PHP files).

| # | Catalog | Pos | UUID (lowercase) | Code | dimensionCode | required | Content (excerpt) |
|---|---|---|---|---|---|---|---|
| 1 | `holland_middle` | 1 | `6c894f00-208d-465a-b8c7-aac88f23665f` | `holland_middle_r_001` | `R:+` | true | Ban thich lap rap va sua chua do vat trong nha. |
| 2 | `holland_middle` | 15 | `17375e5f-4990-4b52-88e6-26abfe848bcb` | `holland_middle_a_015` | `A:+` | true | Ban thich tu trang tri goc hoc tap cua minh. |
| 3 | `holland_middle` | 30 | `81a92de1-7b6d-452a-a06f-df6e34b63abc` | `holland_middle_c_030` | `C:-` | true | Ban it khi kiem tra lai bai truoc khi nop. |
| 4 | `holland_high` | 1 | `416dbd91-8553-40fb-99ed-bf7d673133c3` | `holland_high_r_001` | `R:+` | true | Ban thich tim hieu cach may moc va thiet bi hoat dong. |
| 5 | `holland_high` | 15 | `4f0a13b4-4d84-4310-9f67-600d3ff8d1f8` | `holland_high_a_015` | `A:+` | true | Ban thich tao ra san pham mang dau an ca nhan. |
| 6 | `holland_high` | 30 | `41a193f8-029b-414f-8a26-4caf8c63ec2e` | `holland_high_c_030` | `C:-` | true | Ban it khi ghi chu va kiem tra lai thong tin can than. |
| 7 | `holland_college` | 1 | `8cc66ca8-508b-4e19-a8f4-e7d5c51659f2` | `holland_college_r_001` | `R:+` | true | Ban thich van hanh, bao tri may moc va thiet bi ky thuat trong thuc te. |
| 8 | `holland_college` | 15 | `31118899-36c9-46a2-9564-9fede8802464` | `holland_college_a_015` | `A:+` | true | Ban muon tao ra san pham mang phong cach va dau an ca nhan. |
| 9 | `holland_college` | 30 | `aa29dbe4-0e2c-4c7e-a871-48fb7d454653` | `holland_college_c_030` | `C:-` | true | Ban it khi kiem tra va doi chieu thong tin truoc khi hoan thanh cong viec. |
| 10 | `mbti_middle` | 1 | `030d54b1-bcc1-4f3b-7bd4-9077fc8c7aea` | `mbti_middle_ei_e_001` | `EI:E` | true | Ban thich trao doi va hoc cung cac ban trong lop. |
| 11 | `mbti_middle` | 16 | `26f80f6b-6d48-4cc9-0cb4-d7f3dece70d6` | `mbti_middle_jp_p_016` | `JP:P` | true | Ban de dang thich nghi khi ke hoach hoc tap thay doi. |
| 12 | `mbti_middle` | 32 | `799c02f2-22c1-4608-c2ab-e88676f83f14` | `mbti_middle_jp_p_032` | `JP:P` | true | Ban cam thay thoai mai khi xu ly cac viec bat ngo nay sinh. |
| 13 | `mbti_high` | 1 | `bf142c0f-0543-4a36-f45e-79b8648e39c9` | `mbti_high_ei_e_001` | `EI:E` | true | Ban nap lai nang luong khi cung ban be thao luan cac chu de hoc tap thu vi. |
| 14 | `mbti_high` | 16 | `f3ccc878-10a0-457c-b66c-5b7e64b1b66c` | `mbti_high_jp_p_016` | `JP:P` | true | Ban phat huy kha nang tot khi xu ly nhung tinh huong phat sinh vao phut chot. |
| 15 | `mbti_high` | 32 | `97e2ff5c-b89b-4936-2701-43aa81d24bd6` | `mbti_high_jp_p_032` | `JP:P` | true | Ban san sang thay doi phuong phap lam viec khi xuat hien y tuong moi me hon. |
| 16 | `mbti_college` | 1 | `3e84a57c-29cd-4e97-bb89-77e62461535d` | `mbti_college_ei_e_001` | `EI:E` | true | Ban chu dong thao luan hoc thuat va trao doi chuyen de cung giang vien va ban hoc trong khoa. |
| 17 | `mbti_college` | 16 | `468f04a6-f116-49b5-dacd-f511d555aa79` | `mbti_college_jp_p_016` | `JP:P` | true | Ban thich ung nhanh va lam viec hieu qua trong moi truong hoc tap co tinh bien dong cao. |
| 18 | `mbti_college` | 32 | `b6baf9ec-8f9f-46f1-674e-38185fe49351` | `mbti_college_jp_p_032` | `JP:P` | true | Ban giu tu duy mo va linh hoat don nhan nhung huong nghien cuu phat sinh trong thuc te. |
| 19 | `disc_middle` | 1 | `5a6faca2-f10c-4f5e-98c4-a427f305ce2f` | `disc_middle_d_001` | `D:+` | true | Ban thich xung phong lam nhom truong khi lam bai tap. |
| 20 | `disc_middle` | 14 | `7870a3fc-167c-44d3-a3b5-fa4f6ca769a8` | `disc_middle_i_014` | `I:+` | true | Ban de dang lam quen va ket ban voi nguoi moi. |
| 21 | `disc_middle` | 28 | `6555ba78-2815-49ea-b10d-ebe2fa8dad57` | `disc_middle_c_028` | `C:-` | true | Ban thuong bo qua cac chi tiet nho khi lam bai tap. |
| 22 | `disc_high` | 1 | `dfd6a752-8454-47e3-a9d7-350f63a7324a` | `disc_high_d_001` | `D:+` | true | Ban chu dong nhan vai tro dieu phoi khi nhom lam du an hoc tap. |
| 23 | `disc_high` | 14 | `e9650d7b-4a9a-49cd-943c-b776bf587688` | `disc_high_i_014` | `I:+` | true | Ban de dang ket noi va tao thien cam voi cac ban moi trong truong. |
| 24 | `disc_high` | 28 | `d0ec66c3-563a-46b4-a6b6-4e6cd7b90f77` | `disc_high_c_028` | `C:-` | true | Ban thuong bo qua cac tieu chi phu trong bang danh gia bai tap. |
| 25 | `disc_college` | 1 | `2615d8a2-4f1e-44cb-ba17-9d7669c2df46` | `disc_college_d_001` | `D:+` | true | Ban chu dong dan dat va phan chia dau viec khi nhom thuc hien do an hoc phan. |
| 26 | `disc_college` | 14 | `d887e2d1-36ab-4fc7-9679-093ea0f52960` | `disc_college_i_014` | `I:+` | true | Ban tu tin xay dung moi quan he hop tac voi giang vien va ban hoc cung khoa. |
| 27 | `disc_college` | 28 | `74ed3428-d84d-4bb2-82aa-cc563b4c7558` | `disc_college_c_028` | `C:-` | true | Ban thuong xem nhe cac tieu chi phu luc va tai lieu dinh kem khi nop bai. |
| 28 | `multiple_intelligence_middle` | 1 | `bcacf1df-300b-4916-a83a-4d471525afa1` | `multiple_intelligence_middle_ling_001` | `LING:+` | true | Ban thich doc truyen va viet nhat ky moi ngay. |
| 29 | `multiple_intelligence_middle` | 16 | `fc3baf79-f4f8-46c5-9edc-e1167054162b` | `multiple_intelligence_middle_nat_016` | `NAT:-` | true | Ban it quan tam den viec cham soc cay coi trong vuon. |
| 30 | `multiple_intelligence_middle` | 32 | `89d8e0bf-27d0-4917-ae4a-e33bc3dd0365` | `multiple_intelligence_middle_nat_032` | `NAT:-` | true | Ban it chu y den nhung bien doi cua thoi tiet va tu nhien. |
| 31 | `multiple_intelligence_high` | 1 | `d3033283-f16b-4c6b-adda-7d6c61b9e919` | `multiple_intelligence_high_ling_001` | `LING:+` | true | Ban thich doc sach chuyen de va viet bai luan bay to quan diem. |
| 32 | `multiple_intelligence_high` | 16 | `d01618a4-00a6-4660-8e8a-4929417273d5` | `multiple_intelligence_high_nat_016` | `NAT:-` | true | Ban it co hung thu voi viec tim hieu quy luat sinh truong sinh vat. |
| 33 | `multiple_intelligence_high` | 32 | `fecfe6f8-a96b-4d5c-9465-67cf388dc1c6` | `multiple_intelligence_high_nat_032` | `NAT:-` | true | Ban it quan tam den viec phan loai cay coi hay hien tuong khi tuong. |
| 34 | `multiple_intelligence_college` | 1 | `a30728ff-5109-47e0-adf9-c379c4421d47` | `multiple_intelligence_college_ling_001` | `LING:+` | true | Ban thanh thao trong viec soan thao bao cao nghien cuu va tong hop tai lieu chuyen sau. |
| 35 | `multiple_intelligence_college` | 16 | `03095d4b-7c86-4944-8a35-33e9c8d52b59` | `multiple_intelligence_college_nat_016` | `NAT:-` | true | Ban khong may hung thu voi cac nghien cuu thuc dia ve tai nguyen sinh hoc va dia ly tu nhien. |
| 36 | `multiple_intelligence_college` | 32 | `456ccbd3-4db7-4baa-9713-fdae4bc1177a` | `multiple_intelligence_college_nat_032` | `NAT:-` | true | Ban it theo doi cac tin tuc hoac bao cao khoa hoc lien quan den da dang sinh hoc. |

Notes:

- `content` is stored in Vietnamese with UTF-8. The excerpt column above uses ASCII-safe transliteration for table rendering; the canonical source is the catalog PHP file (UTF-8). Verification must compare against the file’s `content` string verbatim (UTF-8, `mb_strlen` semantics).
- `options` for every question is the fixed Likert 5 set (`Hoan toan khong dong y` .. `Hoan toan dong y`). The seeder serializes it as `optionsJson`.
- The full 366-row manifest with every UUID/code is in Appendix B. The 36 spot-check rows are a subset of that manifest.
- Spot checks on the disposable DB are **NOT EXECUTED** in this DCR (MySQL unavailable). The table above is the **EXPECTED** set.

---

## 14. Disable / Retire Strategy

| Concern | Contract |
|---|---|
| `talent_tests.status` | `draft` / `published` / `retired`. Catalogs are seeded as `published` for the `published` version; the test row itself remains `published` while any published version is active. |
| `test_questions.status` | `draft` / `published` / `retired`. Seeded questions are `published`. A question that is superseded is not updated in place — a new question row with a new `id`/`code` is introduced under a new version. |
| `learner_assessment_versions.status` | `draft` / `published` / `archived`. Seeded versions are `published`. See Section 15. |
| Disable without deleting | To disable a catalog for new attempts, the **version** row is transitioned `published -> archived` (Section 15). No rows are deleted. |
| Important distinction | `retired` (on `talent_tests` / `test_questions`) and `archived` (on `learner_assessment_versions`) are **different status domains on different tables**. Do not conflate them. The DCR does not unify them. |

Disabling `talent_tests` (`retired`) is only for full test retirement and requires its own approval. Version-level disable (`archived`) is the normal path for catalog corrections.

---

## 15. Archive Strategy

- The catalog **seed never archives**. The insert-only seeder has no path that sets `status = 'archived'` or restores/rewrites a `published` version.
- Archiving is a **separately approved operational state transition**, outside the seed transaction, and is only considered **after a corrected `published` version exists** (see Section 16).
- The archive transition is:

```sql
-- Controlled archive of an erroneous version (requires Product Owner + Codex approval)
UPDATE learner_assessment_versions
SET status = 'archived'
WHERE id = :version_id;
```

or, for the conventional versioned-by-test lookup:

```sql
UPDATE learner_assessment_versions
SET status = 'archived'
WHERE testId = (SELECT id FROM talent_tests WHERE code = :test_code)
  AND version = :old_version;
```

- Archived versions are **never selected for new attempts** (the application selects `MAX(version)` where `status = 'published'`).
- Old submitted attempts remain bound to their original `versionId` forever — archiving does not move history.
- If a catalog contains harmful content, see **Emergency Disable** (Section 14 / Section 17 of the plan) — same `UPDATE ... SET status = 'archived'` but with emergency authorization and a 24-hour notification to Product Owner + Codex. The emergency path still preserves all rows.

---

## 16. Roll-Forward Strategy

When corrected content is needed (typo, mistranslation, safety fix), the correction is a **new version**, not an in-place edit:

1. Author the corrected catalog content as a new dataset with a new `schemaHash` and incremented `learner_assessment_versions.version` (e.g. `1.0.0` -> `1.1.0`).
2. Run `tests/learner_catalog_content_validator.php`, `tests/learner_catalog_scorer_integration_test.php`, and `tests/learner_catalog_cross_consistency_test.php` against the corrected content.
3. Complete all review checkpoints (content, educational, bias/safety, scoring, Product Owner, Codex) for the corrected version.
4. Create a new DCR (or DCR addendum) for the new version with its new `schemaHash` and manifest delta.
5. Seed the new version via the same insert-only path — it inserts a new `learner_assessment_versions` row + new bindings (and new `test_questions` rows only if question identity changes; reused `questionId` is allowed if the question content truly is unchanged and hash-consistent).
6. After the new `published` version is verified, **then** consider archiving the old version per Section 15 (separately approved).
7. Existing attempts continue to point at the old `versionId`; new attempts automatically use the newest `published` version. No history rewrite.

---

## 17. Backup Evidence Before / After Disposable Dry-Run

### 17.1 Required artifacts

| Artifact | When | How | Status in this DCR |
|---|---|---|---|
| Snapshot before seed | After migration, before seeder on disposable DB | `mysqldump` or `SELECT COUNT(*)` + `SHOW CREATE TABLE` + `schema_migrations` dump | **NOT EXECUTED — MYSQL UNAVAILABLE** |
| Snapshot after seed | Immediately after seeder commit on disposable DB | Same as above + per-table `COUNT(*)` + `schemaHash` dump | **NOT EXECUTED — MYSQL UNAVAILABLE** |
| Seeder stdout log | During seed | Captured stdout/stderr with timestamp | **NOT EXECUTED** |
| Hash evidence | Before seed | `computeCanonicalSchemaHash` output per catalog (Section 10) | **EXECUTED** — hashes in Section 10 / Appendix A |

### 17.2 What is not evidence

- Any pre-existing backup file (e.g. an AI-related dump) **not** created as the before/after pair around the disposable dry-run is **not** evidence for that dry-run.
- `talenthub_local` counts are **not** disposable-DB evidence.

### 17.3 Retention

Disposable-DB evidence must be attached to the DCR addendum after the dry-run is executed (Section 19 / Appendix C).

---

## 18. Approvals

| Role | Responsibility | Approval artifact | Status |
|---|---|---|---|
| **Product Owner** (`NCnguyenn`) | Question-count decision (Option A/B/C), content approval, DCR approval, archive/roll-forward approval if needed | Signed review event / DCR sign-off with UTC timestamp | **PENDING** |
| **Codex** (schema / contract reviewer) | Schema/migration contract, dimension/scoring contract, DCR review, seeder idempotency/archive boundary | Codex review sign-off | **PENDING** |
| Educational reviewer | Age-appropriateness per band | Per-catalog review event | **PENDING** (all 12 catalogs `review_state = draft`, `review_events = []`) |
| Bias / Safety reviewer | No protected-group / stereotype / harmful content | Per-catalog review event | **PENDING** |
| Scoring reviewer | Scorer integration, dimension balance | Validator evidence | **PENDING** (validators pass on synthetic and real catalogs, but review_events not yet recorded) |

Rules:

- The DCR is **not approved** until both Product Owner and Codex have explicitly recorded approval with reviewer identity and UTC timestamp (per `review_events` contract: `checkpoint`, `reviewer`, `approved_at_utc` in UTC `Z` / `+00:00`).
- `review_state = published` is rejected by the validator unless all six checkpoints in `REVIEW_CHECKPOINTS` are present (`content_review`, `educational_review`, `bias_review`, `scoring_review`, `product_owner_approval`, `codex_schema_review`).
- **No approval is fabricated by this DCR.** All approval placeholders remain `PENDING` until real sign-off exists.

Approval block (to be filled with real approvals only):

```yaml
approvals:
  product_owner:
    reviewer: null          # PENDING — NCnguyenn
    approved_at_utc: null   # PENDING
    decision: null          # PENDING — Option A/B/C
  codex_schema_contract:
    reviewer: null          # PENDING — Codex
    approved_at_utc: null   # PENDING
  dcr_status: PENDING
```

---

## 19. Permitted Execution Window and Safety Conditions

The catalog seed may only be executed when **all** of the following are true:

1. This DCR is **reviewed and approved** by Product Owner **and** Codex (Section 18 `PENDING` -> `APPROVED`).
2. All 12 catalogs have `review_state` beyond `draft` with all required `review_events` recorded and validated (currently `draft` / `[]` — see Section 22.2).
3. Prerequisite migration `20260818000100` is **verified applied** on the target database (`talenthub_local`) via a live `MigrationRunner::status()` check. The OBSERVED status in Section 2.4 must be re-checked live — the DCR does not assume it.
4. The assessor `educational_review` / `bias_review` / `scoring_review` gates in the plan (Sections 10–11) are complete for all 12 catalogs.
5. `TALENTHUB_AI_VISIBLE_PERCENT` remains `0` (deterministic Rule/Scoring baseline; no model-visible rollout).
6. A **disposable MySQL 8.4 dry-run has been executed** per Sections 9/17 with evidence captured and attached to this DCR (currently `NOT EXECUTED` — MySQL unavailable).
7. A **full backup of `talenthub_local`** has been taken immediately before seeding `talenthub_local` (Task 13 prerequisite).
8. Execution is **not during peak hours** and is performed by an operator with `INSERT` privilege on the four target tables and `SELECT` on `schema_migrations`/`information_schema`.
9. The operator uses the **disposable-DB-validated seeder** (Task 10 artifact) and the **canonical hashes in Section 10** — no ad-hoc content edits at seed time.
10. Post-seed checks (Sections 12–13) are run immediately after commit and before announcing availability.

If any condition is not met, the seed is **BLOCKED**.

---

## 20. Post-Seed Verification (to be run on disposable DB and, after approval, on `talenthub_local`)

1. `SELECT COUNT(*)` per table matches Section 12 (12 / 366 / 12 / 366).
2. Per-catalog counts match Appendix A (`question_count` per band).
3. `SELECT schemaHash` per version matches Section 10 / Appendix A (12 hashes).
4. Spot checks (Section 13 — 36 rows) all match source (UUID/code/content/dimensionCode/position/required).
5. Full manifest (Appendix B — 366 rows) present and unique.
6. `learner_assessment_versions.status = 'published'` for all 12; no `archived` introduced by seed.
7. No existing rows outside the 4 target tables were modified (audit via `updatedAt` / `createdAt` sampling if needed).
8. All validator tests pass against the seeded state (where applicable):
   - `tests/learner_catalog_content_validator.php`
   - `tests/learner_catalog_cross_consistency_test.php`
   - `tests/learner_catalog_scorer_integration_test.php`

Results of (1)–(8) must be captured and appended to this DCR’s evidence addendum.

---

## 21. Operational Notes

- **Consent.** Runtime assessment consent is `learner_ai_consent_events` (plan Section 15). The seed does not create consent rows and does not depend on `privacy_consents`.
- **Likert options.** Every question uses the fixed 5-point Likert set (`Hoan toan khong dong y` .. `Hoan toan dong y`) stored as `optionsJson`. `CHECK (JSON_VALID(optionsJson))` enforced by migration.
- **Content language.** All prompts are Vietnamese (UTF-8, `utf8mb4`). Length limits: `middle` <= 60 chars, `high` <= 80, `college` <= 100 (validator and catalog counts confirm compliance).
- **Scoring baseline.** Scorers are deterministic: `HollandScorer`, `MbtiScorer`, `DiscScorer`, `MultipleIntelligenceScorer` via `ScorerRegistry`. Tie-breaks are stable per Section 6 of the cross-consistency test.
- **No destructive SQL.** The seed contains no `DELETE` / `TRUNCATE` / `DROP`. Foreign keys are `ON DELETE RESTRICT`.

---

## 22. Contract Discrepancies — Recorded, Not Silently Fixed

The following discrepancies were found between plan text, catalog files, and runtime contracts during this DCR’s verification. They are recorded here for Product Owner and Codex to resolve. This DCR does not patch the plan or catalogs on its own.

### 22.1 Approval fields inconsistency (Section 7.5)

- The question-count decision shape in the plan (Section 7.5) shows a filled `approved_by: NCnguyenn (Product Owner)` / `approved_at_utc: 2026-08-19T05:38:16Z` but the paragraph immediately after says "Both approval fields remain `null` until the Product Owner records the decision with the real UTC timestamp; no implementation task may assume approval from the recommendation alone."
- **DCR position:** The filled approval block is treated as an **illustrative shape for Option A only**, not as an approval. `approved_by` / `approved_at_utc` remain conceptually `null` pending a real Product Owner decision record. No seeder or DCR approval is inferred from it.
- **Action required:** Product Owner to confirm the selected option (A/B/C) with a real UTC timestamp; Codex to confirm the decision is recorded before seed.

### 22.2 All 12 catalogs are `draft` with empty `review_events`

- **OBSERVED (2026-08-19):** Every catalog reports `metadata.review_state = 'draft'` and `metadata.review_events = []` (re-verified in Section 19 and Appendix A). The validator’s `published` gate (six checkpoints) is therefore not satisfied.
- This means `content_review`, `educational_review`, `bias_review`, `scoring_review`, `product_owner_approval`, and `codex_schema_review` are all **outstanding**.
- **DCR position:** This DCR is **not** an authorization to seed. It documents the current draft state and the remaining gates. The seed is **BLOCKED** until those checkpoints are satisfied and this DCR is approved.

### 22.3 Status contract split: `retired` vs `archived`

- `talent_tests.status` and `test_questions.status` use **`draft` / `published` / `retired`** (`chk_talent_tests_status`, `chk_test_questions_status`).
- `learner_assessment_versions.status` uses **`draft` / `published` / `archived`** (`chk_learner_assessment_versions_status`).
- These are intentionally different status domains on different tables (Section 14). This DCR preserves the split and does not unify the terms. Archive/disable operations must target the correct table and status value.

### 22.4 Consent prerequisite scope

- Plan Section 15 and Section 2.1 define the runtime consent gate as **`learner_ai_consent_events`**. `privacy_consents` is explicitly **not** an assessment seed prerequisite unless a separate code review proves the live assessment endpoint requires it.
- This DCR therefore does **not** list `privacy_consents` as a prerequisite table and does **not** seed consent rows.

---

## 23. Evidence Sources for This DCR

| Source | How verified | Checksum / evidence |
|---|---|---|
| `Database/migrations/20260818000100_create_learner_assessment_schema.php` | `hash_file('sha256', $file)` on 2026-08-20 | `bcda805be57de3a396f036e71cac85a811fa7ff4061a48f77689a3ce0b674f03` |
| `Database/seeds/learner/Assessment/HollandCatalogMiddle.php` | `hash_file('sha256', $file)` | `8c10152d7fe823765cac6d9ddb0148951f47f6af5a4b2c810c65d6b6f08c4a90` |
| `Database/seeds/learner/Assessment/HollandCatalogHigh.php` | `hash_file('sha256', $file)` | `d7e72e45cffe52ca5e97e140b78e9c864c1007e6877236aa1c394579e49906d3` |
| `Database/seeds/learner/Assessment/HollandCatalogCollege.php` | `hash_file('sha256', $file)` | `a6917da1fa87de563a5b495d1450d0cf5f83cd8b861a1f63310bc4b0f93a865c` |
| `Database/seeds/learner/Assessment/MbtiCatalogMiddle.php` | `hash_file('sha256', $file)` | `38c0a3ee654c746c063c378a6e411d0ba7d8068f9e32c695320f6ac946db723d` |
| `Database/seeds/learner/Assessment/MbtiCatalogHigh.php` | `hash_file('sha256', $file)` | `5e989718e33aec096209bc6ed33ba3a2124956158e64986a8bce6c0a96b803cd` |
| `Database/seeds/learner/Assessment/MbtiCatalogCollege.php` | `hash_file('sha256', $file)` | `113982f24d1e6351ede1e640a6c8dca3e0733692aec40dfc11edfe13cc6b6de7` |
| `Database/seeds/learner/Assessment/DiscCatalogMiddle.php` | `hash_file('sha256', $file)` | `36ec6593f28b2579b97e25cf62ccf4d9b5e56b2da5e07f8535efcd9e86a0d995` |
| `Database/seeds/learner/Assessment/DiscCatalogHigh.php` | `hash_file('sha256', $file)` | `ca945ab2fb7ccdab78669fca4b97508e7e815c8215f7032e991cd62f2c518420` |
| `Database/seeds/learner/Assessment/DiscCatalogCollege.php` | `hash_file('sha256', $file)` | `035c7f068cf7b6ff1539c42dd5ba2cef7d492ae470154676ddcb05d7ca0fd85a` |
| `Database/seeds/learner/Assessment/MultipleIntelligenceCatalogMiddle.php` | `hash_file('sha256', $file)` | `dd459455ec9ad41ae693b8715cee4f5e708d64711c637ed79813b5e335d4a557` |
| `Database/seeds/learner/Assessment/MultipleIntelligenceCatalogHigh.php` | `hash_file('sha256', $file)` | `35d237c15cadbc8d7862600769af19678ab7c3d231088d7e419f194784b811db` |
| `Database/seeds/learner/Assessment/MultipleIntelligenceCatalogCollege.php` | `hash_file('sha256', $file)` | `3eef7f747fcdc80c1b700cef17e494cd6e879fa6cc085d2d68635dc817634691` |
| `src/Database/Migration/MigrationRunner.php` | read 2026-08-19 | runner/lock/checksum contract (Section 2.5) |
| `src/Support/Uuid.php` | read 2026-08-19 | `isValid()` regex contract (Section 5) |
| `tests/learner_catalog_content_validator.php` | executed 2026-08-19 (synthetic suite) | 115 assertions OK; canonical hash impl source |
| `tests/learner_catalog_cross_consistency_test.php` | read 2026-08-19 | 8-section, 8.783-assertion cross-catalog suite |
| `tests/learner_catalog_scorer_integration_test.php` | read 2026-08-19 | scorer integration suite |
| `.env` | read 2026-08-19 | `DB_DATABASE=talenthub_local`, `DB_USERNAME=talenthub_app` |
| Live MySQL at `127.0.0.1:3306` | probed 2026-08-19 via PDO | **UNAVAILABLE** — `[2002]` connect failed |

---

## Appendix A. Catalog Matrix (12 rows)

Full matrix recomputed from source files on 2026-08-19. `review_state` and `review_events` reflect current file state.

| # | Framework | Band | Test code | Scoring version | Question count | Schema hash (SHA-256) | Review state |
|---|---|---|---|---|---|---|---|
| 1 | `holland` | `middle` | `holland_middle` | `holland-riasec-1.0` | 30 | `621c22668bfef5ec6f4b9a9d2280be7d0a7510e68ee6169acbbeba1085a7753e` | `draft` |
| 2 | `holland` | `high` | `holland_high` | `holland-riasec-1.0` | 30 | `4ea66cb4c84d548a7b732f6b02c1374c480f8f95bcc345d8ce5f15a283b73e5d` | `draft` |
| 3 | `holland` | `college` | `holland_college` | `holland-riasec-1.0` | 30 | `6d6053595252aaeba977f0a3665cf67c30695116615a4a1fe8aa8ddb7c9d3e06` | `draft` |
| 4 | `mbti` | `middle` | `mbti_middle` | `mbti-education-1.0` | 32 | `5f29ea506b397ab6690bbe77a684d949e4ce55239c99927cec91ef1af0e72779` | `draft` |
| 5 | `mbti` | `high` | `mbti_high` | `mbti-education-1.0` | 32 | `1800b735342c15160e0f37fd1bf17b9137471ec601eca2a05b798955d79592a4` | `draft` |
| 6 | `mbti` | `college` | `mbti_college` | `mbti-education-1.0` | 32 | `0b4a50b864adc33ab158e7af4390b30ed816eba259a5ab8caba6526525cb231f` | `draft` |
| 7 | `disc` | `middle` | `disc_middle` | `disc-education-1.0` | 28 | `0e9b5654ed9f237baf47698976cb98e1db0c454574b47ed77b936b0299527955` | `draft` |
| 8 | `disc` | `high` | `disc_high` | `disc-education-1.0` | 28 | `773a1bd161c5d3ff300ff6be6eb7b1b4f33254143ca792774b0e1da53433cd78` | `draft` |
| 9 | `disc` | `college` | `disc_college` | `disc-education-1.0` | 28 | `42749a295bf54d13b25b298eed9addbcbf610646bf3f36a40f9fa61e057dbf32` | `draft` |
| 10 | `multiple_intelligence` | `middle` | `multiple_intelligence_middle` | `multiple-intelligence-1.0` | 32 | `e21f50e792591ac8a054622acb1854792ac89cf9cd876106861d0e796be6453a` | `draft` |
| 11 | `multiple_intelligence` | `high` | `multiple_intelligence_high` | `multiple-intelligence-1.0` | 32 | `31c3f12e5a9315f862e344cad37a6421bf661cfa138b7fca8fb2b86ca85763e1` | `draft` |
| 12 | `multiple_intelligence` | `college` | `multiple_intelligence_college` | `multiple-intelligence-1.0` | 32 | `cd59f7fb4e9573b6857dc193091c16e425497fcf8f2bf82f7329d3a48816a212` | `draft` |

Totals: 12 catalogs, 366 questions (90 + 96 + 84 + 96), 12 schema hashes. No band exceeds its `MAX_CONTENT_LENGTH` (60/80/100).

---

## Appendix B. Stable Question-Code Manifest (366 rows)

Columns: `catalog` (equals `testCode`), `position` (1-based), `question UUID` (`test_questions.id`), `stable code` (`test_questions.code`).

Every `id` is a canonical hexadecimal UUID (`Uuid::isValid()`). Every `code` is unique globally and matches its catalog namespace (`{framework}_{band}_`). No `...`, no placeholder, no truncation.

| Catalog | Pos | UUID | Stable code |
|---|---|---|---|
| holland_middle | 1 | 6c894f00-208d-465a-b8c7-aac88f23665f | holland_middle_r_001 |
| holland_middle | 2 | 6bf175e1-f60d-46e1-8e42-3f18d057b0b4 | holland_middle_i_002 |
| holland_middle | 3 | 01f0806d-4c23-40db-a901-30d5902485f0 | holland_middle_a_003 |
| holland_middle | 4 | 470dc5f6-bdb1-4855-8528-844b18279cd5 | holland_middle_s_004 |
| holland_middle | 5 | 349efac7-5ff7-4a8b-b53c-701df1cce2ee | holland_middle_e_005 |
| holland_middle | 6 | 252c1ef3-3cb6-4bfe-bd18-0d23e6afb58e | holland_middle_c_006 |
| holland_middle | 7 | f272bfe0-d5e4-4a76-b37f-27318fec7a22 | holland_middle_r_007 |
| holland_middle | 8 | 18ff60b5-3a1a-4501-a625-ffd4fa3a3d7c | holland_middle_i_008 |
| holland_middle | 9 | 244e9964-7550-41d8-94cd-4c41c95d21c8 | holland_middle_a_009 |
| holland_middle | 10 | a1b04df4-3fe4-44b2-ac59-d0a974f9c635 | holland_middle_s_010 |
| holland_middle | 11 | b80362a5-07bb-446e-bf30-7eb3487fd028 | holland_middle_e_011 |
| holland_middle | 12 | 512acc63-3e0f-4e63-8c3d-ab57a07a4cbb | holland_middle_c_012 |
| holland_middle | 13 | 13540f32-d232-432d-ac79-7efd758f7d26 | holland_middle_r_013 |
| holland_middle | 14 | 460b8bbf-7fc0-48c7-bbc5-226851d1f50f | holland_middle_i_014 |
| holland_middle | 15 | 17375e5f-4990-4b52-88e6-26abfe848bcb | holland_middle_a_015 |
| holland_middle | 16 | 3877173a-9e82-4b5a-aa61-1aa9887f8af8 | holland_middle_s_016 |
| holland_middle | 17 | 244f854c-da58-4c5a-833d-b14629bcf2cc | holland_middle_e_017 |
| holland_middle | 18 | a69b70a1-6638-4896-af26-478d95cd35dc | holland_middle_c_018 |
| holland_middle | 19 | 727c864f-18eb-4b4f-a32d-ee03e0871b9c | holland_middle_r_019 |
| holland_middle | 20 | d79b2e11-04ea-46b3-ac51-6a1e153ba943 | holland_middle_i_020 |
| holland_middle | 21 | 2a64cdd3-eb6c-4174-ac82-715244e1e8c1 | holland_middle_a_021 |
| holland_middle | 22 | 40e60272-2887-4c67-abb9-59c920138b1e | holland_middle_s_022 |
| holland_middle | 23 | 19dd9921-e7ff-4efc-86b9-cccd782190eb | holland_middle_e_023 |
| holland_middle | 24 | 0ebf5b37-5315-4184-b55e-090d9be4de1e | holland_middle_c_024 |
| holland_middle | 25 | f64acc77-9f16-471e-869d-18f472832682 | holland_middle_r_025 |
| holland_middle | 26 | 5863357f-f732-4048-8406-da0dc305412b | holland_middle_i_026 |
| holland_middle | 27 | fc1fd8f1-c320-4fb8-88d6-1c531813aaba | holland_middle_a_027 |
| holland_middle | 28 | 12047d93-eb04-4e5f-9e88-04ea82737ae4 | holland_middle_s_028 |
| holland_middle | 29 | 41faba95-f8ed-4007-b8af-89fd2a9c60dc | holland_middle_e_029 |
| holland_middle | 30 | 81a92de1-7b6d-452a-a06f-df6e34b63abc | holland_middle_c_030 |
| holland_high | 1 | 416dbd91-8553-40fb-99ed-bf7d673133c3 | holland_high_r_001 |
| holland_high | 2 | 2fa554ea-8931-42d5-b5f4-30beb5734a2c | holland_high_i_002 |
| holland_high | 3 | 40389b5a-eef5-4e94-a595-2bf9305d2133 | holland_high_a_003 |
| holland_high | 4 | ae6090be-2945-47a3-8ec8-a07bd7c72b9a | holland_high_s_004 |
| holland_high | 5 | b1265963-afa2-4514-b4b9-4876e9a4e7b9 | holland_high_e_005 |
| holland_high | 6 | 9d4d3ae9-6986-4b81-80c0-05ca052343c8 | holland_high_c_006 |
| holland_high | 7 | 52795f8e-4103-47e6-a30d-3ebe52f683fa | holland_high_r_007 |
| holland_high | 8 | 7c9946b0-2e0f-4f17-9b99-6db433c0e204 | holland_high_i_008 |
| holland_high | 9 | 97409d81-ffb3-493f-853e-5a895a332aac | holland_high_a_009 |
| holland_high | 10 | dbaa064a-c10a-4ce1-8c9a-4ce3fb7c2432 | holland_high_s_010 |
| holland_high | 11 | db081882-153f-49c8-81e1-9af5c768d8d5 | holland_high_e_011 |
| holland_high | 12 | e165b8b9-6a06-4f1e-a06d-e335bd566e5c | holland_high_c_012 |
| holland_high | 13 | 0c6bade7-7da5-4b10-8082-ca5b77578162 | holland_high_r_013 |
| holland_high | 14 | cc90fa56-90eb-4c91-88ce-12e5fa807167 | holland_high_i_014 |
| holland_high | 15 | 4f0a13b4-4d84-4310-9f67-600d3ff8d1f8 | holland_high_a_015 |
| holland_high | 16 | 0e6aacc8-ed9c-406f-b552-a7061c729482 | holland_high_s_016 |
| holland_high | 17 | 5cac5980-e1a1-4f3c-844d-60a088dba8d2 | holland_high_e_017 |
| holland_high | 18 | b0d62071-29bd-4b76-a114-bcdd9fe047fb | holland_high_c_018 |
| holland_high | 19 | a6928e26-7ba4-49c3-94ed-d9cfdc56271e | holland_high_r_019 |
| holland_high | 20 | 8e337585-cd58-407a-abde-754ce5fdbe64 | holland_high_i_020 |
| holland_high | 21 | 4ef3fb66-b32d-476c-841a-5657eea8ba6f | holland_high_a_021 |
| holland_high | 22 | 27ba930e-1b04-46c2-88d6-1e6002bb295b | holland_high_s_022 |
| holland_high | 23 | 571bbff8-72e8-4222-940d-c0048dabeab9 | holland_high_e_023 |
| holland_high | 24 | 7627c88c-6a0a-4675-916b-2a81b60fd60d | holland_high_c_024 |
| holland_high | 25 | d34409a7-f697-4071-88d8-4d1000c83b24 | holland_high_r_025 |
| holland_high | 26 | a84de476-029d-4f65-89d5-4cbc67a6f785 | holland_high_i_026 |
| holland_high | 27 | 3fc174d8-441e-4a8e-9516-c43f1e355ec8 | holland_high_a_027 |
| holland_high | 28 | 86f866ec-893f-4717-9c67-1d27842971ae | holland_high_s_028 |
| holland_high | 29 | bed5d883-da25-4c5f-ae77-ffa6bab0e83e | holland_high_e_029 |
| holland_high | 30 | 41a193f8-029b-414f-8a26-4caf8c63ec2e | holland_high_c_030 |
| holland_college | 1 | 8cc66ca8-508b-4e19-a8f4-e7d5c51659f2 | holland_college_r_001 |
| holland_college | 2 | a955ca26-e916-48c8-a1d3-3d12080abcfc | holland_college_i_002 |
| holland_college | 3 | 2bdc0c6f-183d-45b7-9698-6a8bd8cb610f | holland_college_a_003 |
| holland_college | 4 | 11fa5327-09cf-416b-ab5b-2c4c4a664d8c | holland_college_s_004 |
| holland_college | 5 | 941797a0-78f5-4dd0-9b5b-fcf51750a7d9 | holland_college_e_005 |
| holland_college | 6 | 4a470f78-aba1-4d91-b0b3-4ec4fee1a3e8 | holland_college_c_006 |
| holland_college | 7 | dc1c6e82-aaf6-4f45-a420-33f8e0f51d3b | holland_college_r_007 |
| holland_college | 8 | 5d7abe7b-42e3-4799-97f3-341481c2cca3 | holland_college_i_008 |
| holland_college | 9 | 5bbaefda-0c43-41cf-81f5-aa8ef3397fd6 | holland_college_a_009 |
| holland_college | 10 | cee0195a-9d7d-4795-b33b-ff9951fe4854 | holland_college_s_010 |
| holland_college | 11 | cf510f12-83a3-4a3d-a8ae-fd2f7acf0f63 | holland_college_e_011 |
| holland_college | 12 | 28af44f4-745c-4f3a-86f9-5b45d922718b | holland_college_c_012 |
| holland_college | 13 | 786f1981-4c94-4dda-8438-fc71601429e5 | holland_college_r_013 |
| holland_college | 14 | 4d793622-6c24-45fb-bc97-f438c9561f96 | holland_college_i_014 |
| holland_college | 15 | 31118899-36c9-46a2-9564-9fede8802464 | holland_college_a_015 |
| holland_college | 16 | 76ae4eb3-808f-4598-acd4-ef81dc37a095 | holland_college_s_016 |
| holland_college | 17 | ad302989-d23e-47d2-a981-67ec145fb814 | holland_college_e_017 |
| holland_college | 18 | 536761d1-1ce9-4871-80e6-0ed2b7d219ae | holland_college_c_018 |
| holland_college | 19 | 886d64a2-7ac6-4bbe-9971-a4a8f6ed1ee1 | holland_college_r_019 |
| holland_college | 20 | 5a626615-c801-4c99-bc27-be9645701ea0 | holland_college_i_020 |
| holland_college | 21 | 3ec3c853-928c-4802-972f-7b403f72444f | holland_college_a_021 |
| holland_college | 22 | 97f6402c-8695-48eb-b692-08dbbf7fcaed | holland_college_s_022 |
| holland_college | 23 | 0ca0fae1-dc92-498c-baa5-8a59791289b0 | holland_college_e_023 |
| holland_college | 24 | 13276164-e08a-410b-9514-aa807385165d | holland_college_c_024 |
| holland_college | 25 | e488f490-8e42-4c21-acfc-f9a20bdd4895 | holland_college_r_025 |
| holland_college | 26 | c00ecc70-4681-4280-9b37-933b4dbfbccf | holland_college_i_026 |
| holland_college | 27 | 7d43be1c-ba89-476f-85f0-bc44853f7d13 | holland_college_a_027 |
| holland_college | 28 | 5b40704b-1736-479b-8b29-5ea0e34e74d3 | holland_college_s_028 |
| holland_college | 29 | baa58ee8-a22b-45e5-8f27-e800840f966e | holland_college_e_029 |
| holland_college | 30 | aa29dbe4-0e2c-4c7e-a871-48fb7d454653 | holland_college_c_030 |
| mbti_middle | 1 | 030d54b1-bcc1-4f3b-7bd4-9077fc8c7aea | mbti_middle_ei_e_001 |
| mbti_middle | 2 | 3931bcab-19be-4685-241d-32c2f52f1c32 | mbti_middle_ei_i_002 |
| mbti_middle | 3 | 897c7920-b119-487e-37c1-56edf9f0c40f | mbti_middle_sn_s_003 |
| mbti_middle | 4 | bc36ca66-9f8e-4008-7888-4078954f1c5f | mbti_middle_sn_n_004 |
| mbti_middle | 5 | c9d7ea2c-cfa8-4628-dcfd-6afdf9e59156 | mbti_middle_tf_t_005 |
| mbti_middle | 6 | 7c1d7bbf-b5a4-4241-d6ac-7b13f32ab71f | mbti_middle_tf_f_006 |
| mbti_middle | 7 | 578ebec8-4724-4f41-3e85-328e1b30a3de | mbti_middle_jp_j_007 |
| mbti_middle | 8 | 411155d0-eb62-43ea-6e91-7d9900985c5e | mbti_middle_jp_p_008 |
| mbti_middle | 9 | cfcef627-bbbf-4dcf-efc6-897f90d1f22e | mbti_middle_ei_e_009 |
| mbti_middle | 10 | 50e38d14-5e1f-4ee4-cabc-491928696082 | mbti_middle_ei_i_010 |
| mbti_middle | 11 | 5088af16-be7d-41f4-ab31-c542f5625d8b | mbti_middle_sn_s_011 |
| mbti_middle | 12 | 598b3934-cd93-441c-96a3-4b3e3805a12c | mbti_middle_sn_n_012 |
| mbti_middle | 13 | 2400535c-9828-407b-4b73-8f7a7d47f38d | mbti_middle_tf_t_013 |
| mbti_middle | 14 | 3b9d2697-c510-448b-32b1-1e38c733dbd9 | mbti_middle_tf_f_014 |
| mbti_middle | 15 | 56d1db26-a15f-4b11-4457-a548940c8c94 | mbti_middle_jp_j_015 |
| mbti_middle | 16 | 26f80f6b-6d48-4cc9-0cb4-d7f3dece70d6 | mbti_middle_jp_p_016 |
| mbti_middle | 17 | 40cee00e-c72a-406e-9779-853ae153872e | mbti_middle_ei_e_017 |
| mbti_middle | 18 | dcd0a47d-87e0-47f7-efef-ccedf1811a79 | mbti_middle_ei_i_018 |
| mbti_middle | 19 | c739f102-c7f5-4a03-32e3-8add0e015223 | mbti_middle_sn_s_019 |
| mbti_middle | 20 | 44eab23d-f3ed-4066-fe57-e1db59c8bd73 | mbti_middle_sn_n_020 |
| mbti_middle | 21 | a1f760e5-d66b-40fa-5010-b81644b92d62 | mbti_middle_tf_t_021 |
| mbti_middle | 22 | 7c90a237-b64d-405b-ca59-b1350e206d40 | mbti_middle_tf_f_022 |
| mbti_middle | 23 | 0ecbafe6-c1ab-4525-65dc-9fd064dcf3bb | mbti_middle_jp_j_023 |
| mbti_middle | 24 | 505eeb9c-410c-4bfc-d4af-a5b2c62010b2 | mbti_middle_jp_p_024 |
| mbti_middle | 25 | ddf1be30-127b-4c40-0848-f32a9c1ded09 | mbti_middle_ei_e_025 |
| mbti_middle | 26 | 84bd92cb-4fb3-4dc3-a935-4fb0eb57217a | mbti_middle_ei_i_026 |
| mbti_middle | 27 | 794910f6-11cb-4670-86c4-0ee23d1469c7 | mbti_middle_sn_s_027 |
| mbti_middle | 28 | 148aad22-dd30-45a6-af37-0ff72fe6a5db | mbti_middle_sn_n_028 |
| mbti_middle | 29 | df8a8ffe-4379-4b5b-d410-a8533ccb594f | mbti_middle_tf_t_029 |
| mbti_middle | 30 | f31b86ea-c057-4c39-1cda-27cec33eca5d | mbti_middle_tf_f_030 |
| mbti_middle | 31 | 1946c52f-b482-4c4f-7366-9b49478b3908 | mbti_middle_jp_j_031 |
| mbti_middle | 32 | 799c02f2-22c1-4608-c2ab-e88676f83f14 | mbti_middle_jp_p_032 |
| mbti_high | 1 | bf142c0f-0543-4a36-f45e-79b8648e39c9 | mbti_high_ei_e_001 |
| mbti_high | 2 | b8f334c9-9d16-4f64-ea69-99b39cb87554 | mbti_high_ei_i_002 |
| mbti_high | 3 | 20b3c1be-42fc-4d81-4aff-17b5fa0c5557 | mbti_high_sn_s_003 |
| mbti_high | 4 | 5a9e5a14-81dd-40cc-e49b-7f14c255308d | mbti_high_sn_n_004 |
| mbti_high | 5 | d220d8a5-7c97-46a1-7376-4d654e75f42f | mbti_high_tf_t_005 |
| mbti_high | 6 | 57447382-8af1-4ed6-dca2-ab17c298c7bc | mbti_high_tf_f_006 |
| mbti_high | 7 | 08d564c4-6b1c-49b4-d38b-1379f0b04acc | mbti_high_jp_j_007 |
| mbti_high | 8 | c6f24d56-c410-4a8e-149a-61d067f59bc3 | mbti_high_jp_p_008 |
| mbti_high | 9 | a83fa1e7-3ec4-4f93-41ec-f5a1713fe02a | mbti_high_ei_e_009 |
| mbti_high | 10 | 6a5d8e95-e475-4b61-5e08-a190630fe53f | mbti_high_ei_i_010 |
| mbti_high | 11 | 7f91e9bc-0e46-407b-0004-81dda1c11c5e | mbti_high_sn_s_011 |
| mbti_high | 12 | fc2055ed-9197-4c9b-f35b-caaa63ff2945 | mbti_high_sn_n_012 |
| mbti_high | 13 | b9d973ce-84c0-4e6e-c2ab-320784ded854 | mbti_high_tf_t_013 |
| mbti_high | 14 | 53c7c1fb-cdcb-4441-2ec0-d24e900bc3e3 | mbti_high_tf_f_014 |
| mbti_high | 15 | 2bd12a26-2284-45c6-b9e6-821a23517b12 | mbti_high_jp_j_015 |
| mbti_high | 16 | f3ccc878-10a0-457c-b66c-5b7e64b1b66c | mbti_high_jp_p_016 |
| mbti_high | 17 | b0d61ec7-b44b-48dd-09c0-902f9a4acfef | mbti_high_ei_e_017 |
| mbti_high | 18 | 88c220cf-43b6-4b38-29c3-ead49de16635 | mbti_high_ei_i_018 |
| mbti_high | 19 | e28217e8-5fd4-4040-8aa8-4c26dfc49f0a | mbti_high_sn_s_019 |
| mbti_high | 20 | 8a5ca0f0-aa0f-45bb-e81e-eee67cd6918d | mbti_high_sn_n_020 |
| mbti_high | 21 | 30c375b4-3ad5-41fe-e975-f5cfa35c1668 | mbti_high_tf_t_021 |
| mbti_high | 22 | 43224ff4-e90c-4b88-1fa0-cbcd6781c7a0 | mbti_high_tf_f_022 |
| mbti_high | 23 | d2f987c8-f147-4262-9132-99efbaaa2ee5 | mbti_high_jp_j_023 |
| mbti_high | 24 | 414cb5f3-aa3a-47ca-0ed3-65a80f887ffc | mbti_high_jp_p_024 |
| mbti_high | 25 | 25e39a36-05dc-4e4f-7f23-e49d52917626 | mbti_high_ei_e_025 |
| mbti_high | 26 | eaa4bd8f-2597-4df9-b628-e768a8227e00 | mbti_high_ei_i_026 |
| mbti_high | 27 | 68b85755-a453-4f1c-8f27-30ef057433f5 | mbti_high_sn_s_027 |
| mbti_high | 28 | 23e62ed4-2a0e-436a-492f-4db59c899dd4 | mbti_high_sn_n_028 |
| mbti_high | 29 | ad808631-1e52-43f8-9860-30fadb0adae1 | mbti_high_tf_t_029 |
| mbti_high | 30 | 81b07680-be7c-48e5-cf9e-2362e6a0c258 | mbti_high_tf_f_030 |
| mbti_high | 31 | f9be04ac-12aa-497f-4c4a-1e385254e3ab | mbti_high_jp_j_031 |
| mbti_high | 32 | 97e2ff5c-b89b-4936-2701-43aa81d24bd6 | mbti_high_jp_p_032 |
| mbti_college | 1 | 3e84a57c-29cd-4e97-bb89-77e62461535d | mbti_college_ei_e_001 |
| mbti_college | 2 | b77063cf-5f94-4c44-1af8-fc43175ffe12 | mbti_college_ei_i_002 |
| mbti_college | 3 | cf02eb59-a26a-4ac4-1e00-7af494e6971d | mbti_college_sn_s_003 |
| mbti_college | 4 | f5dc5c6c-c9dd-459a-5e44-2b1ecff147b8 | mbti_college_sn_n_004 |
| mbti_college | 5 | 1dd74c39-917f-4d8c-cb03-c40cd3f55e0d | mbti_college_tf_t_005 |
| mbti_college | 6 | 2d2052c9-0bd5-481b-9e72-e187f693382f | mbti_college_tf_f_006 |
| mbti_college | 7 | e01ea751-2f2a-4ce9-0d66-92df10b5e769 | mbti_college_jp_j_007 |
| mbti_college | 8 | 34123129-60db-4769-7dc9-9d90be829e88 | mbti_college_jp_p_008 |
| mbti_college | 9 | 21b0288e-c9de-4336-d33d-054ac8d4101a | mbti_college_ei_e_009 |
| mbti_college | 10 | 9a27066a-f391-4319-a7ce-80bae6037911 | mbti_college_ei_i_010 |
| mbti_college | 11 | b4d3264b-65af-465f-8171-504d8e0cf316 | mbti_college_sn_s_011 |
| mbti_college | 12 | fc8208e6-d354-4c67-0474-0678f311afbd | mbti_college_sn_n_012 |
| mbti_college | 13 | 498f0125-3c01-4ff7-b5fd-d6ab639f0361 | mbti_college_tf_t_013 |
| mbti_college | 14 | 865ef0fe-a820-4830-e3c7-3b504a41cd91 | mbti_college_tf_f_014 |
| mbti_college | 15 | b1d6cfaf-360d-4e5a-7e25-95f8eb0e9b72 | mbti_college_jp_j_015 |
| mbti_college | 16 | 468f04a6-f116-49b5-dacd-f511d555aa79 | mbti_college_jp_p_016 |
| mbti_college | 17 | e965b1c9-d9ae-4ac8-02e9-fc82e10c26bf | mbti_college_ei_e_017 |
| mbti_college | 18 | fec4be18-b007-4ca3-c0c8-838fa37ac877 | mbti_college_ei_i_018 |
| mbti_college | 19 | 8ed8e8cb-e66e-4dfd-cd26-7c3df5980a6a | mbti_college_sn_s_019 |
| mbti_college | 20 | 9a1f4123-f613-4ebc-68df-c7306741450c | mbti_college_sn_n_020 |
| mbti_college | 21 | 2346eb6d-25a6-4a24-6865-d01bd29b468b | mbti_college_tf_t_021 |
| mbti_college | 22 | b8a54fe0-3b6d-429c-7eb1-1590ec57fae5 | mbti_college_tf_f_022 |
| mbti_college | 23 | 99c9d10f-27be-420b-3c12-4c2a2a8a87e5 | mbti_college_jp_j_023 |
| mbti_college | 24 | 091a270b-e445-46e2-23a6-f3e737d0026f | mbti_college_jp_p_024 |
| mbti_college | 25 | bd864d2e-2065-4996-fed0-f81fa38dbddb | mbti_college_ei_e_025 |
| mbti_college | 26 | 70275408-d643-4e76-2fec-ee847ca1c530 | mbti_college_ei_i_026 |
| mbti_college | 27 | 9eaeb220-5475-4445-6ff6-b7c1a23cc279 | mbti_college_sn_s_027 |
| mbti_college | 28 | 7e8ba656-b9d6-4fe9-999f-9fc672947a23 | mbti_college_sn_n_028 |
| mbti_college | 29 | c571ed34-f65e-45d6-5290-b6e6746f996b | mbti_college_tf_t_029 |
| mbti_college | 30 | 01a0394a-e9da-4f0c-71df-909835556e92 | mbti_college_tf_f_030 |
| mbti_college | 31 | 5b58c2e9-c946-428b-7711-72e44bfa68c7 | mbti_college_jp_j_031 |
| mbti_college | 32 | b6baf9ec-8f9f-46f1-674e-38185fe49351 | mbti_college_jp_p_032 |
| disc_middle | 1 | 5a6faca2-f10c-4f5e-98c4-a427f305ce2f | disc_middle_d_001 |
| disc_middle | 2 | 92819fc7-0f18-4261-995f-22d3361bae22 | disc_middle_i_002 |
| disc_middle | 3 | 6bbaa0a7-e514-49c0-bbe9-df3b5bb87427 | disc_middle_s_003 |
| disc_middle | 4 | 4ed080da-4390-4f25-974e-f067ad59b12e | disc_middle_c_004 |
| disc_middle | 5 | 5b65700d-be72-488f-93cd-4ac3d25fa08e | disc_middle_d_005 |
| disc_middle | 6 | 1f2239ef-bfa8-47ad-aa1d-3805101a94c2 | disc_middle_i_006 |
| disc_middle | 7 | 2f68dded-b52e-4f47-b161-85a5d096fa28 | disc_middle_s_007 |
| disc_middle | 8 | 00f6154a-c330-420a-adae-06c3c1057e22 | disc_middle_c_008 |
| disc_middle | 9 | e751f35b-e8d8-4172-b33b-10694e0e25c5 | disc_middle_d_009 |
| disc_middle | 10 | 3a904e0f-d977-442d-8f96-59d1af1e0654 | disc_middle_i_010 |
| disc_middle | 11 | d7e30541-222e-43ed-98b8-d865f797e479 | disc_middle_s_011 |
| disc_middle | 12 | 2e040632-2696-4d16-bebf-db5f5ed328ee | disc_middle_c_012 |
| disc_middle | 13 | c94f64d5-0f5e-402e-a421-9d43102647ef | disc_middle_d_013 |
| disc_middle | 14 | 7870a3fc-167c-44d3-a3b5-fa4f6ca769a8 | disc_middle_i_014 |
| disc_middle | 15 | a086d4cd-5dde-4dab-9983-a3c9db874007 | disc_middle_s_015 |
| disc_middle | 16 | b4fac70d-4344-4759-bd6b-0b2c2adb77a0 | disc_middle_c_016 |
| disc_middle | 17 | efc530cb-deec-4d04-9076-2365d8e0983c | disc_middle_d_017 |
| disc_middle | 18 | 341af4d5-0beb-4087-8f29-b1d632a79507 | disc_middle_i_018 |
| disc_middle | 19 | 4ef66660-d3e3-447a-9851-21a0cf9c3b50 | disc_middle_s_019 |
| disc_middle | 20 | 53b8b5b1-2da8-40a1-8e9a-b79b3123e561 | disc_middle_c_020 |
| disc_middle | 21 | f19ea1fd-d4cb-41fb-b39d-cccb12243211 | disc_middle_d_021 |
| disc_middle | 22 | 8b16a1a3-d819-483a-bb33-877687bf832d | disc_middle_i_022 |
| disc_middle | 23 | aad2ec23-49f5-4f9c-8672-32c7caf13f6e | disc_middle_s_023 |
| disc_middle | 24 | 051fedcc-f951-4e35-a164-66ad9173156a | disc_middle_c_024 |
| disc_middle | 25 | 2624d9e2-b8d7-4aa0-9f42-240e443681b6 | disc_middle_d_025 |
| disc_middle | 26 | 9dac0e43-f3b1-4b7d-909a-3317a7c1b0b2 | disc_middle_i_026 |
| disc_middle | 27 | a2386f20-adeb-431b-bb0b-0457bb79b624 | disc_middle_s_027 |
| disc_middle | 28 | 6555ba78-2815-49ea-b10d-ebe2fa8dad57 | disc_middle_c_028 |
| disc_high | 1 | dfd6a752-8454-47e3-a9d7-350f63a7324a | disc_high_d_001 |
| disc_high | 2 | 09d4ba6e-38b8-4eb3-8f0a-a098eaa9875d | disc_high_i_002 |
| disc_high | 3 | c39a5103-b74a-469e-beed-ff5fff01ed3d | disc_high_s_003 |
| disc_high | 4 | b0f538b2-18ad-418d-a8cf-e40fbdb29df0 | disc_high_c_004 |
| disc_high | 5 | 2a70727f-d0a7-4677-a150-0f704b0ab3f7 | disc_high_d_005 |
| disc_high | 6 | 0abba71f-58ce-4a65-9dce-d1fe4c78aa2e | disc_high_i_006 |
| disc_high | 7 | 6de86b07-e4d6-4b63-b0aa-36860876ee8d | disc_high_s_007 |
| disc_high | 8 | ace08b18-b7c5-454e-9816-3f04023bb706 | disc_high_c_008 |
| disc_high | 9 | ae87b9d1-d65f-4ec7-816a-c3ef0bc96947 | disc_high_d_009 |
| disc_high | 10 | ecf6742a-0543-4487-81e2-7a0c223d344a | disc_high_i_010 |
| disc_high | 11 | ac12f133-4ad4-4750-b200-b3fb35e5eb7a | disc_high_s_011 |
| disc_high | 12 | d6135879-24f1-4434-8527-307ab37c6616 | disc_high_c_012 |
| disc_high | 13 | 06e41be9-a74c-4f58-80f2-9142acedb38d | disc_high_d_013 |
| disc_high | 14 | e9650d7b-4a9a-49cd-943c-b776bf587688 | disc_high_i_014 |
| disc_high | 15 | 4e9f6857-1320-48af-a916-8195a19388e4 | disc_high_s_015 |
| disc_high | 16 | ac877df2-4181-4625-a3fe-e7f7d1deb5c3 | disc_high_c_016 |
| disc_high | 17 | de2885f3-81fb-4258-a9f7-17acbb35d5e1 | disc_high_d_017 |
| disc_high | 18 | 4d2ca4d2-e00e-43ce-80de-8e23b9fb4c6c | disc_high_i_018 |
| disc_high | 19 | a1633a81-d234-4f5c-bf03-798370ef6560 | disc_high_s_019 |
| disc_high | 20 | e67e7a4b-eb8e-4290-bf29-2556163849f0 | disc_high_c_020 |
| disc_high | 21 | 4e935f8a-56c9-43ec-a098-4a5e3b3f7b5c | disc_high_d_021 |
| disc_high | 22 | e74b1469-dbcb-4fdf-8e83-2bd15da11c05 | disc_high_i_022 |
| disc_high | 23 | 91fe1189-e066-412b-b158-4e130daa7057 | disc_high_s_023 |
| disc_high | 24 | 000f16a0-be60-4ae1-a8f7-8d7083378340 | disc_high_c_024 |
| disc_high | 25 | c5a56935-fdf1-40a3-8451-fb127a454faa | disc_high_d_025 |
| disc_high | 26 | 5e4a137f-2fa7-4ca4-b7a3-0fb53c7cac0b | disc_high_i_026 |
| disc_high | 27 | 2abff094-02ce-43f3-836b-c9e3f4c56f8e | disc_high_s_027 |
| disc_high | 28 | d0ec66c3-563a-46b4-a6b6-4e6cd7b90f77 | disc_high_c_028 |
| disc_college | 1 | 2615d8a2-4f1e-44cb-ba17-9d7669c2df46 | disc_college_d_001 |
| disc_college | 2 | 166124a5-15fd-4b74-925b-4c365bda661e | disc_college_i_002 |
| disc_college | 3 | be634dc2-041c-4d1b-bd52-06aa48445f07 | disc_college_s_003 |
| disc_college | 4 | 12825048-3c30-42fb-8da0-1723c3feae4c | disc_college_c_004 |
| disc_college | 5 | 281b00d1-7ea0-486a-a228-f9a2d347b7a3 | disc_college_d_005 |
| disc_college | 6 | 7a05cc40-f6a7-4cb9-9766-f46c7f504be2 | disc_college_i_006 |
| disc_college | 7 | 33c106d6-579b-43c8-8387-b99f495dc5bd | disc_college_s_007 |
| disc_college | 8 | 667c1547-4abf-4c32-87d7-1ce8c020e6c2 | disc_college_c_008 |
| disc_college | 9 | 416a519c-7e12-418f-86a5-e1307a99c019 | disc_college_d_009 |
| disc_college | 10 | 61e65daf-9cf5-4ef9-bb5c-68d4cd9faa2c | disc_college_i_010 |
| disc_college | 11 | b9d4bcac-745f-4f08-bfd1-38dce17148d5 | disc_college_s_011 |
| disc_college | 12 | 4a6adc91-d44a-436f-bb16-c1a0d9b3e975 | disc_college_c_012 |
| disc_college | 13 | 4de5982d-980e-423d-b986-7b5b9e6b8fac | disc_college_d_013 |
| disc_college | 14 | d887e2d1-36ab-4fc7-9679-093ea0f52960 | disc_college_i_014 |
| disc_college | 15 | ff934aed-c5cd-482b-8c87-7a50f7eb84c1 | disc_college_s_015 |
| disc_college | 16 | 9c1f8450-0345-4c8d-8921-feace33006ba | disc_college_c_016 |
| disc_college | 17 | 1e94ce95-af0a-4202-93ca-f03838c49f88 | disc_college_d_017 |
| disc_college | 18 | 450a491d-d8af-4c3e-a1a1-4d3e73572753 | disc_college_i_018 |
| disc_college | 19 | 96386b08-e7e7-4472-b190-b6e5455d32cb | disc_college_s_019 |
| disc_college | 20 | 5ddc8c9a-3b10-4df6-bc52-f36c2e6f34c3 | disc_college_c_020 |
| disc_college | 21 | 4c67f461-938b-4440-82a0-4d41c7940ff3 | disc_college_d_021 |
| disc_college | 22 | 4c5bf147-ea51-46cc-adbf-a5163894334f | disc_college_i_022 |
| disc_college | 23 | 6cf969cc-b995-417c-bc78-b13b983f9a0f | disc_college_s_023 |
| disc_college | 24 | 956ae993-1305-4543-9dc3-89171e1e6d89 | disc_college_c_024 |
| disc_college | 25 | 5e03b4ec-130d-4bdd-bbe1-086ad015c43e | disc_college_d_025 |
| disc_college | 26 | 9425c1e1-d45a-453c-aaff-3832d4eb1532 | disc_college_i_026 |
| disc_college | 27 | 07be33f1-31a9-4651-8dc6-0cede703edc2 | disc_college_s_027 |
| disc_college | 28 | 74ed3428-d84d-4bb2-82aa-cc563b4c7558 | disc_college_c_028 |
| multiple_intelligence_middle | 1 | bcacf1df-300b-4916-a83a-4d471525afa1 | multiple_intelligence_middle_ling_001 |
| multiple_intelligence_middle | 2 | 4c0755ea-8e67-4f5c-98bb-7d666b11f312 | multiple_intelligence_middle_logi_002 |
| multiple_intelligence_middle | 3 | 4f1abd3e-c1dc-4aa6-b777-cf052af7c0ad | multiple_intelligence_middle_spat_003 |
| multiple_intelligence_middle | 4 | 0df127ae-00bb-4df3-8cae-e1868ad2db3b | multiple_intelligence_middle_body_004 |
| multiple_intelligence_middle | 5 | 480201f4-94ad-445f-bec8-4abc09636e84 | multiple_intelligence_middle_music_005 |
| multiple_intelligence_middle | 6 | a1317c49-6c0a-4865-98ff-91a5d724d062 | multiple_intelligence_middle_inter_006 |
| multiple_intelligence_middle | 7 | 541d7871-986a-4248-a2fc-99f0ef7f267b | multiple_intelligence_middle_intra_007 |
| multiple_intelligence_middle | 8 | df8ff542-59f7-450a-8214-2b5f96fc4a7e | multiple_intelligence_middle_nat_008 |
| multiple_intelligence_middle | 9 | 3378af47-7072-4c62-bdf6-1f76c8e5f635 | multiple_intelligence_middle_ling_009 |
| multiple_intelligence_middle | 10 | 33bef3b8-3a23-4c95-a0f7-dcc71acbad60 | multiple_intelligence_middle_logi_010 |
| multiple_intelligence_middle | 11 | fd9a2826-57fd-4143-8e87-719b3316e505 | multiple_intelligence_middle_spat_011 |
| multiple_intelligence_middle | 12 | 8117801d-1d68-4ae1-abfc-dd7a1309daa8 | multiple_intelligence_middle_body_012 |
| multiple_intelligence_middle | 13 | 09d4af5c-e58d-4106-8344-5a622f226355 | multiple_intelligence_middle_music_013 |
| multiple_intelligence_middle | 14 | 0a0a8bb9-8019-4bf3-bcb1-1bf156cc202e | multiple_intelligence_middle_inter_014 |
| multiple_intelligence_middle | 15 | d460a462-0009-419f-a4c3-7be369057931 | multiple_intelligence_middle_intra_015 |
| multiple_intelligence_middle | 16 | fc3baf79-f4f8-46c5-9edc-e1167054162b | multiple_intelligence_middle_nat_016 |
| multiple_intelligence_middle | 17 | c2dcbdb0-9049-4733-813d-1b10f99dc4f6 | multiple_intelligence_middle_ling_017 |
| multiple_intelligence_middle | 18 | 887303eb-43f7-430a-b094-a7c3d3b4c5b0 | multiple_intelligence_middle_logi_018 |
| multiple_intelligence_middle | 19 | 3d2f3875-4d9a-436e-b820-c8353dcb6929 | multiple_intelligence_middle_spat_019 |
| multiple_intelligence_middle | 20 | 2c1bfbd3-d528-4f8a-b35d-a02d01b36fce | multiple_intelligence_middle_body_020 |
| multiple_intelligence_middle | 21 | c7615833-0d3b-46d1-a046-bc8431797f16 | multiple_intelligence_middle_music_021 |
| multiple_intelligence_middle | 22 | 32778957-448a-4129-8661-44a8007bcbe2 | multiple_intelligence_middle_inter_022 |
| multiple_intelligence_middle | 23 | d667a83c-1ccd-4645-83fc-76a1baeecbb8 | multiple_intelligence_middle_intra_023 |
| multiple_intelligence_middle | 24 | da533fff-1334-4825-8a2b-4160114408da | multiple_intelligence_middle_nat_024 |
| multiple_intelligence_middle | 25 | 8470aa12-5fc3-40ed-b449-38c91914c5a2 | multiple_intelligence_middle_ling_025 |
| multiple_intelligence_middle | 26 | ceb5494b-de8a-4bfc-b32d-dda4e5c611af | multiple_intelligence_middle_logi_026 |
| multiple_intelligence_middle | 27 | 3ff49527-df80-4cec-ad72-a8797d0e4ec5 | multiple_intelligence_middle_spat_027 |
| multiple_intelligence_middle | 28 | bfe323da-37da-4372-bf0f-396c256a15c2 | multiple_intelligence_middle_body_028 |
| multiple_intelligence_middle | 29 | b446b926-1773-4e45-96d3-8fa2bab4842c | multiple_intelligence_middle_music_029 |
| multiple_intelligence_middle | 30 | 6f48de11-fdc6-4219-9876-eb10e2720dda | multiple_intelligence_middle_inter_030 |
| multiple_intelligence_middle | 31 | 137707fd-700c-4e64-a54c-363d810a0cda | multiple_intelligence_middle_intra_031 |
| multiple_intelligence_middle | 32 | 89d8e0bf-27d0-4917-ae4a-e33bc3dd0365 | multiple_intelligence_middle_nat_032 |
| multiple_intelligence_high | 1 | d3033283-f16b-4c6b-adda-7d6c61b9e919 | multiple_intelligence_high_ling_001 |
| multiple_intelligence_high | 2 | 08eb36e5-27b7-4570-9507-787e75f6b792 | multiple_intelligence_high_logi_002 |
| multiple_intelligence_high | 3 | 65d8afcb-1278-4a1e-a44e-2da08fcb0086 | multiple_intelligence_high_spat_003 |
| multiple_intelligence_high | 4 | c9d0309b-5860-4435-8d2d-a82b9b4d3681 | multiple_intelligence_high_body_004 |
| multiple_intelligence_high | 5 | 6ac41cf9-4bfb-4389-a9ea-edb2005d971c | multiple_intelligence_high_music_005 |
| multiple_intelligence_high | 6 | 6090c68c-980e-45ac-84da-b861ae9ae180 | multiple_intelligence_high_inter_006 |
| multiple_intelligence_high | 7 | 9cadd909-20f9-4948-be82-95fe1d59c20e | multiple_intelligence_high_intra_007 |
| multiple_intelligence_high | 8 | 3f7de511-12c4-43cf-b960-88ccc3e613cd | multiple_intelligence_high_nat_008 |
| multiple_intelligence_high | 9 | 5c1b0f52-93fc-4f16-907f-6bb43b888d2f | multiple_intelligence_high_ling_009 |
| multiple_intelligence_high | 10 | 566b9b3e-57b7-4ad5-b4f4-811071e64a60 | multiple_intelligence_high_logi_010 |
| multiple_intelligence_high | 11 | 79cac6c9-7f14-4eba-b973-1de91153c8cf | multiple_intelligence_high_spat_011 |
| multiple_intelligence_high | 12 | 6fdd12e1-06c2-403f-b7b8-b0cafafb0e3a | multiple_intelligence_high_body_012 |
| multiple_intelligence_high | 13 | 856a0150-d2f7-4ada-b975-7e0e9015a260 | multiple_intelligence_high_music_013 |
| multiple_intelligence_high | 14 | 2fc62c74-0295-456b-97f8-bf361f1da4d6 | multiple_intelligence_high_inter_014 |
| multiple_intelligence_high | 15 | 56ea5b83-4210-4757-9de1-62503c9f3888 | multiple_intelligence_high_intra_015 |
| multiple_intelligence_high | 16 | d01618a4-00a6-4660-8e8a-4929417273d5 | multiple_intelligence_high_nat_016 |
| multiple_intelligence_high | 17 | 1bb71d33-a312-4829-a264-1028481b2552 | multiple_intelligence_high_ling_017 |
| multiple_intelligence_high | 18 | e87115f1-1e6b-4b31-92dc-8c51c7f9cd5e | multiple_intelligence_high_logi_018 |
| multiple_intelligence_high | 19 | 6ed4c70c-43d0-403f-a071-7dbb0e45a7e0 | multiple_intelligence_high_spat_019 |
| multiple_intelligence_high | 20 | 027c0e96-4b83-4e11-82f8-5de23d832331 | multiple_intelligence_high_body_020 |
| multiple_intelligence_high | 21 | ba9dfc44-c1e3-4cc6-9700-34bd5b64c260 | multiple_intelligence_high_music_021 |
| multiple_intelligence_high | 22 | 32b369a6-5fa9-4e12-8764-c34d866296da | multiple_intelligence_high_inter_022 |
| multiple_intelligence_high | 23 | 59394cc7-3cce-4828-af21-149367f69427 | multiple_intelligence_high_intra_023 |
| multiple_intelligence_high | 24 | 5ccefd08-1da8-48ea-8aee-5fd6a7b07894 | multiple_intelligence_high_nat_024 |
| multiple_intelligence_high | 25 | 51171375-3260-4890-bb56-4edcf6c441c6 | multiple_intelligence_high_ling_025 |
| multiple_intelligence_high | 26 | e30655bf-524a-414b-ae12-c57c818ed441 | multiple_intelligence_high_logi_026 |
| multiple_intelligence_high | 27 | 992fcd36-f68b-4849-96a0-8c9b557caf84 | multiple_intelligence_high_spat_027 |
| multiple_intelligence_high | 28 | 245a3888-a75d-4940-85d8-7a537b25a8a5 | multiple_intelligence_high_body_028 |
| multiple_intelligence_high | 29 | d7a05107-34f2-4d0b-a0fe-c6ba48a648dd | multiple_intelligence_high_music_029 |
| multiple_intelligence_high | 30 | a6dea801-7eec-4e91-a9d5-eb8909327e22 | multiple_intelligence_high_inter_030 |
| multiple_intelligence_high | 31 | 24d5a6c7-5e84-4cac-8c46-ce92fce11c6a | multiple_intelligence_high_intra_031 |
| multiple_intelligence_high | 32 | fecfe6f8-a96b-4d5c-9465-67cf388dc1c6 | multiple_intelligence_high_nat_032 |
| multiple_intelligence_college | 1 | a30728ff-5109-47e0-adf9-c379c4421d47 | multiple_intelligence_college_ling_001 |
| multiple_intelligence_college | 2 | 9c1658af-011f-47ba-800e-e584fec87201 | multiple_intelligence_college_logi_002 |
| multiple_intelligence_college | 3 | c59b9c0a-ef53-4128-96d4-28b0188da808 | multiple_intelligence_college_spat_003 |
| multiple_intelligence_college | 4 | 0a1126c8-cc45-45ce-bdf7-602208af62c6 | multiple_intelligence_college_body_004 |
| multiple_intelligence_college | 5 | 61e330b4-9bd8-459f-a1fd-88545f06b438 | multiple_intelligence_college_music_005 |
| multiple_intelligence_college | 6 | a9ea8f00-3782-4363-b5ba-624822962151 | multiple_intelligence_college_inter_006 |
| multiple_intelligence_college | 7 | 07b63846-cfef-4222-9f67-0d6b9a5e6203 | multiple_intelligence_college_intra_007 |
| multiple_intelligence_college | 8 | a4db1b60-f558-477e-b62e-771ea24240cf | multiple_intelligence_college_nat_008 |
| multiple_intelligence_college | 9 | 8089f9aa-6cd8-4e6b-885e-a6ec9e36cf68 | multiple_intelligence_college_ling_009 |
| multiple_intelligence_college | 10 | 46b9b050-bded-4630-b72f-a665877ffc52 | multiple_intelligence_college_logi_010 |
| multiple_intelligence_college | 11 | f58ab9da-adcf-4c87-a37f-34fe2a5b2eeb | multiple_intelligence_college_spat_011 |
| multiple_intelligence_college | 12 | 2dd21a3e-b45d-4f23-b612-2bc81b50c6b4 | multiple_intelligence_college_body_012 |
| multiple_intelligence_college | 13 | 93cc1c73-2e19-497c-ad1f-a3bf8ba1e9b0 | multiple_intelligence_college_music_013 |
| multiple_intelligence_college | 14 | 15951caa-72cb-4be5-adb7-148508cd3281 | multiple_intelligence_college_inter_014 |
| multiple_intelligence_college | 15 | b9b5c02d-0b9a-4ef8-91d0-f25b4c235b29 | multiple_intelligence_college_intra_015 |
| multiple_intelligence_college | 16 | 03095d4b-7c86-4944-8a35-33e9c8d52b59 | multiple_intelligence_college_nat_016 |
| multiple_intelligence_college | 17 | 86dfac77-4052-4090-9522-a009c941851a | multiple_intelligence_college_ling_017 |
| multiple_intelligence_college | 18 | c0e2f0b9-f73c-4bca-a47b-7bb246816253 | multiple_intelligence_college_logi_018 |
| multiple_intelligence_college | 19 | eb5dbbc7-c5d2-4881-8179-e754dbec7a95 | multiple_intelligence_college_spat_019 |
| multiple_intelligence_college | 20 | e122d36c-7d90-455a-a35a-9d2a151fa98a | multiple_intelligence_college_body_020 |
| multiple_intelligence_college | 21 | c3c3ce31-ab48-4dcc-bd14-8658fcf21a6c | multiple_intelligence_college_music_021 |
| multiple_intelligence_college | 22 | 9ee45646-8120-4bcc-878f-c7e63b9c8ffe | multiple_intelligence_college_inter_022 |
| multiple_intelligence_college | 23 | 50aa507f-46be-4a14-b3a8-c8e333b66394 | multiple_intelligence_college_intra_023 |
| multiple_intelligence_college | 24 | 507b3509-2433-4d6f-8bf9-26cb7b7f4791 | multiple_intelligence_college_nat_024 |
| multiple_intelligence_college | 25 | e38e4bdc-70fb-4ec6-84fe-68b4981684ff | multiple_intelligence_college_ling_025 |
| multiple_intelligence_college | 26 | 078522f4-7a3b-4d11-a9d6-aadc73f9bcac | multiple_intelligence_college_logi_026 |
| multiple_intelligence_college | 27 | 719633d5-7e15-4f73-a704-6301eeab8cf8 | multiple_intelligence_college_spat_027 |
| multiple_intelligence_college | 28 | 689650e1-a303-410d-b78b-901c5d563b7e | multiple_intelligence_college_body_028 |
| multiple_intelligence_college | 29 | 7cd037bf-7dc2-4814-bd39-a26952d91969 | multiple_intelligence_college_music_029 |
| multiple_intelligence_college | 30 | 269c2973-cfdc-4937-b0ae-1ae83386f297 | multiple_intelligence_college_inter_030 |
| multiple_intelligence_college | 31 | 05bf856a-0bab-4769-869e-63ed1da94cc3 | multiple_intelligence_college_intra_031 |
| multiple_intelligence_college | 32 | 456ccbd3-4db7-4baa-9713-fdae4bc1177a | multiple_intelligence_college_nat_032 |

---

## Appendix C. Expected-vs-Observed Evidence Table

`EXPECTED` is the DCR-contract value. `OBSERVED` is what was actually measured at DCR generation time (2026-08-19). `NOT EXECUTED` means the step requires a live MySQL 8.4 that was unavailable. `BLOCKED` means the step is gated and must not run yet.

| Domain | Item | Expected | Observed | Status | Blocker (if any) |
|---|---|---|---|---|---|
| Schema | Migration `20260818000100` file present | Present | Present (`bcda805be57de3a396f036e71cac85a811fa7ff4061a48f77689a3ce0b674f03`) | EXECUTED | — |
| Schema | Migration applied on `talenthub_local` | Applied | Unknown | **NOT EXECUTED** | **MYSQL UNAVAILABLE** — cannot run `MigrationRunner::status()` |
| Schema | `talenthub_app` `CREATE DATABASE` privilege | Sufficient for disposable DB | Unknown | **BLOCKED** | MYSQL UNAVAILABLE — probe could not connect |
| Schema | Disposable DB `talenthub_assessment_catalog_verify_20260819` created | Newly created, empty, utf8mb4 | Not created | **NOT EXECUTED** | MYSQL UNAVAILABLE |
| Schema | Migration on disposable DB | Applied once | Not run | **NOT EXECUTED** | Depends on disposable DB |
| Counts | `talent_tests` = 12 | 12 | Not seeded | **NOT EXECUTED** | Seeder Task 10 not yet implemented; DCR not approved |
| Counts | `test_questions` = 366 | 366 | Not seeded | **NOT EXECUTED** | Same |
| Counts | `learner_assessment_versions` = 12 | 12 (`published`) | Not seeded | **NOT EXECUTED** | Same |
| Counts | `learner_assessment_question_versions` = 366 | 366 | Not seeded | **NOT EXECUTED** | Same |
| Counts | Cross-catalog UUID uniqueness | 366 unique | 366 unique (file-level) | **EXECUTED** | File-level verified; DB-level pending seed |
| Counts | Cross-catalog code uniqueness | 366 unique | 366 unique (file-level) | **EXECUTED** | File-level verified; DB-level pending seed |
| Counts | Normalized prompt uniqueness | 366 unique | 366 unique (file-level) | **EXECUTED** | Via validator / cross-consistency test |
| Counts | `SELECT COUNT(*)` on `talenthub_local` (all 4 tables) | 12 / 366 / 12 / 366 after seed | **No SELECT issued** | **NOT EXECUTED** | DCR task must not query `talenthub_local` counts until seed approved |
| Hashes | 12 canonical schema hashes (Section 10) | 12 hashes as in Appendix A | 12 hashes recomputed from source; all match | **EXECUTED** | — |
| Hashes | `schemaHash` on disposable DB after seed | Matches Section 10 | Not seeded | **NOT EXECUTED** | Depends on disposable seed |
| Seed | Seeder Task 10 (`AbstractCatalogSeeder` / `AssessmentCatalogMasterSeeder`) | Exists | Not yet implemented | **BLOCKED** | Task 10 is after this DCR |
| Seed | Seeder dry-run on disposable DB | Executed with evidence | Not executed | **NOT EXECUTED** | MYSQL UNAVAILABLE + Task 10 missing |
| Seed | Backup before/after disposable dry-run | Captured | Not captured | **NOT EXECUTED** | Depends on disposable run |
| Seed | `talenthub_local` backup before production seed | Captured before Task 13 | Not captured | **BLOCKED** | Task 13 gated on DCR approval |
| Approvals | Product Owner approval | `APPROVED` with UTC timestamp | `PENDING` | **PENDING** | Awaiting sign-off |
| Approvals | Codex approval | `APPROVED` with UTC timestamp | `PENDING` | **PENDING** | Awaiting sign-off |
| Approvals | Catalog `review_state` | At least `approved`/`published` | `draft` (all 12) | **PENDING** | Review gates not yet executed |

---

## Appendix D. Preflight Checklist (before any seed transaction)

Run this checklist live before opening the first catalog transaction. Any `FAIL` blocks the seed.

- [ ] MySQL 8.4.3 reachable at `127.0.0.1:3306` with `session.time_zone = '+00:00'`
- [ ] `Database/migrations/20260818000100_create_learner_assessment_schema.php` present and checksum matches Section 2.1
- [ ] `MigrationRunner::status()` shows `20260818000100 <name> applied` (and `validateState()` clean, no drift)
- [ ] All 8 tables exist with expected `ENGINE`/`CHARSET`/`COLLATION`
- [ ] `information_schema.statistics` shows the 5 unique keys from Section 11.1 with correct columns
- [ ] No conflicting `learner_assessment_versions` rows for same `(testId, version)` with different hash
- [ ] For any existing `test_questions.id` / `(testId, code)`, the existing content hash matches the incoming `schemaHash` for that code (otherwise fail closed)
- [ ] `computeCanonicalSchemaHash()` per catalog matches `metadata.schema_hash` and Appendix A
- [ ] Seeder artifact is the reviewed Task 10 build (no ad-hoc edits)
- [ ] Disposable dry-run evidence has been captured and attached (Sections 9/17) — or, for the disposable run itself, this item is the dry-run

---

## Appendix E. Post-Seed Checklist (immediately after commit on disposable DB and, after approval, on `talenthub_local`)

- [ ] `SELECT COUNT(*)` per table = 12 / 366 / 12 / 366 (Section 12)
- [ ] Per-catalog `COUNT(*)` per band = 30 / 32 / 28 / 32 (Appendix A)
- [ ] `SELECT schemaHash` per `learner_assessment_versions` row matches Appendix A / Section 10 (all 12)
- [ ] 36 spot checks (Section 13) all match (UUID, code, content, dimensionCode, position, required)
- [ ] 366-row manifest (Appendix B) fully present; `COUNT(DISTINCT id) = 366`, `COUNT(DISTINCT code) = 366`
- [ ] `learner_assessment_versions.status = 'published'` for all 12; no `archived` introduced by seed
- [ ] No `UPDATE`/`DELETE` issued by seeder (audit log / general log inspection)
- [ ] All validator tests pass where applicable (`learner_catalog_content_validator.php`, `learner_catalog_cross_consistency_test.php`, `learner_catalog_scorer_integration_test.php`)
- [ ] Evidence snapshots (before/after) retained with this DCR

---

## Appendix F. Approval Block

> **Do not edit this block to `APPROVED` until real approvals exist.** Any commit that marks `APPROVED` must carry the reviewer’s identity and UTC timestamp. Fabricating approvals violates the DCR contract.

```yaml
dcr: 2026-08-18-learner-assessment-catalog-seed-dcr
status: TASK13_EXECUTED_POST_SEED_VERIFIED
version: 1.1.0
created_utc: 2026-08-19
created_by: Claude Opus (Database Change Reviewer)

approvals:
  product_owner:
    name: NCnguyenn
    role: Product Owner
    decision: APPROVED_OPTION_A
    approved_at_utc: 2026-08-20T05:05:10Z
    signature: NCnguyenn
  codex_schema_contract:
    reviewer: Codex lead reviewer
    role: Codex schema/contract reviewer
    approved_at_utc: 2026-08-20T05:05:10Z
    signature: Codex lead reviewer
  educational_review: APPROVED (2026-08-20T05:05:10Z, Codex lead reviewer)
  bias_safety_review: APPROVED (2026-08-20T05:05:10Z, Codex lead reviewer)
  scoring_review: APPROVED (2026-08-20T05:05:10Z, Codex lead reviewer)

gates:
  question_count_decision: APPROVED_OPTION_A   # 30/32/28/32, approved 2026-08-20T05:05:10Z
  disposable_dry_run: EXECUTED_PASS
  talenthub_local_backup: CAPTURED
  seed_on_talenthub_local: EXECUTED_PASS

notes:
  - All 12 catalogs are published with six review_events each; see the 2026-08-20 Codex Review Addendum.
  - Approval inconsistency in plan Section 7.5 is recorded in Section 22.1.
  - Status contract split (retired vs archived) is recorded in Section 22.3.
  - Consent gate is learner_ai_consent_events; privacy_consents is not a prerequisite (Section 22.4).
```

---

## Appendix G. References

- Master plan: `docs/superpowers/plans/2026-08-17-learner-assessment-catalog-content.md`
- Migration: `Database/migrations/20260818000100_create_learner_assessment_schema.php`
- Runner: `src/Database/Migration/MigrationRunner.php`, `src/Database/Migration/AbstractMigration.php`, `src/Database/Migration/MigrationContext.php`
- UUID contract: `src/Support/Uuid.php`
- Validator: `tests/learner_catalog_content_validator.php` (115 assertions, canonical hash impl)
- Cross-consistency: `tests/learner_catalog_cross_consistency_test.php`
- Scorer integration: `tests/learner_catalog_scorer_integration_test.php`
- Catalogs: `Database/seeds/learner/Assessment/*.php` (12 files, 366 questions)

---

*End of DCR — `2026-08-18-learner-assessment-catalog-seed-dcr.md` v1.0.0-draft (2026-08-19). Status: PENDING. Not authorized to seed.*
