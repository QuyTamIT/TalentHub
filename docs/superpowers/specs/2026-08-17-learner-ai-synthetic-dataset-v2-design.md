# Learner AI Synthetic Dataset V2 Design

## Purpose

Build a deterministic, de-identified dataset for developing and evaluating the learner recommendation system when no real participant data is available. The dataset exercises the existing canonical learner AI schema and recommendation pipeline without changing shared tables, deleting data, or writing to the shared `talenthub_local` schema.

The V2 dataset contains exactly 24 synthetic learner participants. The two participants already declared by `LearnerAiPilotSeeder` are included in that total; V2 adds 22 new learners and new versioned evidence for all 24.

## Current State

- `LearnerAiPilotSeeder` V1 declares 61 insert-only rows for two synthetic learners.
- V1 is pinned to `talenthub_ai_backup_verify_004_20260816`, uses the reserved UUID prefix `00000000-0000-4000-8000-`, and refuses shared runtime schemas.
- The selected disposable schema has verified learner migrations 002, 003, and 004.
- The selected disposable schema has `enterprises` but does not have `internship_posts`.
- The recommendation pipeline currently consumes learner profile, skills, versioned assessment results, confirmed activity experience, published teacher evaluation, opportunities when available, and append-only consent.
- The V1 fixture is preserved unchanged as historical provenance.

## Scope and Non-Goals

### In scope

- Exactly 24 synthetic learner participants, IDs ending `000101` through `000124`.
- Six balanced RIASEC primary archetypes, four learners per archetype.
- A complete 24-question synthetic interest assessment with four original questions per RIASEC dimension.
- Versioned attempts, answers, results, skill evidence, activity experience, published evaluations, and consent events.
- Eighteen recommendation-ready learners and six deliberate safety/quality edge cases.
- Insert-only, idempotent seeding into the approved disposable schema.
- Rule recommendation, provenance, consent, isolation, and fallback verification.

### Out of scope

- Any write to `talenthub_local` or another shared/runtime database.
- Production, staging participant, or copied personal data.
- `UPDATE`, `DELETE`, `REPLACE`, `DROP`, `TRUNCATE`, `ALTER`, or cleanup logic.
- Creating or altering `internship_posts` or another shared-role table.
- Seeding recommendation snapshots, runs, items, evidence, feedback, or audit output. Those records must be produced by the application.
- Badges, certificates, projects, team formation, employer candidate matching, model training, or a visible provider rollout.
- Using official or copyrighted assessment questions. Every V2 question is original synthetic content.

## Selected Approach

Create a versioned V2 dataset and seeder alongside V1:

- `Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php` owns deterministic row generation and scenario metadata.
- `Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2Seeder.php` owns target validation, preflight comparison, transactional insert-only persistence, and idempotency reporting.
- V2 verifies V1 prerequisite rows but never edits them.
- V2 uses new deterministic IDs for every new row and appends newer versioned assessment/evidence records for V1 learners 101 and 102.

This avoids changing the V1 61-row contract and allows future datasets to add V3 IDs without rewriting V2 provenance.

## Participant Matrix

Participant IDs use the existing prefix `00000000-0000-4000-8000-` and the final sequence shown below.

| Primary archetype | Complete learners | Edge learner | Edge behavior |
|---|---|---|---|
| R — Realistic | 101, 102, 103 | 104 | Exactly one active skill; recommendation returns `insufficient_data` |
| I — Investigative | 105, 106, 107 | 108 | Activity registration exists but no confirmed check-in/experience; returns `insufficient_data` |
| A — Artistic | 109, 110, 111 | 112 | Latest submitted assessment is older than 365 days; returns `insufficient_data` |
| S — Social | 113, 114, 115 | 116 | Teacher evaluation exists in draft state and is not published; returns `insufficient_data` |
| E — Enterprising | 117, 118, 119 | 120 | Evaluation scope is granted and then revoked; returns `consent_required` |
| C — Conventional | 121, 122, 123 | 124 | Activity consent is absent; returns `consent_required` |

All complete learners have explicit grants for `assessment`, `skills`, `activity`, and `evaluation`. No opportunity consent or opportunity row is required because the selected schema has no `internship_posts` table.

## Dataset Composition

### Identity and parent records

- Reuse the V1 synthetic roles, school, class, teacher user, and teacher profile.
- Reuse learner users/profiles 101 and 102.
- Add learner users and profiles 103 through 124.
- Use `.example` email addresses, fictional names, non-login password placeholders, fictional phones, and fixed UTC timestamps.
- Never derive identifiers from a real name, email, phone number, or runtime account.

### RIASEC assessment V2

- Reuse the canonical synthetic test code `holland` and its three original V1 R/I/A questions.
- Add 21 original synthetic questions so the assessment contains exactly 24 questions: four each for R, I, A, S, E, and C.
- Add a new immutable assessment version `2.0.0` with scoring version `pilot-riasec-2` and a deterministic schema hash.
- Map all 24 questions to version 2 with positions 1 through 24 and four questions per dimension.
- Add one submitted V2 attempt, metadata row, 24 answer rows, and one six-dimension result per learner.
- Complete learners use recent fixed 2026 timestamps. Learner 112 uses the fixed result timestamp `2024-01-15 09:00:00.000000`, which is older than 365 days relative to the fixed 2026 evaluation reference date.
- Dimension scores are deterministic and make the assigned archetype the unique highest dimension. Ties at the top are forbidden by the dataset contract.

### Skills and skill evidence

- The canonical synthetic catalog contains 12 active skills, two aligned to each primary RIASEC dimension.
- Reuse the existing IoT and Python skills where their meaning fits; add ten new synthetic skills with unique codes.
- Each complete learner has at least three active skills and at least two verified skill-evidence records.
- Learner 104 has exactly one active skill to exercise the quality gate.
- Student-skill uniqueness respects the existing student/skill/source constraint; V1 skill links for learners 101 and 102 are reused rather than duplicated.

### Activities, check-ins, and experience

- The catalog contains 12 published synthetic activities, two aligned to each RIASEC dimension. The existing V1 technical workshop counts as one of the twelve.
- Each complete learner has at least one internally consistent registration → confirmed check-in → confirmed experience-log chain.
- Experience hours are positive, bounded synthetic values and are never inferred from a QR token.
- Learner 108 has a registration but no confirmed check-in or confirmed experience row.
- QR values are never stored; only deterministic hashes are used.

### Teacher evaluations

- Each learner has one deterministic teacher assessment associated with an activity.
- Complete learners have `published` assessments with `publishedAt`, an overall score, and criterion scores.
- Scores are varied to exercise both strength and improvement rules, including presentation scores below and above the communication-roadmap threshold.
- Learner 116 has a draft assessment without `publishedAt`; the source adapter must exclude it.
- Comments contain short synthetic coaching language and no personal or sensitive content.

### Consent

- Consent remains append-only.
- Complete learners receive grants for the four required recommendation scopes.
- Learner 120 receives a later `revoked` evaluation event after an earlier grant.
- Learner 124 receives no activity grant.
- Every event has a deterministic request ID, fixed UTC `DATETIME(6)` timestamp, and policy version `pilot-ai-policy-2`.

## Seeder Safety Contract

Before any insert, `LearnerAiSyntheticDatasetV2Seeder` must:

1. Reject an expected schema name that does not match `^talenthub_ai_backup_verify_[A-Za-z0-9_]+$`.
2. Verify `SELECT DATABASE()` exactly equals the expected schema.
3. Verify recorded canonical checksums for migrations 002, 003, and 004.
4. Verify the required tables and columns used by V2 exist; `internship_posts` is not required.
5. Verify all V1 prerequisite rows used as parents match their declared V1 content.
6. Generate the complete V2 row set in memory and validate unique IDs, identifier syntax, RIASEC balance, question balance, and scenario invariants.
7. Read every reserved target ID. A missing ID is eligible for insert; an identical ID is counted as existing; a different row aborts before the transaction begins.

Persistence uses a transaction and `INSERT ... SELECT ... WHERE NOT EXISTS`. The seeder has no update or cleanup path. A foreign-key, uniqueness, trigger, or validation failure rolls back all inserts owned by that call. A concurrent insert is accepted only when the stored row exactly equals the declared row; otherwise the call fails closed.

The result contract is:

```php
array{declared:int,inserted:int,existing:int,students:int,complete:int,edge:int}
```

The expected participant counts are `students=24`, `complete=18`, and `edge=6`. A second invocation must report `inserted=0`.

## Data Flow After Seeding

For each learner:

1. `DatabaseConsentSource` resolves the latest append-only event for each scope.
2. Database source adapters read only consent-eligible profile, skill, assessment, activity, and evaluation data.
3. `RecommendationSnapshotBuilder` creates a minimized immutable input and evidence references.
4. `RuleRecommendationEngine` produces deterministic recommendations or the expected safe state.
5. Complete learners must receive at least one recommendation item, and every item must cite evidence from that learner's snapshot.
6. Edge learners must return their declared `insufficient_data` or `consent_required` state without fabricating a recommendation.

Recommendation persistence is tested through the existing repository/service suites; V2 never preloads recommendation output.

## Testing Strategy

### Pure contract test

`tests/learner_ai_synthetic_dataset_v2_contract_test.php` runs without MySQL and proves:

- exactly 24 distinct participant IDs;
- exactly four participants per RIASEC archetype;
- 18 complete and six edge scenarios;
- exactly 24 questions and four per dimension;
- unique reserved IDs and safe synthetic strings;
- no real-looking email domains or executable password credentials;
- all referenced parents exist within V1 or V2 declarations;
- generated rows contain no destructive SQL or mutable operation contract.

### Disposable MySQL integration test

`tests/learner_ai_synthetic_dataset_v2_mysql_test.php` requires an explicit `LEARNER_MYSQL_TEST_SCHEMA` matching the disposable pattern. It proves:

- the connection is pinned to the approved disposable schema;
- V1 and V2 seed successfully;
- counts outside the reserved prefix remain unchanged;
- the second V2 call inserts zero rows;
- FK, trigger, version, and append-only constraints accept the dataset;
- all complete learner snapshots are sufficient and evidence-backed;
- all six edge learners return the declared safe state;
- evidence source IDs never cross learner boundaries;
- recommendation generation is deterministic for an unchanged snapshot.

### Regression verification

Existing learner migration, source, snapshot, rule, repository, API, rollout, and scope-audit tests remain green. Shared Teacher, School, Enterprise, `src`, and protected API code is not modified.

## Execution Boundary

The implementation may create and test V2 code without database writes. Actual seeding is authorized only for the explicitly selected disposable schema after the V2 DCR, pure contract test, MySQL safety test, and source-scope audit pass. The execution report records target schema, before/after reserved counts, first/second seed results, and recommendation-state totals.

No command in this work may seed `talenthub_local`, create a new shared database, or remove an existing disposable schema.

## Success Criteria

- The repository contains a documented, deterministic V2 dataset and insert-only seeder.
- The selected disposable schema contains exactly 24 V2 participants after execution, counting V1 learners 101 and 102.
- Eighteen learners produce evidence-backed rule recommendations.
- Four learners produce `insufficient_data` and two produce `consent_required` for the documented reasons.
- The seed is idempotent, leaves non-reserved rows unchanged, and performs no destructive operation.
- No shared-role code, shared schema, or `talenthub_local` data changes.
