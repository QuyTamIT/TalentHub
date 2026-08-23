# Phase 4 Rehearsal Report

Result: **PASS**

## Baseline and migration

- Backup restored with `50` tables and `21` applied migrations.
- First preflight exposed an exact foreign-key rule mismatch and stopped before DDL; the assertion was corrected to the runtime canonical `NO ACTION / CASCADE` contract.
- Clean rehearsal applied `20260821000300` successfully.
- Second migration run returned `no changes`; validation returned OK.
- Existing 40 registrations and their status counts remained unchanged.

## MySQL behavior matrix

- Automatic activity: first registration `approved`.
- Capacity full: later registration `waitlisted`.
- Teacher-review policy: registration `pending`; owning Teacher transition `approved`.
- Approved cancellation: cancellation metadata persisted and earliest waitlist row promoted by `registeredAt,id`.
- Reduced capacity: no unsafe promotion when occupied count still meets capacity.
- Two Students / one capacity-one activity: exactly `approved + waitlisted`, never two approved.
- One Student / two overlapping activities: exactly one registration; the other returns `SCHEDULE_CONFLICT`.
- Concurrent cancel/register: final state exactly one approved, one waitlisted, one cancelled.
- Main and worker processes refused the primary schema by name before connecting for mutation.

## Cleanup

- Removed `talenthub_phase4_rehearsal_01a0245e`.
- Removed `talenthub_phase4_rehearsal_20260822120324`.
- Remaining schemas matching `talenthub_phase4_%`: `0`.
- Rehearsal data is recoverable from the verified SQL backup recorded in the DCR.
