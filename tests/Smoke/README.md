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
3. Chạy 5 clean baseline migrations và system seed.
4. Chạy migrate lần hai và xác nhận no-op.
5. Rollback toàn bộ batch reversible và xác nhận 12 bảng business đã bị gỡ.
6. Migrate lại, seed lại và kiểm tra fingerprint: 12 bảng, 4 role, 81 permission, 99 mapping.

Guard bắt buộc `APP_ENV=test` và `DB_DATABASE` chứa `test`. Test không tạo/drop database, không tắt foreign key checks và không chạy trên MariaDB hoặc database legacy. Database phải sạch; nếu test fail giữa DDL, hãy bỏ database test cô lập và tạo lại thay vì sửa metadata thủ công.

Smoke test gọi trực tiếp cùng `MigrationRunner` mà CLI `bin/migrate.php` sử dụng.
