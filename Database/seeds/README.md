# TalentHub database seeds

Seed được chạy sau khi clean baseline migrations đã hoàn tất.

- `System/RolePermissionSeeder.php`: dữ liệu hệ thống bắt buộc, an toàn để chạy lặp; tạo 5 role, 120 permission và 144 mapping chính xác, gồm `platform_admin` và 18 quyền vận hành.
- `Local/AdminAccountSeeder.php`: bootstrap tài khoản Admin chỉ trong local/test, tương thích cả schema legacy và schema migration; mật khẩu chỉ đọc từ `TALENTHUB_ADMIN_PASSWORD`.
- `Testing/MinimalAuthRbacSeeder.php`: fixture local/test, tuyệt đối không chạy production; tạo một school, class, enterprise và một user cho mỗi role.

Thứ tự chạy: system seed trước, test seed sau. CLI hiện tại: `php bin/seed.php` hoặc `php bin/seed.php --testing`; runner giữ advisory lock như quy định tại `document/MIGRATION_STANDARD.md`.

Test seed không chứa mật khẩu trong Git. Runner phải đọc `TALENTHUB_TEST_PASSWORD`, yêu cầu ít nhất 12 ký tự, rồi truyền giá trị vào `run(PDO $pdo, string $environment, string $password)`. Seeder chỉ chấp nhận `test`, `testing`, `development` hoặc `local`.

Các email fixture cố định:

- `student@test.talenthub.local`
- `teacher@test.talenthub.local`
- `school@test.talenthub.local`
- `business@test.talenthub.local`

Hai seeder nhắm tới schema clean baseline MySQL 8.4, không tương thích với dump legacy chưa migration.
