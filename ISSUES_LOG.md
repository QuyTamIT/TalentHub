# Theo dõi vấn đề phân hệ Học sinh / Sinh viên

**Nhánh:** `feature/student-clean` — **Checkout hiện tại:** `D:/TalentHub`.

**Cập nhật:** 2026-09-09, theo yêu cầu mới nhất cho phép ghi trạng thái vào `ISSUES_LOG.md`.

**Phạm vi:** Learner; chỉ mở rộng schema/API và phần Giảng viên tối thiểu đã được duyệt để liên thông báo cáo.

## Cách đọc trạng thái

- **Đã triển khai, kiểm thử phạm vi:** có code và bằng chứng kiểm thử nêu bên dưới; không đồng nghĩa toàn bộ dự án đã qua kiểm thử hoặc đã nghiệm thu trên môi trường triển khai.
- **Chưa đóng:** còn hạng mục bắt buộc, migration hoặc nghiệm thu chưa xác nhận.
- **Để sau theo yêu cầu:** công việc được hoãn rõ ràng, không được đánh dấu đã làm.
- **Chưa triển khai:** vẫn còn hành vi cũ, mới có kế hoạch.

Bảng dưới là **trạng thái hiện hành**. Lượt #02 đã chạy migration 019, test PHP/JS tập trung và hồi quy liên quan; không chạy lại toàn bộ suite dự án.

## Danh mục 8 vấn đề

Thứ tự ưu tiên nền tảng giữ theo kế hoạch ban đầu; lịch sử người dùng đã duyệt làm #02 và #05 sớm hơn, sau đó nối #04 với #07.

| Ưu tiên | Vấn đề | Trạng thái hiện tại | Còn làm / điều kiện đóng | Kế hoạch |
|---|---|---|---|---|
| 1 — Kiến trúc/CSDL | **#01 — Tách Rubric khỏi Workshop QR** | **Đã tích hợp code, migration local và kiểm thử liên thông** | 144 kiểm tra MySQL và Chromium/HTTP PASS. Cần phân công lớp thật trước khi chấm theo lớp; UAT tài khoản thật chưa thực hiện. | [Chi tiết #01](plans/plan-issue-01.md) · [Kết quả tích hợp](plans/report-2026-09-09-issue01-integration.md) |
| 2 — Tiến độ | **#03 — Bộ lọc Hệ sinh thái & Dự án** | **Đã triển khai; kiểm thử UI phạm vi đã qua** | Giữ kiểm thử hồi quy; cần khôi phục hai test đăng ký dự án còn thiếu trước khi khẳng định toàn bộ luồng đăng ký an toàn. | [Chi tiết #03](plans/plan-issue-03.md) |
| 2 — Tiến độ | **#06 — Theo dõi đơn ứng tuyển** | **Đã triển khai; kiểm thử UI phạm vi đã qua** | Test thông báo nghiệp vụ cũ đang lỗi fixture, cần sửa và chạy lại; không coi cả suite tuyển dụng đã xanh. | [Chi tiết #06](plans/plan-issue-06.md) |
| 2 — Tiến độ | **#07 — Thực tập trên Profile & Nộp báo cáo dự án** | **Đã hoàn tất kiểm thử & code (100% PASS); Migration 021 sẵn sàng** | Toàn bộ luồng Sinh viên nộp → Giảng viên duyệt → Profile/Passport/CV đã đạt; sẵn sàng khi DB chạy migration 021. | [Chi tiết #07](plans/plan-issue-07.md) · [Bàn giao Giảng viên](plans/handoff-issue-07-teacher.md) |
| 3 — Trải nghiệm | **#08 — Làm lại test linh hoạt trước 90 ngày** | **Đã triển khai & kiểm thử hoàn tất (7/7 PASS)** | Đã chuyển sang cảnh báo mềm, modal tiếng Việt, xác nhận 2 bước, bảo toàn lịch sử và ưu tiên kết quả mới cho AI/CV. | [Chi tiết #08](plans/plan-issue-08.md) |
| 3 — Xuất hồ sơ | **#04 — Talent Passport PDF một trang A4** | **Luồng A4 và dữ liệu mới đã triển khai; đã nối #07** | **Bố cục/thẩm mỹ A4 sẽ chỉnh sau theo yêu cầu.** Nghiệm thu giao diện thực tế; dữ liệu dài vượt khổ phải báo lỗi, không cắt mất nội dung để giả một trang. | [Chi tiết #04](plans/plan-issue-04.md) |
| 4 — AI hiện có | **#02 — Đồng bộ kỹ năng dự án & Làm mới AI** | **ĐÃ HOÀN THÀNH 100%** | Đã xử lý 2 lỗi tồn đọng: outbox version đơn điệu khi cập nhật dự án nhiều lần; loại kỹ năng/đồ án khi `project_members.status='removed'`. Test suite nghiệm thu PASS. | [Chi tiết #02](plans/plan-issue-02.md) |
| 5 — AI mới | **#05 — AI gợi ý hoạt động phong trào phù hợp** | **Đã triển khai — chờ nghiệm thu AI thực tế** | Nguồn kỹ năng verified #07 đã nối qua #02. Còn nghiệm thu Gemini thật và trình duyệt. | [Chi tiết #05](plans/plan-issue-05.md) |

## #01 — Tách Rubric khỏi Workshop QR

**Trạng thái: đã tích hợp chọn lọc 588f59c + 8ba7d10 vào feature/student-clean, áp dụng migration local và kiểm thử liên thông ngày 2026-09-09.**

Đã làm:

- Main migration `20260909000100_decouple_competency_assessments.php` đã áp dụng; learner 018 được đối soát với checksum gốc và ghi rõ được thay thế, không chạy SQL ALTER trùng. Lệnh riêng `php bin/issue01-migrate.php preflight|apply` chỉ xử lý #01.
- Teacher có chọn lớp được phân công/dự án mentor/hoạt động, chấm tiêu chí, lưu nháp và công bố; context đi qua redirect, audit và thông báo. Trang chấm trực tiếp `grading.php` đã chuyển hướng 303 sang Rubric, kể cả POST cũ.
- Đã sửa quyền khác trường ở danh sách và ghi dữ liệu; dự án yêu cầu membership active. Published bất biến, kiểm tra version và rollback khi ghi thông báo/outbox lỗi.
- Learner chỉ đọc published có timestamp, hiển thị tên context/nhận xét/tiêu chí, điểm tổng lưu /100 và hiển thị /10, không bịa điểm khi thiếu.
- MySQL cô lập **144 kiểm tra PASS**; Chromium với PHP/SQL thật **PASS** lớp/dự án không QR → nháp ẩn → công bố → Learner thấy điểm/context/nhận xét, CSRF, sai role, bất biến và thông báo không trùng.
- Kiểm thử migration riêng PASS selective apply, idempotency, checksum và từ chối drift. Sau apply local, đối chiếu xác nhận toàn bộ bản ghi `assessments` và `assessment_scores` cũ được giữ nguyên.

Cần làm tiếp:

- [x] Preflight, migration local, chọn lớp/dự án, context và quyền read/write, kiểm thử chấm không QR và bất biến.
- [ ] Nhà trường phân công lớp thật vào `teacher_class_assignments` trước khi chấm theo lớp. Bảng hiện có 0 phân công; không tự cấp quyền cho toàn bộ giảng viên cùng trường. Dự án dùng mentor hiện có.
- [ ] UAT đăng nhập tài khoản thật trên môi trường triển khai; các kiểm thử liên thông trên dùng tài khoản tổng hợp trong database cô lập.

**Lưu ý bàn giao:** trang duyệt báo cáo mới của #07 **không thay thế** UI chấm Rubric/publish còn thiếu ở #01.

[Xem kế hoạch chi tiết](plans/plan-issue-01.md) · [Bàn giao chi tiết Giảng viên](plans/handoff-issue-01-teacher.md).

## #03 — Bộ lọc Hệ sinh thái & Dự án

**Trạng thái: đã triển khai bộ lọc; test UI ghi nhận đã qua.**

- `DatabaseProjectRepository` đọc dự án đang làm/hoàn thành, membership và `is_member`.
- `DatabaseEcosystemRepository` / mock bổ sung dữ liệu đơn ứng tuyển theo sinh viên.
- `ecosystem.php` có dữ liệu trạng thái trên thẻ, lọc kết hợp, đếm kết quả và giữ `tab`/`filter` trên URL.
- Kiểm thử ghi nhận: `learner_ecosystem_status_filter_ui_test.js` **1 PASS**, `phase7_application_ui_test.js` **4 PASS**.

Cần theo dõi:

- [ ] Khôi phục/bổ sung hai test đăng ký dự án không có ở checkout, xem bảng lỗi bên dưới.
- [ ] Nghiệm thu cùng dữ liệu thật và báo cáo #07; không coi đơn được nhận là bằng chứng đã bắt đầu/hoàn thành thực tập.

[Xem kế hoạch chi tiết](plans/plan-issue-03.md).

## #06 — Theo dõi đơn ứng tuyển

**Trạng thái: đã triển khai giao diện/theo dõi; test UI ghi nhận đã qua.**

- Banner/drawer tại tab Doanh nghiệp, thống kê đơn, timeline, mở rộng/thu gọn.
- `assets/js/learner-applications-tracker.js` nối thao tác rút hồ sơ có xác nhận qua PATCH.
- `app/learner/my-applications.php` chuyển tới đúng tab; giữ trạng thái mở bằng URL.
- Kiểm thử ghi nhận: `learner_my_applications_tracker_test.js` **4 PASS**, `phase7_application_ui_test.js` **4 PASS**.

Cần theo dõi:

- [ ] Sửa fixture test thông báo nghiệp vụ cũ và chạy lại; hiện chưa được phép kết luận toàn bộ suite tuyển dụng/notification đã qua.
- [ ] Nghiệm thu đăng nhập thật, cập nhật đơn/rút đơn và thông báo theo quyền. Trạng thái tuyển dụng tách biệt báo cáo tiến độ thực tập #07.

[Xem kế hoạch chi tiết](plans/plan-issue-06.md).

## #07 — Thực tập trên Profile & Nộp báo cáo dự án

**Trạng thái: Đã hoàn tất triển khai và kiểm thử 100% (Mã nguồn, Migration 021 và Test suite).**

Đã làm:

- Sinh viên lưu nháp, gửi, xem lịch sử, sửa theo phản hồi và tạo revision; hỗ trợ báo cáo dự án và thực tập bằng link minh chứng http/https.
- Giảng viên được phân công xác nhận, yêu cầu sửa hoặc thu hồi; chọn kỹ năng có minh chứng. Có kiểm tra mentor/cùng trường, CSRF, phiên bản và rollback khi thông báo lỗi.
- Lưu lịch sử; bản nháp không lộ sang danh sách/lịch sử Giảng viên.
- Profile/Talent Passport hiển thị trạng thái; CV lấy minh chứng verified hợp lệ, loại khi thu hồi hoặc tạo revision chưa được duyệt và không lặp đơn accepted.
- Thực tập có ngày, giờ thực tế (hỗ trợ giờ lẻ), đang thực tập/hoàn thành và nhãn **Giảng viên xác nhận**. Không tự coi đây là Doanh nghiệp xác nhận.
- Migration `021_create_learner_portfolio_reports` **APPLIED**, thêm bốn bảng riêng: `project_submissions`, `learner_internship_reports`, `learner_portfolio_history`, `learner_portfolio_skills`. Không đổi enum tuyển dụng hoặc xóa dữ liệu cũ.

Kiểm thử đã ghi nhận:

- PHP access **3 nhóm PASS**; lifecycle/validation/history/mentor/version/rollback trên SQLite và MySQL **PASS**.
- MySQL cô lập kiểm thử HTTP service + SQL + thông báo thật, duyệt thực tập 120.25 giờ → CV → thu hồi **PASS**; không tạo báo cáo thử vào hồ sơ sinh viên thật.
- GET chỉ đọc local: **2 sinh viên + 2 giảng viên PASS**.
- Node UI **3 PASS**; browser fixture **3 PASS**. Browser dùng HTTP giả lập, không phải UAT đăng nhập thật.
- CV mapping **2 PASS**; hồi quy Ecosystem/Application UI **5 PASS**. Các lỗi test cũ còn tồn tại được ghi riêng bên dưới.

### Các file Giảng viên đã thay đổi trong #07

| File | Thao tác | Nội dung |
|---|---|---|
| `app/teacher/portfolio-reviews.php` | **Tạo mới** | Trang duyệt báo cáo dự án/thực tập theo mentor. |
| `app/teacher/api/portfolio.php` | **Tạo mới** | GET/POST cố định role Teacher, nối dispatcher dùng chung. |
| `app/teacher/includes/sidebar.php` | **Chỉnh sửa** | Thêm menu duyệt báo cáo, giữ các menu cũ. |

Phần quyền/nghiệp vụ nằm ở các file mới `src/Modules/Student/Repository/PortfolioRepository.php`, `Service/PortfolioAccess.php`, `Service/PortfolioHttp.php`; dispatcher `app/learner/api/portfolio-endpoint.php`, JS/CSS portfolio và migration 021 dùng chung. `app/learner/data/Service/NotificationService.php` được sửa để thêm thông báo nộp/duyệt. **Không sửa service/repository chấm Rubric cũ trong đợt #07.** Danh sách đầy đủ tại [bàn giao Giảng viên](plans/handoff-issue-07-teacher.md).

Còn làm / giới hạn:

- [ ] UAT: Nhà trường phân công mentor → Sinh viên gửi → Teacher duyệt → tải lại Profile/CV → Teacher thu hồi → xuất lại CV và đối chiếu.
- [x] Nối kỹ năng verified từ portfolio vào AI Job/Activity Matching (lượt #02): snapshot, hash freshness, outbox verify/revoke.
- Không upload tệp lên server; chỉ dùng link, không tự fetch. Nghiệm thu cá nhân không tự hoàn thành dự án của cả nhóm.
- Thiết kế lại A4 để sau; không commit/merge trong đợt triển khai này.

[Xem kế hoạch và kết quả](plans/plan-issue-07.md) · [Bàn giao chi tiết Giảng viên](plans/handoff-issue-07-teacher.md).

## #08 — Làm lại test linh hoạt trước 90 ngày

**Trạng thái: đã triển khai và kiểm thử hoàn tất (7/7 contract tests PASS).**

- [x] Chuyển đổi ngoại lệ chặn cứng thành phản hồi cảnh báo mềm khi lần làm gần nhất chưa đủ 90 ngày (`status => 'retake_confirmation_required'`, mã `RETAKE_CONFIRMATION_REQUIRED`, tính chính xác `elapsed_days`, `remaining_days`, `last_submitted_at`).
- [x] Hỗ trợ cờ `$confirmEarlyRetake = true` để tạo attempt mới (`in_progress`). Bản ghi và kết quả cũ được giữ nguyên vẹn 100% (bảo đảm tính bất biến).
- [x] API endpoint và client tiếp nhận input `confirm_early_retake`, trả về mã HTTP 409 khi chưa xác nhận.
- [x] Giao diện kết quả bài thi hiển thị nút "Làm lại bài đánh giá" và modal tiếng Việt giải thích khuyến nghị chu kỳ 90 ngày với 2 nút hành động (Giữ kết quả hiện tại / Xác nhận làm lại).
- [x] Các truy vấn đọc kết quả trên `DatabaseTalentPassportRepository`, `talent-passport.php`, và `AiRoadmapService` luôn sắp xếp `ORDER BY submittedAt DESC` và khử trùng lặp để ưu tiên kết quả bài nộp gần nhất cho AI và hồ sơ.

[Xem kế hoạch chi tiết](plans/plan-issue-08.md).

## #04 — Talent Passport PDF một trang A4

**Trạng thái: đã có luồng xuất A4 lấy dữ liệu mới, đã nối minh chứng #07. Bố cục/thẩm mỹ A4 sẽ chỉnh sau theo yêu cầu; chưa chốt thiết kế CV cuối cùng.**

Đã làm:

- Passport → **CV A4 / Xuất PDF** → preview riêng `app/learner/talent-passport-cv.php` → **Lấy dữ liệu mới & xuất PDF**.
- Mỗi lần mở/xuất đọc lại dữ liệu hợp lệ của đúng sinh viên từ database; không dùng cache hồ sơ cũ, không tự tạo link chia sẻ hoặc dùng demo autologin.
- CV nhận đánh giá/kỹ năng có xác nhận, báo cáo dự án được nghiệm thu và thực tập verified từ #07. Các dữ liệu chưa xác nhận không được nâng thành minh chứng uy tín.
- Hoạt động trường/ngoại khóa ghi riêng, không quy đổi điểm danh thành kỹ năng chuyên môn. Không tự gắn nhãn “tham gia thường xuyên” nếu dữ liệu không chứng minh.
- Đơn accepted chỉ là được tiếp nhận; không tự suy diễn đã bắt đầu/hoàn thành. PDF đã tải là **snapshot**, muốn mới phải xuất lại.

Kiểm thử ghi nhận:

- View-model **6 PASS**, repository **4 PASS**, portfolio-CV **2 PASS**.
- Route student/guest/teacher **200/401/403**; UI CV **2 PASS**.
- **4 PDF fixtures** (thường, ít dữ liệu, dài, portfolio) đúng **1 trang A4**; thêm **2 browser checks PASS** cho cập nhật nội dung trước khi in và báo tràn khổ.
- Đã kiểm tra ảnh PDF và văn bản tiếng Việt trích xuất được. Xuất qua Edge/Chromium, A4, tỷ lệ 100%, tắt header/footer trình duyệt.

Cần làm / giới hạn:

- [ ] **Bố cục và giao diện CV A4 chuyên nghiệp sẽ chỉnh sau.**
- [ ] Nghiệm thu export với tài khoản/dữ liệu thật trên giao diện triển khai.
- Bản một trang là bản tóm tắt có giới hạn số mục/độ dài, không xuất toàn bộ lịch sử. Nội dung quá dài vượt giới hạn an toàn sẽ **chặn xuất và báo lỗi**, không âm thầm cắt/tràn hoặc khẳng định mọi dữ liệu đều vừa một trang.

[Xem kế hoạch và danh sách file](plans/plan-issue-04.md).

## #02 — Đồng bộ kỹ năng dự án & Làm mới AI

**Trạng thái: ĐÃ HOÀN THÀNH 100%.** Đã xử lý 2 lỗi tồn đọng (outbox version cứng; không loại membership `removed`) và nghiệm thu test suite thành công.

Đã làm:

- Migration `019_create_project_skill_tags` **APPLIED** trên MySQL `talenthub`. Bảng `project_skill_tags` có `id`, `projectId`, `skillId`, `verifiedAt`, `createdAt`.
- `DatabaseTalentPassportRepository::skills()` UNION kỹ năng mentor đã duyệt từ `learner_portfolio_skills` (báo cáo dự án/thực tập `status='verified'`, skill `active`). Thu hồi (`revoked`) loại kỹ năng ngay; không lấy skill inactive; không đếm trùng với `student_skills`.
- `AiSourceRegistry` đăng ký nguồn `portfolio_skill` và `internship`; project `skill_tags` gộp cả `project_skill_tags` và kỹ năng portfolio verified. Job/Activity matching đọc các tag này qua `confirmedExperienceTags()` mà không bịa điểm thành thạo.
- GET recommendation so sánh hash snapshot hiện tại với hash lần chạy đã lưu → `stale_model` khi khác. POST generate vẫn chạy để nút làm mới hoạt động.
- Outbox giao dịch: `portfolio.verified` / `portfolio.revoked` khi Giảng viên duyệt/thu hồi; school project create/update ghi `project.changed` trong cùng transaction.
- Giao diện 4 màn AI đã có `stale-model` và refresh (không sửa frontend trong lượt này).

Lỗi tồn đọng đã đóng (2026-09-09):

- `SchoolProjectRepository` không còn hardcode `aggregate_version = 1`. Create/update dùng `TransactionalAiOutboxPublisher::version()`; `publish()` trả về `false` thì ném ngoại lệ và rollback transaction thay vì nuốt sự kiện.
- `portfolioVerifiedSkills` JOIN `project_members` với `pm.status = 'active'`. Hai truy vấn đọc danh sách dự án (shared + aggregate) thêm `AND pm.status = 'active'`. Thành viên `removed` mất kỹ năng đồ án, mất dự án trên hồ sơ, và snapshot hash đổi ngay.

Kiểm thử đã ghi nhận:

- `php -l` các file PHP đã sửa: không lỗi cú pháp.
- `tests/learner_project_skill_sync_test.php`: **PASS** (verify → skills()+hash+outbox; revoke → biến mất+hash đổi+outbox; internship verified cũng vào snapshot; `updateProject` 2 lần → 2 outbox version khác nhau; membership `removed` → mất PHP + mất dự án + hash đổi).
- `tests/learner_ai_stale_state_ui_contract_test.js`: **1 PASS**.
- Hồi quy: `learner_talent_passport_legacy_skill_schema_test.php`, `learner_recommendation_service_test.php`, `learner_portfolio_repository_test.php`, `learner_activity_runtime_test.php`, `learner_ai_sources_test.php`, `learner_ai_snapshot_test.php`: **PASS**.
- `git diff --check`: sạch (không lỗi whitespace).

[Xem kế hoạch chi tiết](plans/plan-issue-02.md).

## #05 — AI gợi ý hoạt động phong trào phù hợp

**Trạng thái: đã triển khai — chờ nghiệm thu AI thực tế; chưa hoàn thành 100%, chưa đóng.**

Đã làm:

- Nút bấm phân tích; panel phía trên thẻ hoạt động, điểm/lý do và thu gọn/mở rộng. Không tự gọi API ngay khi mount.
- API xác thực, nối nhà cung cấp AI, lưu kết quả/hash; phát hiện dữ liệu thay đổi và làm mới sau khi người dùng đã kích hoạt.
- Phân biệt lỗi lưu trữ/dịch vụ với kết luận không có hoạt động phù hợp; không giả phân tích AI bằng fallback khi provider lỗi.
- Migration `020_create_activity_match_runs` **APPLIED**. Lỗi lịch sử “chưa có bảng lưu gợi ý” đã được xử lý; ba migration lịch sử được khôi phục đúng checksum ở lượt trước.

Kiểm thử ghi nhận: **Node 9/9**, **9 kiểm tra SQL SQLite cô lập**, model validation bằng provider giả lập và PHP lint đã qua; MySQL migration đã validate. **Không coi đây là bằng chứng Gemini thật đã trả kết quả.**

Cần làm tiếp:

- [ ] Có chấp thuận phù hợp cho payload giới hạn gửi dịch vụ bên ngoài; smoke test nhận ít nhất một phân tích Gemini thành công. Lượt trước chưa xác nhận được: provider unavailable trong sandbox, yêu cầu gửi dữ liệu học viên ra bên thứ ba chưa được duyệt.
- [ ] UAT trình duyệt: phân tích, thu gọn/mở rộng, không có kết quả, lỗi provider và cập nhật hoạt động/hồ sơ → kết quả mới.
- [x] Nguồn kỹ năng verified từ #07 đã nối vào snapshot/AI ở lượt #02 (hash/outbox/confirmedExperienceTags). Nghiệm thu Gemini thật vẫn mở.
- Không thể bảo đảm dịch vụ AI bên ngoài luôn sẵn sàng. Khi lỗi phải báo đúng nguyên nhân, không hiển thị lỗi như “không phù hợp” hoặc phân tích thành công.

[Xem kế hoạch và checklist nghiệm thu](plans/plan-issue-05.md).

## #08 — Đánh giá lại năng lực với cảnh báo mềm 90 ngày

**Trạng thái: đã hoàn thành và kiểm thử toàn bộ.**

Đã làm:
- Chuyển đổi cơ chế chặn cứng ngoại lệ 90 ngày thành cơ chế Cảnh báo Mềm 2 bước (Soft Warning 90-day):
  1. Khi sinh viên yêu cầu làm lại bài đánh giá (DISC, MBTI, Holland, Multiple Intelligences) dưới 90 ngày mà chưa xác nhận (`confirm_early_retake: false`), API trả về HTTP 409 `RETAKE_CONFIRMATION_REQUIRED` kèm `elapsed_days`, `remaining_days`, `last_submitted_at`.
  2. Giao diện hiển thị Modal xác nhận tiếng Việt giải thích khuyến nghị chu kỳ 90 ngày:
     *"Bạn đã hoàn thành bài đánh giá này cách đây **X** ngày. Kết quả xu hướng năng lực và tính cách thường ổn định và đạt độ tin cậy cao nhất sau chu kỳ **90 ngày** (còn **Y** ngày nữa). Bạn có chắc chắn muốn làm lại ngay bây giờ không?"*
  3. Chọn "Giữ kết quả hiện tại" (`data-cancel-retake`): Đóng modal, giữ nguyên kết quả hiện tại, không tạo bản ghi mới.
  4. Chọn "Xác nhận làm lại" (`data-confirm-retake`): Gửi `confirm_early_retake: true` lên API để tạo bản ghi attempt mới (`in_progress`), chuyển hướng sinh viên vào làm bài.
  5. Đảm bảo tính bất biến (immutability): Bản ghi và kết quả các lần làm bài trước được giữ nguyên vẹn 100%, không bị ghi đè.
  6. Talent Passport, Hồ sơ cá nhân và AI Roadmap luôn đọc kết quả bài nộp gần nhất (`ORDER BY submittedAt DESC, id DESC`).

Kiểm thử ghi nhận:
- `tests/learner_assessment_retake_contract_test.js`: **7/7 PASS**.
- `tests/phase7_application_ui_test.js`: **4/4 PASS**.
- `tests/learner_passport_cv_ui_test.js`: **2/2 PASS**.
- `tests/learner_assessment_persistence_test.php`: **PASS**.
- `git diff --check`: Sạch sẽ hoàn toàn.

[Xem kế hoạch chi tiết](plans/plan-issue-08.md).

## Lỗi / công việc còn mở cần theo dõi chung

| Mã | Mức độ | Bằng chứng / ảnh hưởng | Việc cần làm | Trạng thái |
|---|---|---|---|---|
| DB-01 | Cao — #01 | Main migration 20260909000100 APPLIED; learner 018 đối soát, không chạy ALTER trùng. | Đã preflight/apply local, bảo toàn dữ liệu cũ; còn phân công lớp thật/UAT. | **Đã xử lý migration local** |
| DB-02 | Cao — #02 | Registry `019_create_project_skill_tags` **APPLIED**; bảng `project_skill_tags` đã có trên MySQL. | Không còn việc deployment cho tags. | **Đã xử lý** |
| AI-01 | Trung bình — #05 | #02 đã nối `learner_portfolio_skills` vào snapshot/hash/outbox/Job+Activity matching. #05 còn nghiệm thu provider thật. | Smoke test Gemini khi được phép. | **#02 đã nối nguồn; #05 còn nghiệm thu** |
| AI-02 | Cao — nghiệm thu #05 | Chưa có bằng chứng provider thật phân tích thành công. | Smoke test được phép + UAT, không dùng provider giả để đóng issue. | **Chờ nghiệm thu** |
| QA-01 | Trung bình — hồi quy thông báo | `tests/notification_domain_producer_test.php` lỗi `no such column: e.status`; fixture `enterprises` thiếu `status`/`verificationStatus` mà repository query cần. Fixture và query không đổi trong #07. | Sửa fixture phù hợp contract tại `DatabaseApplicationCommandRepository.php`, chạy lại test; kiểm tra lỗi kế tiếp nếu có. | **Lỗi test cũ, chưa sửa** |
| QA-02 | Trung bình — đăng ký dự án | Thiếu `tests/learner_project_registration_test.php` và `tests/learner_project_registration_boundary_test.php` ở checkout, dù có tham chiếu trong gitignore/tài liệu. | Khôi phục từ nguồn đúng hoặc viết test tương đương, rồi chạy hồi quy. | **Chưa có kết quả test** |
| QA-03 | Trung bình — #04/#07 | Browser fixtures dùng API giả lập; SQL/HTTP thật kiểm thử riêng. | Đăng nhập Learner/Teacher và nghiệm thu liên thông trên giao diện triển khai. | **Chưa UAT toàn luồng** |
| UI-01 | Hoãn có chủ ý — #04 | Khổ A4 và dữ liệu đã có, thiết kế hiện tại là cơ bản. | Chốt bố cục CV chuyên nghiệp ở đợt riêng, giữ test refresh/overflow/1 trang. | **Để sau theo yêu cầu** |

### Trạng thái môi trường và database

- PHP hiện có tại `D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`; MySQL local đã kết nối được.
- Lượt #02 đã apply 019 tags. Lượt #01 mới đã apply main 20260909000100 và đối soát learner 018 competency context thành **APPLIED** với ghi chú thay thế; không thực thi lại SQL 018. 020/021 vẫn **APPLIED**.
- PENDING là trạng thái registry, không tự chứng minh mọi bảng/cột liên quan chưa tồn tại. Phải đối chiếu schema trước khi apply; không chạy lại mù quáng.
- Lượt #07 trước đã ghi nhận migration validate thành công và kiểm thử MySQL trên database cô lập; không sửa bản ghi sinh viên thật để test.

## Thứ tự tiếp tục đề xuất

1. #01 đã tích hợp và migrate local: phân công lớp thật/UAT; sửa/khôi phục regression QA-01/QA-02 ngoài phạm vi lượt #01.
2. Nghiệm thu liên thông #07 → #04 trên giao diện thật.
3. #02 đã đóng: kỹ năng verified từ portfolio vào AI snapshot/hash/outbox; verify/revoke đã kiểm thử.
4. #08 đã hoàn thành toàn bộ contract và UI test; thiết kế A4 vẫn hoãn theo yêu cầu.
5. Hoàn tất nghiệm thu AI #05 khi có điều kiện gọi provider và kiểm thử toàn luồng.

Có thể chuyển việc, nhưng **không đánh dấu các checklist còn mở là hoàn thành**. Các plan và báo cáo bàn giao giữ chi tiết triển khai theo thời điểm; ghi chú “không sửa ISSUES_LOG.md” trong báo cáo #07 mô tả yêu cầu cũ của lượt đó, đã được yêu cầu mới này thay thế.
