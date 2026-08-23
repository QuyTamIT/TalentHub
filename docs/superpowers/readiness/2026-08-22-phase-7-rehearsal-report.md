# Phase 7 Disposable Rehearsal Report

- Date: 2026-08-22
- Branch: `feature/student`
- Baseline HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Primary database: `talenthub_local`, MySQL 8.4.3
- Decision: PASS

## Corrective context

Independent review rejected the first rehearsal because it was not
self-orchestrating and the applied schema did not match the exact metadata and
index contract. Applied migration `20260821000500` was preserved byte-for-byte.
Two forward-only migrations were used instead:

- `20260821000510_reconcile_phase7_exact_metadata` repairs column defaults,
  widths and the obsolete empty `cvUrl` column;
- `20260821000520_reconcile_phase7_exact_indexes` replaces broader or implicit
  indexes with the exact approved index names and column sequences.

## Final self-orchestrating rehearsal

Running `tests/phase7_rehearsal_integrity_test.php` directly now performs the
complete gate: it selects the verified pre-Phase-7 dump, creates only an
allow-listed `talenthub_phase7_rehearsal_<timestamp>` schema, restores the dump,
validates migrations, applies all migrations twice, runs integrity, lifecycle
and runtime endpoint suites, and drops the exact disposable schema in `finally`.

The pinned baseline artifact is
`C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase7_20260822_225511.sql`
(804,165 bytes), SHA-256
`c7435080598d68e495fe4ed514868bbd0644a900c06341020df5c4f7692e4c8c`.
The orchestrator computes and compares this digest before creating or restoring
any disposable schema; an explicit alternate path also requires its expected
digest through `TALENTHUB_PHASE7_BASELINE_SHA256`.

The final corrected gate passed twice after primary `00520` apply
(`talenthub_phase7_rehearsal_20260822164030` and
`talenthub_phase7_rehearsal_20260822164039`). Both disposable schemas and their
database grants were removed. The final server check found 0 matching schemas
and 0 matching `mysql.db` grant rows. Each run produced:

- first migration pass: `20260821000500`, `20260821000510`, and
  `20260821000520` applied;
- second migration pass: `no changes`;
- `tests/phase7_rehearsal_integrity_test.php`: 88 assertions PASS;
- `tests/Integration/EnterpriseApplicationLifecycleTest.php`: 34 assertions
  PASS;
- `tests/learner_application_endpoint_runtime_test.php`: 32 assertions PASS.

The integrity gate proves exact columns, indexes, foreign keys and named CHECK
constraints for all four Phase 7 tables. It rejects invalid post/application
statuses, invalid post/snapshot JSON, duplicate applications and duplicate
snapshots, and proves the history/snapshot hard-delete barrier. All 51 existing
non-registry baseline tables retained identical row counts and deterministic
content hashes before behavioral fixtures were added.

The runtime gate covers unauthenticated access, invalid CSRF, non-Student role,
an authenticated Student missing the exact create permission, concurrent
duplicate submission with exactly one persisted application, and atomic
rollback after an injected snapshot failure.

## Primary applies and backups

Before metadata repair `00510`:

- backup:
  `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase7_repair_20260822_232109.sql`;
- size: 814,975 bytes;
- SHA-256:
  `21a80936b72351e1893bdf57eddca82e194cc48bd7fbed250e2f93b96c20562c`.

Before exact-index repair `00520`:

- backup:
  `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase7_index_repair_20260822_233010.sql`;
- size: 812,556 bytes;
- SHA-256:
  `5d82d734f688540f9cf0ac11677c795c4fa229b75c7741fe39372869efa94726`.

Primary ended at 56 base tables / 26 applied migrations / 0 pending with
migration validation OK. All four Phase 7 tables remain empty; no seed or demo
row was created. Exact production columns and indexes match the approved
contract.
