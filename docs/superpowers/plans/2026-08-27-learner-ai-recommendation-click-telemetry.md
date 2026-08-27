# Learner AI Recommendation Click Telemetry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record a sanitized aggregate metric when an authenticated learner activates a real recommendation CTA without ever blocking navigation.

**Architecture:** Recommendation CTA links expose safe `data-*` identifiers. A small browser tracker sends a non-awaited same-origin `fetch` with `keepalive`, JSON and the current CSRF token. A dedicated learner endpoint validates authentication, CSRF, the allow-listed action and ownership of the recommendation item/catalog evidence before emitting an allow-listed metric.

**Tech Stack:** PHP 8.3, PDO, existing learner API/session/CSRF infrastructure, vanilla JavaScript, `node:test`.

## Global Constraints

- CSRF remains mandatory; no bypass or token-in-query-string behavior.
- Telemetry failure must never call `preventDefault`, await navigation, mutate recommendation data, or display an error to the learner.
- Never emit student ID, title, summary, URL, evidence, prompt, model response, API key or provider authorization data.
- Only `view_activity`, `view_opportunity`, `register_activity`, and `open_catalog_item` are accepted action categories.
- Do not change AI rollout visibility or `.env`.

---

### Task 1: Server ownership contract and click endpoint

**Files:**
- Create: `tests/learner_ai_recommendation_click_test.php`
- Modify: `app/learner/ai/Persistence/DatabaseRecommendationRepository.php`
- Modify: `app/learner/api/LearnerApiContext.php`
- Create: `app/learner/api/v1/recommendation-click.php`
- Modify: `app/learner/ai/Observability/AiMetricsCollector.php`

**Interfaces:**
- Produces: `DatabaseRecommendationRepository::ownsClickTarget(string $studentId, string $itemId, ?string $catalogId): bool`.
- Produces: `LearnerApiContext::recordRecommendationClick(string $studentId, string $itemId, ?string $catalogId, string $actionType): array`.
- Endpoint consumes JSON `{itemId, catalogId?, actionType}` and returns `{state: "recorded"}`.

- [ ] **Step 1: Write the failing PHP test**

Create a disposable SQLite recommendation run with an owned item and catalog evidence. Assert that an owned item/catalog pair records exactly one event, while a foreign item, mismatched catalog ID, invalid action, and secret-like extra input record none. Add source-contract assertions that the endpoint calls `studentId()`, `mutation()`, and `allowedInput()`.

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_ai_recommendation_click_test.php
```

Expected: failure because `ownsClickTarget`, `recordRecommendationClick`, or the endpoint does not exist.

- [ ] **Step 3: Implement minimal server behavior**

Use an owner-scoped SQL join from recommendation items to runs. When `catalogId` is supplied, additionally require matching `catalog` or `opportunity` evidence on the same item. In the context method, reject unsupported actions, call the ownership method, and emit only:

```php
AiMetricsCollector::shared()->record([
    'recommendation_click' => true,
    'recommendation_action' => $actionType,
]);
```

The endpoint must execute in this order:

```php
$studentId = $context->studentId('student_profile.update_own');
$context->mutation($request->header('x-csrf-token'));
$input = $context->allowedInput($request->json(), ['itemId', 'catalogId', 'actionType']);
```

Validate identifiers as bounded opaque strings of 1–128 characters and return `404` for a non-owned target without revealing whether it exists for another learner.

- [ ] **Step 4: Run the PHP test and verify GREEN**

Run the command from Step 2. Expected: `learner_ai_recommendation_click_test: OK`.

---

### Task 2: Non-blocking browser CTA tracking

**Files:**
- Modify: `tests/learner_ai_recommendation_ui_test.js`
- Modify: `assets/js/learner-recommendations.js`

**Interfaces:**
- Produces: `createRecommendationClickTracker({fetchImpl, csrfToken, endpoint})` with `track({itemId, catalogId, actionType}): void`.
- CTA data attributes: `data-ai-recommendation-cta`, `data-ai-item-id`, `data-ai-catalog-id`, `data-ai-action-type`.

- [ ] **Step 1: Write failing Node tests**

Assert that `track()` calls `fetchImpl` once with:

```js
{
  method: 'POST',
  credentials: 'same-origin',
  keepalive: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-Token': 'csrf-test'
  },
  body: JSON.stringify({ itemId, catalogId, actionType })
}
```

Also assert that `track()` returns `undefined`, catches synchronous fetch errors and rejected promises, rejects invalid payloads locally, and the delegated CTA handler does not use `preventDefault` or `await`.

- [ ] **Step 2: Run Node test and verify RED**

```powershell
node --test tests\learner_ai_recommendation_ui_test.js
```

Expected: failure because `createRecommendationClickTracker` and CTA datasets do not exist.

- [ ] **Step 3: Implement minimal client behavior**

Add safe dataset values while rendering links. For activity links use `register_activity`; for catalog/opportunity links select `view_opportunity` or `open_catalog_item` from the cited evidence type. Add a delegated anchor click branch that calls the tracker and immediately returns without preventing navigation.

The tracker must use:

```js
try {
  Promise.resolve(fetchImpl(endpoint, options)).catch(() => {});
} catch {
  // Analytics must not affect navigation.
}
```

- [ ] **Step 4: Run Node test and verify GREEN**

Run the command from Step 2. Expected: all recommendation UI tests pass.

---

### Task 3: Regression verification and status update

**Files:**
- Modify: `.superpowers/sdd/phase8-observability-report.md`
- Modify: `docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md`

**Interfaces:**
- Consumes the server and client contracts from Tasks 1–2.
- Produces release evidence that actual recommendation CTA clicks are emitted separately from feedback.

- [ ] **Step 1: Run focused verification**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\learner_ai_recommendation_click_test.php
& $php tests\learner_ai_phase8_observability_test.php
& $php tests\learner_ai_phase8_review_fixes_test.php
node --test tests\learner_ai_recommendation_ui_test.js
```

Expected: every command exits `0`.

- [ ] **Step 2: Run safe AI regressions, lint and diff checks**

Run all non-environment-gated `learner_ai*.php` tests, all `*ai*_test.js` tests, PHP lint on changed PHP files, and `git diff --check`. Expected: zero failures; environment-gated Gemini/MySQL production rehearsals remain explicitly blocked rather than silently skipped as passed.

- [ ] **Step 3: Update Phase 8 evidence**

Record that real CTA click telemetry is implemented and feedback is not counted as a click. Keep `LIVE_100_PERCENT_ROLLOUT_BLOCKED` until the existing deployment gates are satisfied.

