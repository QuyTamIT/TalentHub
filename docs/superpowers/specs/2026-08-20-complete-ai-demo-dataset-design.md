# Complete AI Demo Dataset Design

Date: 2026-08-20
Status: Approved approach; awaiting written-spec review

## Goal

Create a complete, realistic, rerunnable demo dataset for the existing THPT Nguyễn Trãi accounts in `talenthub_local`. The dataset must support an end-to-end customer demonstration of learner profiles, four assessments, AI analysis, career-group and activity recommendations, participation evidence, teacher evaluation, and progress history without leaving the main learner tables empty.

The implementation must preserve the existing school, teacher, student, and enterprise workflows. It must also remain safe when the local 9Router key is later replaced by a real provider key.

## Chosen Approach

Use a deterministic, idempotent demo scenario layered on top of `SchoolDemoSeeder`.

- Reuse the existing THPT Nguyễn Trãi school, six teachers, eleven students, classes, and role assignments.
- Add only dependent demo records owned by a new deterministic UUID namespace beginning with `21000000-`.
- Reuse the published high-school assessment catalog by code rather than creating duplicate tests or hard-coding environment-specific catalog UUIDs.
- Separate deterministic database seeding from the optional live AI call. Database population must succeed without network access; a second explicit command runs 9Router against the seeded data and persists the shadow evaluation.
- Keep the current learner-visible rollout at zero. Rule Engine output stays learner-visible and model output remains shadow-only until a later rollout decision.

This approach was selected over importing the disposable synthetic V2 dataset or creating an isolated pilot account. The synthetic V2 seeder is intentionally restricted to its verification database, while the customer demonstration must use the existing school accounts in `talenthub_local`.

## Scope

### Existing records reused

- School: THPT Nguyễn Trãi.
- Teachers: the six `gv.*@talenthub.vn` demo accounts.
- Students: the eleven `hs.*@talenthub.vn` demo accounts.
- Assessment catalog: the published `holland_high`, `mbti_high`, `disc_high`, and `multiple_intelligence_high` versions and their canonical question sets.

The implementation must not modify the standalone `student@test.talenthub.local` or `teacher@test.talenthub.local` fixtures.

### New demo records

The following minimum coverage is required after one seed run:

| Area | Minimum coverage |
| --- | --- |
| Learner skills | 3-5 verified skills per student, with evidence metadata |
| Assessment history | At least 2 submitted/scored results per student; all 4 canonical high-school tests for the hero learner |
| School activities | 10 activities owned by THPT Nguyễn Trãi teachers across technical, business, arts, and sports/academic categories |
| Activity lifecycle | Past, ongoing, and future activities; only valid open activities are recommendation candidates |
| Registrations | At least 24 records spanning pending, approved, attended, and cancelled states |
| QR sessions | Active, expired, and revoked teacher sessions linked to ongoing or completed demo activities |
| Check-ins | At least 12 valid attendance records linked to approved/attended registrations |
| Experience logs | At least 12 confirmed logs with hours, role, reflection, and evidence references |
| Teacher evaluation | Published activity assessments and at least 12 scored learner evaluations with comments |
| AI consent | Explicit granted consent events for `assessment`, `skills`, `activity`, and `evaluation` on the eleven synthetic student accounts |
| Recommendations | Persisted Rule Engine runs for eligible learners and a live shadow-model run for the hero learner when the AI command is invoked |

`hs.minh@talenthub.vn` is the hero learner. This account receives all four assessment results, at least five verified skills, two confirmed activity experiences, two published teacher evaluations, and complete consent so both career-group and activity recommendation scenarios pass the existing quality gate.

Other students receive varied but internally consistent profiles. The variation must cover the four career groups and include a small number of intentionally incomplete histories so the UI can demonstrate both ready and insufficient-data states without making the main dataset look empty.

## Components

### `SchoolAiDemoSeeder`

A dedicated demo seeder will own all `21000000-` records. It will:

1. Verify that the application environment is `local` or `test`.
2. Verify the expected THPT Nguyễn Trãi parent records exist.
3. Resolve canonical published assessment versions by the four `*_high` codes and reject missing or duplicate published versions.
4. Insert or update only deterministic rows owned by the demo namespace.
5. Execute the entire deterministic population in one transaction under the existing seed lock.
6. Return a compact count summary without printing answers, consent payloads, tokens, or provider credentials.

The seeder will be invoked through a new explicit `--demo-ai` option. `--demo-ai` first ensures the base `SchoolDemoSeeder` data exists, then populates the complete AI scenario. Existing `--demo` behavior remains compatible and does not unexpectedly make an external API call.

### Assessment fixture builder

Assessment answers and score summaries will be deterministic and compatible with the canonical scoring contract. The implementation must use the application scoring service wherever practical rather than inventing score payloads. Attempts must have coherent timestamps and terminal statuses, and every persisted result must reference its matching attempt and published version.

The hero learner must produce a clear but plausible Holland profile, plus consistent MBTI, DISC, and multiple-intelligence summaries. Other learners use distinct profiles so recommendations are not identical across all accounts.

### Participation and evaluation fixture builder

Activities belong to THPT Nguyễn Trãi and its teachers, not to the unrelated test school. Dates are relative to an injected UTC clock. Production seeding normalizes the current day, while tests inject a fixed instant; rerunning on the same day is byte-stable and rerunning later refreshes only owned temporal fields so the demo still contains past, ongoing, and future examples. States must remain relationally valid:

- cancelled registrations have no check-in or confirmed experience;
- attended registrations have a valid check-in and confirmed experience;
- teacher scores target only registered learners and published assessment rubrics;
- active QR sessions are unexpired and unrevoked, while expired and revoked examples remain unusable;
- future/open activities remain eligible opportunities and completed activities become evidence sources, not recommendations.

Raw QR tokens must never be persisted. Only deterministic token hashes and safe display metadata are seeded.

### AI demo runner

A separate local-only CLI command will invoke the existing learner recommendation context for the hero learner after deterministic seed verification. It will run the same consent, quality-gate, snapshot, evidence-validation, and persistence boundaries used by the learner API.

One recommendation generation is expected to produce both `explore_career_group` and `register_activity` actions from the current unified service contract. The Rule Engine result remains the visible result. The configured 9Router model receives only the minimized consented snapshot, and its valid model run is stored with the repository's normal evidence and audit records. The evaluator summary is printed in redacted form by the CLI and is not presented as a separate persisted entity. A provider timeout, invalid response, rate limit, or unavailable local endpoint must leave the seeded dataset and visible Rule Engine recommendation intact and return a clear non-secret diagnostic.

No model response is copied into seeded fixture code. Each explicit AI run creates or updates only the designated hero demo evaluation according to the repository's existing idempotency or request-correlation contract.

## Data Flow

1. `php bin/seed.php --demo-ai` verifies local/test safety, ensures the base school demo, resolves the canonical assessment catalog, and transactionally upserts the deterministic scenario.
2. A verification command checks referential integrity and required nonzero counts before any provider call.
3. The local-only AI runner loads the hero learner through the production database sources: profile, skills, assessment results, confirmed experiences, published evaluations, consent, and open opportunities.
4. The quality gate validates that the hero learner has all required evidence.
5. The Rule Engine generates the learner-visible recommendation and persists its evidence references.
6. The 9Router model evaluates the same minimized snapshot through `ShadowRunService`.
7. A valid shadow model run, its evidence, and its normal audit events are persisted for developer inspection; the evaluator summary is returned to the CLI. `TALENTHUB_AI_VISIBLE_PERCENT=0` prevents model output from replacing learner-visible data.

## Idempotency and Isolation

- All new fixture IDs use the reserved `21000000-` namespace and stable natural keys.
- Rerunning the seed updates only rows owned by that namespace; it must not truncate tables or delete rows belonging to other schools, users, or test fixtures.
- Parent links are limited to the existing `20000000-` THPT Nguyễn Trãi records.
- Catalog rows are read-only dependencies and are never modified by this seeder.
- Counts are verified both globally and within the demo namespace to detect accidental cross-role changes.
- The seeder must fail before writing if required migrations, parent records, or published catalog versions are missing.

## Security and Future API-Key Replacement

- Provider credentials remain in the ignored local `.env`; no key is written to source, seed data, SQL dumps, logs, fixtures, commits, or exception messages.
- The database seed performs no network requests.
- The AI runner is forbidden outside `local`/`test` and respects the existing endpoint allowlist, timeout, retry, rate-limit, consent, evidence-validation, and visible-rollout controls.
- Replacing the local 9Router key with a production key changes configuration only; it does not require reseeding or schema changes.
- Existing HTTP allowance remains restricted to the loopback 9Router endpoint. Non-loopback or production provider endpoints still require HTTPS.

## QR Scope

This work seeds coherent QR session, registration, check-in, and experience records so AI has real database evidence and the teacher page can display meaningful sessions.

It does not implement the learner camera/scanner endpoint. The repository currently provides teacher QR session management but the learner scan flow is still Phase 2. End-to-end QR scanning is therefore a separate feature and must not be reported as completed by this dataset task.

## Failure and Recovery

- Any deterministic seed failure rolls back the complete transaction.
- Any AI provider failure occurs after seeding and cannot roll back or corrupt the dataset.
- Before the first write to `talenthub_local`, create a timestamped SQL backup outside the repository.
- Recovery uses that verified backup if required. No broad delete, truncate, or reset command is part of the normal workflow.
- A failed rerun may be retried safely because all owned fixture IDs are deterministic.

## Test and Verification Strategy

Implementation is test-first and must include:

1. Unit tests for environment guards, parent/catalog validation, deterministic IDs, idempotent upserts, and secret-free errors.
2. Disposable-MySQL integration tests that seed twice and assert identical counts, valid foreign keys, coherent lifecycle states, and no changes to unrelated role fixtures.
3. Assessment checks that submitted attempts and results use the four canonical high-school versions and valid scoring payloads.
4. Recommendation quality-gate tests proving the hero learner is eligible and intentionally incomplete learners return the expected status.
5. Shadow-run tests for success, timeout, HTTP failure, malformed output, rate limiting, and the invariant that visible percent zero always returns the Rule Engine result.
6. QR data checks proving active, expired, and revoked sessions have valid hashes/statuses and that attended records join through registration, check-in, and experience.
7. Full PHP and JavaScript regression suites plus migration validation.
8. A before/after role-isolation report showing that no user, role, school membership, teacher profile, student profile, or enterprise fixture was removed or reassigned.

The final local verification must run the seed twice, run the AI demo command, and report redacted evidence that the configured model returned a valid response and that the shadow record was persisted.

## Acceptance Criteria

- `talenthub_local` contains complete, non-empty data for all eight stages in the customer journey, except that QR camera scanning remains explicitly out of scope.
- Eleven existing THPT Nguyễn Trãi students have realistic, varied demo histories; the hero learner passes every AI quality gate.
- Four published high-school assessment catalogs are reused without duplicate test definitions.
- Activities, registrations, QR sessions, check-ins, experiences, and teacher scores are relationally coherent.
- Running the seed twice produces no duplicate rows and no count drift.
- The live 9Router command succeeds for the hero learner, or fails safely without affecting Rule Engine output or seeded data.
- Model output remains shadow-only and cannot become learner-visible while visible rollout is zero.
- No API key or raw QR token appears in Git, application logs, seed output, or database fixture payloads.
- Existing student, teacher, school, and enterprise regression tests pass.
- No merge into `develop` and no push occur as part of this task.
