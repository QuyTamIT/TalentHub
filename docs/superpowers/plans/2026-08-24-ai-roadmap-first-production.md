# AI Roadmap-First Production Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a production-safe Roadmap-first learner AI feature that calls the configured model for real, synthesizes the four completed assessments into a Vietnamese executive summary and an evidence-backed 30–60–90 day plan, persists versions and task progress, and renders the approved FTalentHub mockup without duplicating the “Khám phá năng khiếu” page.

**Architecture:** Keep the existing recommendation snapshot, consent, audit, fallback, evaluation, and rollout controls. Add a versioned `learner-roadmap-1.0.0` structured contract, focused roadmap domain/provider/service/repository components, and additive roadmap persistence linked to the existing model run. Backend and frontend proceed in parallel only after the contract fixture is locked; the UI uses that fixture until the real API passes the same contract.

**Tech Stack:** PHP 8.3.30, PDO MySQL 8.4, existing migration framework, 9Router-compatible HTTP chat-completion provider, vanilla JavaScript with Node.js built-in tests, HTML/CSS using Be Vietnam Pro and existing learner shell.

## Global Constraints

- The Roadmap-first page follows the approved mockup and these tokens: `#F97316`, `#EA580C`, `#FFF7ED`, `#2563EB`, `#EFF6FF`, `#16A34A`, `#F8FAFC`, `#FFFFFF`, `#0F172A`, `#64748B`, `#E2E8F0`; radii `8px` and `12px`; font `Be Vietnam Pro`.
- “Khám phá năng khiếu” owns Holland, MBTI, DISC, Multiple Intelligence labels, scores, charts, interpretations, and history. “AI gợi ý” must not reproduce those blocks.
- A result may display “Tóm tắt từ AI” only when the persisted run has `engineType=model` plus non-empty `provider`, `modelVersion`, and `promptVersion` produced by a successful provider call.
- Rule output remains available for failures, but the API returns `analysis_origin=rule_fallback` and the UI says “Gợi ý dự phòng theo quy tắc”; it must never impersonate AI.
- Production code must not contain hard-coded model outputs, learner-specific conclusions, or demo activities. Deterministic rule templates are allowed only for explicitly labelled fallback output; model fixtures live under `tests/fixtures/` only.
- The model must return Vietnamese JSON that passes the exact contract and cites supplied evidence references. Invalid, unsafe, unsupported, or uncited content is rejected.
- The model cannot invent an activity target. Every `register_activity` task must reference an open, eligible activity UUID supplied by the server.
- Do not display a numeric “86% phù hợp” until a separately reviewed calibration proves the score. Initial production UI uses `low|medium|high` confidence bands and source coverage.
- The first roadmap is allowed after all four required assessments are submitted. Skills, activities, and published evaluations enrich subsequent versions but are not required for version 1.
- Never send names, email addresses, phone numbers, raw answers, direct learner IDs, raw consent rows, or database credentials to the provider.
- Never log or persist the API key, Authorization header, raw provider request body, or raw provider response body.
- Never hold a database transaction open across an external model call.
- Every provider attempt re-checks current consent. Provider failure must preserve an explainable rule fallback and the last completed roadmap.
- Do not edit an already-applied migration. Use an additive migration after exact preflight, disposable-schema rehearsal, backup, and explicit primary-apply approval.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0` in production until the shadow evaluation gate passes and the Product Owner approves a pilot percentage.
- Use TDD for every production behavior: focused RED test, minimal implementation, focused GREEN test, affected regression, commit.
- Do not commit `.env`, API keys, generated test accounts, temporary browser state, or production data.

## Target API Contract

`GET /app/learner/api/v1/ai-roadmap.php` returns the latest owned state. `POST` creates or reuses one idempotent analysis run. The completed model response is:

```json
{
  "state": "ready_model",
  "analysis_origin": "model",
  "run_id": "UUID",
  "roadmap_id": "UUID",
  "version": 1,
  "contract_version": "learner-roadmap-1.0.0",
  "generated_at": "2026-08-24T12:00:00.000000+00:00",
  "engine": {
    "provider": "9router_gemini",
    "model": "ag/gemini-3.7-flash-high",
    "prompt_version": "learner-roadmap-prompt-1.0.0"
  },
  "executive_summary": "Bạn có tiềm năng phát triển theo hướng xây dựng sản phẩm công nghệ và giải quyết vấn đề thực tế.",
  "confidence": {
    "band": "high",
    "source_count": 4,
    "reason": "Đã hoàn thành đủ bốn bài đánh giá bắt buộc."
  },
  "primary_direction": {
    "code": "technology_product",
    "label": "Công nghệ sản phẩm",
    "rationale": "Hướng này cho phép kiểm chứng năng lực thông qua sản phẩm thực tế."
  },
  "alternative_directions": [
    {"code": "automation", "label": "Tự động hóa", "rationale": "Phù hợp để thử nghiệm qua dự án kỹ thuật."},
    {"code": "data_analysis", "label": "Phân tích dữ liệu", "rationale": "Phù hợp để phát triển tư duy phân tích."}
  ],
  "insights": [
    {"category": "strength", "title": "Lợi thế nên phát huy", "summary": "Bạn có thể chuyển ý tưởng thành thử nghiệm nhỏ và quan sát kết quả.", "evidence_ref_ids": ["evidence-001"]},
    {"category": "improvement", "title": "Điểm nghẽn cần cải thiện", "summary": "Bạn cần luyện cách trình bày quyết định và tiếp nhận phản hồi có cấu trúc.", "evidence_ref_ids": ["evidence-002"]},
    {"category": "potential", "title": "Tiềm năng cần kiểm chứng", "summary": "Một dự án nhóm ngắn sẽ giúp kiểm chứng khả năng phối hợp và dẫn dắt sản phẩm.", "evidence_ref_ids": ["evidence-003"]}
  ],
  "phases": [
    {
      "position": 1,
      "start_day": 0,
      "end_day": 30,
      "code": "discover",
      "title": "Khám phá",
      "goal": "Hoàn thành mini project",
      "skill_focus": "Tư duy sản phẩm",
      "deliverable": "Bản demo đầu tiên",
      "effort_label": "3 giờ/tuần",
      "metric_label": "Một bản demo nhận được ít nhất hai phản hồi",
      "evidence_ref_ids": ["evidence-001", "evidence-004"],
      "tasks": [
        {
          "position": 1,
          "title": "Chọn một vấn đề thực tế",
          "description": "Ghi lại người dùng, nhu cầu và tiêu chí hoàn thành.",
          "estimated_minutes": 45,
          "action": {"type": "self_task"},
          "evidence_ref_ids": ["evidence-001"]
        }
      ]
    }
  ],
  "recommended_activities": [],
  "evidence_summary": {
    "assessment_count": 4,
    "skill_count": 0,
    "activity_count": 0,
    "evaluation_count": 0
  }
}
```

The provider contract contains exactly the analysis fields from `executive_summary` through `phases` plus ranked allow-listed activity source IDs. IDs, version numbers, run metadata, task progress, expanded activity records, evidence summary counts, and confidence bands are assigned or verified by the server.

## Planned File Map

### New production files

- `app/learner/ai/Contracts/RoadmapEngine.php`: model/fallback engine boundary.
- `app/learner/ai/Contracts/RoadmapProvider.php`: structured provider boundary.
- `app/learner/ai/Domain/RoadmapAnalysis.php`: immutable validated aggregate.
- `app/learner/ai/Domain/RoadmapDirection.php`: direction value object.
- `app/learner/ai/Domain/RoadmapInsight.php`: insight with evidence references.
- `app/learner/ai/Domain/RoadmapPhase.php`: 30-day phase value object.
- `app/learner/ai/Domain/RoadmapTask.php`: actionable task value object.
- `app/learner/ai/Model/RoadmapPromptRegistry.php`: Vietnamese, non-duplicating, evidence-citing prompt.
- `app/learner/ai/Model/ModelRoadmapEngine.php`: provider execution and rule fallback.
- `app/learner/ai/Provider/HttpRoadmapProvider.php`: 9Router transport and structured response parsing.
- `app/learner/ai/Provider/RoadmapProviderResponse.php`: safe provider result metadata.
- `app/learner/ai/Quality/RoadmapQualityGate.php`: four-assessment initial gate and enrichment state.
- `app/learner/ai/Rules/RuleRoadmapEngine.php`: clearly labelled fallback plan.
- `app/learner/ai/Validation/RoadmapAnalysisValidator.php`: contract, safety, evidence, activity and language validation.
- `app/learner/ai/Persistence/RoadmapRepository.php`: persistence interface.
- `app/learner/ai/Persistence/DatabaseRoadmapRepository.php`: owner-safe roadmap/version/progress persistence.
- `app/learner/ai/Service/RoadmapService.php`: orchestration, idempotency and versioning.
- `app/learner/api/v1/ai-roadmap.php`: latest/generate endpoint.
- `app/learner/api/v1/ai-roadmap-task.php`: append-only progress endpoint.
- `assets/js/learner-ai-roadmap.js`: Roadmap-first controller and safe DOM renderer.
- `assets/js/learner-ai-summary.js`: post-assessment analysis modal controller.
- `Database/migrations/learner/005_create_ai_roadmap_store.php`: canonical MySQL/SQLite roadmap schema.
- `Database/migrations/20260824000300_create_learner_ai_roadmap_store.php`: deployment bridge, only if preflight confirms this ID remains free.
- `docs/superpowers/database-change-requests/2026-08-24-ai-roadmap-store.md`: database change decision/evidence.
- `tests/fixtures/learner_ai_roadmap_v1.php`: canonical non-production contract fixture.

### Existing files to modify

- `app/learner/ai/bootstrap.php`: load the new focused classes.
- `app/learner/api/LearnerApiContext.php`: compose roadmap dependencies.
- `app/learner/api/v1/assessment-submit.php`: return an analysis trigger after the fourth assessment.
- `app/learner/discover.php`: host the asynchronous summary modal.
- `app/learner/ai-recommendations.php`: replace the card list with Roadmap-first semantic markup.
- `assets/js/learner-assessment.js`: preserve the final-test navigation trigger.
- `assets/css/learner.css`: scoped `.learner-page-ai` implementation matching the mockup.
- `app/learner/ai/Config/RecommendationConfig.php`: add reviewed roadmap limits without exposing secrets.
- `app/learner/ai/Consent/ConsentDecision.php`: expose explicit scoped-consent checks without changing the existing all-scope default.
- `app/learner/ai/Consent/ProviderConsentGate.php`: support a constructor-injected required scope list; the existing recommendation path keeps all four scopes.
- `app/learner/ai/Snapshot/RecommendationSnapshotBuilder.php`: select canonical latest four assessment results for roadmap v1.
- `app/learner/ai/Provider/HttpRecommendationProvider.php`: extract only a tested shared HTTP transport if required; its current recommendation behavior must remain unchanged.
- `app/learner/ai/Rollout/RecommendationRolloutSelector.php`: reuse the approved model visibility decision.
- `app/learner/data/Readiness/PhaseRequirements.php`: add roadmap tables after migration approval.
- `.env.example`: document non-secret roadmap configuration keys only.

---

### Task 1: Lock the Roadmap v1 contract and truthful AI provenance

**Files:**
- Create: `tests/fixtures/learner_ai_roadmap_v1.php`
- Create: `tests/learner_ai_roadmap_contract_test.php`
- Create: `app/learner/ai/Domain/RoadmapDirection.php`
- Create: `app/learner/ai/Domain/RoadmapInsight.php`
- Create: `app/learner/ai/Domain/RoadmapTask.php`
- Create: `app/learner/ai/Domain/RoadmapPhase.php`
- Create: `app/learner/ai/Domain/RoadmapAnalysis.php`
- Create: `app/learner/ai/Validation/RoadmapAnalysisValidator.php`
- Modify: `app/learner/ai/bootstrap.php`

**Interfaces:**

```php
final class RoadmapAnalysis
{
    public const CONTRACT_VERSION = 'learner-roadmap-1.0.0';
    public function origin(): string; // model|rule_fallback
    public function executiveSummary(): string;
    public function primaryDirection(): RoadmapDirection;
    /** @return list<RoadmapDirection> */ public function alternativeDirections(): array;
    /** @return list<RoadmapInsight> */ public function insights(): array;
    /** @return list<RoadmapPhase> */ public function phases(): array;
    public function confidenceBand(): string;
    /** @return list<string> */ public function evidenceReferenceIds(): array;
}
```

- [ ] Write the fixture with exactly one executive summary, one primary direction, two alternatives, three insight categories, and phases `(0,30)`, `(31,60)`, `(61,90)` containing 3–5 tasks each.
- [ ] Write failing tests that reject missing fields, unknown fields, English-only output, duplicate phase positions, overlapping day ranges, more than three phases, empty evidence arrays, unsupported action types, and a model origin without provider/model/prompt metadata.

```php
$validator = new RoadmapAnalysisValidator(['evidence-001', 'evidence-002'], []);
$invalid = learner_ai_roadmap_fixture();
$invalid['phases'][1]['start_day'] = 30;
expect_exception(
    static fn () => $validator->fromProviderPayload($invalid, model_metadata()),
    'Roadmap phases must not overlap.'
);
```

- [ ] Implement immutable value objects with constructor validation and `toArray()` methods; do not accept arbitrary metadata arrays after construction.
- [ ] Implement `RoadmapAnalysisValidator::fromProviderPayload(array $payload, array $engineMetadata): RoadmapAnalysis` and `validate(RoadmapAnalysis $analysis): void`.
- [ ] Run the focused contract test and the existing recommendation validator/provider tests.

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_roadmap_contract_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_provider_test.php
```

- [ ] Commit: `git commit -m "feat(ai): define roadmap v1 contract"`.

**Exit gate:** The exact fixture is the single contract consumed by backend tests and frontend tests; no database or network call is introduced.

---

### Task 2: Build the assessment-only roadmap input gate

**Files:**
- Create: `app/learner/ai/Quality/RoadmapQualityGate.php`
- Create: `tests/learner_ai_roadmap_quality_test.php`
- Modify: `app/learner/ai/Snapshot/RecommendationSnapshotBuilder.php`
- Modify: `app/learner/ai/Sources/Database/DatabaseAssessmentSource.php`
- Modify: `app/learner/ai/Consent/ConsentDecision.php`
- Modify: `app/learner/ai/Consent/ProviderConsentGate.php`
- Test: `tests/learner_ai_snapshot_test.php`
- Test: `tests/learner_ai_sources_test.php`
- Test: `tests/learner_ai_fresh_consent_gate_test.php`

**Interfaces:**

```php
final class RoadmapQualityGate
{
    public function evaluate(RecommendationInput $input): DataQualityResult;
}
```

The gate returns `ready` only when the snapshot contains one current submitted result for each canonical test family: `holland`, `mbti`, `disc`, `multiple_intelligence`. It accepts band-specific Holland codes and records optional enrichment counts.

The existing recommendation flow keeps `new ProviderConsentGate($policy)` with all four required scopes. The roadmap flow uses `new ProviderConsentGate($policy, ['assessment'])` for version 1 and includes optional scopes only when they are currently granted.

- [ ] Write failing cases for 0–3 families, duplicate older attempts, four current families, stale assessments, malformed scores, missing assessment consent, four assessments with no skills/activity/evaluation, and optional enrichment data.
- [ ] Update the assessment source/snapshot normalizer to select the newest submitted result per canonical family while retaining immutable evidence references.
- [ ] Implement the gate so version 1 requires assessment consent only; missing optional scopes set `enrichment_available=false` instead of `consent_required`.
- [ ] Add `ConsentDecision::permitsScopes(array $requiredScopes): bool` and constructor-injected required scopes to `ProviderConsentGate`; reject a context scope that is not currently granted and preserve the existing all-scope default behavior.

```php
$result = (new RoadmapQualityGate($now))->evaluate($fourAssessmentOnlyInput);
assert($result->state() === 'ready');
assert($fourAssessmentOnlyInput->qualityFlags()['source_counts']['assessments'] === 4);
```

- [ ] Run source, snapshot, gate, onboarding and cross-student tests.

```powershell
& $php tests\learner_ai_roadmap_quality_test.php
& $php tests\learner_ai_snapshot_test.php
& $php tests\learner_ai_sources_test.php
& $php tests\learner_onboarding_service_test.php
```

- [ ] Commit: `git commit -m "feat(ai): allow roadmap analysis after four assessments"`.

**Exit gate:** A newly onboarded learner with exactly four submitted assessments is eligible; three assessments remain blocked; other learners' results cannot enter the snapshot.

---

### Task 3: Create the Vietnamese evidence-grounded model prompt

**Files:**
- Create: `app/learner/ai/Model/RoadmapPromptRegistry.php`
- Create: `tests/learner_ai_roadmap_prompt_test.php`
- Test: `tests/learner_ai_scope_audit_test.php`

**Interfaces:**

```php
final class RoadmapPromptRegistry
{
    public const VERSION = 'learner-roadmap-prompt-1.0.0';
    public function create(RecommendationInput $input, RecommendationContext $context): ProviderRequest;
}
```

- [ ] Write a failing contract test asserting the system instruction requires Vietnamese, three exact phases, evidence citations on every insight/phase/task, no raw test recitation, no diagnosis, no guaranteed education/employment outcome, and no activity ID outside the allow-list.
- [ ] Add the explicit instruction block:

```php
'instructions' => [
    'Trả về duy nhất một JSON object hợp lệ theo learner-roadmap-1.0.0.',
    'Viết toàn bộ nội dung dành cho học viên bằng tiếng Việt tự nhiên.',
    'Không nhắc lại mã MBTI, điểm Holland, biểu đồ DISC hoặc điểm Multiple Intelligence.',
    'Mỗi insight, phase và task phải trích dẫn evidence_ref_ids được cung cấp.',
    'Không chẩn đoán, không khẳng định chắc chắn nghề nghiệp, tuyển sinh hoặc việc làm.',
    'Chỉ dùng activity_source_id có trong allowed_activity_ids.',
],
```

- [ ] Ensure the provider payload contains only safe profile band, normalized dimension scores/results, optional skills/activities/evaluations, allow-listed opportunities, and opaque evidence IDs.
- [ ] Add a prompt snapshot test proving names, emails, raw answer rows, student IDs and database IDs except allow-listed activity UUIDs are absent.
- [ ] Run prompt, consent and scope audit tests.
- [ ] Commit: `git commit -m "feat(ai): add grounded Vietnamese roadmap prompt"`.

**Exit gate:** The same input produces a deterministic serialized prompt, and every supplied field is covered by consent and privacy tests.

---

### Task 4: Call the real 9Router model and parse structured roadmaps

**Files:**
- Create: `app/learner/ai/Contracts/RoadmapProvider.php`
- Create: `app/learner/ai/Provider/RoadmapProviderResponse.php`
- Create: `app/learner/ai/Provider/HttpRoadmapProvider.php`
- Create: `app/learner/ai/Contracts/RoadmapEngine.php`
- Create: `app/learner/ai/Model/ModelRoadmapEngine.php`
- Create: `app/learner/ai/Rules/RuleRoadmapEngine.php`
- Create: `tests/learner_ai_roadmap_provider_test.php`
- Create: `tests/learner_ai_roadmap_engine_test.php`
- Modify only if extraction is proven behavior-preserving: `app/learner/ai/Provider/HttpRecommendationProvider.php`

**Interfaces:**

```php
interface RoadmapEngine
{
    public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis;
}

interface RoadmapProvider
{
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): RoadmapProviderResponse;
}

final class RoadmapProviderResponse
{
    public function isSuccess(): bool;
    /** @return array<string,mixed> */ public function payload(): array;
    public function errorCode(): ?string;
    public function providerRequestId(): ?string;
    public function responseHash(): ?string;
}
```

- [ ] Write transport tests for OpenAI/9Router envelope, direct JSON, fenced JSON, 400/401/403, 429, 500/502/503, timeout, invalid UTF-8, malformed JSON, unknown fields, missing citations and consent revoked before retry.
- [ ] Implement the provider with the current approved HTTPS/loopback allow-list, configured timeout/max attempts, Bearer key, `X-Model-Name`, and no raw body logging.
- [ ] Persist only a SHA-256 response hash and safe provider request ID in audit metadata; never persist the provider body.
- [ ] Implement `ModelRoadmapEngine` so success returns `origin=model`; all failures call `RuleRoadmapEngine` with `origin=rule_fallback` and an allow-listed fallback reason.
- [ ] Implement the fallback with server-owned Vietnamese copy derived from normalized facts, but keep it visually and contractually distinct from AI.
- [ ] Add a non-default local smoke command in `bin/smoke-learner-ai-roadmap-provider.php` that uses synthetic evidence, exits nonzero unless the live response validates, and prints only provider/model, success, item counts, response hash prefix and error code.
- [ ] Run all mock transport tests; run the live smoke command only in `APP_ENV=local|test` with explicit operator approval.

```powershell
& $php tests\learner_ai_roadmap_provider_test.php
& $php tests\learner_ai_roadmap_engine_test.php
& $php tests\learner_ai_9router_shadow_integration_test.php
& $php bin\smoke-learner-ai-roadmap-provider.php
```

- [ ] Commit: `git commit -m "feat(ai): generate structured roadmaps with real provider"`.

**Exit gate:** A live smoke result is accepted only if one external provider call returns a valid Vietnamese roadmap; provider failure is visibly rule fallback and never reported as model success.

---

### Task 5: Approve and implement additive roadmap persistence

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-24-ai-roadmap-store.md`
- Create after DCR approval: `Database/migrations/learner/005_create_ai_roadmap_store.php`
- Create after ID preflight: `Database/migrations/20260824000300_create_learner_ai_roadmap_store.php`
- Create: `tests/learner_ai_roadmap_migration_contract_test.php`
- Create: `tests/learner_ai_roadmap_schema_test.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`

**Schema:**

```text
learner_ai_roadmaps
  id, studentId, runId, versionNumber, contractVersion, status,
  executiveSummary, primaryDirectionJson, alternativeDirectionsJson,
  insightsJson, confidenceBand, evidenceSummaryJson, providerRequestId,
  responseHash, generatedAt, supersededAt, createdAt

learner_ai_roadmap_phases
  id, roadmapId, position, startDay, endDay, code, title, goal,
  skillFocus, deliverable, effortLabel, metricLabel, evidenceJson, createdAt

learner_ai_roadmap_tasks
  id, phaseId, position, title, description, estimatedMinutes,
  actionType, targetType, targetId, evidenceJson, createdAt

learner_ai_roadmap_task_events
  id, taskId, studentId, status, requestId, occurredAt, createdAt
```

- [ ] Write the DCR with exact columns, sizes, indexes, FKs, checks, triggers, lock expectations, backup, rehearsal and rollback-by-forward-fix procedure.
- [ ] Write a migration contract test before the migration. Require unique `(studentId, versionNumber)`, unique `runId`, unique phase/task positions, JSON checks, owner-match triggers, append-only task events, and immutable generated content.
- [ ] Confirm RED because the migration files do not exist.
- [ ] After DCR approval, implement MySQL and SQLite statements and a deployment bridge using migration ID `20260824000300` only if `bin/migrate.php validate` confirms it is unclaimed.
- [ ] Rehearse twice on a disposable schema and a restored clone. The second apply must be a no-op; pre-existing table row counts and hashes must remain unchanged.

```powershell
& $php tests\learner_ai_roadmap_migration_contract_test.php
& $php tests\learner_ai_roadmap_schema_test.php
& $php bin\migrate.php validate
```

- [ ] Commit migration source only after rehearsal: `git commit -m "feat(ai): add roadmap persistence schema"`.

**Exit gate:** Additive schema passes MySQL/SQLite contracts and rehearsal; primary `talenthub` is not mutated by this task.

---

### Task 6: Persist model provenance, roadmap versions and task progress

**Files:**
- Create: `app/learner/ai/Persistence/RoadmapRepository.php`
- Create: `app/learner/ai/Persistence/DatabaseRoadmapRepository.php`
- Create: `tests/learner_ai_roadmap_repository_test.php`
- Modify: `app/learner/ai/Persistence/DatabaseRecommendationRepository.php`

**Interfaces:**

```php
interface RoadmapRepository
{
    /** @return array<string,mixed> */
    public function saveCompleted(
        string $studentId,
        string $runId,
        RoadmapAnalysis $analysis,
        array $providerAudit
    ): array;
    public function latestForStudent(string $studentId): ?array;
    /** @return array<string,mixed> */
    public function appendTaskEvent(
        string $studentId,
        string $taskId,
        string $status,
        string $requestId
    ): array;
}
```

- [ ] Write repository tests for first version, idempotent reuse, second version superseding the first, cross-owner read/update denial, model provenance required for model origin, fallback provenance, transaction rollback, activity target ownership/existence, task event ordering, duplicate event reuse and progress derivation.
- [ ] Implement `saveCompleted()` as one short transaction after the provider returns. It verifies the owned completed run and snapshot evidence before inserting roadmap, phases and tasks.
- [ ] Derive phase/overall progress from the latest task event; default is `not_started`; accepted transitions are `not_started -> in_progress|completed|skipped`, `in_progress -> completed|skipped`, and `skipped -> in_progress`.
- [ ] Keep older roadmap content immutable and queryable; only one roadmap per learner has `status=active`.
- [ ] Run repository, recommendation repository and cross-student tests.
- [ ] Commit: `git commit -m "feat(ai): persist versioned roadmaps and progress"`.

**Exit gate:** A successful model run, its validated roadmap and its provenance are committed atomically after the network call; progress survives logout without rewriting generated content.

---

### Task 7: Orchestrate generation, reuse, fallback and re-analysis

**Files:**
- Create: `app/learner/ai/Service/RoadmapService.php`
- Create: `tests/learner_ai_roadmap_service_test.php`
- Modify: `app/learner/api/LearnerApiContext.php`
- Modify: `app/learner/ai/Config/RecommendationConfig.php`
- Modify: `.env.example`

**Interfaces:**

```php
final class RoadmapService
{
    public function latest(string $studentId): ?array;
    /** @return array<string,mixed> */
    public function generate(string $studentId, string $requestId, string $idempotencyKey): array;
    /** @return array<string,mixed> */
    public function updateTask(string $studentId, string $taskId, string $status, string $requestId): array;
}
```

- [ ] Write service tests for forbidden owner, consent missing, four-assessment ready, idempotent in-flight reuse, provider success, malformed response fallback, timeout fallback, last completed roadmap retained on failure, optional enrichment generating version 2, and rate limiting.
- [ ] Implement orchestration in this order: authorize owner → resolve current consent → build snapshot → quality gate → create pending run → call engine outside transaction → validate → complete run → save roadmap → map API response.
- [ ] Add a snapshot-content hash check so “Cập nhật phân tích” reuses the active roadmap when no source data changed.
- [ ] Add non-secret limits to `.env.example`: `TALENTHUB_AI_ROADMAP_TIMEOUT_SECONDS=30`, `TALENTHUB_AI_ROADMAP_PER_STUDENT_LIMIT=2`, `TALENTHUB_AI_ROADMAP_GLOBAL_LIMIT=20`.
- [ ] Run service, rollout, consent and provider failure matrix tests.
- [ ] Commit: `git commit -m "feat(ai): orchestrate roadmap generation and refresh"`.

**Exit gate:** The service never fabricates a model result, never duplicates a run for the same idempotency key, and never loses the last valid roadmap when refresh fails.

---

### Task 8: Expose owner-safe roadmap and progress APIs

**Files:**
- Create: `app/learner/api/v1/ai-roadmap.php`
- Create: `app/learner/api/v1/ai-roadmap-task.php`
- Create: `tests/learner_ai_roadmap_api_test.php`
- Modify: `app/learner/api/LearnerApiContext.php`

**HTTP behavior:**

```text
GET  ai-roadmap.php                 -> latest/not_generated/pending/ready_model/fallback_rule
POST ai-roadmap.php                 -> generate or refresh, requires CSRF + idempotency
POST ai-roadmap-task.php            -> {taskId,status}, requires CSRF + idempotency
```

- [ ] Write endpoint tests for authentication, student role, RBAC, CSRF, unknown fields, UUID validation, idempotency, rate limit, cross-student access, no raw provider metadata leak and every stable response state.
- [ ] Implement endpoints with `JsonResponder`, `LearnerApiContext`, `PersistentActionRateLimiter`, and the same safe error codes used by existing learner APIs.
- [ ] Return provider/model/prompt names only inside a collapsed technical block for `ready_model`; never return API URL, key, request body, response body or raw snapshot.
- [ ] Run API, security and onboarding gate regressions.
- [ ] Commit: `git commit -m "feat(ai): expose roadmap and progress APIs"`.

**Exit gate:** Only the authenticated learner can read/generate/update their roadmap; API responses pass the locked contract fixture.

---

### Task 9: Trigger real analysis after the fourth assessment

**Files:**
- Modify: `app/learner/api/v1/assessment-submit.php`
- Modify: `assets/js/learner-assessment.js`
- Modify: `app/learner/discover.php`
- Create: `assets/js/learner-ai-summary.js`
- Create: `tests/learner_ai_post_assessment_flow_test.php`
- Create: `tests/learner_ai_summary_ui_test.js`

**Flow:**

```text
fourth submit commits assessment
  -> response: ai_analysis={required:true,state:"not_generated"}
  -> navigate to discover.php?onboarding=completed&ai=analyze
  -> summary controller POSTs ai-roadmap.php once
  -> loading modal
  -> model success: concise summary + “Xem phân tích chi tiết”
  -> fallback: explicitly labelled fallback + retry option
  -> close/“Để sau”: roadmap remains accessible from AI gợi ý
```

- [ ] Write server tests proving no AI trigger for tests 1–3, one trigger after test 4, and assessment submission remains committed even if AI later fails.
- [ ] Write Node tests for one in-flight call, refresh-safe idempotency, loading, model summary, fallback label, failure retry, “Để sau”, safe redirect and accessible focus trapping.
- [ ] Add only trigger metadata to `assessment-submit.php`; do not perform the network call inside the assessment database transaction.
- [ ] Add semantic modal markup to `discover.php` and implement the controller using the real roadmap API.
- [ ] Ensure login/reload with no completed roadmap still offers generation, while a completed roadmap opens without regenerating.
- [ ] Run onboarding, assessment and summary flow tests.
- [ ] Commit: `git commit -m "feat(ai): analyze learner after final assessment"`.

**Exit gate:** Completing the fourth test reliably starts one real asynchronous analysis without coupling assessment persistence to provider availability.

---

### Task 10: Build the approved Roadmap-first page against the fixture

**Files:**
- Modify: `app/learner/ai-recommendations.php`
- Create: `assets/js/learner-ai-roadmap.js`
- Modify: `assets/css/learner.css`
- Create: `tests/learner_ai_roadmap_ui_test.js`
- Modify: `tests/learner_ai_recommendation_render_test.php`

**Required regions:**

```text
page header + freshness/update action
executive AI summary + evidence count/confidence band
primary direction + two alternatives
dominant 0–30 / 31–60 / 61–90 timeline
phase goal, skill, deliverable, effort, metric, checklist, progress
next actions rail
eligible activities
collapsed evidence summary
helpful/not-helpful feedback
loading, consent, insufficient, pending, error, fallback, ready states
```

- [ ] Write DOM tests from the canonical fixture; assert no raw assessment labels/scores/charts are rendered and untrusted model text is assigned only through `textContent`.
- [ ] Replace the current generic recommendation list with semantic server markup and a controller that consumes only `ai-roadmap.php`.
- [ ] Remove the `learner-recommendations.js` script tag from this page when `learner-ai-roadmap.js` is added; keep the legacy file for existing regression tests until a separate cleanup is approved.
- [ ] Implement the approved mockup using scoped `.learner-page-ai` selectors and the exact tokens in Global Constraints.
- [ ] Make desktop match the 16:9 mockup, tablet stack summary/direction and timeline, and mobile turn phases into vertical cards without horizontal overflow.
- [ ] Add keyboard focus, ARIA live status, reduced motion, 44px controls, meaningful headings and contrast-safe colors.
- [ ] Render “Tóm tắt từ AI” only for `analysis_origin=model`; render “Gợi ý dự phòng theo quy tắc” for fallback.
- [ ] Keep provider/model details collapsed and user-facing evidence readable as counts and dates.
- [ ] Run Node UI, PHP render and existing learner UI tests.

```powershell
& $node tests\learner_ai_roadmap_ui_test.js
& $php tests\learner_ai_recommendation_render_test.php
& $node tests\learner_ai_recommendation_ui_test.js
```

- [ ] Commit: `git commit -m "feat(ai): build roadmap-first learner interface"`.

**Exit gate:** The page visually follows the approved mockup, is responsive/accessibly operable, and contains no duplicated “Khám phá năng khiếu” result blocks.

---

### Task 11: Connect real activity actions, task progress, feedback and version history

**Files:**
- Modify: `assets/js/learner-ai-roadmap.js`
- Modify: `app/learner/ai/Service/RoadmapService.php`
- Modify: `app/learner/ai/Persistence/DatabaseRoadmapRepository.php`
- Modify: `app/learner/api/v1/recommendation-feedback.php`
- Create: `tests/learner_ai_roadmap_interaction_test.js`
- Create: `tests/learner_ai_roadmap_versioning_test.php`

- [ ] Write tests that only allow activity links supplied by the server, reject closed/full/ineligible activity targets, persist checklist status, restore progress after reload, calculate phase/overall progress, save feedback, and show changed sections between versions.
- [ ] Render `register_activity` as a safe link to `activity-detail.php?id=<UUID>` only after backend eligibility validation; self tasks remain local checklist actions.
- [ ] POST progress to `ai-roadmap-task.php`, update optimistically, and roll back the UI on API failure with an accessible error message.
- [ ] Feed aggregate feedback reason codes into the next snapshot as safe preference signals; never send free-form comments to the provider.
- [ ] Add a version selector showing generated time and the complete message “Dữ liệu mới đã làm thay đổi lộ trình này” without silently overwriting old versions.
- [ ] Run interaction, activity eligibility, feedback and version tests.
- [ ] Commit: `git commit -m "feat(ai): connect roadmap actions progress and history"`.

**Exit gate:** Every CTA performs a real application action, progress persists, and subsequent model versions are attributable to changed source data or explicit refresh.

---

### Task 12: Evaluate model quality and prove “real AI” end to end

**Files:**
- Create: `tests/learner_ai_roadmap_safety_evaluation_test.php`
- Create: `tests/learner_ai_roadmap_live_contract_test.php`
- Modify: `app/learner/ai/Evaluation/RecommendationEvaluator.php`
- Modify: `bin/report-learner-ai-evaluation.php`
- Create: `docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md`

**Required evidence:**

```text
provider_call_count = 1 for a successful new snapshot
analysis_origin = model
run.engineType = model
run.provider/modelVersion/promptVersion are non-empty
roadmap.runId matches the model run
responseHash is non-empty
all evidence refs resolve to the run snapshot
all activity targets resolve to eligible database records
Vietnamese language and contract validation pass
no fixture/hard-coded conclusion path is loaded in production
```

- [ ] Add deterministic safety cases covering diagnosis, protected traits, guaranteed outcomes, fabricated activities, unsupported links, prompt injection in profile/activities, English-only content, uncited claims and duplicated raw assessment results.
- [ ] Extend evaluation metrics with roadmap contract validity, Vietnamese-language rate, evidence coverage, activity-grounding rate, unsupported-claim rate, fallback rate and latency percentiles.
- [ ] Run shadow model generation on an approved pseudonymous sample; keep learner-visible output on rule until thresholds pass.
- [ ] Add a live contract test that refuses production, uses a synthetic/local test learner, calls the actual configured gateway, stores a model run in a disposable schema, and proves the required evidence list above.
- [ ] Review sample outputs manually for coherent summary, actionable phases, age/education-band appropriateness and non-repetition of the discovery page.
- [ ] Record hashes, commands, pass/fail counts and safe model metadata in the release checklist; never paste raw student prompts or outputs into the report.
- [ ] Commit: `git commit -m "test(ai): verify roadmap model quality and provenance"`.

**Exit gate:** Automated and reviewed evidence proves the displayed AI content came from the configured external model and meets the product/safety contract.

---

### Task 13: Rehearse database deployment and production rollback

**Files:**
- Modify: `docs/superpowers/database-change-requests/2026-08-24-ai-roadmap-store.md`
- Modify: `docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md`
- Test: `tests/learner_ai_roadmap_mysql_rehearsal_test.php`

- [ ] Create a fresh verified backup and record its path/hash outside the repository; do not print credentials.
- [ ] Restore the backup into an explicitly disposable schema and run the exact migration twice.
- [ ] Verify table/index/FK/check/trigger metadata, old recommendation row counts, four-assessment reads, existing onboarding workflow and recommendation API regressions.
- [ ] Simulate application rollback by running old code against the additive schema; it must ignore the new tables without error.
- [ ] Simulate model outage and confirm the last completed roadmap remains readable while refresh reports fallback/error safely.
- [ ] Obtain explicit primary-apply approval, apply once, run post-apply verification, and stop on any metadata mismatch.
- [ ] Commit only updated evidence documents: `git commit -m "docs(ai): record roadmap deployment rehearsal"`.

**Exit gate:** Production migration is repeatable, additive, backward-compatible and recoverable through application rollback plus forward database repair.

---

### Task 14: Controlled model visibility and browser E2E acceptance

**Files:**
- Modify: deployment environment only after approval; do not commit `.env`
- Modify: `docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md`
- Create: `tests/learner_ai_roadmap_browser_e2e.md`

- [ ] Keep production visibility at zero until Task 12 evaluation and Task 13 deployment gates both pass.
- [ ] Enable an approved pilot percentage and approval reference for an allow-listed student cohort; keep an immediate pause/zero-percent rollback switch.
- [ ] Execute the real browser flow: create disposable student → accept onboarding → answer and submit all four tests → observe AI loading modal → receive model summary → open Roadmap-first page → complete one task → logout/login → confirm progress → refresh after a data change → confirm version 2.
- [ ] Assert the page technical details show model origin and the database contains a matching model run/roadmap/response hash; assert no fallback label during the success path.
- [ ] Execute provider timeout and malformed-response paths and confirm fallback is explicitly labelled, old roadmap remains, and no false AI claim appears.
- [ ] Delete the disposable test account and verify no orphan roadmap, phase, task or event rows remain according to the approved test cleanup procedure.
- [ ] Monitor fallback rate, invalid response rate, p95 latency, user feedback and unsafe-output signals; pause the pilot if any approved threshold fails.

**Exit gate:** Product Owner signs off the real browser workflow and evidence. Only then increase visibility beyond the pilot.

## Full Verification Matrix

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$node = 'C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'

& $php tests\learner_ai_roadmap_contract_test.php
& $php tests\learner_ai_roadmap_quality_test.php
& $php tests\learner_ai_roadmap_prompt_test.php
& $php tests\learner_ai_roadmap_provider_test.php
& $php tests\learner_ai_roadmap_engine_test.php
& $php tests\learner_ai_roadmap_migration_contract_test.php
& $php tests\learner_ai_roadmap_schema_test.php
& $php tests\learner_ai_roadmap_repository_test.php
& $php tests\learner_ai_roadmap_service_test.php
& $php tests\learner_ai_roadmap_api_test.php
& $php tests\learner_ai_post_assessment_flow_test.php
& $php tests\learner_ai_roadmap_versioning_test.php
& $php tests\learner_ai_roadmap_safety_evaluation_test.php
& $node tests\learner_ai_summary_ui_test.js
& $node tests\learner_ai_roadmap_ui_test.js
& $node tests\learner_ai_roadmap_interaction_test.js

& $php tests\learner_recommendation_api_test.php
& $php tests\learner_recommendation_service_test.php
& $php tests\learner_ai_9router_shadow_integration_test.php
& $php tests\learner_ai_snapshot_test.php
& $php tests\learner_ai_sources_test.php
& $php tests\learner_onboarding_service_test.php
& $node tests\learner_ai_recommendation_ui_test.js
& $node tests\learner_onboarding_ui_test.js
& $php bin\migrate.php validate
git diff --check
```

Expected result: every command exits `0`, Node reports zero failures, migration validation reports no duplicate ID or metadata drift, and the readiness checklist contains a reviewed live model provenance record before model visibility is enabled.

## Review Checkpoints

1. Contract and product review after Tasks 1–3.
2. AI/provider and privacy review after Task 4.
3. Database DCR review before Task 5 migration source or any primary mutation.
4. Backend/API review after Tasks 6–8.
5. UI/accessibility review after Tasks 9–11.
6. Safety/evaluation review after Task 12.
7. Deployment approval after Task 13.
8. Product Owner browser acceptance after Task 14.
