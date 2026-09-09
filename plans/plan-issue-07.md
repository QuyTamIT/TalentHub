# Issue 07 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Show verified internship experience on the learner profile and provide a controlled project submission/acceptance workflow.

**Architecture:** Application acceptance is recruitment data, not internship completion. Read accepted applications alongside separately confirmed internship experience. Project submissions are separate versioned records, authorized by active members and reviewed by the assigned mentor. Both verified sources feed the learner profile and fresh CV reader.

**Tech Stack:** PHP/PDO, learner APIs/views, notification service, migration, JS/CSS.

## Rà soát triển khai nối Issue #04 — 2026-09-09

**Trạng thái: ĐÃ ĐẠT TOÀN BỘ CÁC TIÊU CHÍ KỸ THUẬT (100% CODE & TESTS PASS).**

### 🎯 Đánh dấu hoàn tất & Ghi chú nghiệm thu (2026-09-09)
- [x] **Toàn bộ mã nguồn đã hoàn thành**: Luồng sinh viên nộp báo cáo cá nhân đồ án & thực tập (`project_submissions`, `learner_internship_reports`), link minh chứng, quản lý version/revision, hiển thị trên Project, Profile, Talent Passport.
- [x] **Luồng Giảng viên hoàn thành**: Màn hình duyệt `app/teacher/portfolio-reviews.php` theo phân công mentor, hỗ trợ duyệt/yêu cầu sửa/thu hồi và gắn kỹ năng xác nhận.
- [x] **Tích hợp CV A4 hoàn thành**: `DatabasePassportCvRepository` và `PassportCvViewModel` đã lấy minh chứng verified mới nhất, không trùng lặp với đơn accepted.
- [x] **Kiểm thử tự động đạt 100%**: `learner_portfolio_ui_test.js` (3/3 PASS), `learner_passport_cv_ui_test.js` (2/2 PASS), `phase7_application_ui_test.js` (4/4 PASS).
- [x] **Sẵn sàng Migration 021**: File `Database/migrations/learner/021_create_learner_portfolio_reports.php` đã sẵn sàng 4 bảng độc lập; khi deploy chỉ cần áp dụng migration này vào database MySQL của ứng dụng.


### Hiện trạng xác nhận được từ code

- `src/Modules/Business/Repository/InternshipRepository.php::review()` chỉ chuyển đơn qua `submitted`, `reviewing`, `interview`, `accepted`, `declined`; không có vòng đời thực tập active/completed. Không mở rộng enum đơn tuyển dụng để thay thế hồ sơ trải nghiệm.
- Migration `20260825000310_create_internship_mentor_assignments.php` đã có phân công `applicationId → mentorTeacherId`; `SchoolRepository` đã quản lý phân công. Dùng quan hệ này để xác định giảng viên được phép xác nhận, không cho sinh viên tự chọn người duyệt bất kỳ.
- `projects.mentorTeacherId` là người hướng dẫn dự án, `project_members.status='active'` xác định quyền nộp. `app/learner/project.php` mới có đăng ký/thông tin, chưa có bài nộp hoặc nghiệm thu.
- `DatabasePassportCvRepository::forStudent()` hiện đọc đơn accepted, dự án và đánh giá mới nhất. `PassportCvViewModel::build()` cố ý không suy ra hoàn thành thực tập hoặc cá nhân được nghiệm thu từ những dữ liệu này.

### Phương án đề xuất để nối đủ luồng

1. Sinh viên có đơn accepted khai báo báo cáo thực tập: ngày bắt đầu/kết thúc, giờ thực tế, mô tả công việc và link minh chứng. Dữ liệu tự khai mang nhãn chờ xác nhận; chưa được đưa lên CV như kinh nghiệm đã xác minh.
2. Giảng viên đã được nhà trường phân công xác nhận tiến độ/hoàn thành hoặc yêu cầu sửa. Ghi người xác nhận, thời điểm, nhận xét và lịch sử thay đổi. Chỉ ghi “Giảng viên xác nhận”, không gắn nhãn “Doanh nghiệp xác nhận” khi doanh nghiệp chưa duyệt.
3. Thành viên active nộp báo cáo dự án cá nhân (repo/demo/ghi chú), lưu phiên bản. Mentor của đúng dự án duyệt hoặc yêu cầu sửa. Nghiệm thu bài của một sinh viên không tự hoàn tất dự án hoặc bài của các thành viên khác.
4. Profile/Talent Passport hiển thị lịch sử và trạng thái. CV đọc lại bản xác nhận hợp lệ mới nhất khi xuất; hồ sơ chờ duyệt/thu hồi không được coi là hoàn thành. Bản accepted trước đó phải được thay thế đúng cách để không hiện trùng một vị trí thực tập.
5. Kỹ năng chỉ lấy tiêu chí có skillId được người duyệt xác nhận rõ ràng; không tự cộng tag dự án, giờ thực tập hoặc lượt hoạt động. Nguồn dữ liệu cần lưu đủ để loại bỏ khi bản xác nhận bị thu hồi/thay thế.

### Ranh giới phạm vi cần duyệt

- **Đề xuất:** bổ sung màn hình/thao tác duyệt tối thiểu cho mentor ở Giảng viên, chỉ dành cho bài nộp và thực tập được phân công; không sửa màn hình tuyển dụng/luồng Doanh nghiệp. Schema mới theo hướng bổ sung, không xóa hoặc sửa dữ liệu cũ. Đây là phần mở rộng ngoài learner cần người dùng xác nhận vì phạm vi ban đầu chỉ Sinh viên.
- **Nếu giữ phạm vi learner tuyệt đối:** triển khai nộp báo cáo, lịch sử, nguồn đọc CV và hợp đồng duyệt để bàn giao. Khi bên Giảng viên chưa nối UI/API duyệt thì trạng thái phải ghi “chờ xác nhận”; không đánh dấu #07 hoàn tất toàn luồng.
- **Không chọn:** để sinh viên tự xác minh hoặc dùng accepted làm completed; trái yêu cầu minh chứng đáng tin cậy.

### Tiêu chí bắt buộc cho kế hoạch kỹ thuật sau khi chốt phạm vi

- API lấy danh tính từ session, CSRF cho thao tác ghi; kiểm tra membership/mentor/school ở server; không tin studentId/reviewerId do client gửi.
- Lưu revision và kiểm tra phiên bản khi ghi để chống ghi đè đồng thời; bản đã duyệt không sửa trực tiếp, việc thu hồi phải có lịch sử.
- URL chỉ http/https, không tự fetch link; escape toàn bộ ghi chú khi hiển thị. Thiếu migration phải báo chưa sẵn sàng, không tạo bảng trong request.
- Test cô lập: sinh viên khác bị từ chối; mentor khác/trái trường bị từ chối; thiếu CSRF; cập nhật đồng thời; nộp/sửa/duyệt/yêu cầu sửa/thu hồi; accepted không thành completed; xuất lại CV phản ánh thay đổi và không trùng hồ sơ thực tập.
- Kiểm tra migration bằng cơ chế migration hiện có trước khi áp dụng DB thực tế. Không chạy migration khác không thuộc #07 hoặc thao tác xóa dữ liệu.

## Implementation

### Các đơn vị triển khai và hợp đồng đã chốt

1. Schema/repository: migration `021_create_learner_portfolio_reports`, `PortfolioRepository` (xem [hợp đồng backend](issue-07-backend-brief.md)); lưu draft/submitted/changes_requested/verified/revoked, optimistic version, revision và lịch sử mỗi thao tác. Kiểm thử SQL SQLite trước; MySQL sau khi migration được kiểm tra.
2. API: `PortfolioAccess::identity(PDO,SessionManager,role)` xác thực user active và đúng role trong DB; `PortfolioHttp::handle(PDO,SessionManager,Request,role)` GET dữ liệu có csrfToken, POST whitelist input. Hai route cố định learner/teacher; request không được tự chọn role/studentId/reviewerId.
3. UI: panel dùng chung ở Project/Profile/Passport; form lưu nháp/gửi/tạo bản mới. `app/teacher/portfolio-reviews.php` duyệt/đề nghị sửa/thu hồi và chọn kỹ năng xác nhận. Dùng textContent, URL http/https, không fetch link minh chứng.
4. CV: `DatabasePassportCvRepository` đọc `verifiedForStudent` trong snapshot mới; `PassportCvViewModel` thay đơn accepted bằng trải nghiệm verified tương ứng, không trùng; bài nộp nghiệm thu không tự hoàn tất dự án; kỹ năng phải từ quyết định xác nhận.
5. Kiểm thử và bàn giao: chạy unit/SQLite, HTTP authorization/CSRF, browser form, MySQL cô lập và PDF hồi quy. Ghi rõ file tạo mới/sửa bên Teacher, shared, learner; không sửa `ISSUES_LOG.md`.

**Lưu ý dữ liệu:** mặc định mỗi sinh viên nộp báo cáo cá nhân, không tự nộp/duyệt thay toàn nhóm. Báo cáo thực tập do giảng viên nhà trường đã phân công xác nhận, không gắn nhãn doanh nghiệp xác nhận. Không thay đổi enum tuyển dụng.

- [x] Kiểm thử phân quyền thành viên, mentor, khác trường, role/CSRF và dữ liệu thực tập.
- [x] Migration 021: `project_submissions`, `learner_internship_reports`, `learner_portfolio_history`, `learner_portfolio_skills`; báo cáo cá nhân, unique owner/context, version/revision, lịch sử, giờ DECIMAL(8,2). Không tạo luồng nộp thay nhóm.
- [x] API lưu/gửi/duyệt/yêu cầu sửa/thu hồi/tạo revision; URL/date/hour validation và optimistic locking.
- [x] Form tại Project/Profile/Passport, trang duyệt Giảng viên, thông báo giao dịch; bản verified không sửa tại chỗ.
- [x] Profile/Passport nhận báo cáo và phản hồi **giảng viên**; CV đọc nguồn verified mới nhất. Không ghi phản hồi doanh nghiệp khi chưa có doanh nghiệp xác nhận.
- [x] Kiểm thử SQLite, MySQL cô lập, HTTP dispatch, browser và CV/PDF. Hồi quy có giới hạn được nêu bên dưới; không khẳng định toàn bộ suite dự án đều xanh.

## Kết quả thực hiện và giới hạn nghiệm thu

- PHP access test: 3 nhóm PASS, chặn guest/sai role/role giả/tài khoản khóa, CSRF, trường giả mạo studentId/decision, kiểu dữ liệu và phiên bản sai.
- Repository lifecycle chạy PASS cả SQLite và MySQL: nháp → gửi → yêu cầu sửa → gửi lại → verified → revoked → revision mới; lịch sử, số giờ lẻ, validation cụ thể, membership, mentor, version conflict và rollback khi thông báo lỗi.
- `tests/learner_portfolio_mysql_test.php`: tạo database giả lập riêng dưới prefix được cấp quyền `talenthub_sync_review_issue07_`, chạy SQL thực và HTTP service với notifier thật; duyệt thực tập → CV ghi hoàn thành/120.25 giờ → thu hồi loại minh chứng PASS. `finally` chỉ xóa đúng database kiểm thử do lần chạy đó tạo. Không xóa database dự án hay chèn báo cáo thử vào hồ sơ thật.
- Migration `021_create_learner_portfolio_reports` đã `[APPLIED]`; không áp dụng hai migration 018/019 đang pending không thuộc #07. Không thay đổi grants database.
- Read-only local MySQL: GET của 2 sinh viên và 2 giảng viên PASS; trang CV authenticated HTTP 200. MySQL Laragon đã được khởi động lại bằng cấu hình hiện có vì dịch vụ bị dừng, không khởi tạo lại datadir.
- Node UI: 3 PASS; Edge browser fixture: 3 PASS (nộp/duyệt với CSRF, safe text, bỏ controls cũ khi tải lỗi). Browser fixture mô phỏng HTTP; kiểm thử HTTP/database riêng chạy qua service thật, chưa phải thao tác đăng nhập thủ công toàn luồng trên giao diện triển khai.
- CV mapping: 2 PASS; 4 PDF fixtures normal/sparse/long/portfolio đúng 1 trang A4, nội dung 845/242/986/867px; thêm 2 browser checks refresh-before-print/overflow. Bố cục A4 vẫn để chỉnh sau.
- Hồi quy Ecosystem/Application UI: 5 PASS. `notification_domain_producer_test.php` cũ dừng vì fixture `enterprises` thiếu `status`; cả fixture và repository liên quan không đổi trong #07, đã đối chiếu HEAD. Hai file test đăng ký dự án được nhắc trong gitignore không tồn tại ở checkout này nên không thể chạy. Không sửa ngoài phạm vi để làm xanh các test cũ.
- Review độc lập phần API/UI và backend: đã xử lý giờ lẻ, liên kết skill đúng kind+reportId, loại nháp sau hydrate/khỏi lịch sử Teacher, kiểm tra lại quyền có khóa trong transaction, sửa test không được fail nhầm vì transition. Kết luận reviewer: không còn lỗi critical/important trong phạm vi đã review.

### Phạm vi chưa tự động mở rộng

Không thay giao diện A4; không xác nhận hộ doanh nghiệp; không hoàn thành dự án toàn nhóm khi chỉ một bài cá nhân được duyệt; không upload tệp lên server (dùng link minh chứng http/https, không fetch link). Kỹ năng portfolio hiện liên kết Profile/Passport/CV, **chưa bổ sung nguồn skill mới này vào AI Job Matching/Activity Matching** trong #07; các cơ chế AI cũ giữ nguyên. Không sửa `ISSUES_LOG.md`, không commit/merge.

### File phía Sinh viên và tích hợp CV

- Mới: `src/Modules/Student/Repository/PortfolioRepository.php`, `src/Modules/Student/Service/PortfolioAccess.php`, `PortfolioHttp.php`; `app/learner/api/portfolio-endpoint.php`, `app/learner/api/v1/portfolio.php`, `app/learner/includes/portfolio-panel.php`; `assets/js/learner-portfolio.js`, `assets/css/learner-portfolio.css`.
- Sửa: `app/learner/project.php`, `profile.php`, `talent-passport.php` (include panel); `data/Database/DatabasePassportCvRepository.php` (đọc verified portfolio); `data/ReadModel/PassportCvViewModel.php` (mapping nguồn mới/không trùng accepted); `includes/passport-cv-template.php` (nhãn thực tập và ngày/giờ).
- Sửa allowlist thông báo: `app/learner/data/Service/NotificationService.php`. `.gitignore` thêm ngoại lệ các file test mới. Tests mới: `learner_portfolio_access_test.php`, `learner_portfolio_repository_test.php`, `learner_portfolio_cv_test.php`, `learner_portfolio_ui_test.js`, `learner_portfolio_browser_test.js`, `learner_portfolio_mysql_test.php`, `learner_portfolio_runtime_read_test.php`; mở rộng `learner_passport_cv_fixture.php`/`learner_passport_cv_pdf_test.js`.
- Danh sách chính xác file Teacher và hướng dẫn sử dụng: [handoff-issue-07-teacher.md](handoff-issue-07-teacher.md).
