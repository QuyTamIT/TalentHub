# Issue07 backend — kết quả kiểm thử

Đã hoàn tất triển khai và kiểm thử phạm vi được duyệt ngày 2026-09-09. Không commit/merge, không chỉnh ISSUES_LOG.md. Chi tiết bàn giao: [Giảng viên](handoff-issue-07-teacher.md), [kế hoạch và giới hạn](plan-issue-07.md).

## File backend

- Tạo `Database/migrations/learner/021_create_learner_portfolio_reports.php`: 4 bảng additive, migration đã áp dụng MySQL local; không thay enum tuyển dụng hay xóa dữ liệu cũ.
- Tạo `src/Modules/Student/Repository/PortfolioRepository.php`: lưu/duyệt có version, revision, transaction, khóa context/mentor, history, notifications; đọc verified riêng cho CV.
- Tạo `tests/learner_portfolio_repository_test.php`: SQLite hoặc injected MySQL, dữ liệu giả lập. Bao gồm status transitions, quyền, thu hồi, kiểu kỹ năng/context và validation.

## Bằng chứng và review

- RED ban đầu: chưa có migration/repository; RED bổ sung cho skill reportId trùng kind trả dư kỹ năng. Sau sửa join kind+id, SQLite/MySQL PASS.
- Parent access/UI/CV có chu trình RED (class/module chưa có, CV chưa nhận internship verified) → GREEN sau triển khai.
- Chạy PHP `tests/learner_portfolio_repository_test.php`: OK.
- Chạy PHP `tests/learner_portfolio_mysql_test.php`: lifecycle OK; HTTP/CSRF/default notifier thật PASS; internship verified → CV ghi 120.25 giờ/hoàn thành → revoked bỏ minh chứng PASS. Database giả lập do test tạo đã dọn trong finally, không sửa hồ sơ thật.
- Review phát hiện và đã sửa: private draft race sau query danh sách, draft history Teacher, integer/fractional hours mismatch, validation tests fail nhầm transition, membership test dùng sai trạng thái. Re-review không còn critical/important trong phạm vi kiểm tra.
- Migration validate OK; read-only GET 2 learners + 2 teachers và CV HTTP200 PASS.

Không khẳng định toàn bộ test suite dự án đều PASS: test thông báo cũ thiếu cột fixture `enterprises.status`; chi tiết giới hạn ở plan. AI matching chưa được mở rộng để đọc skill portfolio mới trong #07.
