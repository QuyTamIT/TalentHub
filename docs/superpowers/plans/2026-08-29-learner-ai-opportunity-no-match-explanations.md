# Learner AI Opportunity No-Match Explanations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add grounded Gemini explanations for suitable, low-fit, and no-fit learner opportunities while preserving the existing inline ecosystem experience and safety rules.

**Architecture:** Extend the opportunity matching capability with a mode-aware Gemini prompt/validator, a structured exclusion report, threshold classification, and persisted run-level analysis. The service remains the source of truth for scores and tiers; Gemini supplies only bounded, evidence-backed prose and scores. The existing learner API and inline UI map the new states without adding a page, tab, modal, or dashboard block.

**Tech Stack:** PHP 8+, PDO MySQL/SQLite migrations, existing Gemini provider transport, vanilla JavaScript, Node test runner; no new dependencies.

## Global Constraints

- Learner portal only; keep the two ecosystem tabs and render inline above the ordinary opportunity grid.
- Final score remains `round(0.70 * structured_score + 0.30 * gemini_score)` and is calculated server-side.
- `60–100` is suitable, `40–59` is low fit, and `0–39` is no fit.
- Gemini receives only consent-safe profile data, canonical candidates/aggregates, allow-listed IDs/codes and evidence references; never secrets, contact data, precise addresses or protected traits.
- At most ten candidates are sent to Gemini. No-fit mode sends only server-generated safe aggregates when no candidates remain.
- Gemini output is validated before persistence; provider failure never becomes fake analysis.
- Model-derived strings are rendered with `textContent`; existing search/filter behavior remains unchanged.
- Preserve all pre-existing unrelated working-tree changes, especially `PromptRegistry.php`, `learner-recommendations.js`, its test, deleted planning docs, `assets/images/mockups/`, and the credential-card test.

---

### Task 1: Persist run-level diagnostic analysis

**Files:**
- Create: `Database/migrations/learner/016_add_learner_opportunity_analysis.php`
- Create: `Database/migrations/20260829000110_bridge_learner_opportunity_analysis.php`
- Modify: `app/learner/ai/Persistence/OpportunityMatchRepository.php`
- Modify: `app/learner/ai/Persistence/DatabaseOpportunityMatchRepository.php`
- Modify: `tests/learner_ai_opportunity_matching_migration_test.php`
- Modify: `tests/learner_ai_opportunity_service_test.php`

**Interfaces:**
- Add nullable `analysisJson` (portable `TEXT`/MySQL `LONGTEXT`) to `learner_recommendation_runs`.
- Extend `completeRun(string $studentId, string $runId, array $matches, array $analysis = [], string $state = 'ready_model'): array`.
- `latestValid()` returns a completed opportunity run with zero to three active items and a decoded run-level `analysis` payload.

- [ ] **Step 1: Write the failing migration/repository tests**

Assert the new run column exists on SQLite and MySQL statements use `LONGTEXT`. Add a service fixture that attempts to complete a run with zero items and a non-empty validated analysis; assert the current implementation fails because it requires exactly three items.

- [ ] **Step 2: Run the tests to verify RED**

Run:

```powershell
php tests/learner_ai_opportunity_matching_migration_test.php
php tests/learner_ai_opportunity_service_test.php
```

Expected: the new column/zero-item assertions fail against the current implementation.

- [ ] **Step 3: Implement migration 016 and bridge**

Use the existing `ForwardMigrationDefinition` and `LearnerMigrationBridge::migrate()` patterns. Add `analysisJson` with a nullable driver-specific JSON text type and include it in `expectedSchema()`.

- [ ] **Step 4: Implement mode-aware persistence**

Remove the hard requirement for exactly three items. Accept 0–3 items, require a non-empty run-level analysis when there are zero items, serialize validated analysis JSON, and update the run row only when `capability = 'opportunity_match'`. Keep completion transactional and preserve canonical item superseding behavior.

- [ ] **Step 5: Run migration and service tests GREEN**

Run the two commands from Step 2 and verify the new persistence assertions pass while the existing three-item behavior remains green.

- [ ] **Step 6: Commit Task 1**

```powershell
git add Database/migrations/learner/016_add_learner_opportunity_analysis.php Database/migrations/20260829000110_bridge_learner_opportunity_analysis.php app/learner/ai/Persistence/OpportunityMatchRepository.php app/learner/ai/Persistence/DatabaseOpportunityMatchRepository.php tests/learner_ai_opportunity_matching_migration_test.php tests/learner_ai_opportunity_service_test.php
git commit -m "feat(ai): persist opportunity match explanations"
```

---

### Task 2: Add Gemini recommendation, low-fit and no-fit contracts

**Files:**
- Modify: `app/learner/ai/Model/OpportunityMatchPromptRegistry.php`
- Modify: `app/learner/ai/Model/ModelOpportunityMatchEngine.php`
- Modify: `app/learner/ai/Matching/OpportunityMatchValidator.php`
- Modify: `tests/learner_ai_opportunity_provider_test.php`

**Interfaces:**
- `OpportunityMatchPromptRegistry::create(..., string $mode = 'recommendation', array $analysisContext = []): ProviderRequest`.
- `ModelOpportunityMatchEngine::generate(..., string $mode = 'recommendation', array $analysisContext = []): array`.
- Validator accepts one to three items for `recommendation` and `low_fit`, with mode-specific explanation fields; `no_fit` returns a validated run-level analysis and no items.

- [ ] **Step 1: Write failing provider tests**

Add fake Gemini responses for:

1. Two suitable items → valid `partial_model` input.
2. Two low-fit items with `why_not_fit_yet`, `missing_conditions`, and `improvement_steps`.
3. No-fit summary with `headline`, `explanation`, strengths, catalog demands, gaps, next steps and evidence.
4. Reject unallow-listed IDs/codes, missing evidence, unsafe promises and fabricated catalog data in every mode.

- [ ] **Step 2: Run provider tests to verify RED**

```powershell
php tests/learner_ai_opportunity_provider_test.php
```

Expected: current exact-three schema and `why_fit`-only validator reject the new fixtures.

- [ ] **Step 3: Implement mode-aware prompt/schema**

Replace the fixed instruction `Return exactly three...` with mode-specific instructions. Keep the candidate allow-list capped at ten. For no-fit mode include only sanitized server aggregates and exclusion codes in `analysis_context`; do not include raw excluded records.

- [ ] **Step 4: Implement strict mode validation**

Keep candidate/skill/outcome/evidence allow-list validation and unsafe-claim detection. Validate low-fit fields and summary arrays as non-empty bounded strings. Enforce distinct project-specific explanations for item modes. Ignore provider titles, URLs, providers and scores outside the approved fields.

- [ ] **Step 5: Run provider and generic recommendation regressions**

```powershell
php tests/learner_ai_opportunity_provider_test.php
php tests/learner_ai_provider_test.php
```

The focused provider test must pass. If the generic provider test remains red, record it as the pre-existing `PromptRegistry.php` change and do not modify that file.

- [ ] **Step 6: Commit Task 2**

```powershell
git add app/learner/ai/Model/OpportunityMatchPromptRegistry.php app/learner/ai/Model/ModelOpportunityMatchEngine.php app/learner/ai/Matching/OpportunityMatchValidator.php tests/learner_ai_opportunity_provider_test.php
git commit -m "feat(ai): analyze opportunity fit tiers with Gemini"
```

---

### Task 3: Classify thresholds and generate grounded explanations

**Files:**
- Modify: `app/learner/ai/Service/OpportunityMatchService.php`
- Modify: `app/learner/ai/Persistence/DatabaseOpportunityMatchRepository.php`
- Modify: `tests/learner_ai_opportunity_service_test.php`
- Modify: `tests/learner_ai_opportunity_end_to_end_test.php`

**Interfaces:**
- Service returns `ready_model`, `partial_model`, `low_fit_model` or `no_fit_model` with `tier`, `items`, and optional `analysis`.
- Candidate resolution records exclusion reason counts without exposing rejected protected data.

- [ ] **Step 1: Write failing service/E2E tests**

Add fixtures for final-score sets `[72, 61]`, `[58, 47]`, `[39, 34]`, and zero eligible candidates. Assert exact state/tier, item count, Gemini request mode, per-item analysis, summary analysis, and persistence reload. Assert no-fit mode receives safe aggregate demands/exclusion counts and never receives protected values.

- [ ] **Step 2: Run service/E2E tests to verify RED**

```powershell
php tests/learner_ai_opportunity_service_test.php
php tests/learner_ai_opportunity_end_to_end_test.php
```

Expected: current service returns `catalog_insufficient`, refuses fewer than three items, and does not call Gemini in these scenarios.

- [ ] **Step 3: Implement candidate diagnostics**

Track only safe reason codes (`expired`, `full`, `already_applied`, `education_band_mismatch`, `tenant_mismatch`, `protected_eligibility`, `invalid_candidate`, `score_below_threshold`). Build catalog demand aggregates from canonical eligible records and safe skill/category codes.

- [ ] **Step 4: Implement mode selection and threshold classification**

Send up to ten eligible candidates to Gemini. Use recommendation mode when suitable candidates may exist, low-fit mode when no final score reaches 60 but candidates fall in 40–59, and no-fit mode when all scores are below 40 or no candidate survives hard gates. Calculate final 70/30 scores server-side, then classify and persist.

- [ ] **Step 5: Implement stale and idempotent reload for all new states**

`latest()` must return persisted partial/low-fit/no-fit analyses, remove closed/expired items, and mark a valid cached explanation stale only on provider failure. A provider failure without valid cache remains `provider_unavailable`.

- [ ] **Step 6: Run service, persistence and E2E tests GREEN**

```powershell
php tests/learner_ai_opportunity_service_test.php
php tests/learner_ai_database_sync_test.php
php tests/learner_ai_opportunity_end_to_end_test.php
```

- [ ] **Step 7: Commit Task 3**

```powershell
git add app/learner/ai/Service/OpportunityMatchService.php app/learner/ai/Persistence/DatabaseOpportunityMatchRepository.php tests/learner_ai_opportunity_service_test.php tests/learner_ai_opportunity_end_to_end_test.php
git commit -m "feat(ai): explain learner opportunity fit"
```

---

### Task 4: Expose and render the new learner states

**Files:**
- Modify: `app/learner/api/v1/opportunity-matches.php`
- Modify: `assets/js/learner-opportunity-matches.js`
- Modify: `app/learner/ecosystem.php`
- Modify: `assets/css/learner.css`
- Modify: `tests/learner_ai_opportunity_api_test.php`
- Modify: `tests/learner_ai_opportunity_ui_test.js`
- Modify: `tests/learner_ecosystem_ui_test.js`

**Interfaces:**
- API preserves the existing envelope and returns new service states unchanged after server validation.
- UI controller maps `partial_model`, `low_fit_model`, and `no_fit_model` to accessible inline views.

- [ ] **Step 1: Write failing API/UI tests**

Assert API response shape for all new states. Assert suitable cards can render one or two items, low-fit cards display missing conditions/improvement steps and use warning copy, no-fit renders summary lists with no project card, and all dynamic strings use safe DOM APIs. Assert the ordinary opportunity count/filter hooks remain unchanged.

- [ ] **Step 2: Run tests to verify RED**

```powershell
php tests/learner_ai_opportunity_api_test.php
node --test tests/learner_ai_opportunity_ui_test.js
node --test tests/learner_ecosystem_ui_test.js
```

Expected: new states are not mapped/rendered by the current controller/view.

- [ ] **Step 3: Implement API state passthrough**

Keep learner authorization, consent, CSRF, idempotency and persistent rate limiting unchanged. Do not expose internal provider diagnostics or raw prompts.

- [ ] **Step 4: Implement safe UI state mapping and copy**

Add states:

```js
partial_model: 'partial-model',
low_fit_model: 'low-fit-model',
no_fit_model: 'no-fit-model',
```

Render suitable cards without claiming Top 3 when fewer than three exist. Render low-fit diagnostic cards with `Xem yêu cầu dự án`. Render no-fit summary fields with `textContent` and no project cards. Show provider-unavailable copy when Gemini did not return validated analysis.

- [ ] **Step 5: Add responsive styling**

Use existing approved tokens: warning for low-fit/no-fit, green/blue only for suitable scores, Be Vietnam Pro, focus-visible, reduced motion, and no horizontal overflow at 720px.

- [ ] **Step 6: Run UI/API regressions GREEN**

```powershell
node --check assets/js/learner-opportunity-matches.js
node --test tests/learner_ai_opportunity_ui_test.js
node --test tests/learner_ecosystem_ui_test.js
php tests/learner_ai_opportunity_api_test.php
php tests/learner_ecosystem_routes_test.php
```

- [ ] **Step 7: Commit Task 4**

```powershell
git add app/learner/api/v1/opportunity-matches.php assets/js/learner-opportunity-matches.js app/learner/ecosystem.php assets/css/learner.css tests/learner_ai_opportunity_api_test.php tests/learner_ai_opportunity_ui_test.js tests/learner_ecosystem_ui_test.js
git commit -m "feat(learner): explain opportunity fit in ecosystem"
```

---

### Task 5: Full verification and handoff

**Files:**
- Verify all Task 1–4 files.
- Verify approved mockup: `docs/superpowers/plans/assets/learner-ai-opportunity-matching-mockup.png`.

- [ ] **Step 1: Run complete focused suite**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests/learner_ai_opportunity_matching_migration_test.php
& $php tests/learner_ai_opportunity_candidate_test.php
& $php tests/learner_ai_opportunity_scorer_test.php
& $php tests/learner_ai_opportunity_provider_test.php
& $php tests/learner_ai_opportunity_service_test.php
& $php tests/learner_ai_opportunity_api_test.php
& $php tests/learner_ai_opportunity_end_to_end_test.php
& $php tests/learner_ai_database_sync_test.php
& $php tests/learner_ai_end_to_end_test.php
& $php tests/learner_ecosystem_routes_test.php
node --check assets/js/learner-opportunity-matches.js
node --test tests/learner_ai_opportunity_ui_test.js
node --test tests/learner_ecosystem_ui_test.js
git diff --check
```

- [ ] **Step 2: Run baseline checks separately**

Run `tests/learner_ai_provider_test.php` and `tests/learner_ecosystem_data_test.php`; report any pre-existing failures without altering protected user changes.

- [ ] **Step 3: Review state and protected paths**

Confirm only the scoped implementation files are staged in feature commits. Confirm the five pre-existing modified/untracked areas remain untouched.

- [ ] **Step 4: Visually inspect the approved mockup and rendered state contract**

Verify inline placement, suitable/low-fit/no-fit hierarchy, score colors, accessibility labels, and the ordinary opportunity list below the AI block.

- [ ] **Step 5: Commit any final scoped fix and report evidence**

Use a focused commit message, report commit hash and exact test evidence, and stop before merge/push unless the user explicitly chooses an integration option.

## Completion Criteria

- Gemini analyzes suitable, low-fit and no-fit modes with strict allow-lists and evidence references.
- 60–100, 40–59 and 0–39 tiers are reflected accurately in API, persistence and UI.
- No-fit explanations are grounded in server aggregates and never invent projects or requirements.
- Partial, low-fit and no-fit results survive reload and stale handling.
- Existing Top 3, API security, learner-only scope, ordinary opportunity list and protected working-tree paths remain intact.
