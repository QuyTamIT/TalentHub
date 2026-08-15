# Database smoke test

Lệnh này chỉ được chạy trên database test MySQL 8.4:

```powershell
$env:APP_ENV = 'test'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3307'
$env:DB_DATABASE = 'talenthub_test'
$env:DB_USERNAME = 'talenthub_test'
$env:DB_PASSWORD = 'local-test-password'
php bin/smoke-database.php
```

Test thực hiện tuần tự trên database sạch:

1. Kết nối và xác nhận đúng database, MySQL 8.4.x, timezone UTC.
2. Validate migration filename/contract/checksum.
3. Chạy 10 clean baseline migrations và system seed.
4. Chạy migrate lần hai và xác nhận no-op.
5. Rollback toàn bộ batch reversible và xác nhận 19 bảng business/runtime đã bị gỡ.
6. Migrate lại, seed lại và kiểm tra fingerprint: 19 bảng, 4 role, 89 permission, 107 mapping, gồm exact permission matrix của từng role.

Guard bắt buộc `APP_ENV=test` và `DB_DATABASE` chứa `test`. Test không tạo/drop database, không tắt foreign key checks và không chạy trên MariaDB hoặc database legacy. Database phải sạch; nếu test fail giữa DDL, hãy bỏ database test cô lập và tạo lại thay vì sửa metadata thủ công.

Smoke test gọi trực tiếp cùng `MigrationRunner` mà CLI `bin/migrate.php` sử dụng.

## Automated Auth suite

Chạy toàn bộ happy/failure path của Auth trên MySQL 8.4 test database sạch:

```powershell
php bin/test-auth.php
```

Suite bao phủ:

- Student registration: transaction thành công, normalize, duplicate, invalid input, role injection và rollback dữ liệu dở dang.
- Login audit: success, unknown/known invalid credential, inactive account và sensitive metadata exclusion.
- Persistent rate limit: identity/IP threshold, `Retry-After`, clear identity và hashed bucket storage.
- Teacher profile/assessment: login, permission, ownership, activity-registration-assessment schema, unique criteria score, update profile, dashboard, đổi mật khẩu và đăng nhập lại.
- School/Student/Business profile: login, permission cho phép/từ chối, field allow-list, ownership, dashboard, đổi mật khẩu và đăng nhập lại.

Runner bắt buộc `APP_ENV=test`, tên database chứa `test`, server MySQL 8.4.x và database trống. Mỗi case rollback toàn bộ migration; runner chỉ drop `schema_migrations` giữa các case sau khi xác nhận đó là bảng duy nhất còn lại.
