# Phase 5 Rehearsal Report — Learner Check-in and Experience

Date: 2026-08-22
Decision: PASS

## Disposable environment

- Schema: `talenthub_phase5_rehearsal_20260822170000`
- Source: fresh logical clone of `talenthub_local` at 22 applied migrations and 51 tables.
- Baseline dump: `C:\Users\CHINGU~1\AppData\Local\Temp\talenthub_phase5_rehearsal_20260822170000_baseline.sql`
- Bytes: `802774`
- SHA-256: `817371588EB91A33B528B38742E934CA512D4F75D18D30F9FDA448A20EAE4990`

## Results

- Pre-apply migration validation: PASS.
- First apply: `20260821000400` applied.
- Second apply: `no changes`.
- Post-apply validation: PASS; 23 applied migrations.
- Existing-table integrity: 50 existing non-registry tables matched primary count and SHA-256; manifest SHA-256 `9e9744059448397d5eee1a269740a85d6186f16dd1c187404e05445e0970e953`.
- New policy table contained zero rows after migration and test cleanup.
- MySQL integration: PASS for Teacher create/revoke/ownership, same-registration race, last-scan race, revoke/scan race, and policy-update/scan race.
- Runner safety: setting the test gate against `talenthub_local` was refused with exit code 2.

An initial rehearsal exposed MySQL metadata key-casing warnings. The migration normalized metadata keys, the disposable schema was destroyed/recreated from the baseline clone, and the complete rehearsal was rerun cleanly.

After primary verification, the exact disposable schema name was revalidated, dropped, and confirmed absent. The SQL rehearsal baseline remains in the operating-system temporary directory; it is not an application or primary-database artifact.

A second disposable schema, `talenthub_phase5_rehearsal_20260822174500`, was recreated from the same pre-Phase-5 baseline after reviewer remediation. The MySQL suite passed again, including the production-path policy-update/check-in race, and the exact schema was dropped and confirmed absent.
