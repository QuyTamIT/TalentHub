# Đồng bộ database local — 15/09/2026

Phạm vi: database MySQL local `talenthub`; migration từ main `8ce16a16`. Không thay đổi database trên server khác.

## Đã thực hiện

- Sao lưu đủ schema, dữ liệu, trigger, routine và event trước khi ghi. File: `D:/TalentHub/.tmp/database-sync-20260915/before-080801.sql` (11.114.316 byte); hash SHA-256 và số dòng 107 bảng được lưu trong `before.json` cùng thư mục. Bản sao lưu không được đưa lên Git.
- Runner của dự án đã áp dụng `20260914000100_allow_suspended_organization_verification` và `20260915000300_add_score_provenance_metadata`, đồng thời ghi lịch sử và checksum chuẩn.
- Chạy lại runner trả về danh sách rỗng; không chèn lịch sử migration bằng tay.
- 85 migration chung và 23 migration người học hợp lệ, không còn migration chờ. Kiểm tra cả thư mục migration của main và workspace hiện tại đều đạt. Workspace đã bổ sung file migration trạng thái tổ chức vốn có trên main.

## Đồng bộ điểm và bảo toàn dữ liệu

`EvidenceBackedScoreService::projectOfficialScores` đã đồng bộ 63 hồ sơ từ nguồn gốc đánh giá trong một transaction.

- 7 bản ghi điểm chính thức mới có nguồn xác minh.
- 88 bản ghi cũ được giữ nguyên giá trị điểm, nguồn, trạng thái xác minh, người/thời gian xác minh và thời gian tạo/cập nhật; chỉ đổi metadata `scoreState` sang `missing_source` để không dùng làm bằng chứng điểm chính thức. Hash dữ liệu các trường lịch sử trước và sau giống nhau.
- MySQL tự động thay `updatedAt` khi đổi metadata. Lần thử đầu tiên phát hiện việc này và rollback toàn bộ. Bản sửa dùng `updatedAt=updatedAt` để giữ thời gian nguồn lịch sử; kiểm thử MySQL bằng transaction rollback đã xác nhận 88 dòng được bảo toàn trước khi đồng bộ thật.
- Không sửa nội dung đánh giá, minh chứng hoặc xóa hồ sơ. Số dòng chỉ tăng ở `schema_migrations` (83 → 85) và `student_skills` (88 → 95).

## Xác nhận sau đồng bộ

- Schema điểm đạt `assertSchemaReady(true)`.
- Điểm tổng của 63 hồ sơ khớp kết quả tính từ nguồn đánh giá.
- Dựng CV của cả 63 hồ sơ active bằng MySQL thành công.
- Không có kỹ năng chính thức trùng cho cùng học viên/kỹ năng.
- Công cụ audit báo 0 điểm chính thức thiếu liên kết, 0 điểm tổng lệch, 0 minh chứng bị thu hồi còn sử dụng.
- Audit hiện đếm 7 nhóm có nhiều dòng cùng học viên/kỹ năng vì gộp cả lịch sử và bản chiếu chính thức. Đây là các dòng lịch sử được giữ lại theo thiết kế; query riêng `scoreState='scored'` xác nhận số trùng chính thức bằng 0.

Kiểm thử hồi quy bổ sung: `tests/score_projection_mysql_history_test.php`. Cần bật `TALENTHUB_TEST_MYSQL_HISTORY=1` với database local; test luôn rollback và mặc định không chạy trên database.

Đây là đồng bộ schema và dữ liệu dẫn xuất từ đánh giá đã có. Các kỹ năng chưa có nguồn hợp lệ vẫn hiển thị thiếu nguồn, không được tự tạo đánh giá hoặc gán điểm mới.
