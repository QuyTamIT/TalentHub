# TalentHub database seeds

Seed được chạy sau khi clean baseline migrations đã hoàn tất.

- `System/RolePermissionSeeder.php`: dữ liệu hệ thống bắt buộc, an toàn để chạy lặp; tạo 4 role, 99 permission và 117 mapping chính xác, gồm dashboard nền tảng cho cả bốn role và quyền activity/assessment/QR session do Teacher phụ trách.
- `Testing/MinimalAuthRbacSeeder.php`: fixture local/test, tuyệt đối không chạy production; tạo một school, class, enterprise và một user cho mỗi role.

Thứ tự chạy: system seed trước, test seed sau. CLI hiện tại: `php bin/seed.php` hoặc `php bin/seed.php --testing`; runner giữ advisory lock như quy định tại `document/MIGRATION_STANDARD.md`.

Test seed không chứa mật khẩu trong Git. Runner phải đọc `TALENTHUB_TEST_PASSWORD`, yêu cầu ít nhất 12 ký tự, rồi truyền giá trị vào `run(PDO $pdo, string $environment, string $password)`. Seeder chỉ chấp nhận `test`, `testing`, `development` hoặc `local`.

Các email fixture cố định:

- `student@test.talenthub.local`
- `teacher@test.talenthub.local`
- `school@test.talenthub.local`
- `business@test.talenthub.local`

Các seeder nhắm tới schema clean baseline MySQL 8.4, không tương thích với dump legacy chưa migration.

## Complete portal demo

`Demo/CompletePortalDemoSeeder.php` tạo scope đầy đủ cho bốn tài khoản canonical `@talenthub.local`. Seeder chỉ được chạy trong `local` hoặc `test`, không xóa dữ liệu, dùng transaction và chạy bên trong seed lock hiện có:

```powershell
$env:TALENTHUB_DEMO_PASSWORD = 'mat-khau-toi-thieu-12-ky-tu'
php bin/seed.php --complete-demo
Remove-Item Env:TALENTHUB_DEMO_PASSWORD
```

Password chỉ được cập nhật cho bốn tài khoản demo. Seeder có thể chạy lại; conflict fixed ID/email hoặc scope sẽ rollback toàn bộ transaction. Complete demo không tạo activity, registration, assessment, QR hoặc check-in data.
