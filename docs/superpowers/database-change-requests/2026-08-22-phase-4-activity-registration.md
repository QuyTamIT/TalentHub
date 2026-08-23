# Phase 4 Database Change Request — Activity Registration Lifecycle

Date: 2026-08-22
Database: `talenthub_local`
Migration: `20260821000300_extend_activity_registration_lifecycle.php`
Result: **APPLIED / VALIDATED**

## Scope

- Add `cancelledAt DATETIME(6)` and `cancellationReason VARCHAR(500)` to `activity_registrations`.
- Extend the named status CHECK with `waitlisted` while preserving all existing status values.
- Backfill six legacy cancelled rows from their existing `updatedAt` values and label them `legacy_migration`.
- Create optional `activity_registration_policies`; no policy row is invented for existing activities.
- Create and map `activity_registration.update_managed` only to the Teacher role.
- Preserve unique registration identity, existing foreign keys, activity/check-in/assessment consumers, and all 40 existing registrations.

## Backup

- Path: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase4_20260822.sql`
- Size: `766576` bytes
- SHA-256: `b9789e5c5b42b53dda2ceb86abd7a2a5b50a2105e8bf76866e7342f35b4096ae`
- Command properties: single transaction, routines, triggers, no tablespaces, UTF-8.
- Dump completion marker verified.

## Rehearsal and apply evidence

- Restored the backup into isolated schemas; initial rehearsal correctly stopped on an exact FK metadata mismatch (`NO ACTION` vs `RESTRICT`) before any migration DDL.
- Corrected the pending migration, restored/rechecked a clean baseline, applied `00300`, ran it a second time with `no changes`, and passed validation.
- MySQL behavior tests passed for automatic approval, Teacher review, waitlist/FIFO promotion, capacity reduction, same-Student overlapping-registration race, same-activity capacity race, and concurrent cancel/register.
- Both MySQL test entry points refuse `talenthub_local` and accept only `talenthub_phase4_(rehearsal|test)_YYYYMMDDHHMMSS`.
- Primary apply changed migration count `21 -> 22` and table count `50 -> 51`.
- Final state: `22 applied`, `0 pending`, validation OK.

## Primary invariants

- Registration rows: `40` unchanged.
- Status counts unchanged: `approved=8`, `attended=20`, `cancelled=6`, `pending=6`, `waitlisted=0`.
- Invalid cancellation metadata: `0`; registration orphans: `0`; broken assessment/activity references: `0`.
- Policy rows: `0` by design; defaults are derived without fabricating records.
- Teacher permission mappings: `1`; mappings to non-Teacher roles: `0`.
- No Phase 5 table was created.

## Recovery

Migration is forward-only to preserve cancellation history and status truth. Recovery uses the verified pre-Phase-4 SQL backup. Both disposable rehearsal databases were removed after review; the backup remains available.
