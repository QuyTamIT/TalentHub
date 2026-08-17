# Holland Assessment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây dựng luồng Holland mock-first hoàn chỉnh trong Student Portal.

**Architecture:** PHP provider phát định nghĩa và lịch sử mẫu; JavaScript module thuần xử lý scoring, timer và persistence qua adapter; hai PHP route chỉ render UI và boot JSON. Storage interface được cô lập để thay LocalStorage bằng API sau này.

**Tech Stack:** PHP 8.3, JavaScript ES2020, LocalStorage, Node built-in test runner, CSS hiện có.

## Global Constraints

- Không sửa database, `app/enterprise`, `app/school`, `app/mentor`, `app/admin` hoặc code vai trò khác.
- Bộ đề mock phải ghi nguồn `school_expert` và version `1.0`.
- Dữ liệu vừa làm không đồng bộ đa thiết bị cho tới khi có API/backend.
- Mọi thay đổi production phải có failing test trước.

---

### Task 1: Holland mock provider

**Files:**
- Create: `app/learner/includes/assessment-data.php`
- Test: `tests/learner_holland_data_test.php`

**Interfaces:**
- Produces: `learner_assessment_catalog(): array`, `learner_assessment_definition(string): ?array`, `learner_assessment_questions(string): array`, `learner_assessment_history(string, string): array`.

- [ ] Viết test yêu cầu 24 câu, sáu nhóm RIASEC, ID duy nhất, version và history mẫu.
- [ ] Chạy test và xác nhận fail vì provider chưa tồn tại.
- [ ] Thêm provider tối thiểu với contract đã chốt.
- [ ] Chạy test và xác nhận pass.

### Task 2: Scoring and storage module

**Files:**
- Create: `assets/js/learner-assessment.js`
- Test: `tests/learner_holland_ui_test.js`

**Interfaces:**
- Produces: `scoreHolland(questions, answers)`, `getUnansweredQuestionIds(questions, answers)`, `getRemainingSeconds(expiresAt, now)`, `createAssessmentStorage(storage, key)`.

- [ ] Viết Node tests cho scoring chuẩn hóa, mã ba chữ, unanswered, timeout, JSON hỏng và round-trip attempt.
- [ ] Chạy test và xác nhận fail vì module chưa tồn tại.
- [ ] Viết các hàm thuần và storage adapter tối thiểu.
- [ ] Chạy test và xác nhận pass.

### Task 3: Assessment routes and discovery integration

**Files:**
- Create: `app/learner/assessment.php`
- Create: `app/learner/assessment-result.php`
- Modify: `app/learner/discover.php`
- Modify: `assets/js/learner.js`
- Test: `tests/learner_holland_render_test.php`

**Interfaces:**
- Consumes: PHP provider và `window.LearnerAssessment`.
- Produces: markers `data-assessment-runner`, `data-assessment-result-page`, boot JSON `learner-assessment-boot`.

- [ ] Viết render/route test cho intro, runner boot, result, history và route whitelist.
- [ ] Chạy test và xác nhận fail vì routes chưa tồn tại.
- [ ] Render hai route, thay Holland card bằng link thật, giữ ba bài còn lại ở trạng thái demo.
- [ ] Chạy test và xác nhận pass.

### Task 4: Interactive runner and states

**Files:**
- Modify: `assets/js/learner-assessment.js`
- Modify: `app/learner/assessment.php`
- Test: `tests/learner_holland_ui_test.js`

**Interfaces:**
- Consumes: boot JSON và storage adapter.
- Produces: intro/start/resume, autosave, navigation, submit confirmation, loading/error/expired behavior.

- [ ] Bổ sung failing tests cho attempt lifecycle và validation trước submit.
- [ ] Chạy test để quan sát failure đúng nguyên nhân.
- [ ] Nối DOM controller với các hàm đã test.
- [ ] Chạy toàn bộ Node/PHP tests.

### Task 5: Visual system and final verification

**Files:**
- Modify: `assets/css/learner.css`
- Test: all Holland and existing ecosystem tests.

- [ ] Thêm CSS scoped cho intro, runner, navigator, modal submit, expired/error và result/history.
- [ ] Chạy PHP lint toàn learner, Node syntax/tests và render tests.
- [ ] Chạy HTTP smoke cho discovery, assessment và result.
- [ ] Chạy `git diff --check` và scope guard để xác nhận không thay code vai trò khác.
