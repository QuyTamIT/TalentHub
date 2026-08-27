# Learner AI Customer Requirements Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox syntax and are intended to be executed in order.

**Goal:** Hoàn thiện AI TalentHub theo đầy đủ yêu cầu khách hàng cho học sinh, đồng thời bảo đảm khi Gemini lỗi/thời gian chờ/hết quota thì người dùng vẫn nhận được kết quả gần nhất có nguồn gốc rõ ràng, không mất dữ liệu và không tạo fallback im lặng.

**Architecture:** Dùng một `AiAvailabilityPolicy` chung cho roadmap và recommendation; snapshot hợp nhất từ Talent Passport và các nguồn hoạt động; xử lý Gemini bất đồng bộ qua queue có retry/circuit breaker; lưu bản model gần nhất (last-known-good) với trạng thái stale; cập nhật theo event; tách AI insight cấp trường và AI matching doanh nghiệp thành hai capability có consent/privacy riêng.

**Tech Stack:** PHP 8.3, MySQL migrations, existing learner AI domain/services, Gemini HTTP provider, vanilla JavaScript UI, PHPUnit-style standalone test scripts already used by the repository.

## Current audit summary

Chi tiết bằng chứng nằm trong [audit report](../readiness/2026-08-26-learner-ai-customer-requirements-audit.md). Kết quả kiểm tra hiện tại là **chưa đủ toàn bộ yêu cầu**:

| Nhóm | Hiện trạng | Kết luận nghiệm thu |
|---|---|---|
| Bốn bài test Holland/DISC/MBTI/Multiple Intelligence | Có scorer và roadmap input cơ bản | Đạt một phần; cần hợp nhất với Talent Passport |
| Hồ sơ, kỹ năng, hoạt động, đánh giá | Có repository/snapshot cơ bản | Thiếu chứng chỉ, dự án, thành tích, huy hiệu, tiến độ, check-in và nhận xét chi tiết trong prompt |
| Phân tích điểm mạnh/yếu/tiềm năng | Có ba nhóm insight trong roadmap | Thiếu contract riêng cho talent-map và xu hướng phát triển |
| Recommendation | Có rule/model engine và API | Thiếu catalog/action rõ cho group, workshop, project, contest; dashboard chưa hiển thị danh sách live |
| Roadmap 90 ngày | Có model engine, validator, persistence | Đang gọi đồng bộ; chưa có last-known-good/stale và gate dùng chung |
| Adaptive feedback loop | Có task progress và feedback signals | Chưa có event-driven refresh/debounce/versioning |
| Nhà trường | Có analytics deterministic | Chưa có AI aggregate insight/privacy threshold/explanation |
| Doanh nghiệp | Có search/filter/consent | Chưa có job normalization, AI match score/ranking/explanation |
| Độ tin cậy Gemini | Có bounded attempts và xử lý một số mã lỗi | Thiếu queue, exponential backoff, circuit breaker, dead-letter và stale serving |
| Rollout | Recommendation có selector; roadmap context bỏ qua selector | Gate không nhất quán, cần policy duy nhất |

## Global constraints

- Không tuyên bố “100% không lỗi”. Mục tiêu kỹ thuật là **100% người dùng luôn có trạng thái hữu ích**: model mới, model stale có nhãn, hoặc trạng thái đang xử lý rõ ràng; chỉ dùng rule engine khi chưa có model hợp lệ và phải hiển thị nguồn `rule`.
- Không đưa API key vào mã nguồn, log, prompt, response hoặc client. Sau khi hoàn tất kiểm tra cần rotate key đã từng lộ trong terminal.
- Mọi insight phải có evidence reference, timestamp và model/version; không ghi đè dữ liệu gốc của học sinh.
- Consent scope hiện hành là `assessment`, `skills`, `activity`, `evaluation`. Nếu bổ sung scope cho certificate/project/badge/feedback, phải thêm migration, policy, UI grant/revoke và test từ chối từng scope.
- Không gửi PII không cần thiết cho Gemini; school insight chỉ gửi aggregate đạt ngưỡng cohort; enterprise matching loại trừ thuộc tính được bảo vệ.
- Tất cả job phải idempotent theo `(student_id, snapshot_hash, capability)` và có correlation/request id.

## Dynamic data principle — dữ liệu mới không cần sửa prompt/code

Thiết kế này hỗ trợ dữ liệu mới tự động cập nhật AI theo nguyên tắc:

1. **Database là nguồn sự thật:** hoạt động trường, cơ hội doanh nghiệp, chứng chỉ, huy hiệu, dự án và đánh giá được lưu theo entity/catalog chuẩn, có `updated_at`, `version` và trạng thái publish/verify.
2. **Outbox/event thay vì hard-code:** mọi create/update/publish/verify/archive phát sinh event như `activity.changed`, `opportunity.changed`, `credential.changed`, `badge.changed`, `project.changed`, `evaluation.changed`. Event chứa entity id, tenant, affected students (nếu có), version và correlation id.
3. **Source registry có schema version:** snapshot builder đọc danh sách source adapter đã đăng ký và canonical fields; prompt nhận JSON schema ổn định, không chứa danh sách cơ hội được viết cứng trong code.
4. **Incremental snapshot:** worker chỉ tải phần dữ liệu thay đổi, sau đó tạo `snapshot_hash` mới cho học sinh hoặc aggregate phù hợp. Hệ thống debounce nhiều thay đổi liên tiếp và enqueue lại đúng một job.
5. **Catalog recommendation động:** trước khi xếp hạng, engine truy vấn catalog các item đang publish, còn hạn và đủ điều kiện; model chỉ được tham chiếu `catalog_id` tồn tại, vì vậy cơ hội mới được đề xuất mà không cần sửa prompt.
6. **Refresh SLA và fallback minh bạch:** sau event, job được xử lý trong SLA đã công bố; trong lúc chờ hiển thị bản model cũ với nhãn stale hoặc trạng thái pending. Khi provider lỗi, không mất bản model gần nhất.

Điều này có nghĩa là **thêm một bản ghi hoạt động/cơ hội/chứng chỉ/huy hiệu mới sẽ không cần chỉnh code**. Chỉ khi tạo **một loại dữ liệu hoàn toàn mới hoặc thay đổi cấu trúc schema** mới cần thêm adapter/migration và test tương ứng.

## Phase 0 — Khóa baseline và contract khách hàng

### Task 0.1: Đóng băng ma trận yêu cầu bằng test contract

- [ ] Tạo `tests/learner_ai_customer_requirements_contract_test.php`.
- [ ] Khai báo sáu capability: `profile_analysis`, `talent_passport`, `recommendation`, `roadmap`, `adaptive_loop`, `matching`; kiểm tra các output state và provenance bắt buộc.
- [ ] Kiểm tra API response có `analysis_origin`, `evidence`, `generated_at`, `model_version` hoặc trạng thái pending/stale/unavailable.
- [ ] Chạy `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests/learner_ai_customer_requirements_contract_test.php` cùng 11 test baseline trong audit report.

### Task 0.2: Chuẩn hóa release gate

- [ ] Cập nhật `docs/superpowers/readiness/learner-ai-evaluation-gate.md` và `docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md` với tiêu chí dữ liệu, độ mới, độ ổn định provider, privacy và rollback.
- [ ] Định nghĩa gate 0% → pilot → 100%; chỉ bật 100% sau khi gate có sample review, error budget, stale SLA và approval reference hợp lệ.

## Phase 1 — Unified availability/rollout policy

### Task 1.1: Tạo policy quyết định khả dụng AI

- [ ] Tạo `app/learner/ai/Availability/AiAvailabilityDecision.php` với `state()`, `reason()`, `canRefresh()`, `canServeActiveModel()`, `canServeStaleModel()`.
- [ ] Tạo `app/learner/ai/Availability/AiAvailabilityPolicy.php` với method:

  ```php
  public function decide(
      string $studentId,
      RecommendationConfig $config,
      array $allowedScopes,
      bool $snapshotCurrent,
      bool $hasActiveModel,
      bool $ruleFallbackCompleted,
  ): AiAvailabilityDecision
  ```

- [ ] Policy phải kiểm tra enabled, shadow/gate, visibility bucket, pilot pause, approval reference, consent, snapshot freshness và model health theo cùng thứ tự cho roadmap lẫn recommendation.
- [ ] Quy định rõ `shadow` chỉ tạo/đánh giá output không hiển thị; `visible` mới được trả model; `stale_model` được hiển thị khi refresh thất bại; `rule` chỉ là nguồn dự phòng minh bạch.

### Task 1.2: Nối policy vào mọi entry point

- [ ] Sửa `app/learner/api/LearnerApiContext.php`, `app/learner/ai/Service/RecommendationService.php`, `app/learner/ai/Service/RoadmapService.php` và `app/learner/ai/Rollout/RecommendationRolloutSelector.php` để không còn nhánh roadmap tự chọn model chỉ dựa trên `enabled` và assessment consent.
- [ ] Giữ tương thích với `AiPilotPolicy` bằng cách chuyển nó sang đọc `AiAvailabilityDecision` hoặc dùng cùng các predicate.
- [ ] Tạo `tests/learner_ai_availability_policy_test.php` bao phủ gate, pause, visibility 0/100, thiếu consent, snapshot stale, active model và rule fallback.

## Phase 2 — Mở rộng snapshot và output contract

### Task 2.1: Hợp nhất toàn bộ nguồn dữ liệu học sinh

- [x] Tạo `app/learner/ai/Sources/LearnerAiExtendedSource.php` và `app/learner/ai/Sources/Database/DatabaseLearnerAiExtendedSource.php` để đọc có consent từ `DatabaseTalentPassportRepository`, activity/check-in source, evaluation source, feedback/task repositories.
- [x] Sửa `app/learner/ai/Snapshot/RecommendationSnapshotBuilder.php` để thêm `certificates`, `projects`, `achievements`, `badges`, `progress`, `checkin_experience`, `mentor_evaluations`, `teacher_feedback`, `roadmap_feedback` và source timestamps/evidence ids; nguồn chưa có canonical schema được đánh dấu unavailable.
- [x] Giữ các trường hiện có: bốn assessment families, profile, skills, activities, opportunities; chuẩn hóa tất cả về schema version và stable `snapshot_hash`.
- [x] Bổ sung consent mapping và migration nếu nguồn mới cần scope riêng; test revoke phải loại đúng nguồn đó khỏi prompt.
- [x] Tạo `tests/learner_ai_snapshot_extended_sources_test.php` kiểm tra đủ nguồn, allow-list, hash, redaction, production registry wiring và deterministic ordering.

### Task 2.2: Xây source registry và canonical schema mở rộng

- [x] Tạo `app/learner/ai/Sources/AiSourceRegistry.php` và các adapter canonical cho `activity`, `opportunity`, `certificate`, `badge`, `project`, `achievement`, `progress`, `evaluation`, `feedback`, `checkin`.
- [x] Mỗi adapter khai báo `sourceType`, schema version, consent scope, refresh trigger, fields được phép gửi và hàm `changedSince(version/timestamp)`; snapshot builder chỉ phụ thuộc registry.
- [x] Thêm contract test để khi insert entity mới vào catalog, adapter tự đọc được record mà không cần thay đổi prompt template.

### Task 2.3: Nâng contract phân tích và recommendation

- [x] Sửa `app/learner/ai/Domain/RoadmapAnalysis.php`, `app/learner/ai/Model/RoadmapPromptRegistry.php`, `app/learner/ai/Validation/RoadmapAnalysisValidator.php` để có `talent_map` (tỷ lệ theo lĩnh vực), `trend_signals` (tín hiệu tăng/giảm kèm evidence), `growth_hypotheses` và confidence.
- [x] Mở rộng `RecommendationItem`/`PromptRegistry` và `RecommendationResponseMapper` với category/action/catalog id cho group, field, activity, workshop, project, contest, skill; mọi item phải có lý do và evidence.
- [x] Không cho model tự bịa cơ hội: catalog id phải tồn tại và còn hiệu lực; nếu không có catalog match thì trả insight/action chung có nhãn rõ.
- [x] Tạo `tests/learner_ai_customer_output_contract_test.php`, cập nhật roadmap contract/quality fixtures và kiểm tra validator từ chối thiếu evidence, tỷ lệ không hợp lệ, action không nằm allow-list.

## Phase 3 — Resilient Gemini execution và last-known-good

### Task 3.1: Queue và idempotent refresh jobs

- [x] Tạo migration `Database/migrations/learner/006_create_ai_refresh_jobs.php` với job key, capability, snapshot hash, status, attempts, next retry, lease, error code và dead-letter fields.
- [x] Tạo migration `Database/migrations/learner/007_create_ai_data_outbox.php` với aggregate type/id, tenant, event type, aggregate version, payload hash, affected student ids, delivery status và occurred_at; unique key trên aggregate version để chống phát trùng.
- [x] Tạo `app/learner/ai/Queue/AiRefreshJob.php`, `AiRefreshJobRepository.php`, `AiRefreshDispatcher.php` và worker `bin/worker-learner-ai-refresh.php`.
- [x] Tạo `app/learner/ai/Queue/AiDataOutbox.php` và publisher/consumer; mutation transaction phải ghi entity và outbox event trong cùng transaction, consumer đánh dấu delivered sau khi enqueue refresh thành công.
- [x] Sửa `app/learner/api/v1/assessment-submit.php` để ghi kết quả test trước, dispatch refresh sau; không chặn request bởi Gemini.
- [x] Hook dispatcher vào thay đổi profile/skills, activity/check-in, teacher/mentor evaluation, feedback, badge/progress, certificate/project.
- [x] Tạo test idempotency, lease recovery, duplicate event, dead-letter và worker shutdown an toàn.

### Task 3.2: Retry, circuit breaker và provider health

- [x] Tạo `app/learner/ai/Provider/RetryPolicy.php`, `CircuitBreaker.php` và storage health tương ứng; sửa `HttpRecommendationProvider.php` và `HttpRoadmapProvider.php`.
- [x] Áp dụng timeout hữu hạn, exponential backoff + jitter, tôn trọng `Retry-After`, retry có chọn lọc cho 429/5xx/network timeout, không retry lỗi schema/permission; giới hạn token và response size.
- [x] Ghi metrics latency, status, retry count, circuit state, quota/error category; không ghi prompt hoặc secret.
- [x] Tạo provider tests dùng clock/sleep injection cho 429, 500/502/503, timeout, malformed JSON, invalid key và circuit open/half-open.

### Task 3.3: Lưu và phục vụ model gần nhất

- [x] Tạo migration `Database/migrations/learner/008_add_ai_freshness_and_refresh_state.php` cho roadmap/recommendation run: `freshness_status`, `stale_since`, `last_refresh_error`, `next_retry_at`, `model_version`, `snapshot_hash`, `refresh_job_id`.
- [x] Sửa `DatabaseRoadmapRepository.php`, repository interfaces, `RoadmapService.php`, recommendation persistence và response DTO để phục vụ bản active model khi refresh lỗi.
- [x] Chuẩn hóa state: `ready_model`, `stale_model`, `pending`, `ai_unavailable`, `ready_rule`; không đổi model cũ thành rule nếu model hợp lệ còn tồn tại.
- [x] Cập nhật `app/learner/ai-recommendations.php` và API để hiển thị thời điểm stale, lỗi thân thiện, lần thử kế tiếp và nút retry; không lộ chi tiết Google/API.
- [x] Tạo test failure matrix: Gemini success, transient failure, prolonged outage, invalid key, quota exhaustion, stale model absent/present.

## Phase 4 — Adaptive loop và Talent Passport AI profile

### Task 4.1: Event-driven reanalysis

- [x] Tạo event contracts/listeners trong `app/learner/ai/Events` và `app/learner/ai/Listeners`; mỗi event ghi source version và enqueue debounce window.
- [x] Kết nối các mutation service hiện có cho check-in QR, hoàn thành activity, teacher/mentor evaluation, feedback, badge, progress, profile, skill, certificate, project.
- [x] Debounce các thay đổi liên tiếp, hủy job snapshot cũ chưa chạy, tạo run version mới khi snapshot hash đổi; bảo đảm feedback cập nhật roadmap/recommendation trong SLA đã công bố.
- [x] Tạo `tests/learner_ai_adaptive_refresh_test.php` cho từng event, debounce, consent revoke và version ordering.

### Task 4.2: AI capability profile trong Talent Passport

- [x] Tạo `app/learner/ai/Service/AiCapabilityProfileService.php` và read model/version table; lưu talent map, strengths, improvements, potential paths, trends, evidence, generated/stale metadata.
- [x] Tích hợp read model vào `app/learner/data/Database/DatabaseTalentPassportRepository.php`, `app/learner/profile.php` và dashboard; dữ liệu gốc vẫn do repository nguồn sở hữu.
- [x] Cho phép học sinh/mentor xem nguồn bằng chứng, thời điểm cập nhật và phản hồi insight; không dùng AI score để âm thầm thay thế điểm đánh giá chính thức.
- [x] Tạo tests provenance, supersession, consent, stale display và rollback phiên bản.

## Phase 5 — Recommendation catalog và learner UX

### Task 5.1: Catalog-backed recommendations

- [x] Bổ sung catalog adapters/repositories cho group/community, workshop, project, contest và skill resources; mỗi item có trạng thái publish, deadline, eligibility, capacity và URL/action.
- [x] Đọc catalog theo `updated_at`/version trong mỗi refresh; bản ghi mới hoặc bản ghi vừa publish phải tạo outbox event và xuất hiện trong lần recommendation kế tiếp, không cần đổi prompt/template.
- [x] Sửa rule/model engines để prefilter catalog theo quyền/eligibility rồi mới xếp hạng; persist `catalog_id`, `reason_codes`, `evidence_refs`.
- [x] Tạo API contract tests cho GET/POST recommendations, consent boundary, expired opportunity và empty catalog.

### Task 5.2: Dashboard và roadmap UI

- [x] Sửa `assets/js/learner-ai-roadmap.js`, `app/learner/ai-recommendations.php`, `app/learner/index.php` để hiển thị live cards cho đủ nhóm recommendation, talent map, strengths/improvements/trends và 90-day roadmap.
- [x] Thêm polling/backoff cho `pending`, banner cho `stale_model`, trạng thái `ai_unavailable`, provenance tooltip và feedback controls.
- [x] Không gọi Gemini từ browser; mọi request đi qua server API với CSRF/auth/consent.
- [x] Chạy PHP API tests và browser smoke test cho student mới, student đủ dữ liệu, student thiếu consent và Google outage giả lập.

## Phase 6 — School AI aggregate insight

### Task 6.1: Capability riêng cho nhà trường

- [x] Tạo `src/Modules/School/Service/SchoolAiInsightService.php`, aggregate source/repository, endpoint và view; tái sử dụng provider reliability nhưng không tái sử dụng student prompt.
- [x] Tính aggregate theo lớp/khối/trường, min cohort threshold, suppression khi nhóm nhỏ, trend confidence và drill-down chỉ tới dữ liệu được phép; lọc active student và evidence freshness.
- [x] Cho phép AI giải thích xu hướng bằng aggregate evidence; mọi bảng đếm/ranking cơ bản vẫn từ analytics deterministic; payload/response bounded và redacted.
- [x] Tạo tests privacy threshold, role/tenant isolation, consent/retention, prompt redaction, async enqueue và stale insight.

## Phase 7 — Enterprise AI matching

### Task 7.1: Job normalization và ranking có giải thích

- [x] Tạo `src/Modules/Business/Service/EnterpriseMatchService.php` và repository extension trên `EnterpriseTalentRepository.php`.
- [x] Pipeline: parse yêu cầu việc làm → deterministic prefilter theo grant/consent/skill → AI hoặc ranking model chấm `match_score` → reason codes/evidence → sort ổn định.
- [x] Exclude protected traits, hidden student fields và suy luận nhạy cảm; tôn trọng consent rút lại và tenant boundary.
- [x] Tạo tests access grant, consent revoke, ranking determinism, score explanation, no-candidate và provider outage với cached ranking.

## Phase 8 — Observability, rollout và nghiệm thu 100%

### Task 8.1: SLO, dashboards và runbook

- [x] Thêm metrics/structured logs cho queue depth/age, model freshness, stale ratio, provider latency/error/quota, circuit state, fallback rate, recommendation click/feedback và token cost.
- [x] Tạo runbook xử lý quota hết, key hết hạn, Google outage, migration rollback, queue backlog, bad model output và consent incident; có lệnh rotate key.
- [x] Cập nhật readiness docs với owner, alert threshold, retention và privacy review.

### Task 8.2: Verification và staged release

- [x] Chạy static checks và learner migration trên database disposable (SQLite rehearsal); live Gemini test key/network remains environment-gated and synthetic outage is covered by focused contracts.
- [x] Kiểm tra các seam end-to-end và refresh/feedback contracts bằng executable focused tests; production Gemini integration requires deployment credentials.
- [x] Rollout theo 0% shadow, pilot có approval, 10%/25%/50%/100%; gate yêu cầu error budget, freshness SLA, validator pass rate, privacy audit, rollback drill và các verification bắt buộc.
- [x] Chỉ công bố “AI hiển thị 100% sinh viên” khi `enabled=true`, `shadow_gate_approved=true`, `visible_percent=100` (strict integer), `pilot_paused=false`, approval reference hợp lệ, policy thống nhất và last-known-good/queue/monitoring đã nghiệm thu.

## Implementation order and dependencies

1. Phase 0 khóa contract và baseline.
2. Phase 1 sửa rollout inconsistency trước khi tăng visibility.
3. Phase 2 mở rộng snapshot/output để đáp ứng đúng nội dung khách hàng.
4. Phase 3 triển khai queue/provider/LKG để không phụ thuộc uptime tuyệt đối của Google.
5. Phase 4 nối adaptive loop và Talent Passport AI profile.
6. Phase 5 hoàn thiện catalog và trải nghiệm học sinh.
7. Phase 6 và 7 triển khai school/enterprise capability sau khi core reliability ổn định.
8. Phase 8 chạy gate và staged release; chỉ sau bước này mới bật 100%.

## Definition of done

- Sáu capability trong ma trận đều có API/UI/test và provenance.
- Tất cả nguồn dữ liệu khách hàng liệt kê đều xuất hiện trong snapshot hoặc có lý do consent/retention được hiển thị.
- Thêm mới một hoạt động, cơ hội, chứng chỉ, huy hiệu hoặc dự án trong database/catalog tạo outbox event, refresh đúng học sinh/aggregate và xuất hiện trong recommendation/roadmap theo SLA mà không sửa code hoặc prompt.
- Mọi refresh Gemini chạy async, retry/circuit breaker, idempotent và có dead-letter.
- Khi Gemini lỗi, người dùng vẫn nhận model gần nhất có nhãn stale hoặc trạng thái pending/unavailable rõ ràng; không có fallback im lặng.
- Adaptive refresh chạy sau các event dữ liệu đã liệt kê và không tạo duplicate run.
- School insight và enterprise matching đạt privacy/tenant/consent tests.
- Full test suite, outage simulation, migration rehearsal và rollback drill đều đạt; rollout 100% chỉ thực hiện sau approval.
