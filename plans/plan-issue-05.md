# Issue 05 Implementation Plan

## Trạng thái bàn giao chính thức

**Đã triển khai — chờ nghiệm thu AI thực tế. Chưa hoàn thành 100%, chưa đóng Issue #05.**

Có thể chuyển sang issue khác. Việc chuyển task không đồng nghĩa #05 đã qua nghiệm thu. Các kết quả dưới đây ghi nhận từ lần triển khai/kiểm thử trước; lần cập nhật tài liệu này không chạy lại runtime.

- [x] Giao diện: nút kích hoạt, phân tích trên danh sách hoạt động, điểm/lý do/kỹ năng, thu gọn và mở rộng, phân biệt không phù hợp với lỗi dịch vụ.
- [x] Backend: nối model được cấu hình, giữ ID/điểm/URL từ backend, kiểm tra quyền và dữ liệu cho phép; không dùng lời phân tích quy tắc thay thế khi provider lỗi.
- [x] Database: khôi phục đúng nguồn migration lịch sử; áp dụng migration 020 và validate MySQL local. Không xóa dữ liệu của các vai trò khác.
- [x] Freshness: đọc lại snapshot/candidate, kiểm tra lại sau lời gọi AI; tự revalidate tối đa mỗi 60 giây khi trang hiển thị sau lần bấm đầu tiên. Không phải cam kết realtime hoặc dịch vụ luôn sẵn sàng.
- [x] Kiểm thử đã ghi nhận: Node 9/9; 9 kiểm tra SQL cô lập; model validator với provider giả lập; kiểm tra cú pháp PHP. MySQL đọc dữ liệu và lưu/tải kết quả no-match đã chạy.
- [ ] Nhận phê duyệt kiểm thử gửi mã/tên/điểm kỹ năng và thông tin hoạt động tới Gemini (`generativelanguage.googleapis.com`); không gửi tên, email, điện thoại hay mã sinh viên.
- [ ] Chạy thành công ít nhất một phân tích qua Gemini thật và xác nhận lưu/tải lại kết quả. Lần thử trước trả `provider_unavailable`; yêu cầu chạy ngoài sandbox bị từ chối, chưa có bằng chứng thành công.
- [ ] Kiểm thử HTTP/trình duyệt có đăng nhập sinh viên: bấm phân tích, điểm/lý do, thu gọn/mở rộng, no_matches/insufficient_data, lỗi dịch vụ, quyền truy cập và kết quả mới sau đổi dữ liệu.
- [ ] Ghi lệnh/kịch bản, thời điểm và kết quả nghiệm thu vào tài liệu; chỉ sau khi các mục còn thiếu đều đạt mới đổi trạng thái sang **Hoàn thành**.

Các phần tiếp theo giữ lại kế hoạch và lịch sử triển khai. Những ghi chú cũ về thiếu bảng/migration bị chặn đã được thay thế bởi bản cập nhật database; không dùng chúng làm trạng thái hiện tại.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Add AI-ranked activity suggestions using the established opportunity matching contracts.

**Architecture:** Read published/ongoing activities into candidates, score deterministic skill/context fit, optionally generate grounded explanations, persist by learner snapshot, and expose GET/POST API plus an activities widget.

**Tech Stack:** PHP/PDO, existing AI snapshot/scorer/prompt/guard/persistence patterns, learner JS/CSS.

## Implementation

- [x] Define `ActivityCandidate` and `ActivityMatch`; add Node controller/API source-contract tests.
- [x] Create `DatabaseActivityCandidateSource`, delegating eligibility to `DatabaseActivityRepository::discoverForStudent`.
- [x] Implement `ActivityMatchService`: snapshot/candidate hash, deterministic top-three, grounded explanations, persisted state.
- [x] Add authenticated GET/POST endpoint, CSRF and persistent rate limiting.
- [x] Add scoped widget/CSS/controller, progress, collapse, stale, error, empty and completed states.
- [x] PHP scoring, SQL persistence and tenant-isolation fixture tests; MySQL migration and no-match smoke test (see recorded evidence below).
- [ ] Successful live Gemini response and authenticated browser/registration-flow acceptance. Isolated fixtures are not a substitute for this gate.

## Implementation record — 2026-09-08

### Current status — database repaired and model integration implemented

This section supersedes earlier records below that describe missing storage or deterministic-only output.

1. Recovered exact historical migration sources from existing repository commit `2bd7940`: `018_create_learner_sync_tracking`, `018_preserve_learner_participation_history`, `019_create_learner_evaluation_evidence`. Verified canonical checksums against the applied registry. No checksum updates, historical SQL re-execution, or guard bypass.
2. Applied **only** `020_create_activity_match_runs` through `bin/learner-migrate.php`. This creates the module-specific result table/index and its migration registry entry. `validate` passes. Pending Issue #01/#02 migrations were not applied. No existing learner, teacher, business or school records deleted/overwritten. Runtime smoke tests created three empty no-match result rows in the new module table; these are ordinary results, not learner profile modifications.
3. Added `ai/Model/ModelActivityMatchEngine.php`, registered in AI bootstrap and wired in `LearnerApiContext::activityMatchService`. Uses the configured `HttpRecommendationProvider`, rechecks the same consent policy before network attempts, sends allow-listed skill facts and ranked activity facts only. No name, email, phone or student ID is included in this module's prompt. Provider output must cover exactly the real IDs, cite their activity and skill references, pass grounded prose checks, and cannot alter backend scores/URLs. No deterministic prose fallback on provider failure.
4. `ActivityMatchService` now versions cache with provider/model/prompt, uses AI analysis for matched items, rereads profile/candidates after AI completes and refuses persistence when inputs changed during the call. Empty eligibility/matching results do not require an external model. Sortable run IDs resolve rapid same-second latest-result ordering.
5. Controller remains click-to-start. After activation, a visible page revalidates at most once per 60 seconds and on focus (subject to that throttle); stale/not-generated results trigger POST automatically. No work before the initial click; no background job when browser closes. Request timeout increased to 180 seconds to accommodate provider latency. This is bounded freshness, **not** a guarantee of uninterrupted service or zero-latency updates. Existing auth, CSRF, consent, rate limits remain.

Verification:

- Migration 020 apply and migration validate: PASS on configured local MySQL.
- `tests/learner_activity_model_test.php`: PASS for analysis mapping, immutable scoring, rejection of invented IDs, provider failure without fallback (test provider, no network).
- `tests/learner_activity_runtime_test.php`: 9 PASS checks using real repository/service SQL on isolated in-memory SQLite fixtures; school isolation, persistence, score freshness, activity freshness, concurrent data change, consent withdrawal and capacity exclusion. No real records modified by this suite.
- `tests/learner_ai_activity_matching_ui_test.js`: 9/9 PASS, including automatic stale refresh and click-only initial UI.
- `.codex_tmp/issue05-runtime.php --live`: local MySQL reads and no-match save/reload succeeded. External provider attempt failed with `provider_unavailable`. Escalated execution was rejected by approval review because it sends learner skill/activity data to a third-party provider. No retries or alternative route after rejection.

Remaining acceptance gate: explicit approval for the real-data external provider smoke test, then a successful response from the configured provider and authenticated browser end-to-end testing. **Do not claim Gemini runtime success yet.** Offline tests use a test provider; real data with missing skill scores correctly remains insufficient_data. Restoring missing migration files on this checkout does not authorize applying their schema changes to other databases.

### Approved UI correction and read-only database inspection

User approved: one AI trigger near filters, analysis above activity cards, initially hidden; click starts current-data analysis; score/reasons/skill development, collapse/reopen; no-match message distinct from infrastructure failure. Database permission for this follow-up is inspection only: no destructive changes, no registry edits or guard bypass.

Implementation sequence (writing-plans / TDD):

- [x] Extend `tests/learner_ai_activity_matching_ui_test.js` to assert no mount-time GET, initially hidden panel and placement between filters and catalog. Observed two expected failures before changing implementation.
- [x] Move markup in `app/learner/activities.php`; reuse `learner-btn` primary/outline styling, separate header/body and add scoped panel styles in `assets/css/learner-activity-matches.css`.
- [x] Update `assets/js/learner-activity-matches.js::mount`: only trigger POST on click, expand panel/body on each click, retain collapse and protect duplicate requests. Distinguish genuine no_matches from service/storage errors.
- [x] Add endpoint source contract for `ACTIVITY_MATCH_STORAGE_UNAVAILABLE` (observed failure first), then add read-only storage readiness query after permission/CSRF/rate-limit guards. Missing table returns safe 503, not empty recommendations. No automatic schema creation.
- [x] Re-run `.codex_tmp/issue05-db-preflight.php`: same three missing historical migration files, match storage and project_skill_tags still absent. No database writes performed.
- [ ] Real authenticated browser/API end-to-end acceptance after migration alignment; do not mark service operational merely because the UI tests pass.

Files changed in this follow-up: activities page, activity controller/CSS, activity endpoint, Node test, this plan and issues log. No ranking changes, migration changes or database writes.

### Database preflight follow-up — 2026-09-08

Found executable `D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe` (PHP 8.3.30). Connected successfully to the configured local MySQL database. Both `bin/learner-migrate.php status` and `apply --versions=020_create_activity_match_runs` fail with `Applied learner migration drift`; migration 020 was **not applied**. Read-only diagnostic `.codex_tmp/issue05-db-preflight.php` identified three applied versions with no corresponding files in this checkout:

- `018_create_learner_sync_tracking`
- `018_preserve_learner_participation_history`
- `019_create_learner_evaluation_evidence`

`learner_activity_match_runs` and `project_skill_tags` are missing in this database. No registry checksum edits, guard bypass, historical migration deletion or direct schema changes were performed. Reconcile the database's original migration sources with this branch, or provision a separate compatible development database, before retrying. This requires a database/branch alignment decision; do not silently copy unrelated schema migrations.

PHP syntax checks passed for the two domain classes, candidate source, service, API, migration 020, LearnerApiContext and activities page (8 files). Node tests re-run: 7/7 PASS. These checks do **not** verify MySQL scoring/persistence or real HTTP sessions.

Freshness limitation confirmed by inspection: GET/POST rebuild the profile snapshot and current eligible candidates, but GET only marks a changed saved result `stale_model`; recalculation requires POST via the refresh button. No polling/push or automatic stale-refresh is implemented. Thus the current implementation must not be described as automatically always up to date. Next authorized implementation should add bounded automatic refresh for stale results (and revalidation on page focus), with rate-limit/backoff, consent/ownership checks, and runtime fixtures changing learner skill scores and activity tags/status/capacity. No claim of completed end-to-end freshness testing is made while migration is blocked.

Implemented at the user's explicit request ahead of #04/#07. No existing teacher, school or registration workflow is changed. No external model is called: explanations describe canonical skill overlap and gaps, never invented activity attributes.

### Files delivered

New files (paths relative to repository root):

- `app/learner/ai/Matching/ActivityCandidate.php`
- `app/learner/ai/Matching/ActivityMatch.php`
- `app/learner/ai/Sources/Database/DatabaseActivityCandidateSource.php`
- `app/learner/ai/Service/ActivityMatchService.php`
- `app/learner/api/v1/activity-matches.php`
- `Database/migrations/learner/020_create_activity_match_runs.php`
- `assets/js/learner-activity-matches.js`
- `assets/css/learner-activity-matches.css`
- `tests/learner_ai_activity_matching_ui_test.js`

Modified: `app/learner/ai/bootstrap.php`, `app/learner/api/LearnerApiContext.php` (factory only), `app/learner/activities.php`, `.gitignore` (keep the new test trackable), this plan and `ISSUES_LOG.md`. Other dirty files predate this task.

### Data and state contracts

`DatabaseActivityCandidateSource::candidates` reuses same-school approval/visibility, capacity and registration-window checks and excludes existing pending/approved/waitlisted/attended registrations. **Ongoing activities are excluded** because the existing registration workflow closes at start time; this deliberately retains current eligibility instead of suggesting an activity that cannot be joined.

`ActivityMatchService::resolve` requires skills/assessment/activity consent and at least one scored skill. Assessments alone without scored skills yield `insufficient_data`; an assessment is not independently mandatory. Candidate skill labels are normalized against the active skills registry. Unknown or non-overlapping tags produce no match, never fabricated results to fill three slots.

For each overlapping skill: `30 + 60 * (100 - skillScore) / 100 + 10 if confirmed experience contains that code`. Divide by all candidate skill tags; missing evidence contributes zero. Round and order descending, break ties by activity ID, return at most three. Scores are heuristic, not validated probability. Gaps are scored skills below 70. Existing confirmed project/activity/evaluation tags contribute through `LearnerOpportunityProfile::confirmedExperienceTags()` without assuming a project implies mastery.

Hash includes algorithm version, consent-safe profile snapshot and sorted current candidates (ID/title/tags/start). GET rechecks eligibility and hash; stale cards are filtered against current eligible IDs. POST refreshes explicitly; identical hashes reuse results. No background refresh/outbox integration is added. Saved runs are learner-owned in the new table; history retention/cleanup is not implemented here. Progress is explicitly labelled illustrative, capped at 90 while waiting and reaches 100 on a successful response, not server telemetry.

### Verification and deployment gate

Run `node tests/learner_ai_activity_matching_ui_test.js` and `git diff --check`. Tests cover saved/stale state, failed refresh, warning states, concurrent clicks, top-three UI cap, auth-error clearing, safe DOM cards/links, collapse and endpoint/source contracts.

Executed on 2026-09-08: Node **7/7 PASS**; `git diff --check` **PASS**, with only Git line-ending conversion warnings (not whitespace errors).

PHP/database runtime and migration have **not** been executed in this task. Before production rollout, use the existing `bin/learner-migrate.php` workflow against a backed-up development database; inspect its help/configuration first. Then verify:

1. Learners in two schools never see each other's candidates/results; spoof body identity is rejected; missing session/permission/CSRF fails; repeated POST triggers rate limit.
2. Published/open/in-capacity activity with canonical overlapping tags is returned; closed/full/unapproved/invisible/other-school/already-registered candidates are excluded.
3. No scored skills returns insufficient_data; no eligible candidates or tags returns no_matches; no consent returns consent_required with no saved cards.
4. Generate, reload and confirm persistence. Change skill score/title/tags or add an eligible activity: GET becomes stale, POST recalculates. Close an activity: GET drops its saved card.
5. Fixed fixtures reproduce the formula and ID tie-break; fewer than three matches remain fewer than three. Existing filters/detail/registration remain functional.
6. Test narrow/mobile layout and keyboard collapse/refresh in a real browser; Node DOM doubles do not verify visual layout.

### Handoff #07 — future evidence integration

`app/learner/ai/Service/ActivityMatchService.php::profile` contains the required `TODO(issue-07)` hook. Add verified internship/submission evidence through registered, consent-safe snapshot sources in `LearnerApiContext::snapshotBuilder` and `RecommendationSnapshotBuilder`, then extend `LearnerOpportunityProfile::confirmedExperienceTags` if new evidence shapes are needed. Keep canonical codes, learner ownership, verification/status checks and stable evidence hashes. Do not query future tables in the scorer or equate submission with verification. Bump `ActivityMatchService::VERSION` if scoring semantics change. Add runtime fixtures for revoked/unverified evidence and stale detection. No `project_submissions` query or future internship-column dependency was added.

### Handoff #04 — PDF remains independent

No PDF/export hook is required for matching. Future changes belong in `app/learner/talent-passport.php` and its print styles, not this widget/controller. If exporting suggestions is later explicitly requested, use `ActivityMatchService::latest` in an authenticated export flow, label stale results, and never generate/mutate from a print request. Do not infer completed participation from a recommendation.
