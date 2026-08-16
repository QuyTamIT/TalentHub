# Learner AI Readiness Program Design

**Status:** Approved design, pending written-spec review

**Date:** 2026-08-16

**Scope owner:** Learner module

**Target outcome:** A production-ready, explainable recommendation system that starts with deterministic rules and can later add an AI model without changing the learner UI contract or modifying Teacher, School, or Enterprise code.

## 1. Context

TalentHub already contains a learner-facing AI recommendation page, learner repository/read-model foundations, a shared PDO database layer, authentication, RBAC, request IDs, migrations, and readiness checks. The learner AI page currently renders hard-coded mock recommendations. The live database is connected and healthy, but it lacks most of the persisted inputs required for real recommendations, including skills, versioned assessment attempts and answers, check-ins, experience logs, consent, and AI recommendation records.

The product documents define AI as a system that combines assessment results, skills, activities, experience, and published teacher evaluations to explain strengths, improvement areas, suitable opportunities, and a time-based development roadmap. The design must therefore establish trustworthy data and deterministic recommendations before introducing a model provider.

## 2. Goals

1. Persist the minimum real, traceable learner data needed for personalized recommendations.
2. Keep shared facts canonical across Student, Teacher, School, and Enterprise roles.
3. Add new database structures without deleting, rewriting, or breaking existing shared data.
4. Keep all application implementation inside learner-owned code unless the user explicitly approves a wider code change.
5. Build a versioned rule-based recommendation baseline with evidence and explanations.
6. Add recommendation run, item, evidence, snapshot, feedback, and audit persistence.
7. Make the recommendation engine replaceable so rules and external AI models share one domain contract.
8. Introduce model integration only after data, consent, baseline quality, safety, and fallback gates pass.
9. Preserve historical outputs and make every recommendation reproducible.

## 3. Non-goals

- Modifying Teacher, School, or Enterprise application code.
- Allowing AI to verify skills, award experience hours, publish teacher assessments, approve applications, or alter any source-of-truth record.
- Calling an AI model directly from the browser.
- Introducing vector search for the first production release.
- Automatically changing shared tables or applying shared database migrations without prior user approval.
- Deleting tables, rows, historical recommendations, snapshots, feedback, or source data.
- Automated admissions, grading, hiring, or eligibility decisions.

## 4. Global Constraints and Invariants

### 4.1 Code boundary

The default implementation allowlist is:

- `app/learner/**`
- `assets/js/learner*.js`
- learner-owned sections of `assets/css/learner.css`
- `tests/learner_*`
- `docs/superpowers/**`

The following paths are protected and must not be modified without a new explicit approval:

- `app/teacher/**`
- `app/school/**`
- `app/enterprise/**`
- role-specific tests and assets belonging to those roles
- shared bootstrap, router, or service code under `src/**` and `api/**`

If a learner API cannot be implemented without changing shared routing, the implementation must stop and submit a cross-module change request. The preferred fallback is a learner-owned endpoint under `app/learner/api/**` that reuses existing authentication and database bootstrap without changing another role.

### 4.2 Database boundary

Every shared database change is `APPROVAL REQUIRED`. No database migration may be applied until the user has reviewed and approved its Database Change Request.

Approved migrations must be:

- additive and forward-only;
- backward-compatible with all existing roles;
- idempotent;
- free of `DELETE`, `DROP`, `TRUNCATE`, destructive rename, destructive type conversion, or data-rewriting backfill;
- implemented with `ON DELETE RESTRICT` or `NO ACTION` for new foreign keys unless a separately approved contract requires another behavior;
- deployable while old code continues using the previous schema;
- reversible operationally through feature flags and read-path configuration without removing schema or data.

Existing tables and rows are immutable from migration scripts. Runtime learner operations may create or update only records that the learner owns under an explicitly documented data contract, such as the learner's own consent, assessment attempt, registration, or feedback. AI code never updates shared facts.

### 4.3 Privacy and safety

- Recommendation inputs exclude email, phone number, full date of birth, password data, tokens, private CV URLs, and unnecessary names.
- Consent is checked on the server before snapshot construction and again before generation.
- A revoked consent blocks future processing and restricts access to prior snapshots; it does not trigger deletion under this program.
- Any future erasure workflow is a separate legal, product, and database change requiring explicit approval.
- Recommendations are advisory and cannot be the sole basis for admissions, grading, hiring, or disciplinary action.

## 5. Architectural Decision

The selected architecture uses new canonical tables connected by foreign keys to existing shared entities, learner-only application code, and forward-only migrations.

This was selected over directly altering existing shared tables because it minimizes regression risk for Teacher, School, and Enterprise. It was selected over learner-local copies of shared data because copies would drift and create conflicting facts.

Existing shared tables remain sources of truth. New tables add missing versioning, evidence, consent events, and recommendation records without changing how current roles read their data.

## 6. Data Ownership Matrix

| Data | Source of truth | Learner/AI authority |
|---|---|---|
| User identity, class, school | `users`, `student_profiles`, `classes`, `schools` | Read only |
| Skill catalog | `skills` | Read only |
| Learner skill claim | `student_skills` plus optional learner-owned evidence | Create/update own claim only |
| Skill verification | Teacher/School process | Read only; AI never verifies |
| Assessment definition and publication | Canonical assessment catalog | Read published versions only |
| Learner assessment attempt, answer, result | Learner assessment persistence | Create/update own active attempt; submitted result is immutable |
| Activity definition | School/Teacher-owned activity data | Read visible/published rows only |
| Activity registration | Learner-owned relationship to a published activity | Create/cancel own eligible registration |
| Check-in confirmation | Event/check-in authority | Learner may submit evidence; AI reads confirmed result only |
| Experience hours | Confirmed check-in/experience records | Read only; AI never awards hours |
| Teacher evaluation | Teacher-owned published assessment | Read published records only |
| Enterprise opportunity | Enterprise-owned verified active opportunity | Read only |
| AI personalization consent | Learner | Grant/revoke own scopes |
| Recommendation output and feedback | Learner recommendation module | Create own runs and feedback; never rewrite source facts |

## 7. Component Boundaries

### 7.1 Source adapters

Focused adapters read canonical data without leaking table shapes into recommendation logic:

- `StudentProfileSource`
- `SkillSource`
- `AssessmentSource`
- `ActivityExperienceSource`
- `PublishedEvaluationSource`
- `OpportunitySource`
- `ConsentSource`

Each source returns normalized learner-domain records and applies ownership, visibility, status, and publication filters at the database query boundary.

### 7.2 Consent and snapshot pipeline

`ConsentPolicy` resolves allowed input categories for the current learner. `RecommendationSnapshotBuilder` combines only permitted records into an immutable `RecommendationInput`. `DataQualityGate` validates freshness, completeness, verification state, and timestamps before an engine can run.

Every snapshot has:

- a schema version;
- a deterministic content hash;
- source update timestamps;
- data-quality flags;
- included consent scopes;
- internal source identifiers needed for evidence;
- no unnecessary direct personal identifiers.

### 7.3 Engine contract

Rules and models implement one provider-independent interface:

```php
interface RecommendationEngine
{
    public function generate(
        RecommendationInput $input,
        RecommendationContext $context
    ): RecommendationResult;
}
```

`RuleRecommendationEngine` is the mandatory baseline. A later `ModelRecommendationEngine` uses a `RecommendationProvider` adapter. Neither engine writes to the database directly. Orchestration validates input, invokes the engine, validates output, then persists the run transactionally through `RecommendationRepository`.

### 7.4 Explanation and evaluation

`RecommendationExplainer` converts evidence references into learner-facing explanations without exposing private raw data. `RecommendationEvaluator` compares rule and model outputs using offline cases, user feedback, safety checks, and shadow-mode metrics.

### 7.5 Feature flags

Independent flags control:

- database-backed learner input reads;
- rule recommendation generation;
- recommendation feedback;
- model shadow execution;
- model-visible output;
- provider selection.

Disabling a flag stops new behavior without deleting schema or data.

## 8. Canonical Data Foundation Strategy

The live database first receives an approved schema-parity migration for canonical tables already represented by the repository contracts or shared SQL blueprint but absent from the live schema. Candidate tables include:

- `skills`
- `student_skills`
- `talent_tests`
- `test_questions`
- `test_attempts`
- `test_results`
- `privacy_consents`
- `checkins`
- `experience_logs`

The implementation plan must verify every candidate against the authoritative schema immediately before proposing DDL. It must not create a duplicate table if a canonical equivalent already exists.

Missing versioning is added through new extension tables rather than adding mandatory columns to existing tables:

- `learner_assessment_versions`
- `learner_assessment_question_versions`
- `learner_assessment_attempt_metadata`
- `learner_assessment_answers`
- `learner_skill_evidence`
- `learner_ai_consent_events`

These extension tables use nullable or defaulted integration points where necessary so old application code remains valid. Submitted attempts, results, published assessment versions, consent events, and verified evidence are append-only.

## 9. Recommendation Persistence Model

The recommendation domain uses new learner-owned canonical tables:

### `learner_recommendation_runs`

One orchestration attempt. Stores student ID, engine type, status, schema version, rule version, provider, model version, prompt version, snapshot hash, timestamps, fallback reason, and safe error code.

### `learner_recommendation_input_snapshots`

Stores the minimized normalized input or a storage reference, schema version, content hash, consent scopes, quality flags, and source timestamps. Raw prompts and secrets are never stored.

### `learner_recommendation_items`

Stores structured outputs such as strength, improvement area, development direction, activity suggestion, or roadmap step. Each item contains priority, confidence, lifecycle status, and structured action metadata.

### `learner_recommendation_evidence`

Links each item to one or more permitted source records with source type, source ID, observed timestamp, contribution label, and explanation-safe value. Evidence never changes the source record.

### `learner_recommendation_feedback`

Append-only learner feedback containing verdict, reason code, optional safe comment, and timestamp. Feedback does not alter the historical recommendation.

### `learner_recommendation_audit_events`

Append-only operational audit containing run ID, request ID, actor, action, engine/version metadata, status, and timestamp. Sensitive prompt text and provider secrets are excluded.

All new tables use UUID identifiers, explicit foreign keys, unique idempotency constraints, and indexes for learner ownership, latest-run retrieval, evidence traversal, and feedback analysis.

## 10. End-to-End Data Flow

1. The authenticated learner requests the latest recommendation or a permitted regeneration.
2. Authorization resolves the current `student_id`; client-supplied student IDs are ignored.
3. `ConsentPolicy` determines usable input categories.
4. Source adapters load only owned, visible, published, and consented records.
5. `RecommendationSnapshotBuilder` creates an immutable normalized snapshot.
6. `DataQualityGate` returns `insufficient_data` when required coverage or freshness is missing.
7. The orchestrator creates an idempotent pending run.
8. `RuleRecommendationEngine` generates structured items and evidence.
9. Output schema and safety validators reject malformed or unsupported claims.
10. Repository persistence stores the run, snapshot, items, evidence, and audit event in one transaction.
11. The API returns the stable learner response contract.
12. Learner feedback is appended independently.
13. In the AI phase, the model runs in shadow mode against the same snapshot and contract.
14. A failed, timed-out, rate-limited, or invalid model result falls back to the validated rule result.

## 11. Stable API Contract

The learner UI consumes provider-independent resources:

- latest recommendation;
- regeneration request and status;
- evidence/explanation detail;
- consent status;
- feedback submission.

Response items expose type, title, summary, recommended action, confidence band, evidence summaries, generated timestamp, engine label, and disclaimer. Provider request/response payloads are never exposed to the browser.

The first implementation should use learner-owned endpoints. Any required edit to shared routing or `src/**` is a separately approved cross-module change.

## 12. Error and Fallback Contract

| Condition | Public state | Required behavior |
|---|---|---|
| Required input missing | `insufficient_data` | List safe completion actions; generate nothing speculative |
| Required consent absent/revoked | `consent_required` | Block new processing |
| Source database unavailable | `source_unavailable` | Do not fabricate or silently use mock data |
| Duplicate request | Existing run/status | Enforce idempotency |
| Snapshot stale during generation | `stale_input` | Stop and permit a fresh run |
| Rule engine error | `generation_failed` | Record safe audit metadata; show retry path |
| Provider timeout | Rule result | Record fallback reason |
| Provider rate limit | Rule result | Respect retry window; do not busy-loop |
| Provider output schema invalid | Rule result | Reject model output completely |
| Provider safety validation fails | Rule result | Do not show unsafe model content |

Internal errors use safe codes and request IDs. Logs exclude secrets, raw sensitive inputs, private URLs, assessment answers where unnecessary, and provider credentials.

## 13. Program Workstreams and Gates

### Workstream 0: Governance and readiness

Deliver schema inventory, ownership matrix, protected-path guard, database backup procedure, schema snapshot, row-count baseline, and Database Change Request template.

**Gate:** no unexplained schema drift; protected paths clean; database approval process operational.

### Workstream 1: Canonical data foundation

Prepare additive schema-parity and extension migrations, but do not apply them until approved. Verify compatibility against all existing role queries and the shared schema blueprint.

**Gate:** approved DDL; staging migration twice without changes on the second run; no existing row changed or removed.

### Workstream 2: Learner persistence

Implement learner-only repositories, services, endpoints, and UI state for skills, versioned assessments, registrations, check-ins, experience, consent, and read-only published evaluations.

**Gate:** ownership and authorization matrix passes; database mode works without mock fallback; minimum staging data is traceable.

### Workstream 3: Recommendation storage

Add approved recommendation tables and transactional repository behavior.

**Gate:** idempotency, append-only history, evidence traversal, consent isolation, and audit tests pass.

### Workstream 4: Rule baseline

Implement versioned deterministic rules, data-quality thresholds, explanations, activity eligibility filters, confidence bands, and golden test cases.

**Gate:** every output has evidence; no recommendation appears from insufficient or unconsented data; baseline quality is accepted.

### Workstream 5: AI service foundation

Add provider interface, secret configuration, server-side client, timeout, bounded retry, rate limit, output validation, prompt/model registry, and rule fallback.

**Gate:** fake-provider contract tests pass; no browser/provider coupling; no secret or sensitive payload leakage.

### Workstream 6: Evaluation and shadow mode

Run model output alongside rules without showing it to learners. Measure schema validity, evidence consistency, harmful overreach, hallucination, disagreement, latency, cost, and feedback quality.

**Gate:** documented thresholds pass for the pilot dataset; safety and bias review approved.

### Workstream 7: Controlled rollout

Enable model-visible output through feature flags for staging and a limited pilot, retain rule fallback, and monitor feedback and operational metrics.

**Gate:** no cross-role regression, no privacy incident, stable fallback, accepted pilot metrics, explicit approval for broader rollout.

## 14. Database Change Request Protocol

Every database proposal must include:

1. Exact tables, columns, types, indexes, constraints, and foreign keys.
2. Classification of each object as existing canonical, new canonical, or learner-owned extension.
3. Evidence that no equivalent object already exists.
4. Read/write ownership for all four roles.
5. Queries from Teacher, School, and Enterprise that could be affected.
6. Compatibility reasoning showing old code still works.
7. Backup and restore verification steps.
8. Pre-migration schema hash, table row counts, and integrity baseline.
9. Staging dry run and repeated-run result.
10. Post-migration schema hash, unchanged-row evidence, and foreign-key checks.
11. Operational rollback using feature flags without deleting schema or data.
12. A visible `APPROVAL REQUIRED` gate before execution.

## 15. Testing Strategy

### Static scope tests

- Fail when implementation changes Teacher, School, or Enterprise paths.
- Fail when learner migration files contain `DELETE`, `DROP`, `TRUNCATE`, or destructive rename patterns.
- Fail when pages contain model calls, provider secrets, or direct SQL.

### Migration and schema tests

- Apply migrations to a disposable database cloned from the current schema.
- Apply each migration twice and require the second run to be a no-op.
- Compare existing table row counts and checksums before and after.
- Validate all new constraints and indexes.
- Run representative existing-role queries before and after migration.

### Data and authorization tests

- Use two learners to prove no cross-read or cross-write.
- Prove unpublished evaluations and hidden activities are excluded.
- Prove revoked or missing consent excludes the corresponding input category.
- Prove submitted/versioned records remain immutable.

### Rule tests

- Golden inputs produce exact structured outputs and evidence.
- Boundary scores, ties, stale inputs, missing categories, and contradictory sources behave deterministically.
- Ineligible, closed, or unverified opportunities are never recommended.
- Each rule version remains reproducible.

### Model tests

- Use a fake provider for errors, timeouts, rate limits, malformed JSON, unsafe content, and high latency.
- Validate the provider output against the domain schema.
- Confirm every model-derived claim maps to permitted evidence.
- Compare model and rule outputs in shadow mode before visible rollout.
- Test bias slices only on sufficiently sized, consented, de-identified evaluation groups.

### Regression and release tests

- Run all learner PHP and JavaScript tests.
- Run database integration tests under `APP_ENV=test` on a disposable schema.
- Run existing Teacher, School, and Enterprise smoke/read-only regression checks without modifying their code.
- Run `git diff --check`, protected-scope audit, database integrity checks, and secret scanning.

## 16. Extensibility Rules

- Each source adapter, policy, builder, engine, repository, explainer, evaluator, and provider has one responsibility.
- SQL lives only in repositories or schema tooling, never learner pages.
- Domain interfaces use normalized objects and do not expose provider payloads or table-specific arrays.
- New recommendation types extend the item type registry without changing historical rows.
- New providers implement `RecommendationProvider` without changing UI or orchestration contracts.
- New rules are independently versioned and registered; existing rule behavior is not edited retroactively.
- New snapshot fields require a new snapshot schema version and backward-compatible reader.
- Historical runs, items, evidence, snapshots, consent events, feedback, and audit events are append-only.
- Large orchestration classes must be split before they combine source loading, consent, generation, validation, persistence, and presentation responsibilities.

## 17. Readiness Definition for Model Integration

The model phase remains locked until all of the following are true:

- Student core readiness phases required by the learner roadmap are `READY`.
- Approved canonical and recommendation migrations are present in staging.
- Real staging inputs exist for skills, versioned assessments, activities/check-ins, experience, published evaluations, and consent.
- Snapshot generation is deterministic and consent-filtered.
- Rule baseline, explanations, feedback, and audit are operational.
- No AI-specific code reads or writes another learner's data.
- Provider secrets are server-side only.
- Timeout, rate limit, invalid output, safety failure, and provider outage all fall back safely.
- Shadow-mode quality and bias thresholds are documented and approved.
- The user explicitly approves enabling model-visible recommendations.

## 18. Success Criteria

The program is complete when a learner can receive a reproducible recommendation built from permitted real data, inspect why each item was generated, submit feedback, and continue receiving a safe rule result when the model is unavailable. Teacher, School, and Enterprise continue operating without code changes, existing database rows remain intact, and every shared database change is traceable to an approved non-destructive change request.
