# Student Production Foundation Readiness

- Final verification: completed before the original readiness-record commit below
- Branch: feature/student
- Commit: 6a23c2e
- Database state: CANONICAL_READY
- Migration validation: PASS
- Phase 1 readiness: READY
- Protected role paths changed: none
- Secrets recorded: none

## Chronological audit timeline

1. **2026-08-15 22:37:22 +07:00 — historical `DATABASE_BLOCKED`:** `bin/migrate.php status` and `bin/migrate.php validate` each exited 1 with `[FAIL] DATABASE_CONNECTION_FAILED SQLSTATE=HY000`; Phase 1 readiness exited 3 and reported the shared database connection unavailable. This was the state at that time, not the current decision.
2. **Later on 2026-08-15 — controller remediation:** the controller provisioned a fresh, empty migration-managed canonical-pending database and explicitly authorized the pending-migration branch. The fresh audit found all ten shared migrations pending, validation `OK`, and Phase 1 connected with only canonical schema requirements missing.
3. **Before final verification — authorized database initialization:** `bin/migrate.php migrate` applied all ten shared migrations and `bin/seed.php` completed the system seed; both exited 0.
4. **Before the original readiness-record commit — current `CANONICAL_READY`:** migration status showed all ten shared migrations applied, validation was `OK`, and Phase 1 readiness was `READY` with exit 0.
5. **Readiness record commit:** `ba0d74e` was committed at `2026-08-15 22:47:52 +07:00` (Git CommitDate). The canonical-pending audit, authorized migration, system seed, and final Phase 1 `READY` verification were completed before this commit; no unsupported exact event time is recorded for them.

## Evidence

- `bin/migrate.php status` before mutation: exit 0; all 10 known shared migrations pending on the fresh migration-managed database.
- `bin/migrate.php migrate`: exit 0; applied migrations `20260814000100` through `20260815000100`.
- `bin/seed.php`: exit 0; `[OK] system seed`.
- `bin/migrate.php status` after mutation: exit 0; all 10 known shared migrations applied.
- `bin/migrate.php validate`: exit 0; `[OK] validation: OK`.
- learner readiness Phase 1: exit 0; `READY`.
- canonical `users.roleId`: present (verified by Phase 1 readiness).
- legacy `users.roles`: absent in the fresh runtime database created from the canonical migration set.
- `uq_users_email`: present (verified by Phase 1 readiness).
- `uq_student_profiles_user`: present (verified by Phase 1 readiness).

## Phase 11 verified recovery gate

The Phase 11 release rehearsal adds a non-destructive recovery proof without changing the canonical database:

1. Create a transaction-consistent physical `mysqldump` backup and record its byte size and SHA-256.
2. Restore only into an allow-listed `talenthub_phase11_rehearsal_YYYYMMDDHHMMSS` schema.
3. Validate the migration registry and require two migration replay calls to be no-ops.
4. Verify every restored baseline row, all discovered foreign keys, uniqueness constraints, ownership, and the complete four-role journey.
5. Compare primary table count, total row count, and deterministic per-table digest before and after the rehearsal.
6. Revoke disposable grants and drop only the exact allow-listed rehearsal schema in `finally`.

For an operational recovery, stop writes, verify the approved backup digest, restore into a new recovery schema, validate invariants there, and switch configuration only with separate human approval. Never overwrite `talenthub_local` in place. Retain a failed schema for forensic review until explicitly released.

Executable commands and human signature fields are maintained in [student-portal-release-checklist.md](./student-portal-release-checklist.md).
