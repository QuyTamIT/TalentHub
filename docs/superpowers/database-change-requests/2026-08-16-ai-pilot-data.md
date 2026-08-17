# Database Change Request: de-identified learner AI pilot data

**Status:** APPROVED FOR THE DISPOSABLE SCHEMA ONLY — user approval recorded on 2026-08-16. This approval covers creation of the synthetic parent records below only in `talenthub_ai_backup_verify_004_20260816`; it does not cover production, a shared runtime schema, real participant data, or a real provider call.

## Scope, safety boundary, and ownership

The target is exactly `talenthub_ai_backup_verify_004_20260816`. The seeder must reject any other database name, including `talenthub_local`. Every row uses a reserved UUID beginning `00000000-0000-4000-8000-`, `.example` email addresses, non-login placeholder password hashes, and fictional labels.

The only writes are deterministic, insert-only synthetic fixtures required to exercise cross-role foreign keys in the disposable database. The seed has no cleanup method and must never execute `UPDATE`, `DELETE`, `REPLACE`, `DROP`, `TRUNCATE`, `ALTER`, or `ON DUPLICATE KEY UPDATE`. For each proposed primary key, it must:

1. read the existing row, if any;
2. compare all declared values; and
3. either insert with `INSERT ... SELECT ... WHERE NOT EXISTS` or fail before modifying anything when the row differs.

This is intentionally forward-compatible: rows are identified by a unique, documented prefix; new fixture versions must receive new IDs instead of changing old provenance rows.

## Canonical migration prerequisite

The target must have recorded, checksum-verified learner forward migrations in this exact order before the seed runs:

| Version | Expected checksum prefix | Purpose |
|---|---|---|
| `002_create_ai_input_foundation` | `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc` | skills, assessment parents, check-in and experience foundation |
| `003_create_ai_input_extensions` | `6b2c5674e4da5d98bc7540881f90ce5fab421d2cf52e41b7899f51a87d563c38` | immutable assessment versioning, evidence, append-only AI consent |
| `004_create_recommendation_store` | `48d7eaf7122cae13d5dbcb1dbaa2e157c34f2f4cea8f0c430914f193be48f0be` | immutable snapshots, runs, recommendation evidence and feedback |

Migrations are executed one version per runner call because each preflight intentionally checks the recorded predecessor. They are forward-only and create new learner-owned tables; they do not alter or remove shared tables.

### Execution incident — 2026-08-16

The first disposable attempt against `talenthub_ai_backup_verify_20260816` stopped at MySQL error 1419: binary logging is enabled, `log_bin_trust_function_creators=0`, and the configured account cannot create the required audit triggers. MySQL had already created these six 003 tables before the first trigger statement failed: `learner_assessment_versions`, `learner_assessment_question_versions`, `learner_assessment_attempt_metadata`, `learner_assessment_answers`, `learner_skill_evidence`, and `learner_ai_consent_events`. No triggers exist, 003 is not recorded in `learner_forward_migrations`, 004 was not attempted, and no pilot rows were inserted.

Do not retry 003 against that partial schema and do not remove its tables. The selected target `talenthub_ai_backup_verify_004_20260816` is separate, has recorded 002/003/004 checksums and 26 provenance triggers, and had zero reserved pilot IDs before the first seed run. The seed rejects any schema that does not meet these prerequisites before an insert.

## Exact synthetic shared-parent rows

All timestamp values are UTC and use `DATETIME(6)` precision. IDs not otherwise described below are listed explicitly so a future fixture can be audited without relying on source code.

| Table | ID(s) | Deterministic declared content |
|---|---|---|
| `roles` | `...000001` learner, `...000002` teacher | codes `pilot_learner` / `pilot_teacher`, synthetic names, `isSystem=0` |
| `schools` | `...000010` | `Synthetic AI Pilot School`, `active`, `pilot-school@example`, year `2026-2027` |
| `classes` | `...000011` | school `...000010`, `Synthetic AI Pilot 10A`, grade 10, `active` |
| `users` | `...000020` teacher; `...000101`, `...000102` learners | active synthetic accounts; emails `pilot-teacher@example`, `pilot-learner-101@example`, `pilot-learner-102@example`; a non-login fixed placeholder hash |
| `teacher_profiles` | `...000021` | user `...000020`, school `...000010`, `isSchoolAdmin=0`, synthetic specialization only |
| `student_profiles` | `...000000000101`, `...000000000102` | users `...000101` / `...000102`, class `...000011`, DOBs `2010-01-01` / `2010-02-02`, fictional phones, `studyStatus=active` |
| `activities` | `...000030` | school `...000010`, teacher `...000021`, `Synthetic Technical Workshop`, category `technology`, `published`, capacity 20, `2026-08-01T08:00:00Z` to `12:00:00Z` |
| `activity_registrations` | `...000131`, `...000132` | activity `...000030` respectively bound to learners 101/102, status `attended` |
| `activity_qr_tokens` | `...000031` | activity `...000030`, SHA-256 of `synthetic-ai-pilot-qr-v1`, active window covering the workshop |
| `checkins` | `...000141`, `...000142` | respective registrations, QR `...000031`, status `confirmed`, fixed checked-in/confirmed timestamps |
| `experience_logs` | `...000151`, `...000152` | respective learner/activity/check-in chains, `4.50` / `6.00` hours, `confirmed` |
| `assessment_criteria` | `...000040` | code `presentation`, range 0–100, active |
| `assessments` | `...000161`, `...000162` | teacher `...000021`, respective learner/activity registration chains, published overall scores `88.00` / `76.00`; no personal comment |
| `assessment_scores` | `...000171`, `...000172` | respective published assessment with `presentation` score `55.00` / `68.00` |

`...` in this DCR is the literal fixed prefix `00000000-0000-4000-8000-`; the seeder contains the complete 36-character identifiers and never derives IDs from runtime data.

## Exact learner canonical rows

| Table | IDs | Deterministic declared content |
|---|---|---|
| `skills` | `...000050` IoT Fundamentals; `...000051` Python Fundamentals | canonical rule codes `iot` / `python`, category `technology`, active |
| `student_skills` | `...000201`–`...000204` | two verified skills per learner: 101 = 86.00 IoT, 77.00 Python; 102 = 72.00 IoT, 91.00 Python; source `teacher` and fixed verification timestamps |
| `learner_skill_evidence` | `...000211`–`...000214` | one `teacher_assessment` verified evidence record for each student skill |
| `talent_tests` | `...000060` | canonical rule code `holland`, name `Synthetic Interest Check`, type `interest`, published |
| `test_questions` | `...000061`–`...000063` | published R/I/A questions with fixed numeric options JSON `{"min":1,"max":5}` |
| `learner_assessment_versions` | `...000070` | test `...000060`, version `1.0.0`, scoring `pilot-riasec-1`, SHA-256 of `pilot-riasec-v1`, published |
| `learner_assessment_question_versions` | `...000071`–`...000073` | positions 1–3 mapping version `...000070` to the three questions; dimensions R/I/A; all required |
| `test_attempts` | `...000221`, `...000222` | respective learners, test `...000060`, status `submitted` |
| `learner_assessment_attempt_metadata` | `...000231`, `...000232` | respective attempt/version, `submitted`, SHA-256 of `pilot-101-answers-v1` / `pilot-102-answers-v1` |
| `learner_assessment_answers` | `...000241`–`...000246` | three append-only numeric answers per attempt: 101 = R5/I4/A3; 102 = R3/I5/A4 |
| `test_results` | `...000251`, `...000252` | results `RIA` / `IAR`, minimized score JSON `{"R":82,"I":76,"A":64}` / `{"R":74,"I":88,"A":69}`, scoring `pilot-riasec-1` |
| `learner_ai_consent_events` | `...000261`–`...000268` | four append-only `granted` events per learner for `assessment`, `skills`, `activity`, and `evaluation`; policy `pilot-ai-policy-1` |

No opportunities, raw QR values, real names, production records, teacher comments, or provider prompts are seeded. The QR value is represented solely by a deterministic hash; data sources never expose it.

## Verification and compatibility evidence

The disposable test must capture counts outside this reserved ID prefix for every touched table before the first seed call. It then runs the seed twice:

1. first call inserts only missing declared rows (a clean schema inserts all 61 rows);
2. second call inserts zero rows; and
3. counts outside the reserved prefix remain exactly unchanged.

The test also builds consent-filtered snapshots and rule results for both learners. It must prove every result has evidence, produces deterministic rule output, and cannot retrieve the other learner's run, feedback, source rows, or evidence.

After synthetic verification, real pilot participants may only be onboarded through normal UI/API workflows in a separately approved staging plan. This DCR never authorizes bulk import or copying production personal data.
