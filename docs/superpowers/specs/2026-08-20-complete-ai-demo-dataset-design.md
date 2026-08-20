# Complete AI Demo Dataset Design

Date: 2026-08-20
Status: Revised for high-school and university demo coverage; awaiting written-spec review

## Goal

Create a complete, realistic, rerunnable demo dataset for both the existing THPT Nguyễn Trãi accounts and a new synthetic Đại học FPT cohort in `talenthub_local`. The dataset must support an end-to-end customer demonstration of learner profiles, four assessments, AI analysis, career-group and activity recommendations, participation evidence, teacher evaluation, and progress history without leaving the main learner tables empty.

The implementation must preserve the existing school, teacher, student, and enterprise workflows. It must also remain safe when the local 9Router key is later replaced by a real provider key.

## Chosen Approach

Use a deterministic, idempotent demo scenario layered on top of `SchoolDemoSeeder`.

- Reuse the existing THPT Nguyễn Trãi school, six teachers, eleven students, classes, and role assignments.
- Create a synthetic Đại học FPT demo organization with one school administrator, four lecturers, four year-based cohorts, and eight students. The records are product fixtures and do not represent real people or official university data.
- Add THPT Nguyễn Trãi AI-dependent records in the deterministic `21000000-` UUID namespace and all new Đại học FPT records in the separate `22000000-` namespace.
- Reuse the published high-school and college assessment catalogs by code rather than creating duplicate tests or hard-coding environment-specific catalog UUIDs.
- Separate deterministic database seeding from the optional live AI call. Database population must succeed without network access; a second explicit command runs 9Router against the seeded data and persists the shadow evaluation.
- Keep the current learner-visible rollout at zero. Rule Engine output stays learner-visible and model output remains shadow-only until a later rollout decision.

This approach was selected over importing the disposable synthetic V2 dataset or creating an isolated pilot account. The synthetic V2 seeder is intentionally restricted to its verification database, while the customer demonstration must use coherent school and university accounts in `talenthub_local`.

## Scope

### Existing records reused

- School: THPT Nguyễn Trãi.
- Teachers: the six `gv.*@talenthub.vn` demo accounts.
- Students: the eleven `hs.*@talenthub.vn` demo accounts.
- High-school assessment catalog: the published `holland_high`, `mbti_high`, `disc_high`, and `multiple_intelligence_high` versions and their canonical question sets.

The implementation must not modify the standalone `student@test.talenthub.local` or `teacher@test.talenthub.local` fixtures.

### University records created

- Organization: Đại học FPT, marked clearly as synthetic demo data in fixture metadata and documentation.
- One synthetic school administrator and four synthetic lecturers using `@talenthub.vn` demo email addresses, never real FPT addresses.
- Four active cohorts representing university years 1-4 and eight synthetic students, two per year.
- College assessment catalog: the published `holland_college`, `mbti_college`, `disc_college`, and `multiple_intelligence_college` versions and their canonical question sets.

The existing schema stores learners through `schools`, `classes`, and `student_profiles`. University cohorts therefore use `classes.gradeLevel` values 1-4 to represent study years. Every university assessment attempt explicitly confirms the `college` education band so `EducationBandResolver` selects only the `*_college` catalog; grade values 1-4 must never be inferred as middle- or high-school bands.

### New demo records

The following minimum coverage is required after one seed run:

| Area | Minimum coverage |
| --- | --- |
| Learner skills | 3-5 verified skills for each of the 19 learners, with evidence metadata |
| Assessment history | At least 2 submitted/scored results per learner; all 4 band-correct canonical tests for both hero learners |
| School activities | 10 activities owned by THPT Nguyễn Trãi teachers across technical, business, arts, and sports/academic categories |
| University activities | 8 activities owned by Đại học FPT lecturers, including technology projects, entrepreneurship, arts, sports, research, and career experiences |
| Activity lifecycle | Past, ongoing, and future activities; only valid open activities are recommendation candidates |
| Registrations | At least 40 records spanning pending, approved, attended, and cancelled states across both organizations |
| QR sessions | Active, expired, and revoked teacher sessions linked to ongoing or completed demo activities |
| Check-ins | At least 20 valid attendance records linked to approved/attended registrations |
| Experience logs | At least 20 confirmed logs with hours, role, reflection, and evidence references |
| Teacher evaluation | Published activity assessments and at least 20 scored learner evaluations with comments |
| AI consent | Explicit granted consent events for `assessment`, `skills`, `activity`, and `evaluation` on all 19 synthetic learner accounts |
| Recommendations | Persisted Rule Engine runs for eligible learners and live shadow-model runs for one high-school and one university hero learner when the AI command is invoked |

`hs.minh@talenthub.vn` is the high-school hero learner. A deterministic first-year account, `sv.fpt.an@talenthub.vn`, is the university hero learner. Each receives all four band-correct assessment results, at least five verified skills, two confirmed activity experiences, two published lecturer/teacher evaluations, and complete consent so both career-group and activity recommendation scenarios pass the existing quality gate.

Other learners receive varied but internally consistent profiles. Each education band must cover the four career groups and include a small number of intentionally incomplete histories so the UI can demonstrate both ready and insufficient-data states without making the main dataset look empty.

## Components

### `CompleteAiDemoSeeder`

A coordinator seeder will compose the existing school demo, THPT AI fixtures, and new university fixtures. It will own all `21000000-` and `22000000-` records. It will:

1. Verify that the application environment is `local` or `test`.
2. Verify the expected THPT Nguyễn Trãi parent records and create or update only the deterministic Đại học FPT demo parents.
3. Resolve canonical published assessment versions by all four `*_high` and all four `*_college` codes and reject missing or duplicate published versions.
4. Insert or update only deterministic rows owned by the demo namespace.
5. Execute the entire deterministic population in one transaction under the existing seed lock.
6. Return a compact count summary without printing answers, consent payloads, tokens, or provider credentials.

The seeder will be invoked through a new explicit `--demo-ai` option. `--demo-ai` first ensures the base `SchoolDemoSeeder` data exists, then populates the complete AI scenario. Existing `--demo` behavior remains compatible and does not unexpectedly make an external API call.

### Assessment fixture builder

Assessment answers and score summaries will be deterministic and compatible with the canonical scoring contract. The implementation must use the application scoring service wherever practical rather than inventing score payloads. Attempts must have coherent timestamps and terminal statuses, and every persisted result must reference its matching attempt and published version.

Each hero learner must produce a clear but plausible band-correct Holland profile, plus consistent MBTI, DISC, and multiple-intelligence summaries. Other learners use distinct profiles so recommendations are not identical across all accounts.

### Participation and evaluation fixture builder

Activities belong to THPT Nguyễn Trãi or Đại học FPT and to teachers/lecturers from the matching organization, never to the unrelated test school. Dates are relative to an injected UTC clock. Production seeding normalizes the current day, while tests inject a fixed instant; rerunning on the same day is byte-stable and rerunning later refreshes only owned temporal fields so the demo still contains past, ongoing, and future examples. States must remain relationally valid:

- cancelled registrations have no check-in or confirmed experience;
- attended registrations have a valid check-in and confirmed experience;
- teacher scores target only registered learners and published assessment rubrics;
- active QR sessions are unexpired and unrevoked, while expired and revoked examples remain unusable;
- future/open activities remain eligible opportunities and completed activities become evidence sources, not recommendations.

Raw QR tokens must never be persisted. Only deterministic token hashes and safe display metadata are seeded.

### AI demo runner

A separate local-only CLI command will invoke the existing learner recommendation context for both hero learners after deterministic seed verification. It will run the same consent, quality-gate, snapshot, evidence-validation, and persistence boundaries used by the learner API.

One recommendation generation is expected to produce both `explore_career_group` and `register_activity` actions from the current unified service contract. The Rule Engine result remains the visible result. The configured 9Router model receives only the minimized consented snapshot, and its valid model run is stored with the repository's normal evidence and audit records. The evaluator summary is printed in redacted form by the CLI and is not presented as a separate persisted entity. A provider timeout, invalid response, rate limit, or unavailable local endpoint must leave the seeded dataset and visible Rule Engine recommendation intact and return a clear non-secret diagnostic.

No model response is copied into seeded fixture code. Each explicit AI run creates or reuses only the designated hero's demo evaluation according to the repository's existing idempotency or request-correlation contract.

## Data Flow

1. `php bin/seed.php --demo-ai` verifies local/test safety, ensures the base school demo, creates the synthetic university cohort, resolves both canonical assessment bands, and transactionally upserts the deterministic scenario.
2. A verification command checks referential integrity and required nonzero counts before any provider call.
3. The local-only AI runner loads each hero learner through the production database sources: profile, skills, assessment results, confirmed experiences, published evaluations, consent, and open opportunities.
4. The quality gate validates that both hero learners have all required evidence and the assessment sources belong to the correct education band.
5. The Rule Engine generates the learner-visible recommendation and persists its evidence references.
6. The 9Router model evaluates the same minimized snapshot through `ShadowRunService`.
7. A valid shadow model run, its evidence, and its normal audit events are persisted for developer inspection; the evaluator summary is returned to the CLI. `TALENTHUB_AI_VISIBLE_PERCENT=0` prevents model output from replacing learner-visible data.

## Idempotency and Isolation

- THPT AI-dependent fixtures use the reserved `21000000-` namespace; all Đại học FPT entities and dependent fixtures use `22000000-`. Both use stable natural keys.
- Rerunning the seed updates only rows owned by that namespace; it must not truncate tables or delete rows belonging to other schools, users, or test fixtures.
- THPT parent links are limited to the existing `20000000-` records. University parent and dependent links remain within the new `22000000-` organization graph.
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
3. Assessment checks that high-school attempts use only the four `*_high` versions, university attempts use only the four `*_college` versions, and all scoring payloads are valid.
4. Recommendation quality-gate tests proving both hero learners are eligible and intentionally incomplete learners return the expected status.
5. Shadow-run tests for success, timeout, HTTP failure, malformed output, rate limiting, and the invariant that visible percent zero always returns the Rule Engine result.
6. QR data checks proving active, expired, and revoked sessions have valid hashes/statuses and that attended records join through registration, check-in, and experience.
7. Full PHP and JavaScript regression suites plus migration validation.
8. A before/after role-isolation report showing that no pre-existing user, role, school membership, teacher profile, student profile, or enterprise fixture was removed or reassigned. New Đại học FPT users must have only their intended roles and organization links.

The final local verification must run the seed twice, run the AI demo command for both hero learners, and report redacted evidence that the configured model returned valid high-school and college responses and that both shadow records were persisted.

## Acceptance Criteria

- `talenthub_local` contains complete, non-empty data for all eight stages in the customer journey for both high-school and university learners, except that QR camera scanning remains explicitly out of scope.
- Eleven existing THPT Nguyễn Trãi students and eight new synthetic Đại học FPT students have realistic, varied demo histories; both hero learners pass every AI quality gate.
- Four published high-school and four published college assessment catalogs are reused without duplicate test definitions or cross-band results.
- Activities, registrations, QR sessions, check-ins, experiences, and teacher scores are relationally coherent.
- Running the seed twice produces no duplicate rows and no count drift.
- The live 9Router command succeeds for both hero learners, or fails safely without affecting Rule Engine output or seeded data.
- Model output remains shadow-only and cannot become learner-visible while visible rollout is zero.
- No API key or raw QR token appears in Git, application logs, seed output, or database fixture payloads.
- Existing student, teacher, school, and enterprise regression tests pass.
- No merge into `develop` and no push occur as part of this task.
