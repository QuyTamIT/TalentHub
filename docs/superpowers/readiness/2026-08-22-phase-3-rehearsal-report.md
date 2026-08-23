# Phase 3 Forward-Repair Rehearsal Report

**Date:** 2026-08-22
**Status:** PASS
**Primary during rehearsal:** not mutated

## Rehearsal target and backup

- Disposable schema: `talenthub_phase3_fix_20260822102944`
- Source dump: `C:\Users\CHINGU~1\AppData\Local\Temp\TalentHubBackups\talenthub_local_phase3_fix_rehearsal_20260822102944.sql`
- Dump size: `798698` bytes
- Dump SHA-256: `438496765AB0232B6AEA8ABE7D580C441CD77850764F4936A91800B2D89D1411`
- Starting state: 17 applied migrations; `projects`, `project_members`, and `student_profile_shares` contained zero rows.

## Migration execution

1. Restored the current 17-migration primary dump into the disposable schema.
2. Set `DB_DATABASE` only for the rehearsal process.
3. Applied `20260821000210_reconcile_phase_3_contracts.php`: `[OK] 20260821000210`.
4. Ran migration a second time: `[OK] no changes`.
5. Ran validation: `[OK] validation: OK`.
6. Verified `TalentPassportOptionalSchema::status(..., 'projects') = available`.
7. Verified final counts: 18 migrations, 0 projects, 0 project members, 0 profile shares.

## Data invariant

Data-only dumps excluded only the three intentionally altered tables and `schema_migrations`. The first raw dump comparison differed solely in the generated `-- Dump completed on ...` timestamp; a line-level diff confirmed no row difference. After normalizing that metadata line, the before/after SHA-256 matched:

`15FC40F5B94C718CE2DB05F59DAAF0EF055E1EFD663FDDD9B1B5D013167EF756`

This normalization rule was then reused for the primary apply invariant.

## Cleanup

The schema name was checked against `talenthub_phase3_fix_[0-9]{14}` before cleanup. Final query returned `REHEARSAL_SCHEMA_REMAINING=0`.

## MySQL behavior integration

A separate clone, `talenthub_phase3_test_20260822103934`, was restored from the verified pre-repair backup, migrated, and used for real MySQL service behavior:

- Project capability available.
- `certificate.manage_own` granted only to Student.
- Certificate create/invalid partial-date/delete behavior passed.
- Share token hash persistence, consent linkage, and atomic revoke passed.
- Final cleanup returned `INTEGRATION_SCHEMA_REMAINING=0`.

Test result: `phase_3_mysql_integration_test: OK`.

## Post-review semantic-preflight matrix

After independent review, three validation-only precursors were added and exercised on fresh schemas restored from the verified 17-migration pre-repair backup:

| Mode | Expected result | Observed result |
|---|---|---|
| Canonical schema with populated project/member/certificate/share rows | `00204`, `00205`, `00206`, then `00210`, with rows and dates preserved | PASS |
| Incompatible `projects.category` length | Reject before repair DDL | PASS |
| Same-named project-member index with wrong columns/uniqueness | Reject before repair DDL | PASS |
| Same-named project FK with wrong delete action | Reject before repair DDL | PASS |
| Same-named member-status CHECK with incomplete values | Reject before repair DDL | PASS |
| Certificate URL column with wrong nullability | Reject before repair DDL | PASS |
| Required certificate title with wrong length | Reject before repair DDL | PASS |
| Same-named member-status CHECK weakened with `OR TRUE` | Reject before repair DDL | PASS |
| Existing share linked to consent for the wrong Student/scope | Reject before repair DDL | PASS |
| Consent CHECK with identical token order but semantically different parentheses | Reject at exact validation before repair DDL | PASS |
| Ordinary `createdAt` column with unexpected `ON UPDATE CURRENT_TIMESTAMP(6)` | Reject at exact validation before repair DDL | PASS |

The canonical populated fixture applied `00204`, `00205`, `00206`, then `00210`; it received category `general`, while its original title, date values, and row counts were preserved. The first eight negative fixtures recorded none of the Phase 3 validation/repair migrations. The final two targeted fixtures recorded only `00204` and `00205`, then failed at `00206`; neither reached repair DDL. All 11 validated disposable schemas were removed.
