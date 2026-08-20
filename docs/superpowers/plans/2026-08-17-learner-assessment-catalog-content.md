# Learner Assessment Catalog Content Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **Status:** Revised draft — pending Codex review
> **Date:** 2026-08-18
> **Scope:** Author, review, validate, and seed 12 age-banded question catalogs (366 prompts total) for deterministic Rule/Scoring baseline. No Gemini, no 9Router, no model-visible rollout.

**Revision notes (2026-08-18):** Replaced invalid semantic UUID prefixes with canonical UUID plus stable question-code namespaces; separated MBTI pole balance from reverse-item validation; made the MySQL migration contract explicit; made seed idempotency/hash mismatch behavior explicit; separated archive operations from insert-only seeding; aligned consent references with `learner_ai_consent_events`; and added a plan self-review gate.

**Goal:** Safely author, review, validate, and (only after explicit approval) publish 12 immutable assessment catalogs for Holland, MBTI, DISC, and Multiple Intelligence across `middle`, `high`, and `college` bands.

**Architecture:** Catalogs are authored as deterministic, reviewable PHP datasets. Validated datasets are loaded by an insert-only seeder into the canonical MySQL assessment schema. Published versions are immutable; corrections are new versions, while any archive action is a separately approved operational state transition and is never part of the seed transaction.

**Tech Stack:** Laragon PHP 8.3.30, Laragon MySQL Community Server 8.4.3, PDO, existing PHP scorer registry, SQLite in-memory contract fixtures only, SHA-256 canonical content hashes, and PHP CLI tests.

## Global Constraints

- Use only `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` for PHP commands; never XAMPP.
- Use Laragon MySQL 8.4.3 as the application/runtime database; SQLite is only for isolated in-memory tests.
- This plan does not run migrations, seeds, model calls, Gemini, or 9Router calls until the relevant review gates are explicitly approved.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`; deterministic scoring and Rule Engine remain authoritative.
- Seed operations are insert-only and idempotent; they must never update question content, submitted attempts, or published version metadata.
- A UUID is a canonical hexadecimal UUID accepted by `TalentHub\Support\Uuid::isValid()`; semantic identity is carried by `test_questions.code`, not by a UUID prefix.
- No catalog is published until the question-count decision, content review, educational review, bias/safety review, scoring review, Product Owner approval, DCR approval, and Codex review are complete.

---

## 1. Goal

Produce and safely seed 12 reviewed, age-banded assessment catalogs into the canonical TalentHub database:

- 4 frameworks × 3 education bands = 12 catalog versions
- Each catalog binds to one published `learner_assessment_versions` row
- Each version contains a frozen, versioned question set in `learner_assessment_question_versions`
- Total prompts: **366** (distribution subject to Decision Gate in Section 7)
- Driver: deterministic Rule/Scoring baseline; model-visible percentage remains `0`

---

## 2. Current Verified Baseline

### 2.1 Runtime Database

```
PHP runtime:       D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe (8.3.30)
Driver:            mysql (PDO)
Server version:    8.4.3 (Laragon MySQL Community Server)
Database:          talenthub_local
Connection:        OK
Migrations applied: 0
Migrations pending: 0
Drift:             none
```

**⚠️ BLOCKER: No shared assessment schema migration has been applied.** The canonical assessment tables (`talent_tests`, `test_questions`, `learner_assessment_versions`, `learner_assessment_question_versions`, `test_attempts`, `learner_assessment_attempt_metadata`, `learner_assessment_answers`, and `test_results`) are **not yet present** in `talenthub_local`. The assessment consent gate must use the existing `learner_ai_consent_events` contract; `privacy_consents` is not an assessment seed prerequisite unless a separate code review proves that the live assessment endpoint requires it. A **MySQL prerequisite migration** must be designed, reviewed, and applied to `talenthub_local` before any seed can proceed. This plan does not run that migration.

### 2.2 Scorer Contracts (Verified from Implementation)

| Framework | Scoring Version | Dimensions | Dimension Code Format | Reverse Suffix | Result Code |
|---|---|---|---|---|---|
| Holland | `holland-riasec-1.0` | R, I, A, S, E, C | `<dim>:+` or `<dim>:-` | `:-` | Top-3 RIASEC (e.g. `RIA`) |
| MBTI | `mbti-education-1.0` | E, I, S, N, T, F, J, P (8 poles) | `<axis>:<pole>` e.g. `EI:E`, `EI:I` | Implicit (opposite pole auto-scored) | 4-letter type (e.g. `ENTJ`) |
| DISC | `disc-education-1.0` | D, I, S, C | `<dim>:+` or `<dim>:-` | `:-` | Full rank (e.g. `ISCD`) |
| Multiple Intelligence | `multiple-intelligence-1.0` | LING, LOGI, SPAT, BODY, MUSIC, INTER, INTRA, NAT | `<dim>:+` or `<dim>:-` | `:-` | Top-3 dash-separated (e.g. `LOGI-INTER-SPAT`) |

### 2.3 Holland Scorer Detail

- **Dimension codes accepted:** `R`, `I`, `A`, `S`, `E`, `C` with optional `:+` or `:-` suffix
- **Regex:** `/\A([RIASEC])(?::([+-]))?\z/`
- **Result code:** top-3 RIASEC by normalized score; tie-break by stable order `R, I, A, S, E, C`
- **Normalize formula:** `round(((total - count) / (count * 4)) * 100)` — Likert 1-5 → 0-100

### 2.4 MBTI Scorer Detail

- **Dimension codes accepted:** `EI:E`, `EI:I`, `SN:S`, `SN:N`, `TF:T`, `TF:F`, `JP:J`, `JP:P`
- **Regex:** `/\A(EI|SN|TF|JP):([EISNTFJP])\z/`
- **Scoring:** Likert value added to stated pole; `6 - value` added to opposite pole
- **Result code:** per-axis, pole with higher score wins; exact tie defaults to first pole (`E`, `S`, `T`, `J`)
- **Axes:** `EI`, `SN`, `TF`, `JP` (stable iteration order)

### 2.5 DISC Scorer Detail

- **Dimension codes accepted:** `D`, `I`, `S`, `C` with optional `:+` or `:-` suffix
- **Regex:** `/\A([DISC])(?::([+-]))?\z/`
- **Result code:** descending by score; tie-break by stable order `D, I, S, C`

### 2.6 Multiple Intelligence Scorer Detail

- **Dimension codes accepted:** `LING`, `LOGI`, `SPAT`, `BODY`, `MUSIC`, `INTER`, `INTRA`, `NAT` with optional `:+` or `:-` suffix
- **Regex:** `/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/`
- **Result code:** top-3 dash-separated; tie-break by stable order `LING, LOGI, SPAT, BODY, MUSIC, INTER, INTRA, NAT`

### 2.7 Schema for Question Data (from catalog test fixture)

```
talent_tests:              id, code, name, type, status, createdAt, updatedAt
  code format:             {framework}_{band}  (e.g. holland_high, mbti_middle)
  type codes:              holland, mbti, disc, multiple_intelligence
  status values:            draft, published, retired

learner_assessment_versions: id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt
  scoringVersion:           holland-riasec-1.0 | mbti-education-1.0 | disc-education-1.0 | multiple-intelligence-1.0
  status values:            draft, published, archived
  schemaHash:               SHA-256 of canonical JSON containing the ordered question keys, content, options, dimension codes, required flags, and positions. Canonicalization uses UTF-8, stable object-key order, position order, and `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

test_questions:            id, testId, code, content, optionsJson, status, createdAt, updatedAt
  id: canonical hexadecimal UUID accepted by `TalentHub\Support\Uuid::isValid()`; no semantic prefix
  code: stable key such as `holland_middle_r_001`; unique within a catalog and immutable after publication
  optionsJson:              [{"value":1,"label":"..."},{"value":2,"label":"..."},{"value":3,"label":"..."},{"value":4,"label":"..."},{"value":5,"label":"..."}]
  content:                  prompt text (Vietnamese)
  status:                   published (for versioned questions); content is never updated after publication

learner_assessment_question_versions: id, versionId, questionId, position, dimensionCode, required, createdAt
  position:                 1-based sort order within attempt
  dimensionCode:            scorer-specific format (see above)
  required:                 1 = mandatory, 0 = optional

learner_assessment_attempt_metadata: id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt
  status values:            in_progress, submitted, expired

learner_assessment_answers: id, attemptId, questionId, answerJson, answeredAt
  UNIQUE(attemptId, questionId)

test_results:               id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt
```

### 2.8 Existing Files (Read-Only Reference)

- `app/learner/assessment/Scoring/HollandScorer.php` — RIASEC scorer
- `app/learner/assessment/Scoring/MbtiScorer.php` — MBTI-inspired scorer
- `app/learner/assessment/Scoring/DiscScorer.php` — DISC scorer
- `app/learner/assessment/Scoring/MultipleIntelligenceScorer.php` — MI scorer
- `app/learner/assessment/Scoring/LikertScore.php` — Likert 1-5 validation and normalization
- `app/learner/assessment/Scoring/ScorerRegistry.php` — version registry
- `app/learner/data/Database/DatabaseAssessmentWriteRepository.php` — persistence
- `tests/learner_holland_scorer_test.php` — Holland golden test
- `tests/learner_mbti_scorer_test.php` — MBTI golden test
- `tests/learner_disc_scorer_test.php` — DISC golden test
- `tests/learner_multiple_intelligence_scorer_test.php` — MI golden test
- `tests/learner_assessment_catalog_test.php` — catalog fixture schema

---

## 3. Non-Goals

- Clinical, psychiatric, or psychological diagnosis.
- Claiming these instruments are official licensed MBTI, DISC, or Holland assessments.
- Diagnostic certainty or career-path prediction.
- Model-based question generation or scoring.
- Question-bank administration UI.
- Publishing catalog without a reviewed Database Change Request.
- Running migrations or seeds without explicit approval.

---

## 4. Architecture

### 4.1 Content Authoring

Content is authored as structured PHP arrays in `Database/seeds/learner/` using preallocated, canonical hexadecimal UUIDs and stable semantic `test_questions.code` keys. Each catalog is a standalone PHP file returning a structured dataset. UUIDs are opaque identifiers; a code such as `holland_middle_r_001` is the stable identity used for review, idempotency, and content-hash manifests. Authoring uses the Education Band Resolver logic (`EducationBandResolver`) to ensure age-appropriateness.

Every catalog file must return this exact shape before it can be validated:

```php
/** @return array{
 *   metadata: array{
 *     framework: string,
 *     education_band: 'middle'|'high'|'college',
 *     scoring_version: string,
 *     question_count: int,
 *     stable_code_namespace: string,
 *     review_state: 'draft'|'content_review'|'educational_review'|'bias_review'|'scoring_review'|'approved'|'published',
 *     review_events: list<array{checkpoint:string,reviewer:string,approved_at_utc:string}>,
 *     schema_hash: ?string,
 *     advisory_disclaimer: string
 *   },
 *   questions: list<array{
 *     id: string,
 *     code: string,
 *     position: int,
 *     dimension_code: string,
 *     required: bool,
 *     content: string,
 *     options: list<array{value:int,label:string}>
 *   }>
 * } */
return ['metadata' => $metadata, 'questions' => $questions];
```

The validator rejects a dataset with missing keys, wrong enum values, a non-empty `review_events` entry that lacks a real reviewer/timestamp, or a `schema_hash` that does not match the canonical serializer.

### 4.2 Validation Pipeline

Before any seed, a validation test suite verifies:
- Dimension code format compliance per scorer
- Required/optional flag consistency
- Likert scale (1-5) for all answers
- Balanced question distribution across dimensions
- Reverse-item ratio within acceptable bounds for Holland, DISC, and Multiple Intelligence; MBTI uses pole/axis balance because its scorer automatically adds the opposite pole and does not accept a reverse suffix
- No duplicate prompts (by normalized-content SHA-256)
- No duplicate stable keys (by `test_questions.code`)
- Disclaimer presence
- Content safety (banned terms, protected groups)
- Education band consistency
- Expected row counts and deterministic canonical schema hash

### 4.3 Database Change Request (DCR)

Seeding requires a formally reviewed DCR covering:
- Target schema/database
- Prerequisite migrations
- Affected tables and expected row counts
- Stable question-code manifest and canonical UUID allocation (no non-hex UUID prefixes)
- Insert-only policy
- Transaction boundaries
- Preflight checks
- Dry-run verification
- Post-seed counts and spot checks
- Rollback/roll-forward strategy

### 4.4 Seeder Architecture

Each catalog has a content dataset; one shared seeder validates and loads all datasets:
```
Database/seeds/learner/
  Assessment/
    HollandCatalogMiddle.php
    HollandCatalogHigh.php
    HollandCatalogCollege.php
    MbtiCatalogMiddle.php
    MbtiCatalogHigh.php
    MbtiCatalogCollege.php
    DiscCatalogMiddle.php
    DiscCatalogHigh.php
    DiscCatalogCollege.php
    MultipleIntelligenceCatalogMiddle.php
    MultipleIntelligenceCatalogHigh.php
    MultipleIntelligenceCatalogCollege.php
    AbstractCatalogSeeder.php      (shared seeding logic)
    AssessmentCatalogMasterSeeder.php
```

Insertion order inside one transaction per catalog, after all content/hash checks pass in memory:
1. `talent_tests` — one published row per catalog (12 rows total)
2. `test_questions` — all questions for that catalog
3. `learner_assessment_versions` — one published version per catalog
4. `learner_assessment_question_versions` — all question-version bindings
5. Commit only after all rows and foreign-key/count checks pass; no post-commit status update is part of the seed.

---

## 5. Content Safety Principles

### 5.1 Prohibited Content

- Questions asking for: religion, ethnicity, disability, financial status, criminal history, sexual orientation, political affiliation, family medical history, protected group membership.
- Absolute career/major conclusions: "Bạn chắc chắn phù hợp với nghề X."
- Sensitive personal data collection.
- Gender stereotyping or assumptions.
- Copyrighted test items (MBTI official items, DISC official items, Holland official items).

### 5.2 Required Disclaimers

Each framework requires a specific advisory disclaimer in the result presentation and review manifest. The existing deterministic scorer summary remains its exact code contract; do not overwrite it with disclaimer text in seed data unless a separate scorer/UI change is reviewed.

| Framework | Disclaimer (VI) |
|---|---|
| Holland | "Kết quả Holland chỉ mang tính định hướng nghề nghiệp, không phải chẩn đoán tâm lý hay xác nhận nghề nghiệp." |
| MBTI | "Đây là bộ câu hỏi định hướng học tập nội bộ, không phải công cụ MBTI chính thức hay đánh giá tâm lý." |
| DISC | "Kết quả DISC chỉ mang tính tham khảo cho giao tiếp và làm việc nhóm, không phải công cụ đánh giá nhân sự." |
| Multiple Intelligence | "Định hướng đa trí thông minh giúp chọn trải nghiệm học tập, không phải chỉ số năng lực hay chẩn đoán." |

### 5.3 Language and Accessibility

- Vietnamese language throughout; terminology simplified per education band.
- Middle: simple vocabulary, concrete examples, short prompts (max 60 characters prompt body).
- High: moderate vocabulary, abstract concepts introduced, max 80 characters.
- College: academic-adjacent vocabulary, career application context, max 100 characters.
- No double negatives in reverse-scored items.
- Avoid near-duplicate prompts across questions.

---

## 6. Authoritative Contracts

### 6.1 Assessment Type Codes

```
holland
mbti
disc
multiple_intelligence
```

### 6.2 Education Band Codes

```
middle    — grades 6–9
high      — grades 10–12
college   — vocational/university students
```

### 6.3 Banded Assessment Codes (Test Codes)

```
holland_middle
holland_high
holland_college
mbti_middle
mbti_high
mbti_college
disc_middle
disc_high
disc_college
multiple_intelligence_middle
multiple_intelligence_high
multiple_intelligence_college
```

### 6.4 Scoring Version Codes

```
holland-riasec-1.0
mbti-education-1.0
disc-education-1.0
multiple-intelligence-1.0
```

### 6.5 Version Publication State

```
draft      — not available to learners
published  — available to learners
archived   — replaced by newer version, old attempts remain valid
```

### 6.6 Retake Compatibility

- Old submitted attempts remain linked to their original version indefinitely.
- New attempts always use the newest **published** version at time of creation.
- Version `learner_assessment_versions.status = 'archived'` is never selected for new attempts.

### 6.7 Answer Scale

All questions use a 5-point Likert scale:

```json
[
  {"value": 1, "label": "Hoàn toàn không đồng ý"},
  {"value": 2, "label": "Không đồng ý"},
  {"value": 3, "label": "Bình thường"},
  {"value": 4, "label": "Đồng ý"},
  {"value": 5, "label": "Hoàn toàn đồng ý"}
]
```

Labels are fixed for all frameworks and bands. Prompt content changes per framework and band.

### 6.8 Required vs Optional

- `required = 1`: learner must answer before submit; unanswered required = exception thrown by scorer
- `required = 0`: optional; unanswered = skipped, dimension gets 0 contribution
- All prompts in the baseline catalog are `required = 1` (no optional questions)

---

## 7. Question-Count Decision Gate

### 7.1 Master Plan Reference

The master plan (`2026-08-17-learner-hybrid-ai-assessment-platform.md`) specifies "366 prompts total" but does **not** define an authoritative distribution across frameworks or bands.

### 7.2 Constraints for Distribution

1. **Scorer balance:** Each dimension must receive enough questions to produce reliable normalized scores.
2. **Reverse-item coverage:** Each dimension should have at least one reverse-scored item.
3. **Total = 366:** Sum of all 12 catalogs must equal 366.
4. **Per-catalog sanity:** A catalog must have at least enough questions to cover all dimensions.

### 7.3 Minimum Questions Per Framework (Derived from Scorer Requirements)

| Framework | Dimensions | Minimum coverage rule | Recommended |
|---|---|---|---|
| Holland | 6 (R,I,A,S,E,C) | 12 (2 per dim, 1 reverse each) | 30 |
| MBTI | 8 poles (4 axes × 2 poles) | 8 (1 per pole) | 32 |
| DISC | 4 (D,I,S,C) | 8 (2 per dim, 1 reverse each) | 28 |
| Multiple Intelligence | 8 (LING,LOGI,SPAT,BODY,MUSIC,INTER,INTRA,NAT) | 16 (2 per dim, 1 reverse each) | 32 |

### 7.4 Proposed Distributions (Three Options)

#### Option A: Balanced (Recommended, pending approval)
```
Holland:           30 × 3 bands =  90
MBTI:              32 × 3 bands =  96
DISC:              28 × 3 bands =  84
Multiple Int.:     32 × 3 bands =  96
                              TOTAL = 366 ✓
```

#### Option B: Equal per Framework
```
Each framework:   366 ÷ 4 = 91.5 → not divisible evenly
→ 91 + 91 + 92 + 92 = 366
→ Per band: 91÷3 ≈ 30.3 or 92÷3 ≈ 30.7 — awkward per-band split
→ NOT RECOMMENDED
```

#### Option C: Holland-Heavy
```
Holland:           36 × 3 = 108  (RIASEC needs more coverage)
MBTI:              28 × 3 =  84
DISC:              28 × 3 =  84
Multiple Int.:     30 × 3 =  90
                              TOTAL = 366 ✓
```

### 7.5 ⚠️ DECISION REQUIRED

**Product owner must approve one option before content authoring begins.** Options A and C both produce valid 366 totals with different pedagogical trade-offs:

- **Option A (Balanced):** Each framework gets roughly its historically recommended count; MBTI and MI get slightly more than DISC for better axis/dimension coverage.
- **Option C (Holland-Heavy):** Holland is the primary career-orientation tool; RIASEC benefits from more items per dimension.

**Recommendation:** Option A for initial baseline; increase Holland items in v2.0 if analytics show insufficient RIASEC separation. This is only a recommendation, not an approval.

**Decision required by:** Product Owner before the first content-authoring task (Tasks 4–7) begins. Tasks 1–3 may build validators against synthetic fixtures, but they must not create or publish real catalogs until the decision is recorded.

Record the decision in the DCR and the catalog manifest using this exact shape:

```yaml
question_count_decision:
  option: A
  holland_per_band: 30
  mbti_per_band: 32
  disc_per_band: 28
  multiple_intelligence_per_band: 32
  total_questions: 366
  approved_by: NCnguyenn (Product Owner)
  approved_at_utc: 2026-08-19T05:38:16Z
```

The block above is the Option A shape only. Both approval fields remain `null` until the Product Owner records the decision with the real UTC timestamp; no implementation task may assume approval from the recommendation alone.

---

## 8. Catalog / Version Matrix

The matrix below is the **proposed Option A baseline only**. It is not an authorization to author or seed content. The Product Owner decision in Section 7 must select and record the final counts before Tasks 4–7 begin. If Option C is selected, the validator and all task row-count assertions must be updated before content is created.

| Framework | Band | Test Code | Scoring Version | Questions | Reverse/Balance Rule | Stable Code Namespace |
|---|---|---|---|---|---|---|
| Holland | middle | `holland_middle` | `holland-riasec-1.0` | 30 | 2/3 reverse per dimension | `holland_middle_*` |
| Holland | high | `holland_high` | `holland-riasec-1.0` | 30 | 2/3 reverse per dimension | `holland_high_*` |
| Holland | college | `holland_college` | `holland-riasec-1.0` | 30 | 2/3 reverse per dimension | `holland_college_*` |
| MBTI | middle | `mbti_middle` | `mbti-education-1.0` | 32 | 4 items per pole | `mbti_middle_*` |
| MBTI | high | `mbti_high` | `mbti-education-1.0` | 32 | 4 items per pole | `mbti_high_*` |
| MBTI | college | `mbti_college` | `mbti-education-1.0` | 32 | 4 items per pole | `mbti_college_*` |
| DISC | middle | `disc_middle` | `disc-education-1.0` | 28 | 3/4 reverse per dimension | `disc_middle_*` |
| DISC | high | `disc_high` | `disc-education-1.0` | 28 | 3/4 reverse per dimension | `disc_high_*` |
| DISC | college | `disc_college` | `disc-education-1.0` | 28 | 3/4 reverse per dimension | `disc_college_*` |
| Multiple Int. | middle | `multiple_intelligence_middle` | `multiple-intelligence-1.0` | 32 | 2/2 reverse per dimension | `multiple_intelligence_middle_*` |
| Multiple Int. | high | `multiple_intelligence_high` | `multiple-intelligence-1.0` | 32 | 2/2 reverse per dimension | `multiple_intelligence_high_*` |
| Multiple Int. | college | `multiple_intelligence_college` | `multiple-intelligence-1.0` | 32 | 2/2 reverse per dimension | `multiple_intelligence_college_*` |

**Total rows:**
- `talent_tests`: 12
- `test_questions`: 366 with 366 valid UUID IDs and 366 unique stable codes
- `learner_assessment_versions`: 12
- `learner_assessment_question_versions`: 366

---

## 9. Validation Matrix

### 9.1 Pre-Seed Automated Tests

Each test file targets one framework × all three bands.

#### 9.1.1 Content Schema Validator

**File:** `tests/learner_catalog_content_validator.php` (new)

Validates each catalog PHP file against:

| Check | Rule | Expected Failure |
|---|---|---|
| Dimension code valid | Matches scorer regex | RuntimeException from scorer |
| All dimensions covered | Each scorer dimension appears ≥2 times | Assertion failure |
| Reverse/balance rule | Holland/DISC/MI: 40%–60% reversed per dimension; MBTI: four items per pole and no suffix | Assertion failure |
| Likert options | Exactly 5 options, values 1–5 | Assertion failure |
| Required flag | All questions `required = 1` for baseline | Assertion failure |
| Content length | Prompt ≤ max chars per band | Assertion failure |
| Duplicate prompt | SHA-256 content hash unique | Assertion failure |
| Duplicate stable key | `test_questions.code` unique per catalog | Assertion failure |
| Sort order | `position` 1..N without gaps > 2 | Assertion failure |
| Education band | `metadata.education_band` matches the catalog/test-code suffix | Assertion failure |
| Disclaimer present | Summary field non-empty | Assertion failure |
| UTF-8 encoding | All strings valid UTF-8 | Assertion failure |
| Banned terms | None of the prohibited terms | Assertion failure |
| Protected groups | No questions referencing protected groups | Assertion failure |

#### 9.1.2 Scorer Integration Validator

**File:** `tests/learner_catalog_scorer_integration_test.php` (new)

For each catalog:

1. Load all questions and answers from the catalog PHP file.
2. Run the catalog through the corresponding scorer.
3. Assert:
   - No RuntimeException from scorer (all dimension codes valid)
   - `result_code` format matches expected pattern
   - All dimension scores in range 0–100
   - No missing dimension scores
   - Dimension count matches expected dimensions
   - `summary` matches the exact existing scorer contract for that framework
   - the catalog review manifest contains the required advisory disclaimer for the UI layer
   - Scoring is deterministic (run twice, same result)

#### 9.1.3 Cross-Catalog Consistency Validator

**File:** `tests/learner_catalog_cross_consistency_test.php` (new)

- All 12 catalogs produce valid results against their respective scorers.
- Holland across bands produces the same dimension count (6).
- MBTI across bands produces the same pole count (8).
- DISC across bands produces the same dimension count (4).
- MI across bands produces the same dimension count (8).
- All dimension codes are uppercase and match scorer regex.
- All IDs pass `TalentHub\Support\Uuid::isValid()`; no semantic UUID prefix is permitted.
- Stable question codes are unique and namespace-correct.
- Schema hash is deterministic for identical canonical question sets.

### 9.2 Post-Seed Automated Tests

#### 9.2.1 Database Persistence Test

**File:** `tests/learner_assessment_catalog_seed_test.php` (new, extends existing)

After seeder runs on test database:
- All 12 `talent_tests` rows exist with correct codes and types.
- All 366 `test_questions` rows exist with published status.
- All 12 `learner_assessment_versions` rows are published.
- All 366 `learner_assessment_question_versions` bindings are present.
- Schema hash matches computed hash of question set.
- Attempt start/resume works for each catalog (SQLite fixture).

#### 9.2.2 Immutability Test

**File:** `tests/learner_assessment_published_immutability_test.php` (new)

- Seeder/repository paths cannot update published version content or bindings; if no MySQL trigger is introduced, the test reports application-level immutability rather than a database-level UPDATE rejection.
- Question content in published version is frozen at version creation time.
- Archived versions are never selected for new attempts.

### 9.3 Manual Safety Review Checklist

Before each catalog is marked `published` in the seeder, a human reviews:

- [ ] No prompt asks for protected group information
- [ ] No prompt uses absolute career/job certainty language
- [ ] Holland/DISC/MI reverse-scored items read naturally (no double negatives); MBTI paired-pole items are not labeled as reverse items
- [ ] Language is age-appropriate for the target band
- [ ] No copyright-infringing content from proprietary instruments
- [ ] Disclaimer is present and accurate
- [ ] Balance indicator: Holland/DISC/MI dimensions stay within the documented reverse range; MBTI has four items per pole
- [ ] Content does not contain harmful stereotypes
- [ ] No questions about income, family wealth, or financial status
- [ ] No questions about mental health diagnoses or medical conditions

---

## 10. Review and Approval Workflow

### 10.1 State Machine

```
draft → content_review → educational_review → bias_review → scoring_review → approved → published
```

### 10.2 Checkpoint Definitions

| Checkpoint | Owner | Evidence Required |
|---|---|---|
| **Content Review** | Author | Self-review checklist signed; all prompts pass schema validator |
| **Educational Review** | Educational content reviewer | Age-appropriateness sign-off per band; no banned terms |
| **Bias & Safety Review** | Bias reviewer | No protected-group questions; no stereotypes; safe language |
| **Scoring Contract Review** | Codex | Scorer integration test passes; dimension codes verified |
| **Product Owner Approval** | Product Owner | Decision Gate (Option A/B/C) approved; row-count confirmed |
| **Codex Schema Review** | Codex | DCR reviewed and approved; migration/dry-run evidence provided |
| **Published** | System | Seed run on approved disposable database; post-seed counts verified |

### 10.3 Per-Catalog Review Tracking

Each catalog file includes machine-readable metadata returned with the dataset; review evidence is stored in the DCR, not as unresolved placeholders in source:

```php
$metadata = [
    'framework' => 'holland',
    'education_band' => 'middle',
    'scoring_version' => 'holland-riasec-1.0',
    'question_count' => 30,
    'stable_code_namespace' => 'holland_middle_',
    'review_state' => 'draft',
    'review_events' => [],
    'schema_hash' => null,
];
```

The validator rejects `review_state = published` unless the DCR contains all required review events and the computed `schema_hash` matches the dataset. The seeder writes only approved metadata and never fabricates reviewer identities or dates.

---

## 11. Database Change Request (DCR) Workflow

### 11.1 DCR Document Structure

Before any seed, a DCR file must be created and reviewed:

```
docs/superpowers/dcr/
  2026-08-18-learner-assessment-catalog-seed-dcr.md
```

### 11.2 Required DCR Sections

1. **Schema/Database:** `talenthub_local`, driver `mysql`
2. **Prerequisite migrations:** List all migrations that must be applied first (assessment canonical schema)
3. **Affected tables:** `talent_tests`, `test_questions`, `learner_assessment_versions`, `learner_assessment_question_versions`
4. **Row counts per table:**
   - `talent_tests`: 12 new rows
   - `test_questions`: 366 new rows
   - `learner_assessment_versions`: 12 new rows
   - `learner_assessment_question_versions`: 366 new rows
5. **Stable identity strategy:** UUIDs are canonical hexadecimal IDs allocated in a manifest; stable semantic identity is `test_questions.code` (for example `holland_middle_r_001`). No non-hex UUID prefix is permitted.
6. **Insert-only policy:** No UPDATE or DELETE on seeded rows; archive is a separately approved state transition outside the seed transaction
7. **Transaction boundaries:** Each catalog in its own transaction; rollback catalog on failure
8. **Preflight checks:** Schema exists, no conflicting published version for the same `(testCode, version)`, stable codes and UUIDs are unused or match the same content hash, and all prerequisites are applied
9. **Dry-run:** Run against a newly created disposable MySQL 8.4 database, never directly against `talenthub_local`
10. **Expected content hashes:** SHA-256 of each catalog's question set documented in DCR
11. **Duplicate prevention:** UNIQUE constraints on `talent_tests.code`, `(testId, code)` for questions, `(versionId, questionId)`, and `(versionId, position)`; rerun with the same hash is a no-op and a hash mismatch fails closed
12. **Post-seed counts:** Exact row counts per table after seed
13. **Post-seed spot checks:** 3 random questions from each catalog verified against source
14. **Disable/retire strategy:** Version `status = 'archived'` to disable without deleting history
15. **Archive strategy:** A separately approved operational transition may archive an erroneous version only after a corrected published version exists; the seed itself never restores or rewrites a published version
16. **Roll-forward strategy:** Publish new version with incremented version number
17. **Backup evidence:** Snapshot of disposable database before and after seed
18. **Approvals required:** Product Owner + Codex (schema/contract reviewer)
19. **Permitted execution window:** Only after DCR is reviewed, approved, and committed; never during peak hours

### 11.3 DCR Review Checklist

- [ ] All prerequisite migrations identified and documented
- [ ] Row counts match catalog matrix (Section 8)
- [ ] Canonical UUID manifest and stable-code namespace reviewed for collision avoidance
- [ ] Content hashes match source files
- [ ] Transaction boundaries are correct
- [ ] Rollback/roll-forward strategy is safe and reversible
- [ ] No destructive SQL (DELETE, TRUNCATE) in seeder
- [ ] Post-seed test assertions match expected counts
- [ ] Product Owner and Codex have signed off

---

## 12. Task-by-Task Implementation

### Task 1: Assessment Canonical Schema Migration

**Goal:** Create and apply the prerequisite migration that establishes the assessment canonical tables in the target database.

**Files created:** `Database/migrations/20260818000100_create_learner_assessment_schema.php`

**Files NOT modified:** Any existing code, test, or seed files.

**Interfaces:** Returns the repository's `AbstractMigration` anonymous class and creates the MySQL 8.4 tables in Section 2.7. It must use the existing `MigrationContext`/`MigrationRunner` contract and the version `20260818000100`.

**Steps:**
1. Translate the SQLite fixture into explicit MySQL 8.4 DDL; do not copy SQLite syntax blindly. Use `CHAR(36)` canonical UUIDs, `VARCHAR` codes, `JSON` only where the existing repository expects JSON-compatible text, UTC `DATETIME(6)` values, `utf8mb4`, foreign keys, unique keys, and indexes used by the repository queries.
2. Add preflight checks for the required parent tables and fail closed when a conflicting table/column definition exists.
3. Define and test the exact unique constraints for `talent_tests.code`, `(testId, code)`, `(versionId, questionId)`, and `(versionId, position)`.
4. Add CHECK constraints only where MySQL 8.4 enforces the same values used by the repository (`status`, `type`, `required`, and JSON shape); document application-level checks where MySQL cannot enforce the contract.
5. Apply to a **disposable** database only through the existing migration runner; do not run `bin/migrate.php` against `talenthub_local` in this task.
6. Document preflight checks, migration registry version, and no-op second-run behavior.

**RED:** Run migration on empty schema; expect tables created.

**GREEN:** Migration applied successfully; second run is a no-op; row counts 0 in all tables.

**Regression:** Run `tests/learner_assessment_catalog_test.php` — SQLite fixture still works.

**Lint:** `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l Database\migrations\20260818000100_create_learner_assessment_schema.php`

**Safety:** Migration is additive only; no `DROP`, `DELETE`, `TRUNCATE`, destructive `down()` path, or data rewrite. The migration must not create seed rows or consent rows.

**Commit boundary:** `git add Database\migrations\20260818000100_create_learner_assessment_schema.php`

**Commit message:** `Database(learner): add assessment canonical schema migration`

---

### Task 2: Content Schema Validator

**Goal:** Build the automated validation test suite that verifies all catalogs before authoring begins.

**Files created:** `tests/learner_catalog_content_validator.php`

**Files NOT modified:** Any existing production code, migration, or seed.

**Interfaces:** Reads catalog PHP files; outputs validation report.

**Steps:**
1. Create complete synthetic fixtures for all four frameworks (not a 2-question Holland fixture): every required dimension/pole appears at least twice, and each framework exercises its real reverse/balance rule.
2. Assert dimension code format against the exact scorer contract; MBTI must reject `:+`/`:-` and validate axis/pole coverage instead.
3. Assert stable `test_questions.code` uniqueness and canonical hexadecimal UUID validity separately.
4. Assert reverse-item ratio only for Holland, DISC, and Multiple Intelligence; assert 4 items per MBTI pole.
5. Assert Likert options format and fixed option values 1–5.
6. Assert content length limits, UTF-8 validity, banned terms, disclaimer and education-band metadata.
7. Assert no duplicate normalized-content hashes within or across catalogs.
8. Assert canonical schema hash is stable for the same ordered dataset and changes when content, options, position, dimension or required flag changes.
9. Run valid and intentionally invalid fixtures; expect valid fixtures PASS and each invalid fixture to fail for the documented reason.

**RED:** Validator fails on synthetic fixture (class not found, regex mismatch, etc.).

**GREEN:** Validator passes synthetic fixture with correct assertions.

**Regression:** Existing scorer tests still pass.

**Lint:** PHP lint on new test file.

**Safety checks:**
- Read-only file access (no DB writes)
- No API calls
- No environment changes

**Commit boundary:** `git add tests/learner_catalog_content_validator.php`

**Commit message:** `test(learner): add catalog content schema validator`

---

### Task 3: Scorer Integration Validator

**Goal:** Build automated tests that load each catalog and verify it scores correctly against the implementation.

**Files created:** `tests/learner_catalog_scorer_integration_test.php`

**Files NOT modified:** Scorer implementations, bootstrap, or existing tests.

**Interfaces:** Loads catalog PHP files; runs scorer; asserts output format.

**Steps:**
1. Build a minimal synthetic Holland catalog with 12 questions (2 per RIASEC dimension) for initial validation.
2. Run synthetic catalog through `HollandScorer`.
3. Assert: no RuntimeException, result_code format, all scores 0–100, all 6 dimensions present.
4. Extend framework to MBTI, DISC, MI fixtures with known answer patterns.
5. Assert deterministic: run twice, same result.

**RED:** Scorer throws on synthetic fixture.

**GREEN:** All synthetic catalogs score correctly.

**Codex trigger:** This test provides evidence for the Scoring Contract Review checkpoint.

**Commit boundary:** `git add tests/learner_catalog_scorer_integration_test.php`

**Commit message:** `test(learner): add scorer integration validator for catalogs`

---

### Task 4: Holland Catalog Content (Middle, High, College)

**Goal:** Author all 3 Holland catalogs using the Product Owner-approved distribution from Section 7; Option A is only the recommendation until that decision is recorded.

**Decision Gate:** Product Owner must approve Option A/B/C before this task begins.

**Files created:**
- `Database/seeds/learner/Assessment/HollandCatalogMiddle.php`
- `Database/seeds/learner/Assessment/HollandCatalogHigh.php`
- `Database/seeds/learner/Assessment/HollandCatalogCollege.php`

**Files NOT modified:** Any existing production code, scorer, test, or migration.

**Content constraints:**
- 30 questions per band (total 90)
- 6 RIASEC dimensions × ~5 questions each
- ~50% reverse-scored items per dimension
- Prompts: Vietnamese, max 60/80/100 chars for middle/high/college
- Dimension codes: `R`, `I`, `A`, `S`, `E`, `C` with `:+` or `:-` suffix
- `required = 1` for all questions
- Question IDs: preallocated canonical UUIDs only; no semantic prefix is allowed.
- Stable question codes: `holland_middle_{dimension}_{position:03d}` (and corresponding `high`/`college` namespaces); the code is the idempotency and review key.

**Content review checkpoints:**
- [ ] Author self-review
- [ ] Educational review (age-appropriateness)
- [ ] Bias/safety review

**Validation:**
- Run `tests/learner_catalog_content_validator.php` — expect all Holland assertions pass.
- Run `tests/learner_catalog_scorer_integration_test.php` — expect Holland scorer integration passes.

**Safety:** Read-only content; no DB writes.

**Commit boundary:** `git add Database/seeds/learner/Assessment/HollandCatalog*.php`

**Commit message:** `seed(learner): add Holland assessment catalogs (middle, high, college)`

---

### Task 5: MBTI Catalog Content (Middle, High, College)

**Goal:** Author all 3 MBTI catalogs using the Product Owner-approved distribution from Section 7; do not begin until the decision gate is recorded.

**Files created:**
- `Database/seeds/learner/Assessment/MbtiCatalogMiddle.php`
- `Database/seeds/learner/Assessment/MbtiCatalogHigh.php`
- `Database/seeds/learner/Assessment/MbtiCatalogCollege.php`

**Files NOT modified:** Any existing production code, scorer, test, or migration.

**Content constraints:**
- 32 questions per band (total 96)
- 4 axes × 8 poles; ~4 questions per pole
- Dimension codes: `EI:E`, `EI:I`, `SN:S`, `SN:N`, `TF:T`, `TF:F`, `JP:J`, `JP:P`
- `required = 1` for all questions
- Question IDs: preallocated canonical hexadecimal UUIDs only; no semantic prefix is allowed.
- Stable question codes: `mbti_middle_{axis}_{pole}_{position:03d}` (and corresponding `high`/`college` namespaces).
- Disclaimer: MBTI-specific (see Section 5.2)

**Content review checkpoints:**
- [ ] Author self-review
- [ ] Educational review
- [ ] Bias/safety review

**Validation:**
- Run `tests/learner_catalog_content_validator.php`
- Run `tests/learner_catalog_scorer_integration_test.php` — MBTI scorer integration

**Safety:** Read-only content.

**Commit boundary:** `git add Database/seeds/learner/Assessment/MbtiCatalog*.php`

**Commit message:** `seed(learner): add MBTI assessment catalogs (middle, high, college)`

---

### Task 6: DISC Catalog Content (Middle, High, College)

**Goal:** Author all 3 DISC catalogs using the Product Owner-approved distribution from Section 7; do not begin until the decision gate is recorded.

**Files created:**
- `Database/seeds/learner/Assessment/DiscCatalogMiddle.php`
- `Database/seeds/learner/Assessment/DiscCatalogHigh.php`
- `Database/seeds/learner/Assessment/DiscCatalogCollege.php`

**Files NOT modified:** Any existing production code, scorer, test, or migration.

**Content constraints:**
- 28 questions per band (total 84)
- 4 DISC dimensions × ~7 questions each
- ~50% reverse-scored items per dimension
- Dimension codes: `D`, `I`, `S`, `C` with `:+` or `:-` suffix
- `required = 1` for all questions
- Question IDs: preallocated canonical hexadecimal UUIDs only; no semantic prefix is allowed.
- Stable question codes: `disc_middle_{dimension}_{position:03d}` (and corresponding `high`/`college` namespaces).
- Disclaimer: DISC-specific (see Section 5.2)

**Content review checkpoints:**
- [ ] Author self-review
- [ ] Educational review
- [ ] Bias/safety review

**Validation:**
- Run `tests/learner_catalog_content_validator.php`
- Run `tests/learner_catalog_scorer_integration_test.php` — DISC scorer integration

**Safety:** Read-only content.

**Commit boundary:** `git add Database/seeds/learner/Assessment/DiscCatalog*.php`

**Commit message:** `seed(learner): add DISC assessment catalogs (middle, high, college)`

---

### Task 7: Multiple Intelligence Catalog Content (Middle, High, College)

**Goal:** Author all 3 MI catalogs using the Product Owner-approved distribution from Section 7; do not begin until the decision gate is recorded.

**Files created:**
- `Database/seeds/learner/Assessment/MultipleIntelligenceCatalogMiddle.php`
- `Database/seeds/learner/Assessment/MultipleIntelligenceCatalogHigh.php`
- `Database/seeds/learner/Assessment/MultipleIntelligenceCatalogCollege.php`

**Files NOT modified:** Any existing production code, scorer, test, or migration.

**Content constraints:**
- 32 questions per band (total 96)
- 8 MI dimensions × ~4 questions each
- ~50% reverse-scored items per dimension
- Dimension codes: `LING`, `LOGI`, `SPAT`, `BODY`, `MUSIC`, `INTER`, `INTRA`, `NAT` with `:+` or `:-` suffix
- `required = 1` for all questions
- Question IDs: preallocated canonical hexadecimal UUIDs only; no semantic prefix is allowed.
- Stable question codes: `multiple_intelligence_middle_{dimension}_{position:03d}` (and corresponding `high`/`college` namespaces).
- Disclaimer: MI-specific (see Section 5.2)

**Content review checkpoints:**
- [ ] Author self-review
- [ ] Educational review
- [ ] Bias/safety review

**Validation:**
- Run `tests/learner_catalog_content_validator.php`
- Run `tests/learner_catalog_scorer_integration_test.php` — MI scorer integration

**Safety:** Read-only content.

**Commit boundary:** `git add Database/seeds/learner/Assessment/MultipleIntelligenceCatalog*.php`

**Commit message:** `seed(learner): add Multiple Intelligence assessment catalogs (middle, high, college)`

---

### Task 8: Cross-Catalog Consistency Validator

**Goal:** Build automated tests verifying consistency across all 12 catalogs.

**Files created:** `tests/learner_catalog_cross_consistency_test.php`

**Files NOT modified:** Any existing production code, migration, or seed.

**Validation:**
- All 12 catalogs produce valid scorer results.
- Holland across bands: 6 dimensions, top-3 code format.
- MBTI across bands: 8 poles, 4-letter type.
- DISC across bands: 4 dimensions, full rank format.
- MI across bands: 8 dimensions, top-3 dash-separated.
- All dimension codes uppercase and regex-compliant.
- All IDs pass `TalentHub\Support\Uuid::isValid()`; no non-hex semantic prefix is permitted.
- All stable question codes are unique, namespace-correct, and immutable.
- Schema hash is deterministic from the canonical serialization contract in Section 2.7.

**Codex trigger:** Provides evidence for Scoring Contract Review.

**Commit boundary:** `git add tests/learner_catalog_cross_consistency_test.php`

**Commit message:** `test(learner): add cross-catalog consistency validator`

---

### Task 9: Database Change Request Document

**Goal:** Create the formal DCR for the catalog seed operation.

**Files created:** `docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md`

**Files NOT modified:** Any production code, test, migration, or seed.

**Content:** All sections specified in Section 11.2.

**Reviewers:** Product Owner + Codex.

**Evidence required:**
- Preflight checks on disposable database
- Dry-run output with row counts
- Content hashes for each catalog
- canonical UUID-to-stable-code manifest (UUIDs have no semantic prefix)

**Commit boundary:** `git add docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md`

**Commit message:** `docs(learner): add DCR for assessment catalog seed`

---

### Task 10: Abstract Catalog Seeder and Master Seeder

**Goal:** Build the shared seeder infrastructure and master seeder that orchestrates all 12 catalogs.

**Files created:**
- `Database/seeds/learner/Assessment/AbstractCatalogSeeder.php`
- `Database/seeds/learner/AssessmentCatalogMasterSeeder.php`

**Files NOT modified:** Any existing production code, migration, or test.

**Responsibilities:**
- Transaction per catalog
- Rollback an uncommitted catalog transaction on failure; never delete or rewrite committed history
- Content hash computation using the canonical serialization in Section 2.7
- Progress logging
- validation of preallocated canonical UUIDs and stable-code manifest; the seeder must not synthesize semantic UUID prefixes
- Idempotency: same `(testCode, version, questionCode, schemaHash)` is a no-op; same key with a different hash fails closed; no duplicate rows are created on rerun

**Validation:**
- Dry-run on disposable database produces exact row counts from Section 8.
- No existing rows modified.
- All 12 catalogs seeded in order.

**Commit boundary:** `git add Database/seeds/learner/Assessment/AbstractCatalogSeeder.php Database/seeds/learner/AssessmentCatalogMasterSeeder.php`

**Commit message:** `seed(learner): add abstract catalog seeder and master seeder`

---

### Task 11: Published Immutability Tests

**Goal:** Ensure published versions cannot be modified after seed.

**Files created:** `tests/learner_assessment_published_immutability_test.php`

**Files NOT modified:** Any existing production code, migration, or seed.

**Validation:**
- The application seeder and repository never update question content or a published version's `scoringVersion`, `schemaHash`, question bindings, or publication timestamp.
- A published version is frozen by contract; if database-level triggers are not introduced by the schema migration, the test must explicitly label this as application-level immutability rather than claiming MySQL rejects UPDATE.
- Archived versions are never selected for new attempts.
- Archive is a separately approved state transition and is not executed by the insert-only seed transaction.

**Commit boundary:** `git add tests/learner_assessment_published_immutability_test.php`

**Commit message:** `test(learner): add published version immutability tests`

---

### Task 12: Dry-Run on Disposable Database

**Goal:** Execute the seeder on a disposable clone and verify all post-seed checks pass.

**Database:** a newly created disposable MySQL database such as `talenthub_assessment_catalog_verify_20260818`; do not reuse an AI backup fixture unless its schema and ownership are explicitly verified.

**Files NOT modified:** Shared `talenthub_local`.

**Steps:**
1. Create the disposable database with the same MySQL 8.4 charset/collation and no application data.
2. Apply prerequisite migration(s) to the disposable database through the existing migration runner.
3. Run the master seeder.
4. Verify post-seed counts:
   - `talent_tests`: 12 rows
   - `test_questions`: 366 rows
   - `learner_assessment_versions`: 12 rows (all `status = 'published'`)
   - `learner_assessment_question_versions`: 366 rows
5. Spot-check 3 random questions per catalog.
6. Verify schema hash matches documented hash in DCR.
7. Run all validator tests against the seeded clone.
8. Verify no existing rows were modified.

**Evidence:** Capture output and store in DCR evidence section.

**Safety:** Disposable database only; `talenthub_local` unchanged.

---

### Task 13: Seed to Shared Database (After DCR Approval)

**Goal:** Execute the approved seeder on `talenthub_local` after all checkpoints pass.

**Prerequisites (all must be checked):**
- [ ] Task 1: Migration applied to `talenthub_local`
- [ ] Task 2–8: All validators pass
- [ ] Task 9: DCR reviewed and approved by Product Owner + Codex
- [ ] Task 12: Dry-run evidence captured
- [ ] All review checkpoints (Section 10.2) complete for all 12 catalogs
- [ ] TALENTHUB_AI_VISIBLE_PERCENT remains 0

**Execution:** Only after explicit approval from Product Owner and Codex.

**Safety:** Full backup of `talenthub_local` before seed.

---

### Task 14: Post-Seed Verification

**Goal:** Verify the shared database seed was successful and all tests pass.

**Steps:**
1. Connect `tests/learner_assessment_catalog_seed_test.php` to the seeded `talenthub_local`.
2. Assert row counts match Section 8.
3. Run all scorer tests against the seeded database.
4. Run catalog service tests against the seeded database.
5. Verify assessment discovery shows all 12 catalogs.
6. Run assessment API tests against the seeded database.

**Evidence:** Capture output and update `learner-ai-release-checklist.md`.

---

### Task 15: Record Evidence and Update Readiness

**Goal:** Document the completed seed and update the release checklist.

**Files modified:** `docs/superpowers/readiness/learner-ai-release-checklist.md`

**Content added:**
- Seed execution date and database
- Row counts verified
- All validators passed
- DCR reference
- Remaining blockers (if any)

**Commit boundary:** `git add docs/superpowers/readiness/learner-ai-release-checklist.md`

**Commit message:** `docs(learner): record assessment catalog seed evidence`

---

## 13. Verification / Release Gate

### 13.1 Pre-Seed Gate

All of the following must be green before Task 13 (seed to shared DB):

- [ ] `tests/learner_catalog_content_validator.php` — all 12 catalogs pass
- [ ] `tests/learner_catalog_scorer_integration_test.php` — all 12 catalogs score correctly
- [ ] `tests/learner_catalog_cross_consistency_test.php` — all 12 catalogs consistent
- [ ] `tests/learner_assessment_published_immutability_test.php` — pass
- [ ] `tests/learner_assessment_catalog_test.php` — SQLite fixture still passes
- [ ] All existing scorer tests pass (holland, mbti, disc, multiple_intelligence)
- [ ] All existing assessment API tests pass
- [ ] PHP lint on all new files passes
- [ ] `git diff --check` — no whitespace errors
- [ ] DCR approved by Product Owner and Codex
- [ ] Product Owner approved Question Count Decision Gate (Option A/B/C)

### 13.2 Post-Seed Gate

- [ ] Row counts match Section 8 exactly
- [ ] Schema hashes match documented hashes
- [ ] Spot-checks of 3 random questions per catalog pass
- [ ] All scorer integration tests pass against seeded database
- [ ] All catalog service tests pass against seeded database
- [ ] Assessment discovery page shows all 12 catalogs
- [ ] Assessment start/resume works for each catalog (integration test)

---

## 14. Rollback / Roll-Forward Strategy

### 14.1 Post-publication archive (separate operational action, not seed rollback)

If a seeded catalog has errors discovered after publication, the seed itself is not rolled back and no question/history row is rewritten:

1. **Do NOT DELETE** any rows.
2. **Do NOT UPDATE** question content or historical attempt bindings.
3. After Product Owner + Codex approval, archive the erroneous version as a controlled status transition. This is the only permitted post-publication update and is executed by a separately reviewed operational command, never by the insert-only seeder:
   ```sql
   UPDATE learner_assessment_versions
   SET status = 'archived'
   WHERE testId = (SELECT id FROM talent_tests WHERE code = 'holland_middle');
   ```
4. Create and publish a new corrected version with incremented version number (e.g. `1.0.0` → `1.1.0`) before disabling the old version for new attempts.
5. Old submitted attempts remain linked to the original `1.0.0` version.
6. New attempts automatically use the newest `published` version.

### 14.2 Roll-Forward (Publish Corrected Version)

1. Author corrected catalog content.
2. Run validators against corrected content.
3. Complete all review checkpoints.
4. Create new `learner_assessment_versions` row with new version number.
5. Publish the corrected version.
6. Archive the old version if it contained harmful content.

### 14.3 Emergency Disable (Safety Critical, separately authorized)

If a catalog contains harmful content (protected group questions, etc.):

1. With emergency authorization, archive immediately using the controlled state transition:
   ```sql
   UPDATE learner_assessment_versions SET status = 'archived' WHERE id = :version_id;
   ```
2. Notify Product Owner and Codex within 24 hours.
3. Assess whether old attempts need action.
4. Do not delete rows; preserve audit trail.
5. Publish corrected version as Roll-Forward.

---

## 15. Security / Privacy Constraints

- **No sensitive data collection:** Prompts must not ask for protected group information.
- **Consent:** The live learner assessment consent gate must be verified against `learner_ai_consent_events`; no `privacy_consents` rows are seeded by this plan.
- **UUID stability:** All seeded IDs are valid canonical hexadecimal UUIDs; stable codes and the manifest prevent collisions.
- **Content isolation:** Each catalog is independent; one framework's errors cannot affect others.
- **Audit trail:** All seed executions logged with timestamp, operator, and row counts.
- **No production writes during review:** Only disposable database until DCR approval.

---

## 16. Explicit Handoff to Codex

This plan is submitted for Codex review before any implementation begins.

**Questions for Codex:**

1. Is the migration schema in Task 1 consistent with the catalog test fixture?
2. Is the canonical UUID manifest and stable-code namespace acceptable?
3. Should the DCR include migration version numbers as prerequisites?
4. Is the separately authorized archive/roll-forward procedure (outside the insert-only seed) sufficient for safety-critical content?
5. Are there additional validation checks Codex requires before seed approval?

**Codex reviewer must verify:**
- Dimension codes match scorer implementations exactly
- Schema hash computation is deterministic
- UUIDs pass `TalentHub\Support\Uuid::isValid()` and stable codes are unique
- No destructive SQL in seeder
- Transaction boundaries are correct
- Row counts in DCR match catalog matrix

---

## 17. Plan Self-Review Gate

Before implementation begins, Codex must verify the plan itself:

- [ ] All references to UUID prefixes are removed; UUIDs are validated by `TalentHub\Support\Uuid::isValid()` and stable identity uses `test_questions.code`.
- [ ] MBTI validation is separated from reverse-item validation for Holland/DISC/Multiple Intelligence.
- [ ] The question-count decision is explicitly pending until Product Owner approval; no content task assumes an unapproved option.
- [ ] MySQL 8.4 migration details use the existing `MigrationRunner` contract and do not copy SQLite syntax blindly.
- [ ] The canonical schema hash serialization is deterministic and testable.
- [ ] Seeder reruns are idempotent and hash mismatches fail closed.
- [ ] Archive is a separately approved state transition and is not part of the insert-only seed.
- [ ] Consent references match `learner_ai_consent_events`; no unrelated consent table is seeded.
- [ ] No unresolved placeholders, conflict markers, or contradictory safety instructions remain.
- [ ] `git diff --check` and `git status --short` are clean except for the intended plan file and any explicitly ignored local tool artifacts.

## 18. Follow-on Plans

After this plan completes and catalogs are seeded:

1. **`2026-08-17-learner-competency-profile.md`** — Combine four results with verified learner evidence; expose coverage, confidence, and contradictions.
2. **`2026-08-17-learner-recommendation-roadmap.md`** — Real group/activity matching, evidence explanations, rolling three-month roadmaps.
3. **`2026-08-17-learner-gemini-9router-shadow.md`** — Configure provider adapter, minimized request, strict validation, evaluation, rule fallback. Model visible percentage remains 0.
4. **Assessment analytics and quality monitoring** — Track completion rates, retake patterns, score distributions, and content quality feedback.

---

## Appendix A: Holland Prompt Template

```
[
    'education_band' => 'middle',
    'dimension_code' => 'R:+',
    'content' => 'Bạn thích làm việc với máy móc và dụng cụ.',
]
```

## Appendix B: MBTI Prompt Template

```
[
    'education_band' => 'high',
    'dimension_code' => 'EI:E',
    'content' => 'Trong lớp học, bạn thường chủ động hỏi để hiểu rõ vấn đề.',
]
```

## Appendix C: DISC Prompt Template

```
[
    'education_band' => 'college',
    'dimension_code' => 'D:+',
    'content' => 'Khi làm việc nhóm, bạn sẵn sàng nhận trách nhiệm điều phối.',
]
```

## Appendix D: Multiple Intelligence Prompt Template

```
[
    'education_band' => 'middle',
    'dimension_code' => 'LOGI:+',
    'content' => 'Bạn thích tìm quy luật để giải các bài toán logic.',
]
```
