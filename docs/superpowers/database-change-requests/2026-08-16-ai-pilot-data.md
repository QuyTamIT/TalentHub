# Database Change Request: de-identified learner AI pilot data

**Status:** DRAFT — no seed exists or has run. Explicit execution approval is required after the staging parent records below are verified.

## Scope and ownership

The learner module may insert only into its canonical learner-owned tables using reserved synthetic UUIDs beginning `00000000-0000-4000-8000-`. It must not create, update, delete, or backfill `users`, `student_profiles`, `activities`, `activity_registrations`, teacher evaluations, schools, or enterprises.

Two staging learners must already exist through the normal identity workflow, with reserved profile IDs:

| Learner profile ID | Required shared/role-owned staging source records |
|---|---|
| `00000000-0000-4000-8000-000000000101` | active synthetic account with `.example` email; one Teacher/School-published technical activity and confirmed registration/check-in source |
| `00000000-0000-4000-8000-000000000102` | active synthetic account with `.example` email; one Teacher/School-published technical activity and confirmed registration/check-in source |

The Teacher-owned published evaluations must be created through the Teacher workflow against those staging profiles. The learner seed will only reference their immutable published records; it will not manufacture teacher data.

## Minimum learner-owned rows after preconditions pass

For each learner, the proposed idempotent seed will insert only:

- two `skills`/`student_skills` relationships (one verified source must already be permitted by the canonical constraint);
- one published assessment version, its question-version rows, submitted attempt, append-only answers, and result;
- one confirmed `experience_logs` record that references the approved shared activity/check-in records;
- four append-only consent grants: `assessment`, `skills`, `activity`, `evaluation`;
- no opportunities, provider prompts, raw QR tokens, emails, names, or real-person data.

Each row uses a fixed UUID under the reserved prefix and deterministic content. The seed must use `INSERT ... SELECT ... WHERE NOT EXISTS`; if a reserved ID exists with different immutable content, it must fail without updating it. `REPLACE`, `ON DUPLICATE KEY UPDATE`, cleanup functions, `DELETE`, `DROP`, `TRUNCATE`, and `ALTER` are forbidden.

## Required evidence before creating or running a seed

1. Verify the target database is a dedicated staging/disposable database, not `talenthub_local` production/shared runtime.
2. Run read-only checks proving both reserved `student_profiles` and their approved activity/registration/evaluation parents exist with the required published/confirmed states.
3. Record baseline counts outside the reserved UUID prefix; the counts must be unchanged after two seed runs.
4. Run the seed twice: first run inserts only declared rows; second inserts zero rows.
5. Run a two-learner rule-flow test proving consent filtering, provenance, and cross-learner isolation.

## Approval requested

Approval must name the disposable/staging schema and confirm the two synthetic parent profiles plus Teacher/School source records have been created via their own workflows. This DCR does not authorize creation of those parent rows, any production data, real participant data, or a real provider call.
