# Kế hoạch triển khai minh bạch điểm số và nghiệm thu

> Dành cho người triển khai: thực hiện từng gói việc có kiểm thử hồi quy; dùng `subagent-driven-development` hoặc `executing-plans` nếu làm bằng agent. Người dùng đã yêu cầu triển khai; không hỏi lại quyền tiếp tục giữa các bước thông thường.

**Mục tiêu:** mỗi điểm đánh giá/kỹ năng trên mọi cổng đều có nguồn hệ thống và cách giải thích; Gemini không tạo hoặc sửa điểm năng lực; cổng giảng viên, nhà trường, doanh nghiệp không còn đọc điểm không nguồn.

**Thiết kế:** bốn họ điểm tách biệt. Nguồn sự thật của kỹ năng là `learner_evaluations` (bản công bố mới nhất) và `learner_skill_evidence`. `student_skills` và `student_profiles.talentScore` chỉ là bản đọc/tổng hợp do backend ghi. Một dịch vụ đọc (`EvidenceBackedScoreService`) cho trang chi tiết; truy vấn danh sách SQL chỉ đọc bản đọc đã được chiếu từ nguồn hợp lệ. Không nhận điểm từ Gemini.

**Công nghệ:** PHP 8.3, MySQL, JavaScript, kiểm thử PHP và Playwright hiện có.

**Tài liệu yêu cầu:** [Vấn đề và chỉnh sửa](issue-2026-09-15-score-transparency.md).

**Trạng thái lúc tạo tài liệu gốc:** chưa triển khai. **Bản kế hoạch này đã được bổ sung** sau khi đối chiếu mã: khóa luật ưu tiên hai bảng đánh giá, hai đường ghi giảng viên, `talentScore`, consumer SQL doanh nghiệp/nhà trường, `JobMatchScore`, và nhãn/quyền xem.

**Các ô kiểm bên dưới để trống có chủ đích.** Kiểm tra chấm/lưu test ở lượt khảo sát trước không thay thế kiểm thử sau chỉnh sửa.

---

## 0. Vì sao bản kế hoạch trước chưa đủ

Bản trước đúng hành vi mong muốn nhưng thiếu quyết định triển khai. Làm đúng checklist cũ vẫn sót:

| Lỗ hổng đã thấy trong mã | Hệ quả nếu không khóa |
|---|---|
| `app/teacher/grading.php` ghi SQL trực tiếp, cho POST `overallScore` đè tổng, xóa `assessment_scores`, ghi `student_profiles.talentScore` | P3 chỉ sửa `TeacherGradingService` thì đường chấm lớp vẫn tạo điểm không nguồn |
| `TeacherGradingService` chỉ được `app/teacher/assessments/index.php` gọi | Hai đường ghi, hai lịch sử |
| `upsertLearnerSkillEvaluation` **UPDATE tại chỗ**, không tăng `revision` | Cột revision tồn tại nhưng hành vi ghi đang mất lịch sử |
| Enterprise/school `COALESCE(talentScore, AVG(assessments), AVG(levelScore > 0))` | Trộn đánh giá lớp, kỹ năng chưa nguồn, và 0 với thiếu điểm |
| `app/teacher/students/index.php` đọc `talent_map` như “kỹ năng thực tế” | P1 chặn prompt vẫn lộ điểm AI ra cổng giảng viên |
| `JobMatchScore` tách khỏi `OpportunityScore` | P1 cũ chỉ nhắc `gemini_score` |
| `GitScopeGuard` bảo vệ `app/teacher/`, `app/enterprise/`, `src/` | Sửa cổng ngoài learner có thể bị chặn nếu không thêm path |

Phần 2 khóa các quyết định này. P0 **xác minh** danh sách, không còn “đi tìm rồi mới thiết kế”.

---

## 1. Ràng buộc xuyên suốt

- Không nhận điểm năng lực từ Gemini, kể cả trường số hợp lệ có evidence ID.
- Giữ riêng: kết quả test, điểm kỹ năng, đánh giá giảng viên theo ngữ cảnh, minh chứng chưa chấm, độ phù hợp cơ hội, độ phù hợp việc làm.
- Thiếu điểm là `NULL`, không phải 0. Không đủ nguồn thì không tham gia điểm chính thức.
- Không dùng `levelScore > 0` hay `talentScore > 0` để suy “đã có điểm”. 0 là giá trị hợp lệ nếu nguồn hợp lệ; thiếu điểm phải `NULL`.
- Không sửa hoặc xóa dữ liệu lịch sử để làm màn hình trông đầy đủ hơn.
- Nguồn và phép tính phải đúng người học, đúng kỹ năng, đúng phiên bản và còn hiệu lực.
- Duy trì phân quyền khi xem điểm và nguồn; không lộ dữ liệu giữa tài khoản/trường.
- Không ghi đè các chỉnh sửa có trước trong workspace. Không push/merge/deploy production trong phạm vi chuẩn bị và kiểm thử local.
- Tất cả tên file ghi “tạo mới” là đích triển khai đề xuất; không khẳng định file đã tồn tại.
- Không đổi trọng số `OpportunityScore` / `JobMatchScore` trừ khi cần loại dữ kiện không nguồn. Không xây hệ thống phân vị thật.

---

## 2. Quyết định thiết kế đã khóa

### 2.1. Bốn họ điểm — không trộn

| Họ | Nguồn sự thật | Được hiện ở hồ sơ kỹ năng? | Tên hiển thị |
|---|---|---|---|
| A. Bốn bài test | `test_attempts` / `test_results` / câu trả lời / phiên bản chấm | Không | Holland: xu hướng sở thích; MI: tự đánh giá các chiều; MBTI/DISC: xu hướng theo bài test |
| B. Kỹ năng thực hành | `learner_evaluations` + `learner_evaluation_items` (bản công bố mới nhất) hoặc đánh giá rubric gắn báo cáo | Có, khi `state = scored` | Tên kỹ năng + phương thức chấm |
| C. Đánh giá giảng viên theo ngữ cảnh | `assessments` / `learner_evaluations` overall của lớp/hoạt động/dự án | Không | “Đánh giá giảng viên — [lớp/hoạt động/dự án]” |
| D. Độ phù hợp | `OpportunityScore` (hoạt động/dự án/thực tập) và `JobMatchScore` (việc làm) | Không | “Độ phù hợp gợi ý”, không phải năng lực hay xác suất trúng tuyển |

`student_profiles.talentScore` **không còn** là bản sao `assessments.overallScore`. Sau P2 nó chỉ là bản chiếu `skill-mean-1.0` (trung bình kỹ năng đã chấm). Nhãn UI: **“Trung bình kỹ năng đã được chấm”**. Không có kỹ năng đủ điều kiện → `NULL`, hiện “Chưa đủ dữ liệu”.

### 2.2. Luật đọc một kỹ năng (thứ tự bắt buộc)

Với mỗi cặp `(studentId, skillId)`:

1. Bản `learner_evaluations` **published**, không thu hồi, không superseded; `revision` cao nhất; trùng `publishedAt` thì lấy `id` lớn hơn theo chuỗi UUID đã chuẩn hóa.
2. Nếu không có điểm ở (1): evidence còn hiệu lực (`revokedAt IS NULL`, không bị `supersedesId` thay) **và** có đánh giá điểm gắn đúng nguồn → `scored`.
3. Evidence còn hiệu lực nhưng chưa có đánh giá → `evidence_only`, `score = NULL`. Nhãn: “Có minh chứng · Chưa chấm điểm”.
4. Có hàng `student_skills` nhãn `teacher`/`verified` nhưng **không nối được** (1) hoặc (2) → `missing_source`. Giữ hàng để đối soát, **không** hiện như đã chứng minh, **không** vào trung bình.
5. Tự khai / chờ duyệt → `unverified`. Không vào điểm chính thức.
6. Cấm dùng `talent_map`, `gemini_score`, `assessments.overallScore`, hay `talentScore` cũ để điền kỹ năng.

Mỗi kỹ năng xuất hiện **một lần** trên UI/API. Nhiều evidence không nhân đôi điểm.

### 2.3. Hai đường ghi giảng viên — một lối ra

| Đường hiện có | Việc bắt buộc |
|---|---|
| `app/teacher/assessments/index.php` → `TeacherGradingService` | Giữ là API ghi chính. Rubric: backend tính, bỏ qua tổng client. Direct: lưu `teacher_direct`, không gán công thức giả. |
| `app/teacher/grading.php` | **Cấm SQL ghi điểm độc lập.** POST handler gọi cùng `TeacherGradingService` (hoặc adapter mỏng gọi service). Cấm hidden `overallScore` đè kết quả rubric. Cấm `UPDATE student_profiles.talentScore` từ overall. |

Khi công bố:

- Ghi `assessments` (legacy, có `legacyAssessmentId` trên evaluation).
- **Luôn** tạo revision mới trên `learner_evaluations` (insert `revision + 1`), không `UPDATE` điểm/status trên bản cũ. Sửa `TeacherGradingRepository::upsertLearnerSkillEvaluation`.
- Ghi `assessment_scores` / items trong cùng transaction.
- Chiếu lại `student_skills` và `talentScore` qua bộ chiếu P2, không ghi tay số overall.

`grading.php` nếu chỉ có đánh giá theo lớp (họ C) thì được lưu overall ngữ cảnh; **không** biến overall đó thành Python/Piano hay `talentScore`.

### 2.4. Bản đọc cho danh sách SQL

Trang chi tiết (hồ sơ, passport, CV, API một học viên) gọi `EvidenceBackedScoreService`.

Trang danh sách (doanh nghiệp, nhà trường, giảng viên sort) **không** gọi PHP từng dòng. Chúng đọc bản đọc:

- `student_skills.levelScore` được phép `NULL`.
- Thêm cột đọc (nullable, không xóa cột cũ): `scoreState`, `sourceEvaluationId`, `sourceEvidenceId`, `formulaVersion`.
- Bộ lọc chính thức: `scoreState = 'scored' AND levelScore IS NOT NULL`.
- Cấm `levelScore > 0` và cấm `COALESCE` sang `AVG(assessments.overallScore)` hay `AVG(ss.levelScore)`.

`talentScore`: chỉ bộ chiếu được ghi. Ý nghĩa mới = `skill-mean-1.0`. P6: giá trị cũ không khớp nguồn → đặt `NULL` (không bịa số khác).

Chỉ `EvidenceBackedScoreService` / `ScoreProjectionWriter` (cùng module, có thể là method của service) được `INSERT`/`UPDATE` `student_skills` và `talentScore` sau P2. `TeacherGradingRepository::upsertStudentSkill` chuyển sang gọi bộ chiếu, không tự gắn `verified` khi thiếu evaluation id.

### 2.5. AI và điểm phù hợp

- Hợp đồng roadmap **bỏ** `talent_map` và mọi trường điểm năng lực. Validator từ chối payload còn các trường đó; không chỉ sửa prompt.
- Đường đọc cache (`DatabaseRoadmapRepository`, `DatabaseAiCapabilityProfileRepository`, `DatabaseSchoolCredentialRepository`, `student-data.php`, `app/teacher/students/index.php`, `learner-ai-roadmap.js`) **lọc bỏ** điểm AI. Bản cũ giữ để truy vết, không cấp ra UI/API.
- `GroundedProseGuard`: số trong lời văn đánh giá chỉ lấy từ dữ kiện có cấu trúc đã xác minh. Không regex “mọi số đều đúng”.
- `OpportunityScore.finalScore()` = structured; `gemini_score` chẩn đoán nội bộ.
- `JobMatchScore` giữ công thức `40/35/25`. Đầu vào skill/assessment **chỉ** kỹ năng `scored` và kết quả test hệ thống — không skill `missing_source`/`unverified`, không `talent_map`.
- Gọi lại AI, đổi model, AI lỗi: không đổi họ A/B/C.

### 2.6. Nhãn “Nguồn và cách tính”

Mỗi điểm hiện được phải trả lời năm câu: điểm gì, lấy từ đâu, ai chấm hoặc công thức nào, tính khi nào, còn minh chứng hợp lệ không.

Ví dụ khóa:

> Python 85/100 — Giảng viên chấm theo rubric. Tiêu chí: thực hành 8/10, sản phẩm 18/20; trọng số bằng nhau. Điểm tổng: 85/100. Nguồn: bản đánh giá được công bố và báo cáo dự án liên quan.

> Giao tiếp — Có minh chứng từ báo cáo thực tập đã duyệt. Chưa có đánh giá điểm.

> Tư duy logic (MI) 25% — Kết quả tự đánh giá trên bài test MI, phiên bản bộ câu hỏi X, phiên bản chấm Y. Công thức Likert: `round(100 × (sum(x) − n) / (4 × n))`. Đây không phải điểm thực hành nghề.

Holland/MI/MBTI/DISC **cấm** nhãn “điểm năng lực” / “điểm Python”.

Xếp loại cùng thang trên mọi trang, không Top %:

- ≥90 Xuất sắc; ≥80 Tốt; ≥70 Khá; còn lại Đạt nếu có điểm hợp lệ.
- Không có điểm: “Chưa đủ dữ liệu”. Không “Top 10/15/30%”, không “Trong nhóm tiến bộ vượt bậc” gắn ngưỡng.

### 2.7. Quyền xem nguồn

| Người xem | Giá trị / thang / phương thức / ngày | Tên người chấm | Câu trả lời test | ID kỹ thuật |
|---|---|---|---|---|
| Học viên (chính mình) | Có | Có | Có (bài của mình) | Trong chi tiết thu gọn |
| Giảng viên trong phạm vi | Có | Có | Không, trừ khi màn chấm cần | Có |
| Nhà trường cùng trường | Có | Có | Không | Có |
| Doanh nghiệp (đã consent) | Có giá trị kỹ năng `scored` + nhãn phương thức | Không (hiện “Giảng viên trường”) | Không | Không |
| Người khác / khác trường | Không | Không | Không | Không |

Mọi API nguồn phải kiểm tra học viên thuộc phạm vi. T23 bắt buộc. Escape HTML; không thực thi chỉ dẫn trong comment.

### 2.8. GitScopeGuard

Trước khi sửa cổng ngoài `app/learner/`, thêm path vào `ALLOWED_EXACT_PATHS` của `app/learner/data/Readiness/GitScopeGuard.php` (file đã liệt kê `TeacherGradingService` / `SchoolDashboardService`). Không đặt migration mới dưới `Database/migrations/learner/` (prefix bị cấm). Dùng `Database/migrations/20260915000100_*.php`.

### 2.9. Phạm vi Git / workspace

P0 ghi diff hiện có. Không revert việc AI/UI đang làm dở để “dọn chỗ”. Không commit secrets.

---

## 3. Phân chia và thứ tự công việc

| Gói | Nội dung | Phụ thuộc | Kết quả bàn giao |
|---|---|---|---|
| P0 | Baseline, xác minh consumer đã liệt kê, GitScopeGuard | Không | Danh sách nguồn/ngoại lệ, baseline test, path guard |
| P1 | Chặn điểm AI từ hợp đồng đến mọi cổng đọc | P0 | AI/cache cũ không còn cấp điểm năng lực |
| P2 | Nguồn điểm thống nhất + bộ chiếu bản đọc | P0 | Một bộ đọc; SQL danh sách dùng `scoreState` |
| P3 | Rubric, metadata, gộp hai đường ghi giảng viên | P2 | Backend tính; `grading.php` không ghi độc lập |
| P4 | Minh chứng dự án/thực tập và thu hồi | P2, P3 | Kỹ năng đúng báo cáo cá nhân |
| P5 | UI/API/CV/cổng ngoài và giải thích | P1–P4 | Năm câu hỏi nguồn trên điểm liên quan |
| P6 | Audit, chuyển dữ liệu, nghiệm thu | P1–P5 | Báo cáo đạt/chưa đạt, dry-run, quay lui |

P1 và P2 có thể song song nếu không sửa cùng file. P5 dùng hợp đồng cuối của P2/P3. Không hai người cùng sửa một file chưa thỏa thuận tích hợp.

---

## 4. Bản đồ file

### Tạo mới

- `app/learner/data/Service/EvidenceBackedScoreService.php` — đọc điểm hợp lệ + chiếu bản đọc (`projectOfficialScores` hoặc method tương đương).
- `src/Modules/Teacher/Service/RubricScoreCalculator.php`
- `app/learner/includes/score-provenance.php` — khối “Nguồn và cách tính”.
- `Database/migrations/20260915000100_add_score_provenance_metadata.php` — kiểm tra tên chưa dùng.
- `bin/audit-score-provenance.php` — mặc định chỉ đọc.
- Test: `tests/learner_ai_no_competency_scores_test.php`, `tests/learner_evidence_backed_scores_test.php`, `tests/teacher_rubric_calculation_test.php`, `tests/assessment_calculation_metadata_migration_test.php`, `tests/learner_portfolio_score_provenance_test.php`, `tests/teacher_grading_write_path_unification_test.php`, `tests/enterprise_official_score_query_test.php`, `tests/score-transparency.spec.ts`.

### Sửa — AI / learner

- `app/learner/ai/Model/RoadmapPromptRegistry.php`
- `app/learner/ai/Validation/RoadmapAnalysisValidator.php`
- `app/learner/ai/Domain/RoadmapAnalysis.php`
- `app/learner/ai/Model/ModelRoadmapEngine.php`
- `app/learner/ai/Service/ProfileAnalysisRefreshService.php`
- `app/learner/ai/Service/RoadmapService.php`
- `app/learner/ai/Service/RoadmapCustomizationService.php`
- `app/learner/ai/Service/AiCapabilityProfileService.php`
- `app/learner/ai/Persistence/DatabaseRoadmapRepository.php`
- `app/learner/ai/Persistence/DatabaseAiCapabilityProfileRepository.php`
- `app/learner/ai/Grounding/GroundedProseGuard.php`
- `app/learner/ai/Matching/JobMatchScorer.php`
- `app/learner/ai/Matching/StructuredOpportunityScorer.php` (chỉ nếu đang cộng skill không nguồn)
- `app/learner/ai/Service/JobMatchingService.php`
- `app/learner/ai/Sources/Database/DatabasePublishedEvaluationSource.php`
- `app/learner/includes/student-data.php`
- `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- `app/learner/data/Database/DatabaseStatisticsRepository.php`
- `app/learner/data/Database/DatabasePassportCvRepository.php`
- `app/learner/data/Database/DatabaseSchoolCredentialRepository.php`
- `app/learner/data/Service/StatisticsService.php`
- `app/learner/data/RepositoryFactory.php`, `app/learner/data/bootstrap.php`
- `app/learner/index.php`, `profile.php`, `discover.php`, `statistics.php`, `talent-passport.php`, `talent-passport-cv.php`, `evaluation.php`, `ai-recommendations.php`
- `assets/js/learner-assessment.js`, `learner-statistics.js`, `learner-ai-roadmap.js`

### Sửa — giảng viên / nhà trường / doanh nghiệp

- `src/Modules/Teacher/Service/TeacherGradingService.php`
- `src/Modules/Teacher/Repository/TeacherGradingRepository.php`
- `app/teacher/assessments/index.php`
- `app/teacher/grading.php`
- `app/teacher/students/index.php`
- `app/teacher/includes/dashboard-data.php`
- `src/Modules/School/Service/SchoolDashboardService.php`
- `src/Modules/Business/Repository/EnterpriseTalentRepository.php`
- `src/Modules/Business/Service/EnterpriseMatchService.php`
- `app/enterprise/index.php`
- `app/enterprise/internships/applicants.php`
- `app/enterprise/includes/featured-talents.php`
- `src/Modules/Student/Repository/PortfolioRepository.php`
- `app/learner/data/Readiness/GitScopeGuard.php` — thêm exact path các file trên trước khi sửa chúng.

P0 còn phải xác nhận không có consumer mới ngoài danh sách. Nếu có, bổ sung vào P5 cùng luật 2.2–2.4, không invent đường đọc thứ ba.

---

## 5. P0 — Baseline và kiểm kê

**Đọc:** cấu hình local, schema thật, migration đã áp dụng, file mục 4, `git status --short`, `scratch/p0_inventory.php` nếu còn (chỉ tham khảo, không phải bàn giao).

- [ ] Ghi commit hiện tại, `git status --short`, danh sách file đã thay đổi từ trước. Lưu đối chiếu chỉ cho file sắp sửa; không sao chép khóa/mật khẩu.
- [ ] Thêm path P3–P5 vào `GitScopeGuard::ALLOWED_EXACT_PATHS`; chạy test guard hiện có nếu có.
- [ ] Chạy baseline: chấm test, lưu test, đánh giá giảng viên (`assessments/index.php` **và** `grading.php`), portfolio.
- [ ] Đếm điểm nối được nguồn / không nối được; phân biệt demo bằng dấu nguồn (`E2E-VERIFIED`, seed), không suy theo tên/email.
- [ ] Xác minh khóa `uq_student_skills_student_skill_source (studentId, skillId, sourceType)` và việc `upsertLearnerSkillEvaluation` đang UPDATE tại chỗ.
- [ ] Xác minh consumer mục 4 bằng tìm kiếm `talent_map`, `levelScore`, `level_score`, `overallScore`, `gemini_score`, `talentScore`, `Top 10%` trong `app`, `src`, `assets/js`. Ghi consumer **mới** nếu có.

**Điều kiện xong:** có số liệu baseline và danh sách đọc/ghi đã đối chiếu mục 4. Không chuyển dữ liệu ở P0.

---

## 6. P1 — Chặn điểm Gemini

**Sửa:** nhóm AI / learner trong mục 4, cộng `app/teacher/students/index.php` (đang biến `talent_map` thành kỹ năng).

**Tạo test:** `tests/learner_ai_no_competency_scores_test.php`.

Hợp đồng đọc sau P1: mọi DTO roadmap/profile đưa ra UI **không** có `talent_map` điểm, hoặc có thì consumer phải bỏ qua và không render thanh điểm.

- [ ] Test thất bại: payload `talent_map` `[{field: "Tư duy Logic & Hệ thống", score: 0.99}]` không thành điểm hồ sơ; lặp với hàng roadmap/profile cũ trong DB.
- [ ] Test thất bại: `app/teacher/students/index.php` không còn nhánh “kỹ năng thực tế từ talent_map”.
- [ ] Loại trường điểm năng lực khỏi schema/prompt/validator; tăng phiên bản hợp đồng/prompt.
- [ ] Bỏ fallback `student-data.php` (khoảng dòng 276–330): khi `$skills === []` không lấy `talent_map`; KPI “Điểm năng lực” → “Chưa đủ dữ liệu”.
- [ ] Lọc bản phân tích cũ tại **đường đọc** (`DatabaseRoadmapRepository`, capability profile, school credential). Không chỉ chặn ghi mới.
- [ ] `GroundedProseGuard`: dữ kiện số từ skill `scored` / test hệ thống. Lời model có số không đối chiếu được thì loại hoặc thay mẫu backend.
- [ ] Roadmap vẫn chạy khi model không trả ba thanh điểm.
- [ ] `OpportunityScore` / `JobMatchScore`: xếp hạng = điểm hệ thống; `gemini_score` không rò ra hồ sơ hay sort doanh nghiệp.

**Điều kiện xong:** gọi lại Gemini, đổi model, model lỗi, đọc cache cũ, mở cổng giảng viên — không tạo/đổi điểm họ A/B/C.

---

## 7. P2 — Nguồn điểm thống nhất

**Tạo:** `EvidenceBackedScoreService.php`.

**Sửa:** passport/statistics/CV repositories, `RepositoryFactory`, `bootstrap.php`, `DatabasePublishedEvaluationSource`, `JobMatchScorer`, `TeacherGradingRepository` (chuyển upsert skill sang bộ chiếu).

**Hợp đồng đọc:**

```php
public function forStudent(string $studentId, ScoreViewer $viewer): array;
// skills: [{skill_id, name, score: ?float, max_score: 100,
//   state: scored|evidence_only|unverified|missing_source,
//   source_type, source_id, source_version, assessed_at,
//   method, formula_version, calculation, evidence_refs}]
// summary: {score: ?float, formula_version: 'skill-mean-1.0', included_skill_ids}
// teacher_context_assessments: [{assessment_id, context_type, context_label,
//   overall_score: ?float, method, published_at}]  // họ C, tách khỏi skills
```

`ScoreViewer` mang role + phạm vi. Không trả tên người chấm hay câu trả lời nếu bảng 2.7 cấm.

`skill-mean-1.0`: `sum(valid_skill_scores) / count(valid_skill_scores)` trên thang 100, chỉ `state = scored`. Không test, không AI, không fit, không overall lớp. Không điểm hợp lệ → `summary.score = NULL`.

**Tạo test:** `tests/learner_evidence_backed_scores_test.php`.

- [ ] Test chọn bản published mới nhất; bỏ nháp/thu hồi/superseded; trùng thời gian theo mục 2.2.
- [ ] Test `teacher/verified` không nối nguồn → `missing_source`, không vào trung bình.
- [ ] Test evidence chưa chấm → `NULL`, không vào mẫu số.
- [ ] Test nhiều evidence cùng kỹ năng → một hàng.
- [ ] Test chiếu: sau khi đọc, `student_skills.scoreState` và `talentScore` khớp service; không ghi đè lịch sử evaluation.
- [ ] Test `JobMatchScorer` bỏ skill không `scored`.
- [ ] Khi sửa đánh giá công bố: insert revision mới, một bản hiện hành cho cùng `seriesId`/`eventKey`.

**Điều kiện xong:** cùng học viên, cùng dữ liệu, mọi consumer PHP nhận cùng điểm/trạng thái/nguồn. Consumer SQL dùng `scoreState = 'scored'` (sau migration P3 nếu cột chưa có — P2 có thể ship PHP trước, P3 thêm cột, P5 chuyển SQL; không để P5 còn `AVG(... > 0)`).

Thứ tự khuyến nghị: migration cột đọc nằm ở P3 nhưng **schema `levelScore` nullable** phải có trước khi P4 ghi `NULL`. Nếu P2 cần `NULL` sớm, đưa phần ALTER `student_skills` vào migration P3 và **làm P3 ngay sau khi test P2 đỏ về NULL**, không ship P4 trước migration.

---

## 8. P3 — Rubric, metadata, gộp đường ghi

**Tạo:**

- `src/Modules/Teacher/Service/RubricScoreCalculator.php`
- `Database/migrations/20260915000100_add_score_provenance_metadata.php`
- `tests/teacher_rubric_calculation_test.php`
- `tests/assessment_calculation_metadata_migration_test.php`
- `tests/teacher_grading_write_path_unification_test.php`

**Sửa:** `TeacherGradingService.php`, `TeacherGradingRepository.php`, `app/teacher/assessments/index.php`, **`app/teacher/grading.php`**.

**Migration (MySQL; SQLite text tương ứng trong fixture):**

Trên `assessments` và `learner_evaluations` (nullable):

| Trường | Kiểu | Ý nghĩa |
|---|---|---|
| `scoreMethod` | `VARCHAR(32) NULL` | `teacher_direct` hoặc `rubric_weighted` |
| `formulaVersion` | `VARCHAR(64) NULL` | Direct không gán công thức giả |
| `calculationJson` | `LONGTEXT NULL` | Snapshot backend đã kiểm |

Trên `student_skills`:

| Trường | Kiểu | Ý nghĩa |
|---|---|---|
| `levelScore` | `DECIMAL(5,2) NULL` | Đổi từ NOT NULL; CHECK cho phép NULL |
| `scoreState` | `VARCHAR(32) NULL` | `scored` / `evidence_only` / `unverified` / `missing_source` |
| `sourceEvaluationId` | `CHAR(36) NULL` | FK mềm tới evaluation hiện hành |
| `sourceEvidenceId` | `CHAR(36) NULL` | Khi nguồn là evidence |
| `formulaVersion` | `VARCHAR(64) NULL` | |

Không tạo bảng điểm song song. Không mặc định hàng cũ là rubric. Kiểm tra cột trước khi ADD. Không đặt file dưới `Database/migrations/learner/`.

**Snapshot:**

```json
{
  "formula_version": "rubric-weighted-1.0",
  "rounding": "half_up_2_decimals",
  "criteria": [
    {"id": "<uuid>", "code": "practice", "score": 8, "min": 0, "max": 10, "weight": 1},
    {"id": "<uuid>", "code": "product", "score": 18, "min": 0, "max": 20, "weight": 1}
  ],
  "total": 85
}
```

Backend tạo snapshot. Không tin tổng/trọng số browser. Hai bản `assessments` + `learner_evaluations` và liên kết trong một transaction.

**Bộ tính:**

```php
public function calculate(array $criteria): array;
// Input: list of {id, code, score, min, max, weight, required}
// Output: {score: float, formula_version: 'rubric-weighted-1.0', calculation: array}
// Reject: thiếu tiêu chí bắt buộc, score ngoài thang, min != 0,
//         max <= 0, weight <= 0, bộ rỗng
// Công thức: round(100 * sum(weight_i * score_i / max_i) / sum(weight_i), 2)
```

Thang min ≠ 0 không dùng công thức này.

- [ ] Test 8/10 và 18/20, trọng số 1 → 85; client gửi 99 bị bỏ/từ chối.
- [ ] Test `grading.php` (hoặc service được nó gọi) không còn nhánh `$_POST['overallScore']` đè rubric; không `UPDATE talentScore` từ overall.
- [ ] Test publish tạo `revision` mới; bản cũ còn overall/snapshot cũ.
- [ ] Form rubric: tổng chỉ đọc.
- [ ] Direct: bắt buộc người chấm + bản nguồn; `formulaVersion` null.
- [ ] Đổi rubric sau công bố không đổi số đã lưu.
- [ ] Chạy migration trên DB thử; dữ liệu cũ không mất.

**Điều kiện xong:** tái tính từ snapshot ra đúng điểm đã lưu, không gọi Gemini. `grading.php` và `assessments/index.php` cùng luật.

---

## 9. P4 — Dự án, thực tập, vòng đời minh chứng

**Sửa:** `PortfolioRepository.php`, đường đánh giá theo ngữ cảnh, bộ chiếu P2, sự kiện AI liên quan.

**Tạo test:** `tests/learner_portfolio_score_provenance_test.php`.

- [ ] Dự án hoàn thành, báo cáo cá nhân chưa duyệt → không cấp kỹ năng xác minh/điểm.
- [ ] Báo cáo đã duyệt, chưa chấm → evidence, `score = NULL`, `scoreState = evidence_only`.
- [ ] Chấm trên báo cáo hợp lệ → evaluation P3 + evidence theo phiên bản nguồn.
- [ ] Backend kiểm quyền duyệt và quan hệ học viên–dự án/thực tập.
- [ ] Thu hồi/sửa nguồn: điểm phụ thuộc hết hiệu lực; chọn nguồn khác nếu còn; không còn thì chưa đủ dữ liệu; chiếu lại `student_skills`/`talentScore`.
- [ ] Lặp sự kiện duyệt/thu hồi không nhân đôi (`eventKey`). Không cộng điểm theo số dự án/giờ/báo cáo.

**Điều kiện xong:** mỗi kỹ năng/điểm truy được báo cáo cá nhân và người đánh giá; việc của người khác không cấp điểm cho tài khoản đang xem.

---

## 10. P5 — Hiển thị, SQL danh sách, giải thích

**Learner:** trang mục 4 + `score-provenance.php` + JS assessment/statistics/roadmap.

**Cổng ngoài (bắt buộc, không để “rà sau”):**

- `app/teacher/students/index.php` — sort `talentScore` = skill-mean đã chiếu; bỏ `talent_map`.
- `app/teacher/includes/dashboard-data.php` — `AVG(talentScore)` chỉ hàng `NOT NULL`; cấm `talentScore = 0` như “chưa có”.
- `src/Modules/School/Service/SchoolDashboardService.php` — không xếp hạng bằng `AVG(overallScore)` như năng lực hồ sơ; overall là họ C.
- `EnterpriseTalentRepository.php`, `EnterpriseMatchService.php`, `app/enterprise/index.php`, `applicants.php`, `featured-talents.php` — xóa `COALESCE(..., AVG(sa.overallScore), AVG(ss.levelScore > 0))`. Điểm list = `sp.talentScore` (bản chiếu) hoặc subquery `AVG(ss.levelScore) WHERE scoreState = 'scored'`. Nhãn match 10% hiện tại (“Điểm đánh giá năng lực giáo viên”) đổi thành “Trung bình kỹ năng đã được chấm” hoặc bỏ khỏi lý do nếu `NULL`.

**Tạo:** `tests/score-transparency.spec.ts`, `tests/enterprise_official_score_query_test.php`.

- [ ] MI đủ tám chiều; nếu biểu đồ ít hơn thì ghi rõ + bảng đủ chiều. MBTI: không ghi đè kết quả gốc để khớp chart; giải thích làm tròn.
- [ ] Bỏ thanh điểm năng lực AI.
- [ ] Mỗi điểm có khối nguồn theo 2.6–2.7. `evidence_only` không thanh 0/100.
- [ ] Bỏ Top 10/15/30%. Cùng xếp loại 2.6 trên mọi trang.
- [ ] Gợi ý cơ hội: “Cách tính độ phù hợp” từ `OpportunityScore::MAX` (35/25/15/15/10). Việc làm: 40/35/25. Không gọi xác suất tuyển dụng.
- [ ] Escape HTML; T23 phạm vi.

**Điều kiện xong:** khách hàng xem được nguồn/cách tính trên UI khi AI tắt; list doanh nghiệp/giảng viên/nhà trường không còn AVG không nguồn.

---

## 11. P6 — Dữ liệu cũ, triển khai, quay lui

**Tạo:** `bin/audit-score-provenance.php` (mặc định dry-run).

- [ ] Audit: theo nguồn, thiếu liên kết, AI cũ còn consumer, trùng, evidence thu hồi, `talentScore` ≠ skill-mean. Không log PII.
- [ ] Chuyển đổi chỉ theo ID nối được; dry-run; transaction nhóm; idempotent.
- [ ] Thử trên DB sao chép; so sánh hash điểm test và lịch sử. Không sửa bản test cũ cho khớp công thức mới.
- [ ] `missing_source` giữ lịch sử + trạng thái giải thích; không cấp điểm thay.
- [ ] `talentScore` không tái tính được → `NULL`.
- [ ] Job AI đang chạy không ghi điểm năng lực theo hợp đồng cũ.
- [ ] Thiếu cột: readiness rõ, không fallback 0/AI.
- [ ] Quay lui UI/tính mới khi lỗi **vẫn giữ** chặn AI và loại điểm không nguồn. Không drop cột snapshot.

**Điều kiện xong:** dry-run + migration/rollback trên bản sao; ngoại lệ được liệt kê, không che bằng số phát sinh.

---

## 12. Ma trận kiểm thử bắt buộc

Tất cả **chưa chạy cho bản sửa này**.

| ID | Tình huống | Kết quả bắt buộc | Tầng |
|---|---|---|---|
| T01 | Tài khoản mới, chưa test/chấm | Không điểm giả; chưa đủ dữ liệu | API/UI |
| T02 | Làm đủ bốn test | Chỉ kết quả test; không tự có Python/Piano/điểm GV | Tích hợp/UI |
| T03 | Tính lại từ câu trả lời | Khớp bộ chấm đúng phiên bản, có nguồn | Unit/DB |
| T04 | Likert toàn 1, 2, 5; đảo chiều | 0/25/100; thiếu câu bắt buộc không hoàn thành | Unit |
| T05 | MI tám chiều; MBTI làm tròn | Đủ chiều; tỷ lệ UI nhất quán, giải thích được | UI |
| T06 | GV chấm trực tiếp | Có người chấm/bản nguồn; không gắn rubric giả | DB/UI |
| T07 | Rubric 8/10 và 18/20, trọng số 1 | Tổng 85; frontend 99 không thắng | Unit/API |
| T08 | Thiếu tiêu chí / ngoài thang / trọng số 0 | Từ chối; không công bố nửa chừng | Unit/API |
| T09 | Đổi rubric sau công bố | Snapshot cũ tái tính ra điểm cũ | DB |
| T10 | Nháp / công bố / thu hồi / trùng thời gian | Đúng bản hiện hành; không lấy điểm cao nhất | Unit/DB |
| T11 | Dự án xong, báo cáo cá nhân chưa duyệt | Không tự cấp kỹ năng/điểm | Tích hợp |
| T12 | Báo cáo đã duyệt, chưa chấm | Minh chứng, `NULL`, không 0/100 | DB/UI |
| T13 | Chấm gắn báo cáo hợp lệ | Đúng người học/ngữ cảnh | Tích hợp |
| T14 | Thu hồi minh chứng/đánh giá | Điểm phụ thuộc hết hiệu lực; history còn | Tích hợp |
| T15 | Xử lý lại cùng sự kiện | Không nhân đôi | Tích hợp |
| T16 | Gemini trả điểm hoặc số trong lời văn | Không thành dữ kiện đánh giá | Unit/Tích hợp |
| T17 | Roadmap/profile cũ 90/75/80 | API/UI/cổng GV không dùng làm năng lực | DB/UI |
| T18 | Gọi AI nhiều lần / đổi model / lỗi | Điểm A/B/C không đổi | Tích hợp |
| T19 | Độ phù hợp cơ hội | Structured score; không vào hồ sơ | Unit/UI |
| T20 | Legacy `teacher/verified` thiếu nguồn | `missing_source`; giữ lịch sử | DB/UI |
| T21 | Nhiều evidence cùng kỹ năng | Một lần; thiếu điểm không vào mẫu số | Unit |
| T22 | Tổng quan / Hồ sơ / Thống kê / CV / GV / trường / DN | Cùng giá trị–trạng thái–thang–nhãn nguồn | UI/API |
| T23 | Đổi ID sang học viên khác / ngoài trường | Từ chối; không lộ nguồn/câu trả lời | API |
| T24 | HTML/script hoặc chỉ dẫn AI trong nguồn | Hiển thị an toàn; không đổi phép tính | API/UI |
| T25 | Migration schema cũ và chạy lại runner | Nâng cấp đúng; không mất dữ liệu/nhân cột | DB |
| T26 | Dry-run và chuyển đổi lặp | Ngoại lệ rõ; không đổi điểm gốc hợp lệ | DB |
| T27 | Thiếu schema / nguồn / AI timeout | Trạng thái rõ; không điểm bịa | API/UI |
| T28 | Điểm ≥90 | Không nhãn Top % | UI |
| T29 | POST `grading.php` overall đè rubric | Bị bỏ/từ chối; tổng = backend | API |
| T30 | Công bố đánh giá lớp (họ C) | Không ghi `talentScore` từ overall | DB |
| T31 | Query list enterprise/school | Không `AVG(levelScore > 0)` / `AVG(overallScore)` làm năng lực hồ sơ | Unit/DB |
| T32 | Trang học viên của GV | Không render `talent_map` như kỹ năng | UI |
| T33 | `JobMatchScorer` với skill `missing_source` | Không cộng vào thành phần skill | Unit |
| T34 | Publish lần 2 cùng assessment | `learner_evaluations.revision` tăng; bản 1 còn | DB |
| T35 | Doanh nghiệp xem nguồn | Không tên GV thật, không câu trả lời test | API |

---

## 13. Lệnh và bằng chứng

Chạy tại `D:/TalentHub`. PHP local:

```powershell
$scorePhp = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $scorePhp tests/learner_assessment_scorer_contract_test.php
& $scorePhp tests/learner_assessment_persistence_test.php
& $scorePhp tests/teacher_grading_school_skills_test.php
```

Test mới (sau khi tạo):

```powershell
$scoreTests = @(
  'tests/learner_ai_no_competency_scores_test.php',
  'tests/learner_evidence_backed_scores_test.php',
  'tests/teacher_rubric_calculation_test.php',
  'tests/assessment_calculation_metadata_migration_test.php',
  'tests/teacher_grading_write_path_unification_test.php',
  'tests/learner_portfolio_score_provenance_test.php',
  'tests/enterprise_official_score_query_test.php'
)
foreach ($scoreTest in $scoreTests) {
  & $scorePhp $scoreTest
  if ($LASTEXITCODE -ne 0) { throw "Test failed: $scoreTest" }
}
```

Hồi quy hiện có: `learner_assessment_history_test.php`, `teacher_competency_assessments_test.php`, `learner_portfolio_repository_test.php`, `learner_portfolio_access_test.php`, `learner_portfolio_runtime_read_test.php`, `learner_portfolio_cv_test.php`, `learner_ai_evaluation_skill_snapshot_test.php`, `learner_ai_skill_evidence_revoke_test.php`, `learner_project_skill_sync_test.php`, `learner_statistics_api_test.php`, `learner_ai_roadmap_freshness_test.php`, `learner_ai_matching_recovery_test.php`. Test ghi MySQL chỉ chạy trên DB kiểm thử riêng.

```powershell
npx playwright test tests/score-transparency.spec.ts --project=chromium --workers=1
```

```powershell
& $scorePhp bin/migrate.php status
& $scorePhp bin/migrate.php validate
# Chỉ trên database thử đã kiểm cấu hình và pending:
& $scorePhp bin/migrate.php migrate
```

Bằng chứng: log exit code, tên test, ngày, commit/diff; ảnh desktop/mobile trang đổi; đối chiếu nguồn/công thức; dry-run. Thư mục `artifacts/score-transparency/`. Không ghi PASS vì file test tồn tại hoặc vì lần chạy cũ còn trên đĩa.

---

## 14. Checklist hoàn thành

- [ ] P0–P6 xong, đã rà tích hợp.
- [ ] T01–T35 có kết quả và bằng chứng; ca chưa chạy không tính đạt.
- [ ] Không còn đường đọc/ghi điểm năng lực từ Gemini, gồm lịch sử AI và cổng ngoài learner.
- [ ] `grading.php` và `TeacherGradingService` cùng luật; không đè tổng rubric; không ghi `talentScore` từ overall.
- [ ] `learner_evaluations` tăng revision khi công bố lại.
- [ ] Mỗi điểm hiện hành có nguồn và phương thức; tái chạy phép tính ra đúng kết quả.
- [ ] Thiếu nguồn/thiếu điểm hiện đúng trạng thái; không số lấp chỗ trống.
- [ ] Tài khoản mới xong bốn test không tự có điểm kỹ năng thực hành.
- [ ] List GV/trường/DN không `AVG` không nguồn; `JobMatchScore` không dùng skill thiếu nguồn.
- [ ] Duyệt/thu hồi đồng bộ, giữ lịch sử, chống trùng.
- [ ] UI/API/CV nhất quán, đúng bảng quyền 2.7, không Top % giả.
- [ ] Hồi quy đạt hoặc lỗi sẵn có tách rõ.
- [ ] Migration/chuyển dữ liệu thử trên bản sao; quay lui không khôi phục điểm AI.
- [ ] Báo cáo bàn giao: đã sửa gì, đã chạy gì, kết quả, dữ liệu chưa đủ nguồn, môi trường nào. Phân biệt code xong / test đạt / migration local / chưa production.

---

## 15. Quy tắc báo trạng thái

Không dùng “hoàn thiện toàn bộ” khi còn ca bắt buộc chưa chạy, consumer mục 4 chưa sửa, hoặc migration chưa thử. Không môi trường production thì hoàn tất local + tài liệu; báo production chưa triển khai riêng.
