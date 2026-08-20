# Learner AI Rule and Shadow Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden the deterministic Holland career-group recommendation path and its shadow-AI boundary so the four-group flow is deterministic, consent-safe, and ready for offline/staging AI evaluation without implementing a real activity-registration API.

**Architecture:** Normalize banded Holland assessment codes in the rule engine, classify validated RIASEC scores through `CareerGroupClassifier`, and map only known open activity categories to the four career groups. Keep `RecommendationService` as the production consent/data-quality boundary and keep model output hidden at visible percent zero; all integration writes occur only on an approved disposable MySQL database.

**Tech Stack:** PHP 8.3.30, PDO MySQL, Laragon MySQL Community Server 8.4.3, deterministic PHP test harnesses, Node.js UI tests, existing recommendation/scoring contracts.

## Global Constraints

- PHP runtime: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- Node runtime: `D:\nodejs\node.exe`.
- MySQL runtime: Laragon MySQL Community Server 8.4.3.
- Never run migration, seed, INSERT, UPDATE, or DELETE against `talenthub_local` during implementation or tests.
- Do not add an API key, call Gemini/9Router, or enable model-visible rollout; `TALENTHUB_AI_VISIBLE_PERCENT` remains `0`.
- Do not implement a real learner activity-registration controller/service; the UI remains mock/localStorage based.
- Disposable write tests must use an approved `talenthub_ai_*` schema and restore/clean their fixture rows.
- Preserve unrelated dirty-worktree changes; do not modify `.claude/` or `.qwen/`.
- Do not commit or push; each task ends with a Codex review checkpoint.

---

### Task 1: Lock the CareerGroupClassifier Contract

**Files:**
- Modify: `app/learner/ai/Rules/CareerGroupClassifier.php`
- Modify: `tests/learner_career_group_classifier_test.php`

**Interfaces:**
- Consumes: `classify(array $dimensionScores, string $testCode = 'holland'): list<array>` and `topGroup(array $dimensionScores, string $testCode = 'holland'): ?array`.
- Produces: deterministic groups `technical`, `business`, `arts`, `sports_academic`; invalid input returns `[]`/`null`.

- [x] **Step 1: Add failing contract assertions**

Add tests for:

```php
classifier_assert($classifier->topGroup(['R'=>90,'I'=>85,'A'=>20,'S'=>20,'E'=>20,'C'=>20], 'holland_high')['code'] === 'technical', 'banded Holland technical');
classifier_assert($classifier->topGroup(['R'=>20,'I'=>20,'A'=>20,'S'=>20,'E'=>95,'C'=>20], 'holland_college')['code'] === 'business', 'banded Holland business');
classifier_assert($classifier->topGroup(['R'=>20,'I'=>20,'A'=>95,'S'=>20,'E'=>20,'C'=>20], 'holland_middle')['code'] === 'arts', 'banded Holland arts');
classifier_assert($classifier->topGroup(['R'=>20,'I'=>20,'A'=>20,'S'=>90,'E'=>20,'C'=>88], 'holland_middle')['code'] === 'sports_academic', 'banded Holland sports academic');
classifier_assert($classifier->classify(['R'=>50,'I'=>50,'A'=>50,'S'=>50,'E'=>50,'C'=>50], 'holland')[0]['code'] === 'arts', 'stable code tie-break');
classifier_assert($classifier->classify(['R'=>50,'I'=>50], 'holland') === [], 'incomplete dimension map rejected');
classifier_assert($classifier->classify(['R'=>101,'I'=>50,'A'=>50,'S'=>50,'E'=>50,'C'=>50], 'holland') === [], 'out-of-range score rejected');
classifier_assert($classifier->classify(['R'=>90,'I'=>80,'A'=>70,'S'=>60,'E'=>50,'C'=>40], 'mbti_high') === [], 'non-Holland test rejected');
```

- [x] **Step 2: Run the focused test and verify the expected failure**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_career_group_classifier_test.php
```

Expected: FAIL only on the new banded-code/complete-dimension assertions if the current implementation does not satisfy them.

- [x] **Step 3: Implement the minimal classifier contract**

Keep the public `GROUPS` mapping and stable code tie-break. Normalize the test code by accepting exactly `holland` or `holland_` followed by `middle`, `high`, or `college`. Require all six unique RIASEC dimensions before ranking; reject unknown dimensions, duplicate normalized dimensions, non-numeric values, and values outside `[0,100]`.

- [x] **Step 4: Run focused and existing classifier tests**

Run the focused test and confirm `learner_career_group_classifier_test: OK`. Do not touch the database.

---

### Task 2: Harden RuleRecommendationEngine Facts and Fallbacks

**Files:**
- Modify: `app/learner/ai/Rules/RuleRecommendationEngine.php`
- Modify: `tests/learner_career_rules_test.php`
- Modify: `tests/learner_rule_recommendation_test.php`

**Interfaces:**
- Consumes: assessment evidence with `test_code`, `dimension_scores`, and opportunity evidence with `category`, `status`, `title`.
- Produces: validated `RecommendationResult` items with `career_group` and `activity_source_id`; safe fallback reasons `insufficient_data` or `consent_required`.

- [x] **Step 1: Add failing rule tests**

Add fixture assertions that:

```php
$banded = $engine->generate($assessmentOnlyWithBandedHollandAndAllRequiredSources, $context);
rule_assert(hasCareerGroup($banded, 'technical'), 'holland_high assessment creates technical group');
rule_assert(allActivityGroupsAre($banded, 'technical'), 'technical result contains only technical opportunities');
rule_assert($engine->generate($inputWithUnknownOpportunityCategory, $context)->items() === [], 'unknown category is ignored');
rule_assert($engine->generate($inputWithClosedMatchingOpportunity, $context)->items() === [], 'closed matching opportunity is excluded');
rule_assert($engine->generate($inputMissingAssessment, $context)->fallbackReason() === 'insufficient_data', 'missing assessment is safe');
```

Use existing fixture helpers and do not insert database rows in this unit test.

- [x] **Step 2: Run the focused rule tests and verify the expected failure**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_career_rules_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_rule_recommendation_test.php
```

Expected: the new assertions fail before implementation changes.

- [x] **Step 3: Implement normalized facts and explicit category mapping**

In `RuleRecommendationEngine`:

```php
$isHolland = $testCode === 'holland' || preg_match('/\Aholland_(middle|high|college)\z/', $testCode) === 1;
```

Use `CareerGroupClassifier` only for valid Holland assessment facts. Map only the explicit categories `career_technical`, `career_business`, `career_arts`, and `career_sports_academic` (case-insensitive); unknown categories return `null`. Exclude `closed`, `inactive`, `cancelled`, `completed`, and `archived` opportunity statuses. Preserve deterministic sorting by priority and stable source IDs. Keep `RecommendationService`'s default `DataQualityGate` unchanged.

- [x] **Step 4: Run focused tests and the full rule regression**

Run the two focused tests plus:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_career_quality_gate_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_rule_recommendation_test.php
```

Expected: all pass, with production quality gate still requiring assessment, skills, activity, and evaluation.

---

### Task 3: Lock Shadow-AI Boundary Regression Tests

**Files:**
- Modify: `tests/learner_ai_9router_shadow_integration_test.php`
- Verify only: `app/learner/api/LearnerApiContext.php`, `app/learner/ai/Provider/HttpRecommendationProvider.php`

**Interfaces:**
- Consumes: `RecommendationConfig`, `LearnerApiContext::shadowRunService()`, `HttpRecommendationProvider`, `RecommendationRolloutSelector`.
- Produces: no model-visible output at visible percent zero and no provider construction when AI/shadow is disabled.

- [x] **Step 1: Add/retain failing boundary assertions**

Ensure the test asserts all of the following with mock transport counters:

```php
test_assert($config->visiblePercent() === 0, 'visible percent is zero');
test_assert($contextWithAiDisabled->shadowRunService() === null, 'disabled AI has no shadow service');
test_assert($contextWithShadowDisabled->shadowRunService() === null, 'disabled shadow has no shadow service');
test_assert($visibleResponse['engine_type'] === 'rule', 'outward engine remains rule');
test_assert($visibleResponse['provider'] === null && $visibleResponse['model_version'] === null, 'provider metadata is hidden');
```

- [x] **Step 2: Run the boundary test and verify any missing assertion fails**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_9router_shadow_integration_test.php
```

- [x] **Step 3: Make only minimal wiring changes if a boundary assertion fails**

Do not add live transport, API key loading beyond existing environment configuration, or database writes. Preserve the default quality gate and the `visiblePercent() > 0` rollout check.

- [x] **Step 4: Run all AI regression tests**

Run the provider, rollout, evaluation, snapshot, source, scope-policy, scope-audit, recommendation-service, recommendation-API, and career quality-gate tests using the required PHP runtime.

---

### Task 4: Disposable MySQL Four-Group E2E Verification

**Files:**
- Create or modify: `tests/learner_career_group_full_e2e_integration_test.php`
- Verify only: `Database/seeds/learner/Staging/LearnerCareerActivitySeeder.php`

**Interfaces:**
- Consumes: `LearnerAssessmentService`, `AssessmentCatalogService`, `CareerGroupClassifier`, `RuleRecommendationEngine`, `DatabaseOpportunitySource`, and the existing staging seeders.
- Produces: disposable-only evidence for discovery, persistence, scoring, four group recommendations, opportunity exclusion, learner isolation, and visible-percent-zero behavior.

- [x] **Step 1: Pin and preflight the disposable database**

The test must reject `talenthub_local`, require the approved `talenthub_ai_backup_verify_*` pattern, assert `SELECT DATABASE()` equals the requested schema, and record pre-test counts for attempts, metadata, answers, results, and registrations.

- [x] **Step 2: Run the existing E2E test before changing it**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_career_group_full_e2e_integration_test.php
```

Expected: current behavior is captured; any missing acceptance assertion is identified before code changes.

- [x] **Step 3: Add deterministic scenarios for all four groups**

For each scenario, start the correct banded Holland attempt, answer reverse-keyed items with the scenario's target dimension weighting, submit through `LearnerAssessmentService`, assert `submitted` state and six persisted RIASEC scores, then assert the top group and at least two matching published opportunity IDs.

- [x] **Step 4: Add cleanup and isolation assertions**

Register only a fixture row in the disposable database, assert the registered opportunity disappears while another matching opportunity remains, assert the second learner cannot access or mutate the first learner's attempt/result/registration, then delete only test-owned rows and verify baseline counts are restored.

- [x] **Step 5: Run the E2E test and inspect the disposable database**

Expected output includes four group passes, activity exclusion pass, isolation pass, visible `engine_type=rule`, and cleanup success. Confirm no tables or rows in `talenthub_local` changed.

---

### Task 5: Full Regression and Readiness Evidence

**Files:**
- Verify: all files changed by Tasks 1–4.
- Modify only if evidence is stale: `docs/superpowers/readiness/learner-ai-release-checklist.md`

- [x] **Step 1: Run PHP lint and whitespace checks**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php -l app\learner\ai\Rules\CareerGroupClassifier.php
& $php -l app\learner\ai\Rules\RuleRecommendationEngine.php
& $php -l tests\learner_career_group_full_e2e_integration_test.php
git diff --check
```

- [x] **Step 2: Run the full focused regression matrix**

Run classifier, career rules, career activity source/seeder, quality gate, rule recommendation, shadow integration, assessment catalog seed verification, catalog cross-consistency, scorer integration, published immutability, assessment API/persistence, and Node assessment/recommendation UI tests with the required runtimes.

- [x] **Step 3: Verify primary database invariants**

Run `bin/connect-check.php --json` without `--quick` and `bin/migrate.php validate`. Confirm `talenthub_local` remains at 13 applied migrations, 0 pending, drift=false; assessment catalog counts remain 12/366/12/366; attempts, answers, metadata, results remain unchanged; and the eight career activity rows remain published.

- [x] **Step 4: Perform scope review**

Confirm no API key, external provider call, migration, primary-database write, `.claude/` change, `.qwen/` change, commit, or push occurred. Report that disposable persistence verification is not a real learner registration API.

- [x] **Step 5: Stop at the Codex review gate**

Report `STATUS: READY_FOR_CODEX_REVIEW` with files changed, tests, disposable database, primary database before/after, AI visible percent, provider-call evidence, and open issues. Do not commit or push.
